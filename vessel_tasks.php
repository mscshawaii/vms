<?php
require 'session_check.php';
require 'db_connect.php';
require_once __DIR__ . '/includes/message_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$vessel_id = isset($_GET['vessel_id']) ? (int)$_GET['vessel_id'] : 0;
if ($vessel_id <= 0) {
    die("Invalid vessel ID.");
}

$run_id = isset($_GET['icr_run_id']) && $_GET['icr_run_id'] !== '' ? (int)$_GET['icr_run_id'] : null;

/* ------- Read filters/sort from GET ------- */
$task_filter = isset($_GET['task_filter']) && $_GET['task_filter'] !== '' ? trim((string)$_GET['task_filter']) : null;
$status      = isset($_GET['status']) && $_GET['status'] !== '' ? trim((string)$_GET['status']) : null;
$priority    = isset($_GET['priority']) && $_GET['priority'] !== '' ? trim((string)$_GET['priority']) : null;
$due_from    = isset($_GET['due_from']) && $_GET['due_from'] !== '' ? trim((string)$_GET['due_from']) : null;
$due_to      = isset($_GET['due_to'])   && $_GET['due_to']   !== '' ? trim((string)$_GET['due_to'])   : null;
$sort        = $_GET['sort'] ?? 'due_asc';
$q           = trim((string)($_GET['q'] ?? ''));

/* ------- Helpers ------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dt($d){ return ($d && $d !== '0000-00-00') ? date('M j, Y', strtotime($d)) : '—'; }

function badge_status($s){
    $map = [
        'open'        => 'secondary',
        'in_progress' => 'info',
        'overdue'     => 'warning',
        'deferred'    => 'dark',
        'complete'    => 'success',
    ];
    $class = $map[$s] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.h(ucwords(str_replace('_',' ',$s))).'</span>';
}

function badge_priority($p){
    $map = [
        'urgent'         => 'danger',
        'moderate'       => 'primary',
        'low'            => 'secondary',
        'recommendation' => 'warning'
    ];
    $class = $map[strtolower((string)$p)] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.h(ucfirst((string)$p)).'</span>';
}

function qs($overrides = []) {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

function task_matches_search(array $task, string $q): bool {
    if ($q === '') return true;

    $haystack = strtolower(implode(' ', [
        (string)($task['title'] ?? ''),
        (string)($task['status'] ?? ''),
        (string)($task['priority'] ?? ''),
        (string)($task['fName'] ?? ''),
        (string)($task['lName'] ?? ''),
        (string)($task['icr_number'] ?? ''),
        (string)($task['due_date'] ?? ''),
        (string)($task['completed_date'] ?? ''),
        (string)($task['corrected_date'] ?? ''),
    ]));

    return str_contains($haystack, strtolower($q));
}

/* ------- Vessel header ------- */
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

/* ------- Build base query ------- */
$sql = "
    SELECT 
        t.task_id,
        t.title,
        t.status,
        t.priority,
        t.due_date,
        t.completed_date,
        t.corrected_date,
        t.assigned_to,
        u.fName,
        u.lName,
        t.vessel_icr_run_id,
        vi.icr_id,
        i.icr_number
    FROM tasks t
    LEFT JOIN users       u  ON t.assigned_to   = u.id
    LEFT JOIN vessel_icrs vi ON t.vessel_icr_id = vi.vessel_icr_id
    LEFT JOIN icrs        i  ON vi.icr_id       = i.icr_id
    WHERE t.vessel_id = ?
";
$params = [$vessel_id];

/* ------- Optional filter: by ICR run ------- */
if ($run_id) {
    $sql .= " AND t.vessel_icr_run_id = ? ";
    $params[] = $run_id;
}

/* ------- Status behavior ------- */
$appliedStatusFilter = false;

if ($status !== null && $status !== '') {
    $sql .= " AND t.status = ? ";
    $params[] = $status;
    $appliedStatusFilter = true;
}

if (!$appliedStatusFilter) {
    $active = ['open','in_progress','overdue','deferred'];
    $in = implode(',', array_fill(0, count($active), '?'));
    $sql .= " AND t.status IN ($in) ";
    $params = array_merge($params, $active);
}

/* ------- Other filters ------- */
if ($priority !== null && $priority !== '') {
    $sql .= " AND t.priority = ? ";
    $params[] = $priority;
}

if ($due_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_from)) {
    $sql .= " AND (t.due_date IS NOT NULL AND t.due_date >= ?) ";
    $params[] = $due_from;
}

if ($due_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_to)) {
    $sql .= " AND (t.due_date IS NOT NULL AND t.due_date <= ?) ";
    $params[] = $due_to;
}

/* ------- Sorting ------- */
$sortSql = match ($sort) {
    'due_desc'    => " ORDER BY (t.due_date IS NULL) ASC, t.due_date DESC, t.priority ASC, t.title ASC ",
    'prio_asc'    => " ORDER BY t.priority ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'prio_desc'   => " ORDER BY t.priority DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_asc'  => " ORDER BY t.status ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_desc' => " ORDER BY t.status DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    default       => " ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.priority ASC, t.title ASC ",
};
$sql .= $sortSql;

