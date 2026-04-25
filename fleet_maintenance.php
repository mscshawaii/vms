<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function fleet_safe($value): string
{
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function fleet_date(?string $value): string
{
    if (!$value || $value === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y', $ts) : fleet_safe($value);
}

function fleet_datetime(?string $value): string
{
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('M j, Y g:i A', $ts) : fleet_safe($value);
}

function fleet_task_status_badge(?string $status): string
{
    $status = strtolower(trim((string)$status));
    $map = [
        'open' => 'secondary',
        'in_progress' => 'info',
        'overdue' => 'danger',
        'deferred' => 'dark',
        'complete' => 'success',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = $status !== '' ? ucwords(str_replace('_', ' ', $status)) : 'Unknown';
    return '<span class="badge bg-' . $class . '">' . fleet_safe($label) . '</span>';
}

function fleet_focus_badge(string $label, string $class = 'secondary'): string
{
    return '<span class="badge bg-' . $class . '">' . fleet_safe($label) . '</span>';
}

function fleet_summary_card(string $label, int $count, string $class, string $meta = ''): string
{
    $metaHtml = $meta !== '' ? '<div class="fleet-summary-meta">' . fleet_safe($meta) . '</div>' : '';
    return '
        <div class="fleet-summary-card border-start border-4 border-' . $class . '">
            <div class="fleet-summary-label">' . fleet_safe($label) . '</div>
            <div class="fleet-summary-value text-' . $class . '">' . (int)$count . '</div>
            ' . $metaHtml . '
        </div>
    ';
}

function fleet_show_section(string $focus, string $section): bool
{
    return $focus === 'all' || $focus === $section;
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
$companyId = (int)($_SESSION['company_id'] ?? 0);

$vesselFilter = (int)($_GET['vessel_id'] ?? 0);
$focus = trim((string)($_GET['focus'] ?? 'all'));
$allowedFocus = ['all', 'cars', 'overdue', 'due_soon', 'verifications', 'completions', 'issues'];
if (!in_array($focus, $allowedFocus, true)) {
    $focus = 'all';
}

$completionDays = (int)($_GET['completion_days'] ?? 30);
if (!in_array($completionDays, [7, 30, 60, 90], true)) {
    $completionDays = 30;
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

$vesselSql = "
    SELECT v.vessel_id, v.vesselName, o.company_name
    FROM vessels v
    LEFT JOIN owners o ON o.owner_id = v.company_id
    WHERE COALESCE(v.is_active, 1) = 1
";
$vesselParams = [];

if ($roleId !== 1) {
    $vesselSql .= " AND v.company_id = ?";
    $vesselParams[] = $companyId;
}

$vesselSql .= " ORDER BY o.company_name ASC, v.vesselName ASC";
$vesselStmt = $pdo->prepare($vesselSql);
$vesselStmt->execute($vesselParams);
$accessibleVessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);

$accessibleVesselIds = array_map(static fn(array $row): int => (int)$row['vessel_id'], $accessibleVessels);
if ($vesselFilter > 0 && !in_array($vesselFilter, $accessibleVesselIds, true)) {
    $vesselFilter = 0;
}

$scopeSql = " COALESCE(v.is_active, 1) = 1 ";
$scopeParams = [];
if ($roleId !== 1) {
    $scopeSql .= " AND v.company_id = ? ";
    $scopeParams[] = $companyId;
}
if ($vesselFilter > 0) {
    $scopeSql .= " AND v.vessel_id = ? ";
    $scopeParams[] = $vesselFilter;
}

$recentCompletionStart = (new DateTimeImmutable('today'))->modify('-' . max(1, $completionDays - 1) . ' days')->format('Y-m-d');

$openCarsSql = "
    SELECT
        t.task_id,
        t.title,
        t.task_type,
        t.status,
        t.due_date,
        v.vessel_id,
        v.vesselName,
        u.fName AS assigned_fName,
        u.lName AS assigned_lName
    FROM tasks t
    INNER JOIN vessels v ON v.vessel_id = t.vessel_id
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE {$scopeSql}
      AND COALESCE(t.status, '') <> 'complete'
      AND COALESCE(t.task_type, 'corrective_action') NOT IN ('hour_maintenance', 'meter_verification')
    ORDER BY
      CASE COALESCE(t.status, '')
        WHEN 'overdue' THEN 0
        WHEN 'open' THEN 1
        WHEN 'in_progress' THEN 2
        WHEN 'deferred' THEN 3
        ELSE 4
      END,
      (t.due_date IS NULL) ASC,
      t.due_date ASC,
      v.vesselName ASC,
      t.title ASC
";
$openCarsStmt = $pdo->prepare($openCarsSql);
$openCarsStmt->execute($scopeParams);
$openCars = $openCarsStmt->fetchAll(PDO::FETCH_ASSOC);

$overdueSql = "
    SELECT
        s.schedule_id,
        s.service_name,
        s.next_due_hours,
        hm.current_hours,
        e.eid AS equipment_id,
        e.equipmentName,
        e.equipmentLocation,
        v.vessel_id,
        v.vesselName,
        t.task_id AS open_task_id
    FROM equipment_maintenance_schedules s
    INNER JOIN equipment_hour_meters hm ON hm.meter_id = s.meter_id
    INNER JOIN equipment e ON e.eid = s.equipment_id
    INNER JOIN vessels v ON v.vessel_id = s.vessel_id
    LEFT JOIN tasks t
        ON t.related_schedule_id = s.schedule_id
       AND t.task_type = 'hour_maintenance'
       AND t.cycle_due_hours = s.next_due_hours
       AND t.status IN ('open', 'in_progress', 'overdue', 'deferred')
    WHERE {$scopeSql}
      AND COALESCE(hm.is_active, 1) = 1
      AND COALESCE(e.is_active, 1) = 1
      AND COALESCE(s.is_active, 1) = 1
      AND s.next_due_hours IS NOT NULL
      AND hm.current_hours > s.next_due_hours
    ORDER BY (hm.current_hours - s.next_due_hours) DESC, v.vesselName ASC, e.equipmentName ASC, s.service_name ASC
";
$overdueStmt = $pdo->prepare($overdueSql);
$overdueStmt->execute($scopeParams);
$overdueMaintenance = $overdueStmt->fetchAll(PDO::FETCH_ASSOC);

$dueSoonSql = "
    SELECT
        s.schedule_id,
        s.service_name,
        s.next_due_hours,
        s.advance_notice_hours,
        hm.current_hours,
        e.eid AS equipment_id,
        e.equipmentName,
        e.equipmentLocation,
        v.vessel_id,
        v.vesselName,
        t.task_id AS open_task_id
    FROM equipment_maintenance_schedules s
    INNER JOIN equipment_hour_meters hm ON hm.meter_id = s.meter_id
    INNER JOIN equipment e ON e.eid = s.equipment_id
    INNER JOIN vessels v ON v.vessel_id = s.vessel_id
    LEFT JOIN tasks t
        ON t.related_schedule_id = s.schedule_id
       AND t.task_type = 'hour_maintenance'
       AND t.cycle_due_hours = s.next_due_hours
       AND t.status IN ('open', 'in_progress', 'overdue', 'deferred')
    WHERE {$scopeSql}
      AND COALESCE(hm.is_active, 1) = 1
      AND COALESCE(e.is_active, 1) = 1
      AND COALESCE(s.is_active, 1) = 1
      AND s.next_due_hours IS NOT NULL
      AND hm.current_hours >= (s.next_due_hours - COALESCE(s.advance_notice_hours, 0))
      AND hm.current_hours <= s.next_due_hours
    ORDER BY (s.next_due_hours - hm.current_hours) ASC, v.vesselName ASC, e.equipmentName ASC, s.service_name ASC
";
$dueSoonStmt = $pdo->prepare($dueSoonSql);
$dueSoonStmt->execute($scopeParams);
$dueSoonMaintenance = $dueSoonStmt->fetchAll(PDO::FETCH_ASSOC);

$verificationSql = "
    SELECT
        t.task_id,
        t.status,
        t.verification_month,
        v.vessel_id,
        v.vesselName
    FROM tasks t
    INNER JOIN vessels v ON v.vessel_id = t.vessel_id
    WHERE {$scopeSql}
      AND t.task_type = 'meter_verification'
      AND COALESCE(t.status, '') <> 'complete'
    ORDER BY COALESCE(t.verification_month, '') ASC, v.vesselName ASC
";
$verificationStmt = $pdo->prepare($verificationSql);
$verificationStmt->execute($scopeParams);
$openVerifications = $verificationStmt->fetchAll(PDO::FETCH_ASSOC);

$recentSql = "
    SELECT
        ev.event_id,
        ev.completion_date,
        ev.completion_hours,
        ev.performed_by,
        ev.note,
        e.eid AS equipment_id,
        e.equipmentName,
        v.vessel_id,
        v.vesselName,
        s.service_name
    FROM equipment_maintenance_events ev
    INNER JOIN vessels v ON v.vessel_id = ev.vessel_id
    INNER JOIN equipment e ON e.eid = ev.equipment_id
    INNER JOIN equipment_maintenance_schedules s ON s.schedule_id = ev.schedule_id
    WHERE {$scopeSql}
      AND ev.completion_date >= ?
    ORDER BY ev.completion_date DESC, ev.event_id DESC
";
$recentParams = $scopeParams;
$recentParams[] = $recentCompletionStart;
$recentStmt = $pdo->prepare($recentSql);
$recentStmt->execute($recentParams);
$recentCompletions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$setupIssues = [];

$issueSqlNoSchedule = "
    SELECT
        v.vessel_id,
        v.vesselName,
        e.eid AS equipment_id,
        e.equipmentName,
        e.equipmentLocation,
        hm.meter_id
    FROM equipment_hour_meters hm
    INNER JOIN equipment e ON e.eid = hm.equipment_id
    INNER JOIN vessels v ON v.vessel_id = hm.vessel_id
    LEFT JOIN equipment_maintenance_schedules s
        ON s.meter_id = hm.meter_id
       AND COALESCE(s.is_active, 1) = 1
    WHERE {$scopeSql}
      AND COALESCE(hm.is_active, 1) = 1
      AND COALESCE(e.is_active, 1) = 1
      AND s.schedule_id IS NULL
    ORDER BY v.vesselName ASC, e.equipmentName ASC
";
$issueStmtNoSchedule = $pdo->prepare($issueSqlNoSchedule);
$issueStmtNoSchedule->execute($scopeParams);
foreach ($issueStmtNoSchedule->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $setupIssues[] = [
        'issue_type' => 'No schedules',
        'severity' => 'warning',
        'vessel_id' => (int)$row['vessel_id'],
        'vesselName' => (string)$row['vesselName'],
        'equipment_id' => (int)$row['equipment_id'],
        'equipmentName' => (string)$row['equipmentName'],
        'detail' => 'Tracked equipment has no active maintenance schedules.',
    ];
}

$issueSqlNoDue = "
    SELECT
        v.vessel_id,
        v.vesselName,
        e.eid AS equipment_id,
        e.equipmentName,
        s.schedule_id,
        s.service_name
    FROM equipment_maintenance_schedules s
    INNER JOIN equipment_hour_meters hm ON hm.meter_id = s.meter_id
    INNER JOIN equipment e ON e.eid = s.equipment_id
    INNER JOIN vessels v ON v.vessel_id = s.vessel_id
    WHERE {$scopeSql}
      AND COALESCE(hm.is_active, 1) = 1
      AND COALESCE(e.is_active, 1) = 1
      AND COALESCE(s.is_active, 1) = 1
      AND s.next_due_hours IS NULL
    ORDER BY v.vesselName ASC, e.equipmentName ASC, s.service_name ASC
";
$issueStmtNoDue = $pdo->prepare($issueSqlNoDue);
$issueStmtNoDue->execute($scopeParams);
foreach ($issueStmtNoDue->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $setupIssues[] = [
        'issue_type' => 'Missing next due',
        'severity' => 'danger',
        'vessel_id' => (int)$row['vessel_id'],
        'vesselName' => (string)$row['vesselName'],
        'equipment_id' => (int)$row['equipment_id'],
        'equipmentName' => (string)$row['equipmentName'],
        'detail' => 'Schedule "' . (string)$row['service_name'] . '" is active but has no next due hours.',
    ];
}

$issueSqlUnverified = "
    SELECT
        v.vessel_id,
        v.vesselName,
        e.eid AS equipment_id,
        e.equipmentName,
        hm.last_verified_at
    FROM equipment_hour_meters hm
    INNER JOIN equipment e ON e.eid = hm.equipment_id
    INNER JOIN vessels v ON v.vessel_id = hm.vessel_id
    WHERE {$scopeSql}
      AND COALESCE(hm.is_active, 1) = 1
      AND COALESCE(e.is_active, 1) = 1
      AND (
            hm.last_verified_at IS NULL
         OR hm.last_verified_at < DATE_SUB(NOW(), INTERVAL 45 DAY)
      )
    ORDER BY hm.last_verified_at ASC, v.vesselName ASC, e.equipmentName ASC
";
$issueStmtUnverified = $pdo->prepare($issueSqlUnverified);
$issueStmtUnverified->execute($scopeParams);
foreach ($issueStmtUnverified->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $lastVerified = !empty($row['last_verified_at']) ? fleet_datetime((string)$row['last_verified_at']) : 'Never verified';
    $setupIssues[] = [
        'issue_type' => 'Verification stale',
        'severity' => 'secondary',
        'vessel_id' => (int)$row['vessel_id'],
        'vesselName' => (string)$row['vesselName'],
        'equipment_id' => (int)$row['equipment_id'],
        'equipmentName' => (string)$row['equipmentName'],
        'detail' => 'Last verified: ' . $lastVerified,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fleet Maintenance - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .fleet-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .fleet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }
        .fleet-title {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .fleet-subtitle {
            color: #6b7280;
            margin: 0;
        }
        .fleet-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .fleet-summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 14px;
            box-shadow: 0 8px 24px rgba(16,24,40,.08);
        }
        .fleet-summary-label {
            color: #6b7280;
            font-size: .88rem;
            margin-bottom: 6px;
        }
        .fleet-summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .fleet-summary-meta {
            color: #6b7280;
            font-size: .84rem;
            margin-top: 4px;
        }
        .fleet-section-card .card-header {
            font-weight: 700;
        }
        .fleet-table td,
        .fleet-table th {
            vertical-align: middle;
        }
        .fleet-mini-meta {
            color: #6b7280;
            font-size: .88rem;
        }
        .fleet-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        @media (min-width: 768px) {
            .fleet-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (min-width: 1200px) {
            .fleet-summary-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Fleet Maintenance';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="fleet-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card mb-3">
                <div class="fleet-header">
                    <div>
                        <h1 class="fleet-title">Fleet Maintenance</h1>
                        <p class="fleet-subtitle">Corrective actions, hour-based maintenance, and meter verification across the fleet.</p>
                    </div>
                    <div class="fleet-actions">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="vms-card mb-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label">Vessel</label>
                        <select name="vessel_id" class="form-select">
                            <option value="0">All active vessels</option>
                            <?php foreach ($accessibleVessels as $vesselOption): ?>
                                <option value="<?= (int)$vesselOption['vessel_id'] ?>" <?= $vesselFilter === (int)$vesselOption['vessel_id'] ? 'selected' : '' ?>>
                                    <?= fleet_safe($vesselOption['vesselName']) ?>
                                    <?php if ($roleId === 1 && !empty($vesselOption['company_name'])): ?>
                                        — <?= fleet_safe($vesselOption['company_name']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label">Queue Focus</label>
                        <select name="focus" class="form-select">
                            <option value="all" <?= $focus === 'all' ? 'selected' : '' ?>>All Sections</option>
                            <option value="cars" <?= $focus === 'cars' ? 'selected' : '' ?>>Corrective Actions / CARs</option>
                            <option value="overdue" <?= $focus === 'overdue' ? 'selected' : '' ?>>Overdue Maintenance</option>
                            <option value="due_soon" <?= $focus === 'due_soon' ? 'selected' : '' ?>>Due Soon Maintenance</option>
                            <option value="verifications" <?= $focus === 'verifications' ? 'selected' : '' ?>>Meter Verifications</option>
                            <option value="completions" <?= $focus === 'completions' ? 'selected' : '' ?>>Recent Completions</option>
                            <option value="issues" <?= $focus === 'issues' ? 'selected' : '' ?>>Setup / Data Issues</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-4 col-lg-2">
                        <label class="form-label">Recent Window</label>
                        <select name="completion_days" class="form-select">
                            <?php foreach ([7, 30, 60, 90] as $dayOption): ?>
                                <option value="<?= $dayOption ?>" <?= $completionDays === $dayOption ? 'selected' : '' ?>>Last <?= $dayOption ?> days</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary">Apply</button>
                        <a href="fleet_maintenance.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="fleet-summary-grid mb-4">
                <?= fleet_summary_card('Open CARs', count($openCars), 'secondary', 'Non-hour-maintenance tasks') ?>
                <?= fleet_summary_card('Overdue Maintenance', count($overdueMaintenance), 'danger', 'Current hours past due hours') ?>
                <?= fleet_summary_card('Due Soon Maintenance', count($dueSoonMaintenance), 'warning', 'Inside advance notice window') ?>
                <?= fleet_summary_card('Open Meter Verifications', count($openVerifications), 'primary', 'Monthly verification tasks') ?>
                <?= fleet_summary_card('Recent Completions', count($recentCompletions), 'success', 'Last ' . $completionDays . ' days') ?>
                <?= fleet_summary_card('Setup Issues', count($setupIssues), 'dark', 'Basic maintenance data checks') ?>
            </div>

            <?php if (fleet_show_section($focus, 'cars')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Open Corrective Actions / CARs</span>
                        <span class="badge bg-light text-dark"><?= count($openCars) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$openCars): ?>
                            <div class="p-3 text-muted">No open non-hour-based corrective actions match this view.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Task</th>
                                            <th>Source / Type</th>
                                            <th>Due Date</th>
                                            <th>Assigned</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($openCars as $task): ?>
                                            <?php $assignedName = trim((string)($task['assigned_fName'] ?? '') . ' ' . (string)($task['assigned_lName'] ?? '')); ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= fleet_safe($task['vesselName']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= fleet_safe($task['title']) ?></div>
                                                    <div class="fleet-mini-meta"><?= fleet_focus_badge('CAR', 'secondary') ?></div>
                                                </td>
                                                <td><?= fleet_safe($task['task_type'] ?: 'corrective_action') ?></td>
                                                <td><?= fleet_date($task['due_date'] ?? null) ?></td>
                                                <td><?= $assignedName !== '' ? fleet_safe($assignedName) : '—' ?></td>
                                                <td><?= fleet_task_status_badge($task['status'] ?? '') ?></td>
                                                <td>
                                                    <a href="view_task.php?id=<?= (int)$task['task_id'] ?>" class="btn btn-sm btn-outline-primary">Open Task</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (fleet_show_section($focus, 'overdue')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Overdue Hour-Based Maintenance</span>
                        <span class="badge bg-light text-dark"><?= count($overdueMaintenance) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$overdueMaintenance): ?>
                            <div class="p-3 text-muted">No overdue hour-based maintenance items in this view.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Equipment</th>
                                            <th>Service</th>
                                            <th>Current Hours</th>
                                            <th>Due Hours</th>
                                            <th>Hours Overdue</th>
                                            <th>Open Task</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($overdueMaintenance as $row): ?>
                                            <?php $hoursOverdue = round((float)$row['current_hours'] - (float)$row['next_due_hours'], 1); ?>
                                            <tr>
                                                <td><?= fleet_safe($row['vesselName']) ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= fleet_safe($row['equipmentName']) ?></div>
                                                    <div class="fleet-mini-meta"><?= fleet_safe($row['equipmentLocation']) ?></div>
                                                </td>
                                                <td>
                                                    <?= fleet_safe($row['service_name']) ?><br>
                                                    <?= fleet_focus_badge('Overdue', 'danger') ?>
                                                </td>
                                                <td><?= number_format((float)$row['current_hours'], 1) ?></td>
                                                <td><?= number_format((float)$row['next_due_hours'], 1) ?></td>
                                                <td class="text-danger fw-semibold"><?= number_format($hoursOverdue, 1) ?></td>
                                                <td>
                                                    <?php if (!empty($row['open_task_id'])): ?>
                                                        <a href="view_task.php?id=<?= (int)$row['open_task_id'] ?>" class="btn btn-sm btn-outline-danger">Open Task</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No open task</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="equipment_detail.php?id=<?= (int)$row['equipment_id'] ?>" class="btn btn-sm btn-outline-primary">Equipment</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (fleet_show_section($focus, 'due_soon')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Due Soon Hour-Based Maintenance</span>
                        <span class="badge bg-light text-dark"><?= count($dueSoonMaintenance) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$dueSoonMaintenance): ?>
                            <div class="p-3 text-muted">No due-soon hour-based maintenance items in this view.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Equipment</th>
                                            <th>Service</th>
                                            <th>Current Hours</th>
                                            <th>Due Hours</th>
                                            <th>Hours Remaining</th>
                                            <th>Open Task</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dueSoonMaintenance as $row): ?>
                                            <?php $hoursRemaining = round((float)$row['next_due_hours'] - (float)$row['current_hours'], 1); ?>
                                            <tr>
                                                <td><?= fleet_safe($row['vesselName']) ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= fleet_safe($row['equipmentName']) ?></div>
                                                    <div class="fleet-mini-meta"><?= fleet_safe($row['equipmentLocation']) ?></div>
                                                </td>
                                                <td>
                                                    <?= fleet_safe($row['service_name']) ?><br>
                                                    <?= fleet_focus_badge('Due Soon', 'warning') ?>
                                                </td>
                                                <td><?= number_format((float)$row['current_hours'], 1) ?></td>
                                                <td><?= number_format((float)$row['next_due_hours'], 1) ?></td>
                                                <td class="fw-semibold"><?= number_format($hoursRemaining, 1) ?></td>
                                                <td>
                                                    <?php if (!empty($row['open_task_id'])): ?>
                                                        <a href="view_task.php?id=<?= (int)$row['open_task_id'] ?>" class="btn btn-sm btn-outline-warning">Open Task</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No open task</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="equipment_detail.php?id=<?= (int)$row['equipment_id'] ?>" class="btn btn-sm btn-outline-primary">Equipment</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (fleet_show_section($focus, 'verifications')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Open Meter Verification</span>
                        <span class="badge bg-light text-dark"><?= count($openVerifications) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$openVerifications): ?>
                            <div class="p-3 text-muted">No open meter verification tasks in this view.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Verification Month</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($openVerifications as $row): ?>
                                            <tr>
                                                <td>
                                                    <?= fleet_safe($row['vesselName']) ?><br>
                                                    <?= fleet_focus_badge('Verification', 'primary') ?>
                                                </td>
                                                <td><?= fleet_safe($row['verification_month'] ?: 'Current month') ?></td>
                                                <td><?= fleet_task_status_badge($row['status'] ?? '') ?></td>
                                                <td>
                                                    <div class="fleet-actions">
                                                        <a href="meter_verification.php?vessel_id=<?= (int)$row['vessel_id'] ?>&task_id=<?= (int)$row['task_id'] ?>" class="btn btn-sm btn-outline-primary">Verify</a>
                                                        <a href="view_task.php?id=<?= (int)$row['task_id'] ?>" class="btn btn-sm btn-outline-secondary">Task</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (fleet_show_section($focus, 'completions')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Recent Completions</span>
                        <span class="badge bg-light text-dark"><?= count($recentCompletions) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$recentCompletions): ?>
                            <div class="p-3 text-muted">No hour-based maintenance completions in the last <?= (int)$completionDays ?> days.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Equipment</th>
                                            <th>Service</th>
                                            <th>Completion Date</th>
                                            <th>Completion Hours</th>
                                            <th>Performed By / Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCompletions as $row): ?>
                                            <tr>
                                                <td><?= fleet_safe($row['vesselName']) ?></td>
                                                <td><?= fleet_safe($row['equipmentName']) ?></td>
                                                <td>
                                                    <?= fleet_safe($row['service_name']) ?><br>
                                                    <?= fleet_focus_badge('Complete', 'success') ?>
                                                </td>
                                                <td><?= fleet_date($row['completion_date'] ?? null) ?></td>
                                                <td><?= number_format((float)$row['completion_hours'], 1) ?></td>
                                                <td>
                                                    <div><?= fleet_safe($row['performed_by'] ?? null) ?></div>
                                                    <div class="fleet-mini-meta"><?= fleet_safe($row['note'] ?? null) ?></div>
                                                </td>
                                                <td>
                                                    <a href="equipment_detail.php?id=<?= (int)$row['equipment_id'] ?>" class="btn btn-sm btn-outline-primary">Equipment</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (fleet_show_section($focus, 'issues')): ?>
                <div class="card fleet-section-card mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Setup / Data Issues</span>
                        <span class="badge bg-light text-dark"><?= count($setupIssues) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$setupIssues): ?>
                            <div class="p-3 text-muted">No simple setup or maintenance data issues were detected in this view.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0 fleet-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vessel</th>
                                            <th>Equipment</th>
                                            <th>Issue</th>
                                            <th>Detail</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($setupIssues as $issue): ?>
                                            <?php
                                            $badgeClass = $issue['severity'] === 'danger'
                                                ? 'danger'
                                                : ($issue['severity'] === 'warning' ? 'warning' : 'secondary');
                                            ?>
                                            <tr>
                                                <td><?= fleet_safe($issue['vesselName']) ?></td>
                                                <td><?= fleet_safe($issue['equipmentName']) ?></td>
                                                <td><?= fleet_focus_badge($issue['issue_type'], $badgeClass) ?></td>
                                                <td><?= fleet_safe($issue['detail']) ?></td>
                                                <td>
                                                    <a href="equipment_detail.php?id=<?= (int)$issue['equipment_id'] ?>" class="btn btn-sm btn-outline-primary">Equipment</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
