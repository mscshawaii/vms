<?php
declare(strict_types=1);

require __DIR__ . '/../db_connect.php';
require __DIR__ . '/../session_check.php';
require_once __DIR__ . '/../includes/document_reminder_functions.php';
require_once __DIR__ . '/../includes/equipment_reminder_functions.php';
require_once __DIR__ . '/../includes/icr_reminder_functions.php';
require_once __DIR__ . '/../includes/reminder_recipient_helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);

if ($company_id !== 1 && $role_id !== 1) {
    http_response_code(403);
    exit('Access denied.');
}

$config = require __DIR__ . '/../config_reminders.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function cleanAuditEmailList(array $emails): array
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

function addAuditRecipient(array &$bucket, string $email, string $roleLabel, string $sourceName = ''): void
{
    $normalized = reminder_normalize_email($email);
    if ($normalized === null) {
        return;
    }

    if (!isset($bucket[$normalized])) {
        $bucket[$normalized] = [
            'email' => trim($email),
            'roles' => [],
            'sources' => [],
        ];
    }

    if (!in_array($roleLabel, $bucket[$normalized]['roles'], true)) {
        $bucket[$normalized]['roles'][] = $roleLabel;
    }
    if ($sourceName !== '' && !in_array($sourceName, $bucket[$normalized]['sources'], true)) {
        $bucket[$normalized]['sources'][] = $sourceName;
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function badge(string $text, string $class = 'secondary'): string
{
    return '<span class="badge text-bg-' . h($class) . '">' . h($text) . '</span>';
}

function formatAuditRecipients(array $recipients): string
{
    if (!$recipients) {
        return '<span class="text-muted">None</span>';
    }

    $parts = [];
    foreach ($recipients as $recipient) {
        $roles = !empty($recipient['roles']) ? ' <span class="text-muted">(' . h(implode(', ', $recipient['roles'])) . ')</span>' : '';
        $parts[] = '<div class="recipient-line">' . h($recipient['email'] ?? '') . $roles . '</div>';
    }
    return implode('', $parts);
}

function getAuditVesselRecipients(PDO $pdo, int $vesselId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.email, CONCAT_WS(' ', u.fName, u.lName) AS user_name
        FROM vessel_crew vc
        INNER JOIN users u ON u.id = vc.crew_id
        WHERE vc.vessel_id = ?
          AND vc.is_active = 1
          AND u.is_active = 1
          AND u.receive_notifications = 1
          AND u.email IS NOT NULL
          AND u.email <> ''
    ");
    $stmt->execute([$vesselId]);

    $recipients = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        addAuditRecipient($recipients, (string)$row['email'], 'vessel_user', (string)$row['user_name']);
    }
    return $recipients;
}

function getAuditGlobalMscsRecipients(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT DISTINCT u.email, CONCAT_WS(' ', u.fName, u.lName) AS user_name
        FROM users u
        WHERE u.company_id = 1
          AND u.is_active = 1
          AND u.receive_notifications = 1
          AND u.email IS NOT NULL
          AND u.email <> ''
    ");

    $recipients = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        addAuditRecipient($recipients, (string)$row['email'], 'mscs_staff', (string)$row['user_name']);
    }
    return $recipients;
}

function getAuditOwnerRecipients(PDO $pdo, int $vesselId): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT email, source_name, source_role
        FROM (
            SELECT o.email AS email, o.company_name AS source_name, 'owner_company' AS source_role
            FROM vessels v
            INNER JOIN owners o ON o.owner_id = v.company_id
            WHERE v.vessel_id = ?
              AND o.email IS NOT NULL
              AND o.email <> ''

            UNION

            SELECT u1.email AS email, CONCAT_WS(' ', u1.fName, u1.lName) AS source_name, 'owner_primary_user' AS source_role
            FROM vessels v
            INNER JOIN owners o ON o.owner_id = v.company_id
            INNER JOIN users u1 ON u1.id = o.primary_contact_user_id
            WHERE v.vessel_id = ?
              AND u1.is_active = 1
              AND u1.receive_notifications = 1
              AND u1.email IS NOT NULL
              AND u1.email <> ''

            UNION

            SELECT u2.email AS email, CONCAT_WS(' ', u2.fName, u2.lName) AS source_name, 'owner_alt_user' AS source_role
            FROM vessels v
            INNER JOIN owners o ON o.owner_id = v.company_id
            INNER JOIN users u2 ON u2.id = o.alt_contact_user_id
            WHERE v.vessel_id = ?
              AND u2.is_active = 1
              AND u2.receive_notifications = 1
              AND u2.email IS NOT NULL
              AND u2.email <> ''
        ) owner_emails
    ");
    $stmt->execute([$vesselId, $vesselId, $vesselId]);

    $recipients = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        addAuditRecipient($recipients, (string)$row['email'], (string)$row['source_role'], (string)$row['source_name']);
    }
    return $recipients;
}

