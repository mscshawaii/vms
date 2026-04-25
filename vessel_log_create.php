<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function buildChecklistReturnUrl(string $path, array $params): string {
    return $path . '?' . http_build_query($params);
}

function normalizeChecklistRunType(?string $runType): ?string {
    $runType = trim((string)$runType);
    $allowed = ['pre_underway', 'post_underway'];
    return in_array($runType, $allowed, true) ? $runType : null;
}

function generateFormStateKey(): string {
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return bin2hex(pack('d', microtime(true))) . mt_rand(1000, 9999);
    }
}

function getChecklistFormState(string $formStateKey): array {
    if ($formStateKey === '') {
        return [];
    }

    $stored = $_SESSION['vessel_log_form_state'][$formStateKey] ?? [];
    return is_array($stored) ? $stored : [];
}

function storeChecklistFormState(string $formStateKey, array $state): void {
    if ($formStateKey === '') {
        return;
    }

    if (!isset($_SESSION['vessel_log_form_state']) || !is_array($_SESSION['vessel_log_form_state'])) {
        $_SESSION['vessel_log_form_state'] = [];
    }

    $_SESSION['vessel_log_form_state'][$formStateKey] = $state;
}

$vessel_id = (int)($_GET['vessel_id'] ?? $_POST['vessel_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);
$formStateKey = trim((string)($_GET['form_state_key'] ?? $_POST['form_state_key'] ?? ''));

if ($formStateKey === '') {
    $formStateKey = generateFormStateKey();
}

if ($vessel_id <= 0) {
    die('Invalid vessel ID.');
}

if ($role_id === 1) {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id]);
} else {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
          AND company_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id, $company_id]);
}

$vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die('Access denied or vessel not found.');
}

$trackedMeters = vms_hour_get_tracked_meters_for_vessel($pdo, $vessel_id, true);

$existingDraftId = 0;

