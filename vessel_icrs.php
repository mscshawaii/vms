<?php
require 'db_connect.php';
require 'session_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$vessel_id = isset($_GET['vessel_id']) ? (int) $_GET['vessel_id'] : 0;
if ($vessel_id <= 0) {
    die("Invalid vessel ID.");
}

$show_all_completed = isset($_GET['show_all_completed']) && $_GET['show_all_completed'] == '1';
$completed_limit = $show_all_completed ? 100 : 10;

$today = new DateTime('now', new DateTimeZone('Pacific/Honolulu'));
$dueSoonThreshold = (clone $today)->modify('+45 days');

$inspector = isset($_SESSION['username']) ? urlencode($_SESSION['username']) : 'unknown';

$q = trim((string)($_GET['q'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));
$history_scope = trim((string)($_GET['history_scope'] ?? ''));
$completed_sort = trim((string)($_GET['completed_sort'] ?? 'newest'));
$completed_window = trim((string)($_GET['completed_window'] ?? '90d'));
$completed_q = trim((string)($_GET['completed_q'] ?? ''));

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function icrMatchesSearch(array $row, string $q): bool {
    if ($q === '') return true;

    $haystack = strtolower(implode(' ', [
        (string)($row['icr_number'] ?? ''),
        (string)($row['title'] ?? ''),
        (string)($row['frequency'] ?? ''),
        (string)($row['status_label'] ?? ''),
        (string)($row['last_run_display'] ?? ''),
        (string)($row['next_due_display'] ?? '')
    ]));

    return str_contains($haystack, strtolower($q));
}

function completedMatchesSearch(array $row, string $q): bool {
    if ($q === '') return true;

    $haystack = strtolower(implode(' ', [
        (string)($row['icr_number'] ?? ''),
        (string)($row['title'] ?? ''),
        (string)($row['inspector'] ?? ''),
        (string)($row['run_date'] ?? ''),
        (string)($row['failed_steps'] ?? ''),
        (string)($row['failed_substeps'] ?? '')
    ]));

    return str_contains($haystack, strtolower($q));
}

/**
 * Vessel header
 */
$vesselStmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON, hailingPort
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$vesselStmt->execute([$vessel_id]);
$vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found.");
}

/**
 * Assigned ICRs + latest final run + latest draft run
 */