function buildAuditRecipientPreview(PDO $pdo, int $vesselId, array $config): array
{
    $dryRun = (bool)($config['dry_run'] ?? true);
    $testMode = (bool)($config['test_mode'] ?? true);
    $ownersEnabled = (bool)($config['owners_enabled'] ?? false);
    $testOverride = strtolower(trim((string)($config['test_email_override'] ?? '')));

    $to = getAuditVesselRecipients($pdo, $vesselId);

    if ($ownersEnabled) {
        foreach (getAuditOwnerRecipients($pdo, $vesselId) as $recipient) {
            foreach ((array)($recipient['roles'] ?? ['owner']) as $role) {
                addAuditRecipient($to, (string)$recipient['email'], (string)$role, implode(', ', (array)($recipient['sources'] ?? [])));
            }
        }
    }

    $cc = getAuditGlobalMscsRecipients($pdo);
    foreach (array_keys($to) as $toEmail) {
        unset($cc[$toEmail]);
    }

    $effectiveTo = $to;
    $effectiveCc = $cc;
    $testOverrideActive = false;

    if (!$dryRun && $testMode && $testOverride !== '' && filter_var($testOverride, FILTER_VALIDATE_EMAIL)) {
        $effectiveTo = [
            $testOverride => [
                'email' => $testOverride,
                'roles' => ['test_override'],
                'sources' => ['config_reminders.php'],
            ],
        ];
        $effectiveCc = [];
        $testOverrideActive = true;
    }

    return [
        'configured_to' => $to,
        'configured_cc' => $cc,
        'effective_to' => $effectiveTo,
        'effective_cc' => $effectiveCc,
        'test_override_active' => $testOverrideActive,
    ];
}

function configModeText(array $config): string
{
    if ((bool)($config['dry_run'] ?? true)) {
        return 'Dry run';
    }
    if ((bool)($config['test_mode'] ?? true)) {
        return 'Test mode';
    }
    return 'Release mode';
}

$filterType = trim((string)($_GET['type'] ?? 'all'));
$filterCompany = (int)($_GET['company_id'] ?? 0);
$filterEligibility = trim((string)($_GET['eligibility'] ?? 'all'));
$showWeekly = in_array($filterType, ['all', 'weekly'], true);
$showMonthly = in_array($filterType, ['all', 'monthly'], true);

$companies = $pdo->query("
    SELECT owner_id, company_name
    FROM owners
    ORDER BY company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$vesselSql = "
    SELECT v.vessel_id, v.vesselName, v.vesselON, v.company_id, o.company_name
    FROM vessels v
    LEFT JOIN owners o ON o.owner_id = v.company_id
    WHERE v.is_active = 1
      AND v.archived_at IS NULL
      AND COALESCE(v.is_deleted, 0) = 0
";
$vesselParams = [];
if ($filterCompany > 0) {
    $vesselSql .= " AND v.company_id = ?";
    $vesselParams[] = $filterCompany;
}
$vesselSql .= " ORDER BY o.company_name ASC, v.vesselName ASC";
$vesselStmt = $pdo->prepare($vesselSql);
$vesselStmt->execute($vesselParams);
$vessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);

$weeklyDocs = getWeeklyDigestVesselDocuments($pdo);
$weeklyEquipment = getWeeklyDigestVesselEquipment($pdo);
$weeklyItemCounts = [];
foreach ($weeklyDocs as $row) {
    $id = (int)$row['vessel_id'];
    $weeklyItemCounts[$id]['documents'] = ($weeklyItemCounts[$id]['documents'] ?? 0) + 1;
}
foreach ($weeklyEquipment as $row) {
    $id = (int)$row['vessel_id'];
    $weeklyItemCounts[$id]['equipment'] = ($weeklyItemCounts[$id]['equipment'] ?? 0) + 1;
}

$asOf = new DateTimeImmutable('today');
$monthlyDueByVessel = getDueVesselICRs($pdo, $asOf);