/* ------- Distinct values for dropdowns ------- */
$distinctStatus = $pdo->prepare("
    SELECT DISTINCT t.status 
    FROM tasks t 
    WHERE t.vessel_id = ? AND t.status IS NOT NULL AND t.status <> '' 
    ORDER BY t.status
");
$distinctStatus->execute([$vessel_id]);
$statuses = array_column($distinctStatus->fetchAll(PDO::FETCH_ASSOC), 'status');

$distinctPriority = $pdo->prepare("
    SELECT DISTINCT t.priority 
    FROM tasks t 
    WHERE t.vessel_id = ? AND t.priority IS NOT NULL AND t.priority <> '' 
    ORDER BY t.priority
");
$distinctPriority->execute([$vessel_id]);
$priorities = array_column($distinctPriority->fetchAll(PDO::FETCH_ASSOC), 'priority');

/* ------- Execute main query ------- */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------- Apply text search in PHP (matches original modal behavior style) ------- */
$rows = array_values(array_filter($rows, fn($row) => task_matches_search($row, $q)));

/* ------- Summary counts (full vessel scope, not just filtered rows) ------- */
$countStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM tasks
    WHERE vessel_id = ?
    GROUP BY status
");
$countStmt->execute([$vessel_id]);

$summary = [
    'open' => 0,
    'in_progress' => 0,
    'overdue' => 0,
    'deferred' => 0,
    'complete' => 0,
];
while ($r = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $summary[$r['status']] = (int)$r['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Corrective Actions • <?= h($vessel['vesselName']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .tasks-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card {
            border: 0;
            border-radius: 1rem;
        }
        .tasks-meta {
            color: #6b7280;
            margin: 0;
        }
        .summary-nav-btn {
            border-radius: 999px;
            font-size: .9rem;
            font-weight: 600;
            padding: .55rem .95rem;
        }
        .task-card {
            border: 0;
            border-radius: 1rem;
        }
        .task-meta {
            font-size: .92rem;
            color: #6b7280;
        }
        .task-actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
        @media (min-width: 992px) {
            .task-card-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Corrective Actions';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="tasks-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Corrective Actions</h1>
                            <p class="tasks-meta">
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
                            <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
                                Add Task
                            </a>
                            <a href="print_tasks.php?<?= qs([]) ?>" target="_blank" class="btn btn-outline-secondary">
                                Print
                            </a>
                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                Back to Vessel
                            </a>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="?<?= qs(['status' => 'open']) ?>" class="btn btn-outline-secondary summary-nav-btn">
                            Open: <?= (int)$summary['open'] ?>
                        </a>
                        <a href="?<?= qs(['status' => 'in_progress']) ?>" class="btn btn-outline-info summary-nav-btn">
                            In Progress: <?= (int)$summary['in_progress'] ?>
                        </a>
                        <a href="?<?= qs(['status' => 'overdue']) ?>" class="btn btn-outline-warning summary-nav-btn">
                            Overdue: <?= (int)$summary['overdue'] ?>
                        </a>
                        <a href="?<?= qs(['status' => 'deferred']) ?>" class="btn btn-outline-dark summary-nav-btn">
                            Deferred: <?= (int)$summary['deferred'] ?>
                        </a>
                        <a href="?<?= qs(['status' => 'complete']) ?>" class="btn btn-outline-success summary-nav-btn">
                            Complete: <?= (int)$summary['complete'] ?>
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($run_id): ?>
                <div class="alert alert-info py-2">
                    Showing corrective actions generated from ICR Run #<?= h($run_id) ?>.
                    <a href="vessel_tasks.php?vessel_id=<?= h($vessel_id) ?>" class="ms-2">Clear Filter</a>
                </div>
            <?php endif; ?>

            <?php if ($task_filter === 'open' && ($status === null || $status === '')): ?>
                <div class="alert alert-warning py-2 px-3 mb-3">
                    Showing open corrective actions only.
                    <a href="vessel_tasks.php?vessel_id=<?= h($vessel_id) ?>" class="ms-2">Show default view</a>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <form method="get" class="row g-2 align-items-end">
                        <input type="hidden" name="vessel_id" value="<?= h($vessel_id) ?>">
                        <?php if ($run_id): ?>
                            <input type="hidden" name="icr_run_id" value="<?= h($run_id) ?>">
                        <?php endif; ?>
                        <?php if ($task_filter): ?>
                            <input type="hidden" name="task_filter" value="<?= h($task_filter) ?>">
                        <?php endif; ?>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Search</label>
                            <input
                                type="text"
                                name="q"
                                value="<?= h($q) ?>"
                                class="form-control"
                                placeholder="Search title, assignee, status, ICR..."
                            >
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All (Active by default)</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= h($s) ?>" <?= ($status === $s ? 'selected' : '') ?>>
                                        <?= h(ucwords(str_replace('_',' ',$s))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($priorities as $p): ?>
                                    <option value="<?= h($p) ?>" <?= ($priority === $p ? 'selected' : '') ?>>
                                        <?= h(ucfirst($p)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">Due From</label>
                            <input type="date" name="due_from" value="<?= h($due_from ?? '') ?>" class="form-control">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label">Due To</label>
                            <input type="date" name="due_to" value="<?= h($due_to ?? '') ?>" class="form-control">
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">Sort</label>
                            <select name="sort" class="form-select">
                                <option value="due_asc"   <?= $sort==='due_asc'?'selected':''; ?>>Due Date ↑</option>
                                <option value="due_desc"  <?= $sort==='due_desc'?'selected':''; ?>>Due Date ↓</option>
                                <option value="prio_asc"  <?= $sort==='prio_asc'?'selected':''; ?>>Priority ↑</option>
                                <option value="prio_desc" <?= $sort==='prio_desc'?'selected':''; ?>>Priority ↓</option>
                                <option value="status_asc"  <?= $sort==='status_asc'?'selected':''; ?>>Status ↑</option>
                                <option value="status_desc" <?= $sort==='status_desc'?'selected':''; ?>>Status ↓</option>
                            </select>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary">Apply</button>
                            <a class="btn btn-outline-secondary" href="vessel_tasks.php?vessel_id=<?= urlencode((string)$vessel_id) ?><?= $run_id ? '&icr_run_id='.urlencode((string)$run_id) : '' ?><?= $task_filter ? '&task_filter='.urlencode((string)$task_filter) : '' ?>">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!$rows): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center text-muted">
                        No corrective actions match your current filters.
                    </div>
                </div>
            <?php else: ?>
                <div class="task-card-grid">
                    <?php
                    $today = date('Y-m-d');
                    $currentUserId = (int)($_SESSION['user_id'] ?? 0);

                    foreach ($rows as $task):
                        $assignee = trim(($task['fName'] ?? '') . ' ' . ($task['lName'] ?? ''));
                        $due = $task['due_date'] ?? null;
                        $isComplete = (($task['status'] ?? '') === 'complete');
                        $isOverdue = (!$isComplete && $due && $due < $today);
                        $occurredCompleted = $task['corrected_date'] ?: $task['completed_date'];

                        $threadId = ensureTaskThread($pdo, (int)$task['task_id'], $currentUserId);
                        syncTaskThreadMembers($pdo, (int)$task['task_id'], $currentUserId);

                        $messageCount = getThreadMessageCount($pdo, $threadId);
                        $unreadCount  = getThreadUnreadCount($pdo, $threadId, $currentUserId);
                        $discussionBtnClass = $unreadCount > 0 ? 'btn-outline-danger' : 'btn-outline-dark';
                    ?>
                        <div class="card shadow-sm task-card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold fs-6"><?= h($task['title'] ?? '—') ?></div>
                                        <?php if (!empty($task['icr_number'])): ?>
                                            <div class="small text-muted">Linked ICR: <?= h($task['icr_number']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <?= badge_status($task['status'] ?? '') ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?= badge_priority($task['priority'] ?? '') ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge bg-danger">Past Due</span>
                                    <?php endif; ?>
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="badge bg-danger"><?= (int)$unreadCount ?> unread</span>
                                    <?php endif; ?>
                                </div>

                                <div class="task-meta mb-3">
                                    <div><strong>Assigned:</strong> <?= $assignee !== '' ? h($assignee) : '—' ?></div>
                                    <div><strong>Due:</strong> <?= $due ? ($isOverdue ? '<span class="text-danger fw-semibold">'.h(dt($due)).'</span>' : h(dt($due))) : '—' ?></div>
                                    <div><strong>Occurred / Completed:</strong> <?= h(dt($occurredCompleted)) ?></div>
                                </div>

                                <div class="task-actions-grid">
                                    <a href="view_task.php?id=<?= (int)$task['task_id'] ?>" class="btn btn-outline-secondary btn-sm">Open</a>
                                    <a href="edit_task.php?id=<?= (int)$task['task_id'] ?>" class="btn btn-primary btn-sm">Edit</a>

                                    <a href="task_discussion.php?task_id=<?= (int)$task['task_id'] ?>" class="btn <?= h($discussionBtnClass) ?> btn-sm">
                                        Discussion <span class="badge bg-secondary ms-1"><?= (int)$messageCount ?></span>
                                        <?php if ($unreadCount > 0): ?>
                                            <span class="badge bg-danger ms-1"><?= (int)$unreadCount ?></span>
                                        <?php endif; ?>
                                    </a>

                                    <?php if (!empty($task['vessel_icr_run_id'])): ?>
                                        <a href="view_icr_run.php?run_id=<?= (int)$task['vessel_icr_run_id'] ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                                            ICR Run
                                        </a>
                                    <?php else: ?>
                                        <a href="delete_task.php?id=<?= (int)$task['task_id'] ?>&vessel_id=<?= (int)$vessel_id ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this corrective action?')">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
                Add Task
            </a>
            <a href="print_tasks.php?<?= qs([]) ?>" target="_blank" class="btn btn-outline-secondary">
                Print
            </a>
        </div>
    </div>
</div>
</body>
</html>