$assignedStmt = $pdo->prepare("
    SELECT
        vi.vessel_icr_id,
        vi.icr_id,
        vi.vessel_id,
        vi.is_removed,
        i.icr_number,
        i.title,
        i.frequency,

        (
            SELECT MAX(r.run_date)
            FROM vessel_icr_runs r
            WHERE r.vessel_icr_id = vi.vessel_icr_id
              AND r.save_state = 'final'
        ) AS last_run,

        (
            SELECT r2.run_id
            FROM vessel_icr_runs r2
            WHERE r2.vessel_icr_id = vi.vessel_icr_id
              AND r2.save_state = 'draft'
            ORDER BY r2.run_id DESC
            LIMIT 1
        ) AS draft_run_id

    FROM vessel_icrs vi
    JOIN icrs i ON vi.icr_id = i.icr_id
    WHERE vi.vessel_id = ?
      AND vi.is_removed = 0
    ORDER BY i.icr_number ASC, i.title ASC
");
$assignedStmt->execute([$vessel_id]);
$assignedRows = $assignedStmt->fetchAll(PDO::FETCH_ASSOC);

$inProgress = [];
$overdue = [];
$dueSoon = [];
$okAssigned = [];

foreach ($assignedRows as $icr) {
    $lastRun = $icr['last_run'];
    $freq = $icr['frequency'] ?? '';
    $draftRunId = (int)($icr['draft_run_id'] ?? 0);

    $nextDue = null;
    $statusLabel = 'OK';
    $statusClass = 'success';
    $lastRunDisplay = $lastRun ?: 'Never';

    $actionLink = "run_icr.php?vessel_id={$vessel_id}&vessel_icr_id={$icr['vessel_icr_id']}&icr_id={$icr['icr_id']}&inspector={$inspector}";
    $actionLabel = $draftRunId > 0 ? 'Resume Draft' : 'Perform ICR';
    $actionClass = $draftRunId > 0 ? 'btn-warning' : 'btn-primary';

    if ($draftRunId > 0) {
        $icr['status_label'] = 'Draft In Progress';
        $icr['status_class'] = 'warning';
        $icr['last_run_display'] = $lastRunDisplay;
        $icr['next_due_display'] = '—';
        $icr['action_link'] = $actionLink;
        $icr['action_label'] = $actionLabel;
        $icr['action_class'] = $actionClass;
        $icr['draft_run_id'] = $draftRunId;
        $inProgress[] = $icr;
        continue;
    }

    if ($lastRun) {
        $next = new DateTime($lastRun, new DateTimeZone('Pacific/Honolulu'));

        switch ($freq) {
            case 'Weekly':
                $next->modify('+1 week');
                break;
            case 'Monthly':
                $next->modify('+1 month');
                break;
            case 'Quarterly':
                $next->modify('+3 months');
                break;
            case 'Annually':
            case 'Annual':
                $next->modify('+1 year');
                break;
            default:
                $next->modify('+1 month');
                break;
        }

        $nextDue = $next;
    }

    if (!$lastRun) {
        $statusLabel = 'Overdue';
        $statusClass = 'danger';
    } elseif ($nextDue < $today) {
        $statusLabel = 'Overdue';
        $statusClass = 'danger';
    } elseif ($nextDue <= $dueSoonThreshold) {
        $statusLabel = 'Due Soon';
        $statusClass = 'warning';
    } else {
        $statusLabel = 'OK';
        $statusClass = 'success';
    }

    $icr['status_label'] = $statusLabel;
    $icr['status_class'] = $statusClass;
    $icr['last_run_display'] = $lastRunDisplay;
    $icr['next_due_display'] = $nextDue ? $nextDue->format('Y-m-d') : '—';
    $icr['action_link'] = $actionLink;
    $icr['action_label'] = $actionLabel;
    $icr['action_class'] = $actionClass;

    if ($statusLabel === 'Overdue') {
        $overdue[] = $icr;
    } elseif ($statusLabel === 'Due Soon') {
        $dueSoon[] = $icr;
    } else {
        $okAssigned[] = $icr;
    }
}

$completed_date_clause = '';
if ($completed_window === '90d') {
    $completed_date_clause = " AND r.run_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) ";
} elseif ($completed_window === '30d') {
    $completed_date_clause = " AND r.run_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ";
}

$completed_order_sql = "r.run_date DESC";
if ($completed_sort === 'oldest') {
    $completed_order_sql = "r.run_date ASC";
} elseif ($completed_sort === 'code') {
    $completed_order_sql = "i.icr_number ASC, r.run_date DESC";
}

/**
 * Recent completed runs
 */
$historyStmt = $pdo->prepare("
    SELECT
        r.run_id,
        r.run_date,
        r.inspector,
        i.icr_number,
        i.title,
        COALESCE(fs.failed_steps, 0) AS failed_steps,
        COALESCE(fss.failed_substeps, 0) AS failed_substeps
    FROM vessel_icr_runs r
    JOIN icrs i ON r.icr_id = i.icr_id
    LEFT JOIN (
        SELECT run_id, COUNT(*) AS failed_steps
        FROM vessel_icr_step_status
        WHERE LOWER(status) = 'fail'
        GROUP BY run_id
    ) fs ON fs.run_id = r.run_id
    LEFT JOIN (
        SELECT run_id, COUNT(*) AS failed_substeps
        FROM vessel_icr_substep_status
        WHERE LOWER(status) = 'fail'
        GROUP BY run_id
    ) fss ON fss.run_id = r.run_id
    WHERE r.vessel_id = ?
      AND r.save_state = 'final'
      {$completed_date_clause}
    ORDER BY {$completed_order_sql}
    LIMIT {$completed_limit}
");
$historyStmt->execute([$vessel_id]);
$recentCompleted = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Search/filter application
 */
$inProgress = array_values(array_filter($inProgress, fn($row) => icrMatchesSearch($row, $q)));
$overdue    = array_values(array_filter($overdue, fn($row) => icrMatchesSearch($row, $q)));
$dueSoon    = array_values(array_filter($dueSoon, fn($row) => icrMatchesSearch($row, $q)));
$okAssigned = array_values(array_filter($okAssigned, fn($row) => icrMatchesSearch($row, $q)));

if ($status_filter !== '') {
    if ($status_filter !== 'in_progress') $inProgress = [];
    if ($status_filter !== 'overdue')     $overdue = [];
    if ($status_filter !== 'due_soon')    $dueSoon = [];
    if ($status_filter !== 'assigned_ok') $okAssigned = [];
}

$recentCompleted = array_values(array_filter($recentCompleted, fn($row) => completedMatchesSearch($row, $completed_q)));

if ($history_scope === 'failed_only') {
    $recentCompleted = array_values(array_filter($recentCompleted, function($row) {
        return ((int)$row['failed_steps'] + (int)$row['failed_substeps']) > 0;
    }));
}

function renderIcrCard(array $icr, int $vessel_id) {
    $statusClassMap = [
        'danger'  => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        'success' => 'text-bg-success',
        'secondary' => 'text-bg-secondary'
    ];

    $badgeClass = $statusClassMap[$icr['status_class']] ?? 'text-bg-secondary';
    $editUrl = "edit_vessel_icr.php?vessel_id={$vessel_id}&icr_id=" . (int)$icr['icr_id'] . "&vessel_icr_id=" . (int)$icr['vessel_icr_id'];
    ?>
    <div class="card shadow-sm border-0 mb-3 icr-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="fw-bold fs-6"><?= h($icr['icr_number']) ?></div>
                    <div class="fw-semibold"><?= h($icr['title']) ?></div>
                </div>
                <span class="badge <?= $badgeClass ?>"><?= h($icr['status_label']) ?></span>
            </div>

            <div class="small text-muted mb-3">
                <div><strong>Frequency:</strong> <?= h($icr['frequency']) ?></div>
                <div><strong>Last Completed:</strong> <?= h($icr['last_run_display']) ?></div>
                <div><strong>Next Due:</strong> <?= h($icr['next_due_display']) ?></div>
            </div>

            <div class="d-grid gap-2">
                <a href="<?= h($icr['action_link']) ?>" class="btn <?= h($icr['action_class']) ?>">
                    <?= h($icr['action_label']) ?>
                </a>

                <div class="d-flex gap-2">
                    <a href="<?= h($editUrl) ?>" class="btn btn-outline-secondary flex-fill">Edit</a>

                    <form method="POST" action="remove_vessel_icr.php" class="flex-fill">
                        <input type="hidden" name="vessel_icr_id" value="<?= (int)$icr['vessel_icr_id'] ?>">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                        <button
                            type="submit"
                            class="btn btn-outline-danger w-100"
                            onclick="return confirm('Remove this ICR from the vessel? This will hide it from the active list but preserves history.')"
                        >
                            Remove
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function renderCompletedCard(array $row, int $vessel_id) {
    $failSteps = (int)$row['failed_steps'];
    $failSubsteps = (int)$row['failed_substeps'];
    $failTotal = $failSteps + $failSubsteps;

    $badgeClass = $failTotal > 0 ? 'text-bg-warning' : 'text-bg-success';
    $badgeText = $failTotal > 0 ? "Failed Items: {$failTotal}" : "No Failed Items";

    $viewUrl = "view_icr_run.php?run_id=" . (int)$row['run_id'];
    $caUrl = "vessel_dashboard.php?vessel_id={$vessel_id}&icr_run_id=" . (int)$row['run_id'] . "#tasks";
    ?>
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="fw-bold fs-6"><?= h($row['icr_number']) ?></div>
                    <div class="fw-semibold"><?= h($row['title']) ?></div>
                </div>
                <span class="badge <?= $badgeClass ?>"><?= h($badgeText) ?></span>
            </div>

            <div class="small text-muted mb-3">
                <div><strong>Date:</strong> <?= h($row['run_date']) ?></div>
                <div><strong>Inspector:</strong> <?= h($row['inspector']) ?></div>
                <div><strong>Failed Steps:</strong> <?= $failSteps ?></div>
                <div><strong>Failed Substeps:</strong> <?= $failSubsteps ?></div>
            </div>

            <div class="d-grid gap-2">
                <a href="<?= h($viewUrl) ?>" class="btn btn-outline-primary">View Completed Run</a>

                <?php if ($failTotal > 0): ?>
                    <a href="<?= h($caUrl) ?>" class="btn btn-warning">View Corrective Actions</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vessel ICRs • <?= h($vessel['vesselName']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .docs-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card {
            border: 0;
            border-radius: 1rem;
        }
        .summary-nav-btn {
            border-radius: 999px;
            font-size: .9rem;
            font-weight: 600;
            padding: .55rem .95rem;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
        .collapse-toggle.btn {
            text-align: left;
            font-weight: 600;
            border-radius: .9rem;
        }
        .icr-meta {
            color: #6b7280;
            margin: 0;
        }
        @media (min-width: 992px) {
            .mobile-stack-two {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Vessel ICRs';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="docs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Inspection Criteria Records</h1>
                            <p class="icr-meta">
                                <?= h($vessel['vesselName']) ?>
                                <?php if (!empty($vessel['vesselON'])): ?>
                                    · Official No. <?= h($vessel['vesselON']) ?>
                                <?php endif; ?>
                                <?php if (!empty($vessel['hailingPort'])): ?>
                                    · Hailing Port: <?= h($vessel['hailingPort']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="add_vessel_icr.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
                                Add ICR from Template
                            </a>
                            <a href="create_custom_icr.php?vessel_id=<?= (int)$vessel_id ?>"
                            class="btn btn-outline-secondary disabled"
                            aria-disabled="true"
                            tabindex="-1"
                            title="Feature under development. Custom vessel-specific ICR creation is not fully implemented yet.">
                                Create Custom ICR
                            </a>
                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                Back to Vessel
                            </a>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <?php if (count($inProgress) > 0): ?>
                            <a href="#section-in-progress" class="btn btn-warning summary-nav-btn">
                                In Progress: <?= count($inProgress) ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary summary-nav-btn" disabled>
                                In Progress: 0
                            </button>
                        <?php endif; ?>

                        <?php if (count($overdue) > 0): ?>
                            <a href="#section-overdue" class="btn btn-danger summary-nav-btn">
                                Overdue: <?= count($overdue) ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary summary-nav-btn" disabled>
                                Overdue: 0
                            </button>
                        <?php endif; ?>

                        <?php if (count($dueSoon) > 0): ?>
                            <a href="#section-due-soon" class="btn btn-outline-warning summary-nav-btn">
                                Due Soon: <?= count($dueSoon) ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary summary-nav-btn" disabled>
                                Due Soon: 0
                            </button>
                        <?php endif; ?>

                        <?php if (count($recentCompleted) > 0): ?>
                            <a href="#section-completed" class="btn btn-outline-success summary-nav-btn">
                                Completed: <?= count($recentCompleted) ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary summary-nav-btn" disabled>
                                Completed: 0
                            </button>
                        <?php endif; ?>

                        <?php if (count($okAssigned) > 0): ?>
                            <a href="#section-assigned-ok" class="btn btn-outline-primary summary-nav-btn">
                                Assigned OK: <?= count($okAssigned) ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary summary-nav-btn" disabled>
                                Assigned OK: 0
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['assigned']) || isset($_GET['skipped'])): ?>
                <div class="alert alert-success mb-3">
                    Assigned <?= (int)($_GET['assigned'] ?? 0) ?> ICR(s).
                    <?php if (!empty($_GET['skipped'])): ?>
                        Skipped <?= (int)$_GET['skipped'] ?> already assigned.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['steps_saved'])): ?>
                <div class="alert alert-success mb-3">
                    Vessel-specific ICR steps saved successfully.
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['removed'])): ?>
                <div class="alert alert-warning mb-3">
                    ICR removed from vessel.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                        <?php if ($show_all_completed): ?>
                            <input type="hidden" name="show_all_completed" value="1">
                        <?php endif; ?>

                        <div class="col-md-5">
                            <label class="form-label">Search ICRs</label>
                            <input
                                type="text"
                                name="q"
                                value="<?= h($q) ?>"
                                class="form-control"
                                placeholder="Search code, title, inspector, frequency..."
                            >
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Active Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Active Sections</option>
                                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                                <option value="due_soon" <?= $status_filter === 'due_soon' ? 'selected' : '' ?>>Due Soon</option>
                                <option value="assigned_ok" <?= $status_filter === 'assigned_ok' ? 'selected' : '' ?>>Assigned / Not Yet Due</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Completed Runs</label>
                            <select name="history_scope" class="form-select">
                                <option value="">All Completed</option>
                                <option value="failed_only" <?= $history_scope === 'failed_only' ? 'selected' : '' ?>>Failed Only</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-fill">Apply</button>
                            <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary flex-fill">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="accordion" id="icrSectionsAccordion">

                <?php if (!empty($inProgress)): ?>
                    <div class="mb-4" id="section-in-progress">
                        <button
                            class="btn btn-outline-warning w-100 collapse-toggle mb-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseInProgress"
                            aria-expanded="false"
                            aria-controls="collapseInProgress"
                        >
                            In Progress (<?= count($inProgress) ?>)
                        </button>

                        <div class="collapse" id="collapseInProgress" data-bs-parent="#icrSectionsAccordion">
                            <div class="mobile-stack-two">
                                <?php foreach ($inProgress as $icr) renderIcrCard($icr, $vessel_id); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($overdue)): ?>
                    <div class="mb-4" id="section-overdue">
                        <button
                            class="btn btn-outline-danger w-100 collapse-toggle mb-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseOverdue"
                            aria-expanded="false"
                            aria-controls="collapseOverdue"
                        >
                            Overdue (<?= count($overdue) ?>)
                        </button>

                        <div class="collapse" id="collapseOverdue" data-bs-parent="#icrSectionsAccordion">
                            <div class="mobile-stack-two">
                                <?php foreach ($overdue as $icr) renderIcrCard($icr, $vessel_id); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($dueSoon)): ?>
                    <div class="mb-4" id="section-due-soon">
                        <button
                            class="btn btn-outline-warning w-100 collapse-toggle mb-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseDueSoon"
                            aria-expanded="false"
                            aria-controls="collapseDueSoon"
                        >
                            Due Soon (<?= count($dueSoon) ?>)
                        </button>

                        <div class="collapse" id="collapseDueSoon" data-bs-parent="#icrSectionsAccordion">
                            <div class="mobile-stack-two">
                                <?php foreach ($dueSoon as $icr) renderIcrCard($icr, $vessel_id); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($okAssigned)): ?>
                    <div class="mb-4" id="section-assigned-ok">
                        <button
                            class="btn btn-outline-primary w-100 collapse-toggle mb-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseAssignedOk"
                            aria-expanded="false"
                            aria-controls="collapseAssignedOk"
                        >
                            Assigned / Not Yet Due (<?= count($okAssigned) ?>)
                        </button>

                        <div class="collapse" id="collapseAssignedOk" data-bs-parent="#icrSectionsAccordion">
                            <div class="mobile-stack-two">
                                <?php foreach ($okAssigned as $icr) renderIcrCard($icr, $vessel_id); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-4" id="section-completed">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                        <button
                            class="btn btn-outline-success flex-grow-1 text-start collapse-toggle"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseCompleted"
                            aria-expanded="false"
                            aria-controls="collapseCompleted"
                        >
                            Recent Completed ICRs (<?= count($recentCompleted) ?>)
                        </button>



                        <div class="collapse" id="collapseCompleted" data-bs-parent="#icrSectionsAccordion">
                            <div class="card shadow-sm border-0 mb-3 mt-2">
                                <div class="card-body">
                                    <form method="get" class="row g-2 align-items-end">
                                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                                        <?php if ($show_all_completed): ?>
                                            <input type="hidden" name="show_all_completed" value="1">
                                        <?php endif; ?>
                                        <input type="hidden" name="q" value="<?= h($q) ?>">
                                        <input type="hidden" name="status" value="<?= h($status_filter) ?>">

                                        <div class="col-md-4">
                                            <label class="form-label">Search Completed Runs</label>
                                            <input
                                                type="text"
                                                name="completed_q"
                                                value="<?= h($completed_q) ?>"
                                                class="form-control"
                                                placeholder="Search code, title, inspector..."
                                            >
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Failed Filter</label>
                                            <select name="history_scope" class="form-select">
                                                <option value="">All Completed</option>
                                                <option value="failed_only" <?= $history_scope === 'failed_only' ? 'selected' : '' ?>>Failed Only</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label">Date Window</label>
                                            <select name="completed_window" class="form-select">
                                                <option value="90d" <?= $completed_window === '90d' ? 'selected' : '' ?>>Last 90 Days</option>
                                                <option value="30d" <?= $completed_window === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                                                <option value="" <?= $completed_window === '' ? 'selected' : '' ?>>All Dates</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Sort</label>
                                            <select name="completed_sort" class="form-select">
                                                <option value="newest" <?= $completed_sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                                <option value="oldest" <?= $completed_sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                                <option value="code" <?= $completed_sort === 'code' ? 'selected' : '' ?>>ICR Code</option>
                                            </select>
                                        </div>

                                        <div class="col-12 d-flex gap-2">
                                            <button class="btn btn-primary">Apply</button>
                                            <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>&show_all_completed=1#section-completed" class="btn btn-outline-secondary">Reset</a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <?php if (!empty($recentCompleted)): ?>
                            <div class="mobile-stack-two mt-2">
                                <?php foreach ($recentCompleted as $row) renderCompletedCard($row, $vessel_id); ?>
                            </div>
                        <?php else: ?>
                            <div class="card shadow-sm border-0">
                                <div class="card-body text-center text-muted">
                                    No completed ICR runs found for this vessel yet.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <a href="add_vessel_icr.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
                Add ICR from Template
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('a.summary-nav-btn[href^="#"]').forEach(function(link) {
    link.addEventListener('click', function() {
        const targetId = this.getAttribute('href');
        const targetSection = document.querySelector(targetId);
        if (!targetSection) return;

        const collapseEl = targetSection.querySelector('.collapse');
        if (collapseEl && !collapseEl.classList.contains('show')) {
            const bsCollapse = new bootstrap.Collapse(collapseEl, { toggle: false });
            bsCollapse.show();
        }

        setTimeout(function() {
            targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 180);
    });
});
</script>
</body>
</html>