$vesselAccessCounts = [];
$accessStmt = $pdo->query("
    SELECT crew_id, COUNT(DISTINCT vessel_id) AS vessel_count
    FROM vessel_crew
    WHERE is_active = 1
    GROUP BY crew_id
");
foreach ($accessStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $vesselAccessCounts[(int)$row['crew_id']] = (int)$row['vessel_count'];
}

$userSql = "
    SELECT
        u.id, u.fName, u.lName, u.email, u.company_id,
        u.role_id, u.is_active, u.receive_notifications,
        u.receive_doc_reminders, u.receive_equipment_reminders,
        u.receive_crew_reminders, u.receive_inspection_reminders,
        o.company_name, r.role_name,
        EXISTS (
            SELECT 1 FROM owners ox
            WHERE ox.primary_contact_user_id = u.id
               OR ox.alt_contact_user_id = u.id
        ) AS is_owner_contact
    FROM users u
    LEFT JOIN owners o ON o.owner_id = u.company_id
    LEFT JOIN roles r ON r.role_id = u.role_id
";
$userParams = [];
if ($filterCompany > 0) {
    $userSql .= " WHERE u.company_id = ?";
    $userParams[] = $filterCompany;
}
$userSql .= " ORDER BY o.company_name ASC, u.is_active DESC, u.lName ASC, u.fName ASC";
$userStmt = $pdo->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$matrixRows = [];
foreach ($users as $user) {
    $userId = (int)$user['id'];
    $email = trim((string)$user['email']);
    $validEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    $isActive = (int)$user['is_active'] === 1;
    $isMscs = (int)$user['company_id'] === 1;
    $vesselCount = $vesselAccessCounts[$userId] ?? 0;
    $isOwnerContact = (int)$user['is_owner_contact'] === 1;
    $enrolled = (int)$user['receive_notifications'] === 1;

    $eligible = $isActive && $validEmail && ($isMscs || $vesselCount > 0 || $isOwnerContact);
    $group = $eligible
        ? ($enrolled ? 'Eligible + enrolled' : 'Eligible + not enrolled')
        : ($enrolled ? 'Not eligible + enrolled' : 'Not eligible + not enrolled');

    $notes = [];
    if (!$validEmail) $notes[] = 'Missing/invalid email';
    if (!$isActive) $notes[] = 'Inactive user';
    if (!$isMscs && $vesselCount === 0 && !$isOwnerContact) $notes[] = 'No active vessel access or owner contact link';
    if (!$enrolled) $notes[] = 'Master receive_notifications is off';
    if ((int)$user['receive_doc_reminders'] || (int)$user['receive_equipment_reminders'] || (int)$user['receive_crew_reminders'] || (int)$user['receive_inspection_reminders']) {
        $notes[] = 'Granular reminder columns exist but current runners use receive_notifications';
    }

    $matrixRows[] = [
        'user' => $user,
        'vessel_count' => $vesselCount,
        'eligible' => $eligible,
        'enrolled' => $enrolled,
        'group' => $group,
        'notes' => $notes,
    ];
}

if ($filterEligibility !== 'all') {
    $matrixRows = array_values(array_filter($matrixRows, static function (array $row) use ($filterEligibility): bool {
        return $row['group'] === $filterEligibility;
    }));
}

$lastWeekly = null;
if (tableExists($pdo, 'document_reminder_log') || tableExists($pdo, 'equipment_reminder_log')) {
    $lastWeekly = $pdo->query("
        SELECT MAX(sent_at)
        FROM (
            SELECT sent_at FROM document_reminder_log
            UNION ALL
            SELECT sent_at FROM equipment_reminder_log
        ) x
    ")->fetchColumn() ?: null;
}

$lastMonthly = tableExists($pdo, 'icr_reminder_log')
    ? ($pdo->query("SELECT MAX(sent_at) FROM icr_reminder_log")->fetchColumn() ?: null)
    : null;

$recentLogs = [];
if (tableExists($pdo, 'document_reminder_log')) {
    $recentLogs = array_merge($recentLogs, $pdo->query("
        SELECT drl.sent_at, 'Weekly document' AS reminder_type, v.vesselName, drl.recipient_email,
               drl.email_subject, drl.email_status, drl.error_message
        FROM document_reminder_log drl
        LEFT JOIN vessels v ON v.vessel_id = drl.vessel_id
        ORDER BY drl.sent_at DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC));
}
if (tableExists($pdo, 'equipment_reminder_log')) {
    $recentLogs = array_merge($recentLogs, $pdo->query("
        SELECT erl.sent_at, 'Weekly equipment' AS reminder_type, v.vesselName, erl.recipient_email,
               erl.email_subject, erl.email_status, erl.error_message
        FROM equipment_reminder_log erl
        LEFT JOIN vessels v ON v.vessel_id = erl.vessel_id
        ORDER BY erl.sent_at DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC));
}
if (tableExists($pdo, 'icr_reminder_log')) {
    $recentLogs = array_merge($recentLogs, $pdo->query("
        SELECT irl.sent_at, 'Monthly maintenance' AS reminder_type, v.vesselName, irl.recipient_email,
               irl.email_subject, irl.email_status, irl.error_message
        FROM icr_reminder_log irl
        LEFT JOIN vessels v ON v.vessel_id = irl.vessel_id
        ORDER BY irl.sent_at DESC
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC));
}
usort($recentLogs, static fn($a, $b) => strcmp((string)$b['sent_at'], (string)$a['sent_at']));
$recentLogs = array_values(array_filter($recentLogs, static function (array $log) use ($filterType): bool {
    if ($filterType === 'weekly') {
        return strpos((string)($log['reminder_type'] ?? ''), 'Weekly') === 0;
    }
    if ($filterType === 'monthly') {
        return strpos((string)($log['reminder_type'] ?? ''), 'Monthly') === 0;
    }
    return true;
}));
$recentLogs = array_slice($recentLogs, 0, 40);

$today = new DateTimeImmutable('today');
$weekStart = ((int)$today->format('N') === 1) ? $today : $today->modify('last monday');
$weekEnd = $weekStart->modify('+7 days');
$summaryMonth = $today->modify('first day of this month')->format('Y-m-d');

$weeklyWindowCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM (
        SELECT sent_at, email_status FROM document_reminder_log
        UNION ALL
        SELECT sent_at, email_status FROM equipment_reminder_log
    ) x
    WHERE sent_at >= ?
      AND sent_at < ?
");
$weeklyWindowCount->execute([$weekStart->format('Y-m-d 00:00:00'), $weekEnd->format('Y-m-d 00:00:00')]);
$weeklyWindowTotal = (int)$weeklyWindowCount->fetchColumn();

$monthlyWindowTotal = 0;
if (tableExists($pdo, 'icr_reminder_log')) {
    $monthlyStmt = $pdo->prepare("SELECT COUNT(*) FROM icr_reminder_log WHERE summary_month = ?");
    $monthlyStmt->execute([$summaryMonth]);
    $monthlyWindowTotal = (int)$monthlyStmt->fetchColumn();
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminder Audit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vms-mobile.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
        .audit-shell { max-width: 1500px; margin: 0 auto; padding: 18px; }
        .section-card { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 16px; }
        .section-card .card-body { padding: 16px; }
        .table-sm td, .table-sm th { vertical-align: middle; }
        .recipient-line { white-space: nowrap; }
        .sticky-head th { position: sticky; top: 0; background: #fff; z-index: 1; }
        .small-muted { color: #6b7280; font-size: .86rem; }
        .filter-bar .form-select { min-height: 40px; }
    </style>
</head>
<body>
<div class="audit-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Reminder Audit</h1>
            <div class="text-muted">Read-only preview of reminder configuration, eligibility, recipients, and logs.</div>
        </div>
        <a class="btn btn-outline-secondary" href="../dashboard.php?company_id=<?= (int)$company_id ?>">Back</a>
    </div>

    <form class="section-card filter-bar" method="get">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Reminder type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="weekly" <?= $filterType === 'weekly' ? 'selected' : '' ?>>Weekly Compliance</option>
                        <option value="monthly" <?= $filterType === 'monthly' ? 'selected' : '' ?>>Monthly Maintenance</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Company</label>
                    <select name="company_id" class="form-select">
                        <option value="0">All companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= (int)$company['owner_id'] ?>" <?= $filterCompany === (int)$company['owner_id'] ? 'selected' : '' ?>>
                                <?= h($company['company_name'] ?: ('Company #' . $company['owner_id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Eligibility group</label>
                    <select name="eligibility" class="form-select">
                        <?php
                        $groups = ['all' => 'All', 'Eligible + enrolled' => 'Eligible + enrolled', 'Eligible + not enrolled' => 'Eligible + not enrolled', 'Not eligible + enrolled' => 'Not eligible + enrolled', 'Not eligible + not enrolled' => 'Not eligible + not enrolled'];
                        foreach ($groups as $value => $label):
                        ?>
                            <option value="<?= h($value) ?>" <?= $filterEligibility === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Apply</button>
                </div>
            </div>
        </div>
    </form>

    <div class="section-card">
        <div class="card-body">
            <h2 class="h5">Section A - Reminder System Status</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>System</th><th>Status</th><th>Mode</th><th>Script</th><th>Last logged send</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php if ($showWeekly): ?>
                    <tr>
                        <td>Weekly Compliance Digest</td>
                        <td><?= (bool)($config['reminders_enabled'] ?? false) ? badge('Enabled', 'success') : badge('Disabled', 'secondary') ?></td>
                        <td><?= h(configModeText($config)) ?></td>
                        <td><code>run_document_expiration_reminders.php</code></td>
                        <td><?= h($lastWeekly ?: 'Unknown / not detected') ?></td>
                        <td>Monday-only runner; duplicate window is current Monday-to-Monday cycle.</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($showMonthly): ?>
                    <tr>
                        <td>Monthly Maintenance Digest</td>
                        <td><?= (bool)($config['reminders_enabled'] ?? false) ? badge('Enabled', 'success') : badge('Disabled', 'secondary') ?></td>
                        <td><?= h(configModeText($config)) ?></td>
                        <td><code>run_icr_monthly_reminders.php</code></td>
                        <td><?= h($lastMonthly ?: 'Unknown / not detected') ?></td>
                        <td>Duplicate window is current <code>summary_month</code>. Corrective action digest not included.</td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="small-muted mt-2">
                Test override: <?= (!empty($config['test_email_override']) && !(bool)($config['dry_run'] ?? true) && (bool)($config['test_mode'] ?? true)) ? badge('Active', 'warning') . ' ' . h($config['test_email_override']) : badge('Inactive', 'secondary') ?>
                Owners enabled: <?= (bool)($config['owners_enabled'] ?? false) ? badge('Yes', 'success') : badge('No', 'secondary') ?>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="card-body">
            <h2 class="h5">Section B - User Reminder Eligibility Matrix</h2>
            <div class="small-muted mb-2">Current runners use <code>receive_notifications</code> for both weekly and monthly user enrollment. Recipient previews are normalized and deduped by email.</div>
            <div class="table-responsive" style="max-height: 620px;">
                <table class="table table-sm table-striped">
                    <thead class="sticky-head">
                    <tr>
                        <th>User ID</th><th>Name</th><th>Company</th><th>Email</th><th>Active</th><th>Role</th><th>Vessel access</th>
                        <th>Weekly enrolled</th><th>Monthly enrolled</th><th>Weekly eligibility</th><th>Monthly eligibility</th><th>Reason / notes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($matrixRows as $row): $u = $row['user']; ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><?= h(trim(($u['fName'] ?? '') . ' ' . ($u['lName'] ?? ''))) ?></td>
                            <td><?= h($u['company_name'] ?? '') ?></td>
                            <td>
                                <?= h($u['email'] ?? '') ?>
                                <?php if (!filter_var((string)($u['email'] ?? ''), FILTER_VALIDATE_EMAIL)): ?> <?= badge('Missing email', 'warning') ?><?php endif; ?>
                            </td>
                            <td><?= (int)$u['is_active'] === 1 ? badge('Active', 'success') : badge('Inactive', 'secondary') ?></td>
                            <td><?= h($u['role_name'] ?? ('Role #' . ($u['role_id'] ?? ''))) ?></td>
                            <td><?= (int)$row['vessel_count'] ?></td>
                            <td><?= $row['enrolled'] ? badge('Enabled', 'success') : badge('Off', 'secondary') ?></td>
                            <td><?= $row['enrolled'] ? badge('Enabled', 'success') : badge('Off', 'secondary') ?></td>
                            <td><?= $row['eligible'] ? badge('Eligible', 'success') : badge('Not eligible', 'secondary') ?></td>
                            <td><?= $row['eligible'] ? badge('Eligible', 'success') : badge('Not eligible', 'secondary') ?></td>
                            <td>
                                <?= h(implode('; ', $row['notes']) ?: $row['group']) ?>
                                <?php if ($row['eligible'] && !$row['enrolled']): ?> <?= badge('Eligible but not enrolled', 'warning') ?><?php endif; ?>
                                <?php if (!$row['eligible'] && $row['enrolled']): ?> <?= badge('Enrolled but not eligible', 'danger') ?><?php endif; ?>
                                <?php if ((int)$u['is_active'] !== 1 && $row['enrolled']): ?> <?= badge('Inactive but enrolled', 'danger') ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="card-body">
            <h2 class="h5">Section C - Vessel Recipient Preview</h2>
            <div class="table-responsive" style="max-height: 620px;">
                <table class="table table-sm table-striped">
                    <thead class="sticky-head">
                    <tr>
                        <th>Vessel</th><th>Owner/company</th>
                        <?php if ($showWeekly): ?><th>Weekly Compliance final TO</th><?php endif; ?>
                        <?php if ($showMonthly): ?><th>Monthly Maintenance final TO</th><?php endif; ?>
                        <th>Final CC / test recipients</th><th>Warnings</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($vessels as $vessel):
                        $preview = buildAuditRecipientPreview($pdo, (int)$vessel['vessel_id'], $config);
                        $weeklyCounts = $weeklyItemCounts[(int)$vessel['vessel_id']] ?? ['documents' => 0, 'equipment' => 0];
                        $monthlyPayload = $monthlyDueByVessel[(int)$vessel['vessel_id']] ?? null;
                        $warnings = [];
                        if (!$preview['effective_to']) $warnings[] = badge('Vessel has no live recipients', 'danger');
                        if ($preview['test_override_active']) $warnings[] = badge('Only test override receives live email', 'warning');
                        if (!$preview['configured_to'] && $preview['configured_cc']) $warnings[] = badge('Only MSCS/test recipients', 'warning');
                    ?>
                        <tr>
                            <td>
                                <strong><?= h($vessel['vesselName']) ?></strong><br>
                                <span class="small-muted">#<?= (int)$vessel['vessel_id'] ?> ON <?= h($vessel['vesselON'] ?? '') ?></span>
                            </td>
                            <td><?= h($vessel['company_name'] ?? '') ?></td>
                            <?php if ($showWeekly): ?>
                            <td>
                                <?= formatAuditRecipients($preview['effective_to']) ?>
                                <div class="small-muted">Items now: docs <?= (int)($weeklyCounts['documents'] ?? 0) ?>, equipment <?= (int)($weeklyCounts['equipment'] ?? 0) ?></div>
                                <?php if ($preview['test_override_active']): ?>
                                    <div class="small-muted">Configured live TO before override: <?= count($preview['configured_to']) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <?php if ($showMonthly): ?>
                            <td>
                                <?= formatAuditRecipients($preview['effective_to']) ?>
                                <div class="small-muted">
                                    ICRs now:
                                    <?php if ($monthlyPayload): ?>
                                        overdue <?= count($monthlyPayload['overdue'] ?? []) ?>,
                                        due <?= count($monthlyPayload['due_this_month'] ?? []) ?>,
                                        upcoming <?= count($monthlyPayload['upcoming'] ?? []) ?>
                                    <?php else: ?>
                                        none detected
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?= $preview['test_override_active'] ? formatAuditRecipients($preview['effective_to']) : formatAuditRecipients($preview['effective_cc']) ?>
                            </td>
                            <td><?= $warnings ? implode(' ', $warnings) : badge('OK', 'success') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="card-body">
            <h2 class="h5">Section D - Reminder Log Summary</h2>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <strong>Weekly duplicate window</strong><br>
                        <?= h($weekStart->format('Y-m-d')) ?> to <?= h($weekEnd->format('Y-m-d')) ?>:
                        <?= (int)$weeklyWindowTotal ?> log row(s)
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light">
                        <strong>Monthly duplicate window</strong><br>
                        Summary month <?= h($summaryMonth) ?>:
                        <?= (int)$monthlyWindowTotal ?> log row(s)
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Date/time</th><th>Type</th><th>Vessel</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Error</th></tr></thead>
                    <tbody>
                    <?php if (!$recentLogs): ?>
                        <tr><td colspan="7" class="text-muted">No reminder log activity detected.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?= h($log['sent_at'] ?? '') ?></td>
                            <td><?= h($log['reminder_type'] ?? '') ?></td>
                            <td><?= h($log['vesselName'] ?? 'Unknown / not detected') ?></td>
                            <td><?= h($log['recipient_email'] ?? '') ?></td>
                            <td><?= h($log['email_subject'] ?? 'Unknown / not detected') ?></td>
                            <td><?= h($log['email_status'] ?? 'Unknown / not detected') ?></td>
                            <td><?= h($log['error_message'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="small-muted">Missing fields are shown as Unknown / not detected. This page performs no sends and no database writes.</div>
        </div>
    </div>
</div>
</body>
</html>
