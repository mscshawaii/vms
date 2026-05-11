<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/icr_reminder_functions.php';
require_once __DIR__ . '/includes/email_helper.php';
require_once __DIR__ . '/includes/reminder_recipient_helpers.php';

$config = require __DIR__ . '/config_reminders.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if (
    empty($config['runner_token']) ||
    empty($token) ||
    !hash_equals((string)$config['runner_token'], $token)
) {
    http_response_code(403);
    exit("Forbidden\n");
}

$reminders_enabled      = (bool)($config['reminders_enabled'] ?? false);
$dry_run                = (bool)($config['dry_run'] ?? true);
$test_mode              = (bool)($config['test_mode'] ?? true);
$ownersEnabled          = (bool)($config['owners_enabled'] ?? false);
$test_email_override    = trim((string)($config['test_email_override'] ?? ''));
$maxEmailsPerRun        = max(1, (int)($config['max_emails_per_run'] ?? 10));
$fromEmail              = trim((string)($config['from_email'] ?? 'info@mschawaii.org'));
$fromName               = trim((string)($config['from_name'] ?? 'MSCS Hawaii VMS'));

header('Content-Type: text/plain; charset=UTF-8');

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

    $primaryTo = array_shift($to);

    $result = sendVmsSystemEmail(
        $primaryTo,
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

echo "[" . date('Y-m-d H:i:s') . "] Starting ICR monthly reminder job...\n";

if (!$reminders_enabled) {
    echo "Reminder job disabled.\n";
    exit(0);
}

$asOf = new DateTimeImmutable('today');
$summaryMonth = $asOf->modify('first day of this month')->format('Y-m-d');

$dueByVessel = getDueVesselICRs($pdo, $asOf);

if (empty($dueByVessel)) {
    echo "No due/overdue vessel ICRs found.\n";
    exit(0);
}

$emailQueue = [];

foreach ($dueByVessel as $vesselId => $payload) {
    $toRecipients = [];
    $ccRecipients = [];

    $vesselRecipients = getVesselReminderRecipients($pdo, (int)$vesselId);
    foreach ($vesselRecipients as $email) {
        addGroupedRecipient($toRecipients, $email, 'vessel_user');
    }

    if ($ownersEnabled) {
        $ownerRecipients = getOwnerReminderRecipients($pdo, (int)$vesselId);
        foreach ($ownerRecipients as $email) {
            addGroupedRecipient($toRecipients, $email, 'owner');
        }
    }

    $mscsRecipients = getGlobalMSCSReminderRecipients($pdo);
    foreach ($mscsRecipients as $email) {
        addGroupedRecipient($ccRecipients, $email, 'mscs_staff');
    }

    foreach (array_keys($toRecipients) as $toEmail) {
        unset($ccRecipients[$toEmail]);
    }

    if (!$dry_run && $test_mode && $test_email_override !== '') {
        $toRecipients = [
            strtolower($test_email_override) => [
                'email' => strtolower($test_email_override),
                'roles' => ['test_override'],
            ]
        ];
        $ccRecipients = [];
    }

    if (empty($toRecipients) && empty($ccRecipients)) {
        continue;
    }

        $summaryMonth = $asOf->modify('first day of this month')->format('Y-m-d');

    if (!$dry_run && !$test_mode) {
        $primaryRecipient = array_values($toRecipients)[0]['email'] ?? '';
        if ($primaryRecipient !== '' && icrMonthlyReminderAlreadySent($pdo, (int)$vesselId, $summaryMonth, 'grouped', $primaryRecipient)) {
            echo "SKIPPED DUPLICATE MONTHLY ICR | {$payload['vessel_name']} | {$primaryRecipient}\n";
            continue;
        }
    }

    foreach ($toRecipients as $r) {
        echo "QUEUE TO | {$r['email']} | {$payload['vessel_name']} | roles=" . implode(',', $r['roles']) . "\n";
    }
    foreach ($ccRecipients as $r) {
        echo "QUEUE CC | {$r['email']} | {$payload['vessel_name']} | roles=" . implode(',', $r['roles']) . "\n";
    }

    $emailQueue[$vesselId] = [
        'vessel_name'    => $payload['vessel_name'],
        'overdue'        => $payload['overdue'],
        'due_this_month' => $payload['due_this_month'],
        'upcoming'       => $payload['upcoming'],
        'drill_summary'  => getVesselDrillSummary($pdo, (int)$vesselId),
        'to_recipients'  => array_values($toRecipients),
        'cc_recipients'  => array_values($ccRecipients),
    ];
}

$emailsProcessed = 0;

foreach ($emailQueue as $vesselId => $payload) {
    if ($emailsProcessed >= $maxEmailsPerRun) {
        echo "Send cap reached ({$maxEmailsPerRun}). Stopping run.\n";
        break;
    }

$vesselName = (string)$payload['vessel_name'];
$overdue = (array)$payload['overdue'];
$dueThisMonth = (array)$payload['due_this_month'];
$upcoming = (array)$payload['upcoming'];
$drillSummary = (array)($payload['drill_summary'] ?? []);
$toRecipients = (array)$payload['to_recipients'];
$ccRecipients = (array)$payload['cc_recipients'];

$toEmails = array_map(fn($r) => $r['email'], $toRecipients);
$ccEmails = array_map(fn($r) => $r['email'], $ccRecipients);
[$toEmails, $ccEmails] = reminder_dedupe_to_cc($toEmails, $ccEmails);

$subject = buildIcrReminderSubject($vesselName, $asOf);

$body = buildIcrReminderBody(
    $vesselName,
    (int)$vesselId,
    $overdue,
    $dueThisMonth,
    $upcoming,
    $drillSummary
);

$htmlBody = buildIcrReminderHtmlBody(
    $vesselName,
    (int)$vesselId,
    $overdue,
    $dueThisMonth,
    $upcoming,
    $drillSummary
);

    $emailsProcessed++;

    if ($dry_run) {
        echo "DRY RUN GROUPED ICR | {$vesselName}\n";
        echo "  TO: " . (!empty($toEmails) ? implode(', ', $toEmails) : '[none]') . "\n";
        echo "  CC: " . (!empty($ccEmails) ? implode(', ', $ccEmails) : '[none]') . "\n";
        echo "  SUBJECT: {$subject}\n";
        echo "  OVERDUE: " . count($overdue) . " | DUE: " . count($dueThisMonth) . " | UPCOMING: " . count($upcoming) . "\n";
        continue;
    }

    $primaryTo = $toEmails[0] ?? '';
    if ($primaryTo === '') {
        echo "SKIPPED NO TO RECIPIENT | {$vesselName}\n";
        continue;
    }

    $sendResult = sendVmsEmail($toEmails, $subject, $body, $htmlBody, $fromEmail, $fromName, $ccEmails);

    logIcrReminder($pdo, [
        'vessel_id'       => (int)$vesselId,
        'summary_month'   => $summaryMonth,
        'recipient_type'  => 'grouped',
        'recipient_email' => $primaryTo,
        'email_subject'   => $subject,
        'overdue_count'   => count($overdue),
        'due_count'       => count($dueThisMonth),
        'upcoming_count'  => count($upcoming),
        'email_status'    => $sendResult['success'] ? 'sent' : 'failed',
        'error_message'   => $sendResult['error'],
    ]);

    echo sprintf(
        "%s | GROUPED ICR | %s | TO: %s | CC: %s | overdue=%d | due=%d | upcoming=%d\n",
        $sendResult['success'] ? 'SENT' : 'FAILED',
        $vesselName,
        implode(', ', $toEmails),
        implode(', ', $ccEmails),
        count($overdue),
        count($dueThisMonth),
        count($upcoming)
    );
}

echo "[" . date('Y-m-d H:i:s') . "] ICR monthly reminder job complete.\n";
exit(0);