try {
    $draftStmt = $pdo->prepare("
        SELECT log_id
        FROM vessel_logs
        WHERE vessel_id = ?
          AND status = 'draft'
        ORDER BY log_id DESC
        LIMIT 1
    ");
    $draftStmt->execute([$vessel_id]);
    $existingDraftId = (int)($draftStmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $existingDraftId = 0;
}

$preChecklistId = (int)($_GET['pre_checklist_id'] ?? 0);
$postChecklistId = (int)($_GET['post_checklist_id'] ?? 0);
$returnedChecklistRunId = (int)($_GET['checklist_run_id'] ?? 0);
$returnedChecklistType = trim((string)($_GET['checklist_type'] ?? ''));

if ($returnedChecklistRunId > 0) {
    if ($returnedChecklistType === 'pre_underway') {
        $preChecklistId = $returnedChecklistRunId;
    } elseif ($returnedChecklistType === 'post_underway') {
        $postChecklistId = $returnedChecklistRunId;
    }
}

$storedFormState = getChecklistFormState($formStateKey);
if (!empty($storedFormState)) {
    $preChecklistId = (int)($storedFormState['pre_checklist_id'] ?? $preChecklistId);
    $postChecklistId = (int)($storedFormState['post_checklist_id'] ?? $postChecklistId);
}

if ($returnedChecklistRunId > 0) {
    if ($returnedChecklistType === 'pre_underway') {
        $preChecklistId = $returnedChecklistRunId;
    } elseif ($returnedChecklistType === 'post_underway') {
        $postChecklistId = $returnedChecklistRunId;
    }
}

$formValues = [
    'depart_dt' => (string)($storedFormState['depart_dt'] ?? ''),
    'origin_port' => (string)($storedFormState['origin_port'] ?? ''),
    'passenger_count' => (string)($storedFormState['passenger_count'] ?? ''),
    'crew_ids' => array_values(array_unique(array_map('intval', is_array($storedFormState['crew_ids'] ?? null) ? $storedFormState['crew_ids'] : []))),
    'return_dt' => (string)($storedFormState['return_dt'] ?? ''),
    'arrival_port' => (string)($storedFormState['arrival_port'] ?? ''),
    'engine_hours_port' => (string)($storedFormState['engine_hours_port'] ?? ''),
    'engine_hours_stbd' => (string)($storedFormState['engine_hours_stbd'] ?? ''),
    'meter_hours' => vms_hour_log_form_values($storedFormState),
    'trip_summary' => (string)($storedFormState['trip_summary'] ?? ''),
    'signed_by_name' => (string)($storedFormState['signed_by_name'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $openChecklist = normalizeChecklistRunType($_POST['open_checklist'] ?? '');
    if ($openChecklist !== null) {
        $state = [
            'depart_dt' => (string)($_POST['depart_dt'] ?? ''),
            'origin_port' => (string)($_POST['origin_port'] ?? ''),
            'passenger_count' => (string)($_POST['passenger_count'] ?? ''),
            'crew_ids' => is_array($_POST['crew_ids'] ?? null) ? array_values(array_unique(array_map('intval', $_POST['crew_ids']))) : [],
            'return_dt' => (string)($_POST['return_dt'] ?? ''),
            'arrival_port' => (string)($_POST['arrival_port'] ?? ''),
            'engine_hours_port' => (string)($_POST['engine_hours_port'] ?? ''),
            'engine_hours_stbd' => (string)($_POST['engine_hours_stbd'] ?? ''),
            'meter_hours' => vms_hour_parse_posted_meter_readings($_POST),
            'trip_summary' => (string)($_POST['trip_summary'] ?? ''),
            'signed_by_name' => (string)($_POST['signed_by_name'] ?? ''),
            'pre_checklist_id' => (int)($_POST['pre_checklist_id'] ?? $preChecklistId),
            'post_checklist_id' => (int)($_POST['post_checklist_id'] ?? $postChecklistId),
        ];

        storeChecklistFormState($formStateKey, $state);

        $returnTo = buildChecklistReturnUrl('vessel_log_create.php', [
            'vessel_id' => $vessel_id,
            'pre_checklist_id' => (int)$state['pre_checklist_id'],
            'post_checklist_id' => (int)$state['post_checklist_id'],
            'form_state_key' => $formStateKey,
        ]);

        $redirectTo = buildChecklistReturnUrl('checklist_run.php', [
            'vessel_id' => $vessel_id,
            'type' => $openChecklist,
            'return_to' => $returnTo,
            'form_state_key' => $formStateKey,
        ]);

        header('Location: ' . $redirectTo);
        exit;
    }
}

$preChecklistReturnTo = buildChecklistReturnUrl('vessel_log_create.php', [
    'vessel_id' => $vessel_id,
    'pre_checklist_id' => $preChecklistId,
    'post_checklist_id' => $postChecklistId,
    'form_state_key' => $formStateKey,
]);

$postChecklistReturnTo = buildChecklistReturnUrl('vessel_log_create.php', [
    'vessel_id' => $vessel_id,
    'pre_checklist_id' => $preChecklistId,
    'post_checklist_id' => $postChecklistId,
    'form_state_key' => $formStateKey,
]);

// =====================================================
// HELPERS
// =====================================================

$colExists = function(string $table, string $col) use ($pdo): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
};

$tableExists = function(string $table) use ($pdo): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
};

$norm = function(string $s): string {
    return strtolower(preg_replace('/[^a-z0-9]/', '', $s));
};

$getCols = function(string $table) use ($pdo): array {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    } catch (Throwable $e) {
        return [];
    }
};

$findColumn = function(array $cols, array $candidateNorms) use ($norm): ?string {
    foreach ($candidateNorms as $cand) {
        foreach ($cols as $raw) {
            if ($norm($raw) === $cand) {
                return $raw;
            }
        }
    }
    return null;
};

// =====================================================
// ALERT / BANNER COUNTS
// =====================================================
$todayLocal = date('Y-m-d');
$thirtyDaysOut = date('Y-m-d', strtotime('+30 days'));
$openCorrectiveCount = 0;
$expiredDocCount = 0;
$expiringDocCount = 0;
$expiredEquipCount = 0;
$expiringEquipCount = 0;
$showAttentionBanner = false;

// ---------- Open corrective actions ----------
try {
    if ($tableExists('tasks')) {
        $taskCols = $getCols('tasks');

        $taskVesselCol = $findColumn($taskCols, ['vesselid']);
        $taskStatusCol = $findColumn($taskCols, ['status', 'taskstatus']);

        if ($taskVesselCol && $taskStatusCol) {
            $sql = "
                SELECT COUNT(*)
                FROM `tasks`
                WHERE `$taskVesselCol` = ?
                  AND LOWER(REPLACE(TRIM(`$taskStatusCol`), '_', ' ')) IN ('open','in progress','overdue','deferred')
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$vessel_id]);
            $openCorrectiveCount = (int)$stmt->fetchColumn();
        }
    }
} catch (Throwable $e) {
    $openCorrectiveCount = 0;
}

