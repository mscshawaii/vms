<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/document_reminder_functions.php';
require_once __DIR__ . '/includes/equipment_reminder_functions.php';
require_once __DIR__ . '/includes/email_helper.php';

$config = require __DIR__ . '/config_reminders.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if (php_sapi_name() !== 'cli') {
    if (
        empty($config['runner_token']) ||
        empty($token) ||
        !hash_equals((string)$config['runner_token'], $token)
    ) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$reminders_enabled      = (bool)($config['reminders_enabled'] ?? false);
$dry_run                = (bool)($config['dry_run'] ?? true);
$test_mode              = (bool)($config['test_mode'] ?? true);
$ownersEnabled          = (bool)($config['owners_enabled'] ?? false);
$ignoreCooldown         = (bool)($config['ignore_cooldown'] ?? false);
$test_email_override    = trim((string)($config['test_email_override'] ?? ''));
$maxEmailsPerRun        = max(1, (int)($config['max_emails_per_run'] ?? 10));
$allowedRecipientEmails = array_values(array_unique(array_filter(array_map(
    static fn($email) => strtolower(trim((string)$email)),
    (array)($config['allowed_recipients'] ?? [])
))));
$fromEmail              = trim((string)($config['from_email'] ?? 'info@mschawaii.org'));
$fromName               = trim((string)($config['from_name'] ?? 'MSCS Hawaii VMS'));
$cooldownDays           = 7;

header('Content-Type: text/plain; charset=UTF-8');

if ($test_email_override !== '' && !filter_var($test_email_override, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid test_email_override configured.\n";
    exit(1);
}

function cleanEmailList(array $emails): array
{
    $clean = [];
    foreach ($emails as $email) {
        $email = strtolower(trim((string)$email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $clean[$email] = $email;
        }
    }
    return array_values($clean);
}

function addGroupedRecipient(array &$bucket, string $email, string $roleLabel): void
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    if (!isset($bucket[$email])) {
        $bucket[$email] = [
            'email' => $email,
            'roles' => [],
        ];
    }

    if (!in_array($roleLabel, $bucket[$email]['roles'], true)) {
        $bucket[$email]['roles'][] = $roleLabel;
    }
}

/**
 * Active vessel-assigned users who have the master notification toggle on.
 */
function getVesselReminderRecipients(PDO $pdo, int $vesselId): array
{
    $sql = "
        SELECT DISTINCT u.email
        FROM vessel_crew vc
        INNER JOIN users u
            ON u.id = vc.crew_id
        WHERE vc.vessel_id = ?
          AND vc.is_active = 1
          AND u.is_active = 1
          AND u.receive_notifications = 1
          AND u.email IS NOT NULL
          AND u.email <> ''
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vesselId]);

    return cleanEmailList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Global MSCS recipients do not require vessel assignment.
 */