// ---------- Documents ----------
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM documents
        WHERE vessel_id = ?
          AND archived_at IS NULL
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate < ?
    ");
    $stmt->execute([$vessel_id, $todayLocal]);
    $expiredDocCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM documents
        WHERE vessel_id = ?
          AND archived_at IS NULL
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate BETWEEN ? AND ?
    ");
    $stmt->execute([$vessel_id, $todayLocal, $thirtyDaysOut]);
    $expiringDocCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $expiredDocCount = 0;
    $expiringDocCount = 0;
}

// ---------- Equipment ----------
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE vessel_id = ?
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate < ?
    ");
    $stmt->execute([$vessel_id, $todayLocal]);
    $expiredEquipCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE vessel_id = ?
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate BETWEEN ? AND ?
    ");
    $stmt->execute([$vessel_id, $todayLocal, $thirtyDaysOut]);
    $expiringEquipCount = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $expiredEquipCount = 0;
    $expiringEquipCount = 0;
}

$showAttentionBanner =
    $openCorrectiveCount > 0 ||
    $expiredDocCount > 0 ||
    $expiringDocCount > 0 ||
    $expiredEquipCount > 0 ||
    $expiringEquipCount > 0;

// =====================================================
// CREW LOADER
// =====================================================
$crewOptions = [];

try {
    $crewStmt = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            u.fName,
            u.lName,
            vc.role
        FROM vessel_crew vc
        INNER JOIN users u
            ON u.id = vc.crew_id
        WHERE vc.vessel_id = ?
          AND vc.is_active = 1
          AND u.is_active = 1
          AND vc.counts_for_voyage_logs = 1
          AND vc.role IN ('Master', 'Deckhand')
        ORDER BY
            FIELD(vc.role, 'Master', 'Deckhand'),
            u.lName,
            u.fName
    ");
    $crewStmt->execute([$vessel_id]);
    $crewOptions = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $crewOptions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Voyage Log - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .logs-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .logs-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .logs-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .logs-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .logs-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .logs-form-card .form-control,
        .logs-form-card .form-select,
        .logs-form-card .btn {
            border-radius: 12px;
        }

        .logs-signature-wrap canvas {
            background: #fff;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
<?php
$title = ($vessel['vesselName'] ?? 'Vessel') . ' Voyage Log';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="logs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="logs-header">
                    <div>
                        <h1 class="logs-title">New Voyage Log</h1>
                        <p class="logs-subtitle">
                            <?= safe($vessel['vesselName'] ?? '') ?> · Official No. <?= safe($vessel['vesselON'] ?? '') ?> · Hailing Port: <?= safe($vessel['hailingPort'] ?? '') ?>
                        </p>
                    </div>

                    <div class="logs-actions">
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                        <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-dark">View Log Entries</a>
                    </div>
                </div>
            </div>

            <?php if ($showAttentionBanner): ?>
                <div class="alert alert-warning border border-warning shadow-sm mb-4" id="complianceAttentionBanner">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h5 class="alert-heading mb-2">Attention Required Before Saving This Log</h5>
                            <p class="mb-2">This vessel currently has compliance items requiring attention:</p>

                            <ul class="mb-2">
                                <li>
                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>&task_filter=open" class="fw-bold text-decoration-none">
                                        <?= (int)$openCorrectiveCount ?>
                                    </a>
                                    open corrective action item<?= $openCorrectiveCount === 1 ? '' : 's' ?>
                                </li>

                                <li>
                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="fw-bold text-decoration-none">
                                        <?= (int)$expiredDocCount ?>
                                    </a>
                                    expired document<?= $expiredDocCount === 1 ? '' : 's' ?>
                                </li>

                                <li>
                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="fw-bold text-decoration-none">
                                        <?= (int)$expiringDocCount ?>
                                    </a>
                                    document<?= $expiringDocCount === 1 ? ' is' : 's are' ?> expiring within 30 days
                                </li>

                                <li>
                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="fw-bold text-decoration-none">
                                        <?= (int)$expiredEquipCount ?>
                                    </a>
                                    expired equipment/service item<?= $expiredEquipCount === 1 ? '' : 's' ?>
                                </li>

                                <li>
                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="fw-bold text-decoration-none">
                                        <?= (int)$expiringEquipCount ?>
                                    </a>
                                    equipment/service item<?= $expiringEquipCount === 1 ? ' is' : 's are' ?> expiring within 30 days
                                </li>
                            </ul>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" value="1" id="alertAckCheckbox">
                                <label class="form-check-label" for="alertAckCheckbox">
                                    I acknowledge these open or expiring items and want to continue.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>&task_filter=open" class="btn btn-outline-primary btn-sm">
                                View Corrective Actions
                            </a>

                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">
                                View Documents
                            </a>

                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">
                                View Equipment
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($existingDraftId > 0): ?>
                <div class="alert alert-warning border border-warning shadow-sm mb-4">
                    <h5 class="alert-heading mb-2">Draft Log In Progress</h5>
                    <p class="mb-3">
                        A draft vessel log already exists for this vessel. Please resume and finalize that draft before starting a new log.
                    </p>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="log_edit.php?log_id=<?= (int)$existingDraftId ?>" class="btn btn-primary">
                            Resume Draft
                        </a>

                        <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-dark">
                            View Log Entries
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <?php if (!$trackedMeters): ?>
                    <div class="alert alert-info border shadow-sm mb-4">
                        No active hour-tracked propulsion engines or generators are configured for this vessel. Add or edit equipment to enable voyage-log meter entry.
                    </div>
                <?php endif; ?>

                <div class="vms-card logs-form-card">
                    <form id="logForm" method="post" action="log_create.php" enctype="multipart/form-data">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                        <input type="hidden" name="form_state_key" value="<?= safe($formStateKey) ?>">
                        <input type="hidden" name="pre_checklist_id" value="<?= (int)$preChecklistId ?>">
                        <input type="hidden" name="post_checklist_id" value="<?= (int)$postChecklistId ?>">
                        <input type="hidden" name="signature_png" id="signature_png">
                        <input type="hidden" name="casualty_flag" id="casualty_flag" value="0">
                        <input type="hidden" name="alert_acknowledged" id="alert_acknowledged" value="0">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Departure (local)</label>
                                <input type="datetime-local" class="form-control" name="depart_dt" value="<?= safe($formValues['depart_dt']) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Origin Port</label>
                                <input type="text" class="form-control" name="origin_port" maxlength="120" value="<?= safe($formValues['origin_port']) ?>">
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit"
                                            class="btn btn-outline-secondary w-100"
                                            name="open_checklist"
                                            value="pre_underway"
                                            formaction="vessel_log_create.php?vessel_id=<?= (int)$vessel_id ?>"
                                            formnovalidate>
                                        <?= $preChecklistId > 0 ? 'Replace Pre-Underway Checklist' : 'Pre-Underway Checklist' ?>
                                    </button>
                                    <div class="form-text">
                                        <?= $preChecklistId > 0 ? 'Selected run #' . (int)$preChecklistId : 'Not selected' ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Passengers (qty)</label>
                                <input type="number" min="0" class="form-control" name="passenger_count" placeholder="0" value="<?= safe($formValues['passenger_count']) ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Crew Onboard</label>
                                <select name="crew_ids[]" class="form-select" multiple size="6">
                                    <?php foreach ($crewOptions as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $formValues['crew_ids'], true) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim(($c['lName'] ?? '') . ', ' . ($c['fName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($c['role'])): ?>
                                                (<?= htmlspecialchars($c['role'], ENT_QUOTES, 'UTF-8') ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd (or drag) to select multiple crew.</div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Return (local)</label>
                                <input type="datetime-local" class="form-control" name="return_dt" value="<?= safe($formValues['return_dt']) ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Arrival Port</label>
                                <input type="text" class="form-control" name="arrival_port" maxlength="120" value="<?= safe($formValues['arrival_port']) ?>">
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit"
                                            class="btn btn-outline-secondary w-100"
                                            name="open_checklist"
                                            value="post_underway"
                                            formaction="vessel_log_create.php?vessel_id=<?= (int)$vessel_id ?>"
                                            formnovalidate>
                                        <?= $postChecklistId > 0 ? 'Replace Post-Underway Checklist' : 'Post-Underway Checklist' ?>
                                    </button>
                                    <div class="form-text">
                                        <?= $postChecklistId > 0 ? 'Selected run #' . (int)$postChecklistId : 'Not selected' ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($trackedMeters): ?>
                                <div class="col-12">
                                    <div class="card border">
                                        <div class="card-header bg-light"><strong>Tracked Equipment Hours</strong></div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <?php foreach ($trackedMeters as $meter): ?>
                                                    <?php
                                                    $meterId = (int)$meter['meter_id'];
                                                    $value = $formValues['meter_hours'][$meterId] ?? '';
                                                    ?>
                                                    <div class="col-md-6">
                                                        <label class="form-label">
                                                            <?= safe($meter['equipmentName']) ?>
                                                            <span class="text-muted">(<?= safe($meter['equipmentLocation']) ?>, current <?= safe(number_format((float)$meter['current_hours'], 1)) ?>)</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.1"
                                                            min="0"
                                                            class="form-control tracked-meter-input"
                                                            data-current-hours="<?= htmlspecialchars((string)$meter['current_hours'], ENT_QUOTES, 'UTF-8') ?>"
                                                            name="meter_hours[<?= $meterId ?>]"
                                                            placeholder="e.g., 1234.5"
                                                            value="<?= safe($value) ?>"
                                                            required
                                                        >
                                                        <div class="form-text">Enter the ending actual meter reading.</div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label class="form-label">Trip Summary / Notes</label>
                                <textarea class="form-control" name="trip_summary" rows="4"
                                          placeholder="Weather, incidents, ops limits, sightings, maintenance notes, etc."><?= safe($formValues['trip_summary']) ?></textarea>
                            </div>

                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-6 d-grid">
                                        <span class="d-grid"
                                              data-bs-toggle="tooltip"
                                              data-bs-trigger="hover focus click"
                                              data-bs-placement="top"
                                              title="Under development">
                                            <button type="button"
                                                    class="btn btn-outline-secondary btn-lg w-100"
                                                    disabled>
                                                Report a Marine Casualty
                                            </button>
                                        </span>
                                    </div>

                                    <div class="col-md-6 d-grid">
                                        <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>&origin=log"
                                           class="btn btn-outline-primary btn-lg w-100"
                                           title="Create a Corrective Action and report equipment failure">
                                            Report a Corrective Action Requirement
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Photos / Videos</label>
                                <input class="form-control" type="file" name="media_files[]" accept="image/*,video/*" multiple>
                                <div class="form-text">Select multiple files (images & common video formats).</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Signature</label>
                                <div class="border rounded p-2 bg-light logs-signature-wrap">
                                    <canvas id="sigCanvas" width="800" height="140" class="w-100"></canvas>
                                    <div class="mt-2 d-flex gap-2">
                                        <input type="text" class="form-control" name="signed_by_name" placeholder="Printed name (optional)" value="<?= safe($formValues['signed_by_name']) ?>">
                                        <button type="button" class="btn btn-outline-secondary" id="btnClearSig">Clear</button>
                                    </div>
                                    <div class="form-text">Sign with mouse or touch. Signature will be timestamped on save.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mt-4">
                            <button type="submit" class="btn btn-secondary" name="save_mode" value="draft">Save Draft</button>
                            <button type="submit" class="btn btn-primary" name="save_mode" value="submit">Submit Log</button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('logForm');
    if (!form) return;

    const canvas = document.getElementById('sigCanvas');
    const alertAckCheckbox = document.getElementById('alertAckCheckbox');
    const alertAckHidden = document.getElementById('alert_acknowledged');

    if (canvas) {
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let last = null;

        function relPos(e) {
            const r = canvas.getBoundingClientRect();
            if (e.touches && e.touches.length) {
                return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
            }
            return { x: e.clientX - r.left, y: e.clientY - r.top };
        }

        function start(e) {
            drawing = true;
            last = relPos(e);
            e.preventDefault();
        }

        function move(e) {
            if (!drawing) return;
            const p = relPos(e);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(last.x, last.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            last = p;
            e.preventDefault();
        }

        function end() {
            drawing = false;
            last = null;
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end);

        const clearBtn = document.getElementById('btnClearSig');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });
        }

        form.addEventListener('submit', function () {
            const blank = document.createElement('canvas');
            blank.width = canvas.width;
            blank.height = canvas.height;
            if (canvas.toDataURL() !== blank.toDataURL()) {
                document.getElementById('signature_png').value = canvas.toDataURL('image/png');
            }
        });
    }

    form.addEventListener('submit', function (e) {
        if (alertAckCheckbox) {
            if (!alertAckCheckbox.checked) {
                e.preventDefault();
                alert('Please acknowledge the open or expiring compliance items before saving this log.');
                alertAckCheckbox.focus();
                return false;
            }

            if (alertAckHidden) {
                alertAckHidden.value = '1';
            }
        }
    });

    const KEY = 'vms_log_draft_vessel_<?= (int)$vessel_id ?>';

    try {
        const data = JSON.parse(localStorage.getItem(KEY) || '{}');
        for (const [k, v] of Object.entries(data)) {
            const el = form.elements[k];
            if (!el) continue;
            if (el.type === 'file') continue;

            if (el instanceof HTMLSelectElement && el.multiple && Array.isArray(v)) {
                for (const opt of el.options) {
                    opt.selected = v.includes(parseInt(opt.value, 10));
                }
            } else {
                el.value = v;
            }
        }

        if (alertAckCheckbox && data.alertAckCheckbox) {
            alertAckCheckbox.checked = !!data.alertAckCheckbox;
        }

        if (form.elements['pre_checklist_id'] && Object.prototype.hasOwnProperty.call(data, 'pre_checklist_id')) {
            form.elements['pre_checklist_id'].value = data.pre_checklist_id;
        }

        if (form.elements['post_checklist_id'] && Object.prototype.hasOwnProperty.call(data, 'post_checklist_id')) {
            form.elements['post_checklist_id'].value = data.post_checklist_id;
        }

        if (data.meter_hours && typeof data.meter_hours === 'object') {
            Object.keys(data.meter_hours).forEach(function (meterId) {
                const el = form.querySelector('input[name="meter_hours[' + meterId + ']"]');
                if (el) el.value = data.meter_hours[meterId];
            });
        }
    } catch (e) {}

    setInterval(function () {
        const payload = {};
        const f = form.elements;

        [
            'depart_dt',
            'origin_port',
            'return_dt',
            'arrival_port',
            'passenger_count',
            'trip_summary',
            'engine_hours_port',
            'engine_hours_stbd',
            'signed_by_name',
            'pre_checklist_id',
            'post_checklist_id'
        ].forEach(function (name) {
            if (f[name]) payload[name] = f[name].value;
        });

        form.querySelectorAll('input[name^="meter_hours["]').forEach(function (el) {
            const match = el.name.match(/^meter_hours\[(\d+)\]$/);
            if (!match) return;
            if (!payload.meter_hours) payload.meter_hours = {};
            payload.meter_hours[match[1]] = el.value;
        });

        if (f['crew_ids[]']) {
            payload['crew_ids[]'] = Array.from(f['crew_ids[]'].options)
                .filter(function (o) { return o.selected; })
                .map(function (o) { return parseInt(o.value, 10); });
        }

        if (alertAckCheckbox) {
            payload.alertAckCheckbox = alertAckCheckbox.checked ? 1 : 0;
        }

        localStorage.setItem(KEY, JSON.stringify(payload));
    }, 60000);

    form.addEventListener('submit', function () {
        localStorage.removeItem(KEY);
    });

    form.querySelectorAll('.tracked-meter-input').forEach(function (input) {
        input.addEventListener('change', function () {
            const current = parseFloat(input.dataset.currentHours || '0');
            const value = parseFloat(input.value || '0');
            const departEl = form.elements['depart_dt'];
            const returnEl = form.elements['return_dt'];
            const depart = departEl ? Date.parse(departEl.value) : NaN;
            const ret = returnEl ? Date.parse(returnEl.value) : NaN;
            if (Number.isNaN(current) || Number.isNaN(value) || Number.isNaN(depart) || Number.isNaN(ret) || ret <= depart) {
                return;
            }

            const voyageDurationHours = (ret - depart) / 3600000;
            const allowedIncrease = voyageDurationHours + 5.0;
            const actualIncrease = value - current;

            if (actualIncrease > allowedIncrease) {
                alert('This reading exceeds the expected increase for the voyage duration. It will save with a warning only.');
            }
        });
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