function getGlobalMSCSReminderRecipients(PDO $pdo): array
{
    $sql = "
        SELECT DISTINCT u.email
        FROM users u
        WHERE u.company_id = 1
          AND u.is_active = 1
          AND u.receive_notifications = 1
          AND u.email IS NOT NULL
          AND u.email <> ''
    ";

    $stmt = $pdo->query($sql);
    return cleanEmailList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Owner/company contacts.
 * Company email always included if present.
 * User-linked contacts only included if active and opted in.
 */
function getOwnerReminderRecipients(PDO $pdo, int $vesselId): array
{
    $sql = "
        SELECT DISTINCT email
        FROM (
            SELECT o.email AS email
            FROM vessels v
            INNER JOIN owners o
                ON o.owner_id = v.company_id
            WHERE v.vessel_id = ?
              AND o.email IS NOT NULL
              AND o.email <> ''

            UNION

            SELECT u1.email AS email
            FROM vessels v
            INNER JOIN owners o
                ON o.owner_id = v.company_id
            INNER JOIN users u1
                ON u1.id = o.primary_contact_user_id
            WHERE v.vessel_id = ?
              AND u1.is_active = 1
              AND u1.receive_notifications = 1
              AND u1.email IS NOT NULL
              AND u1.email <> ''

            UNION

            SELECT u2.email AS email
            FROM vessels v
            INNER JOIN owners o
                ON o.owner_id = v.company_id
            INNER JOIN users u2
                ON u2.id = o.alt_contact_user_id
            WHERE v.vessel_id = ?
              AND u2.is_active = 1
              AND u2.receive_notifications = 1
              AND u2.email IS NOT NULL
              AND u2.email <> ''
        ) owner_emails
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vesselId, $vesselId, $vesselId]);

    return cleanEmailList($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sendVmsEmail(
    array $to,
    string $subject,
    string $plainBody,
    string $htmlBody,
    string $fromEmail,
    string $fromName,
    array $cc = []
): array {
    if (empty($to)) {
        return ['success' => false, 'error' => 'No TO recipients supplied'];
    }

    $result = sendVmsSystemEmail(
        $to,
        $subject,
        $plainBody,
        true,
        $htmlBody,
        $cc
    );

    return [
        'success' => !empty($result['success']),
        'error'   => !empty($result['success']) ? null : ($result['error'] ?? 'Unknown mailer error'),
    ];
}

echo "[" . date('Y-m-d H:i:s') . "] Starting compliance reminder job...\n";

if (!$reminders_enabled) {
    echo "Reminder job disabled.\n";
    exit(0);
}

$today = new DateTimeImmutable('today');
if ((int)$today->format('N') !== 1) {
    echo "SKIPPED | WEEKLY DIGEST | ALL VESSELS | reason=not_monday\n";
    exit(0);
}

$cycleStart = $today->setTime(0, 0, 0);
$cycleEnd   = $cycleStart->modify('+7 days');

$digestDocs = getWeeklyDigestVesselDocuments($pdo);
$digestEquipment = getWeeklyDigestVesselEquipment($pdo);

// TEMP TEST FILTER (set to null for all vessels)
$testOnlyVesselId = null; // or 7 to test a specific vessel

if (!empty($testOnlyVesselId)) {
    $digestDocs = array_values(array_filter($digestDocs, function ($doc) use ($testOnlyVesselId) {
        return (int)$doc['vessel_id'] === (int)$testOnlyVesselId;
    }));

    $digestEquipment = array_values(array_filter($digestEquipment, function ($equip) use ($testOnlyVesselId) {
        return (int)$equip['vessel_id'] === (int)$testOnlyVesselId;
    }));
}

if (!$digestDocs && !$digestEquipment) {
    echo "No weekly digest items found.\n";
    exit(0);
}

$vesselsToProcess = [];
foreach ($digestDocs as $doc) {
    $vesselId = (int)$doc['vessel_id'];

    if ($vesselId <= 0) {
        continue;
    }

    if (!isset($vesselsToProcess[$vesselId])) {
        $vesselsToProcess[$vesselId] = [
            'vessel_name' => (string)$doc['vesselName'],
            'documents'   => [],
            'equipment'   => [],
        ];
    }

    $vesselsToProcess[$vesselId]['documents'][] = $doc;
}

foreach ($digestEquipment as $equip) {
    $vesselId = (int)$equip['vessel_id'];

    if ($vesselId <= 0) {
        continue;
    }

    if (!isset($vesselsToProcess[$vesselId])) {
        $vesselsToProcess[$vesselId] = [
            'vessel_name' => (string)$equip['vesselName'],
            'documents'   => [],
            'equipment'   => [],
        ];
    }

    $vesselsToProcess[$vesselId]['equipment'][] = $equip;
}
$emailQueue = [];

foreach ($vesselsToProcess as $vesselId => $payload) {
    $vesselName = (string)$payload['vessel_name'];
    $documents  = (array)$payload['documents'];
    $equipment  = (array)$payload['equipment'];

    if (empty($documents) && empty($equipment)) {
        echo "SKIPPED | WEEKLY DIGEST | {$vesselName} | reason=no_items\n";
        continue;
    }

    $toRecipients = [];
    $ccRecipients = [];

    // ======================
    // TO: vessel users
    // ======================
    $vesselRecipients = getVesselReminderRecipients($pdo, (int)$vesselId);
    foreach ($vesselRecipients as $email) {
        addGroupedRecipient($toRecipients, $email, 'vessel_user');
    }

    // ======================
    // TO: owners
    // ======================
    if ($ownersEnabled) {
        $ownerRecipients = getOwnerReminderRecipients($pdo, (int)$vesselId);
        foreach ($ownerRecipients as $email) {
            addGroupedRecipient($toRecipients, $email, 'owner');
        }
    }

    // ======================
    // CC: MSCS staff
    // ======================
    $mscsRecipients = getGlobalMSCSReminderRecipients($pdo);
    foreach ($mscsRecipients as $email) {
        addGroupedRecipient($ccRecipients, $email, 'mscs_staff');
    }

    // Remove any TO recipient from CC if same email appears in both
    foreach (array_keys($toRecipients) as $toEmail) {
        unset($ccRecipients[$toEmail]);
    }

    // In dry run, show the real grouped recipients.
    // Only collapse to the override address during actual sending.
    if (!$dry_run && $test_mode && $test_email_override !== '') {
        $toRecipients = [
            strtolower($test_email_override) => [
                'email' => strtolower($test_email_override),
                'roles' => ['test_override'],
            ]
        ];
        $ccRecipients = [];
    }

    if (empty($toRecipients)) {
        echo "SKIPPED | WEEKLY DIGEST | {$vesselName} | reason=no_to_recipients\n";
        continue;
    }

    // Dry-run visibility
    foreach ($toRecipients as $r) {
        echo "QUEUE TO | {$r['email']} | {$vesselName} | roles=" . implode(',', $r['roles']) . "\n";
    }
    foreach ($ccRecipients as $r) {
        echo "QUEUE CC | {$r['email']} | {$vesselName} | roles=" . implode(',', $r['roles']) . "\n";
    }

    if (!$dry_run && !$ignoreCooldown) {
        $digestSubject = buildReminderSubject('weekly_digest', $vesselName, $documents, $equipment);
        if (weeklyDigestAlreadySent(
            $pdo,
            (int)$vesselId,
            $digestSubject,
            $cycleStart->format('Y-m-d H:i:s'),
            $cycleEnd->format('Y-m-d H:i:s')
        )) {
            echo "SKIPPED | WEEKLY DIGEST | {$vesselName} | reason=already_sent_this_week\n";
            continue;
        }
    }

    $emailQueue[$vesselId] = [
        'vessel_name'   => $vesselName,
        'documents'     => $documents,
        'equipment'     => $equipment,
        'to_recipients' => array_values($toRecipients),
        'cc_recipients' => array_values($ccRecipients),
    ];
}

$emailsProcessed = 0;

foreach ($emailQueue as $vesselId => $payload) {
    if ($emailsProcessed >= $maxEmailsPerRun) {
        echo "Send cap reached ({$maxEmailsPerRun}). Stopping run.\n";
        break;
    }

    $vesselName = (string)$payload['vessel_name'];
    $documents = (array)$payload['documents'];
    $equipment = (array)$payload['equipment'];
    $toRecipients = (array)$payload['to_recipients'];
    $ccRecipients = (array)$payload['cc_recipients'];

    $toEmails = array_map(fn($r) => $r['email'], $toRecipients);
    $ccEmails = array_map(fn($r) => $r['email'], $ccRecipients);

    $subject  = buildReminderSubject('weekly_digest', $vesselName, $documents, $equipment);
    $body     = buildReminderBody('weekly_digest', $vesselName, (int)$vesselId, $documents, $equipment);
    $htmlBody = buildReminderHtmlBody('weekly_digest', $vesselName, (int)$vesselId, $documents, $equipment);

    $emailsProcessed++;

    if ($dry_run) {
        echo "DRY RUN WEEKLY DIGEST | {$vesselName}\n";
        echo "  TO: " . (!empty($toEmails) ? implode(', ', $toEmails) : '[none]') . "\n";
        echo "  CC: " . (!empty($ccEmails) ? implode(', ', $ccEmails) : '[none]') . "\n";
        echo "  SUBJECT: {$subject}\n";
        echo "  DOCS: " . count($documents) . " | EQUIPMENT: " . count($equipment) . "\n";
        continue;
    }

    $primaryTo = $toEmails[0] ?? '';
    if ($primaryTo === '') {
        echo "SKIPPED | WEEKLY DIGEST | {$vesselName} | reason=no_to_recipients\n";
        continue;
    }

    $sendResult = sendVmsEmail($toEmails, $subject, $body, $htmlBody, $fromEmail, $fromName, $ccEmails);

    foreach ($documents as $doc) {
        logDocumentReminder($pdo, [
            'document_id'         => (int)$doc['id'],
            'vessel_id'           => (int)$doc['vessel_id'],
            'reminder_type'       => (string)$doc['reminder_type'],
            'expiration_snapshot' => (string)$doc['expDate'],
            'recipient_type'      => 'grouped',
            'recipient_email'     => $primaryTo,
            'email_subject'       => $subject,
            'email_status'        => $sendResult['success'] ? 'sent' : 'failed',
            'error_message'       => $sendResult['error'],
        ]);
    }

    foreach ($equipment as $item) {
        logEquipmentReminder($pdo, [
            'eid'                 => (int)$item['eid'],
            'vessel_id'           => (int)$item['vessel_id'],
            'reminder_type'       => (string)$item['reminder_type'],
            'expiration_snapshot' => (string)$item['expDate'],
            'recipient_type'      => 'grouped',
            'recipient_email'     => $primaryTo,
            'email_subject'       => $subject,
            'email_status'        => $sendResult['success'] ? 'sent' : 'failed',
            'error_message'       => $sendResult['error'],
        ]);
    }

    echo sprintf(
        "%s | WEEKLY DIGEST | %s | TO: %s | CC: %s | %d doc(s) | %d equipment item(s)\n",
        $sendResult['success'] ? 'SENT' : 'FAILED',
        $vesselName,
        implode(', ', $toEmails),
        implode(', ', $ccEmails),
        count($documents),
        count($equipment)
    );
}

echo "[" . date('Y-m-d H:i:s') . "] Compliance reminder job complete.\n";
exit(0);
