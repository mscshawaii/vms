<?php
require 'session_check.php';
require 'db_connect.php';

$id = intval($_GET['id'] ?? 0);

// Fetch the task info
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("❌ Task not found.");
}

$vessel_id = (int)$task['vessel_id'];

// Load active vessel-assigned users
$crew_stmt = $pdo->prepare("
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
    ORDER BY
        FIELD(vc.role, 'Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'),
        u.lName,
        u.fName
");
$crew_stmt->execute([$vessel_id]);
$crew = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);

// Load existing notify recipients
$notify_selected = [];
try {
    $notify_stmt = $pdo->prepare("
        SELECT user_id
        FROM task_notification_recipients
        WHERE task_id = ?
    ");
    $notify_stmt->execute([$id]);
    $notify_selected = array_map('intval', $notify_stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable $e) {
    $notify_selected = [];
}

// Optional vessel header info for page context
$vessel = null;
try {
    $vessel_stmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort
        FROM vessels
        WHERE vessel_id = ?
        LIMIT 1
    ");
    $vessel_stmt->execute([$vessel_id]);
    $vessel = $vessel_stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $vessel = null;
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Corrective Action</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .tasks-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .form-section-card {
            border: 0;
            border-radius: 1rem;
        }
        .tasks-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
<?php
$title = 'Edit Corrective Action';
$back_link = 'view_task.php?id=' . (int)$id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="tasks-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Edit Corrective Action</h1>
                            <p class="tasks-meta">
                                <?php if (!empty($vessel['vesselName'])): ?>
                                    <?= h($vessel['vesselName']) ?>
                                    <?php if (!empty($vessel['vesselON'])): ?>
                                        · Official No. <?= h($vessel['vesselON']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($vessel['hailingPort'])): ?>
                                        · Hailing Port: <?= h($vessel['hailingPort']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Task #<?= (int)$task['task_id'] ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="view_task.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                                View Task
                            </a>
                            <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                Back to Corrective Actions
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <form id="editTaskForm" method="post" action="update_task.php" enctype="multipart/form-data">
                <input type="hidden" name="task_id" value="<?= (int)$task['task_id'] ?>">
                <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Core Details</div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input
                                type="text"
                                id="title"
                                name="title"
                                value="<?= h($task['title'] ?? '') ?>"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                class="form-control"
                            ><?= h($task['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-0">
                            <label for="notes" class="form-label">Notes / Running History</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="5"
                                class="form-control"
                                placeholder="Troubleshooting, parts ordered, vendor contact, follow-up notes, questions, etc."
                            ><?= h($task['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Regulation / Assignment</div>

                        <div class="mb-3">
                            <label for="regulation" class="form-label">Supporting regulation (optional)</label>
                            <textarea
                                id="regulation"
                                name="regulation"
                                rows="2"
                                class="form-control"
                                placeholder="e.g. 46 CFR 122.320"
                            ><?= h($task['supporting_regulations'] ?? '') ?></textarea>
                            <div class="form-text">
                                If your schema doesn’t have a <code>supporting_regulations</code> column, this will still be appended to Description as
                                <em>“Supporting regulation: …”</em> without duplicating it.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Assigned To (Primary Owner)</label>
                            <select name="assigned_to" id="assigned_to" class="form-select" required>
                                <option value="">-- Select Crew Member --</option>
                                <?php foreach ($crew as $c):
                                    $selected = ((int)($task['assigned_to'] ?? 0) === (int)$c['id']) ? 'selected' : '';
                                    $label = trim(($c['fName'] ?? '') . ' ' . ($c['lName'] ?? ''));
                                    if (!empty($c['role'])) {
                                        $label .= " ({$c['role']})";
                                    }
                                ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $selected ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-0">
                            <label for="notify_users" class="form-label">Notify / Keep Informed</label>
                            <select name="notify_users[]" id="notify_users" class="form-select" multiple size="6">
                                <?php foreach ($crew as $c):
                                    $selected = in_array((int)$c['id'], $notify_selected, true) ? 'selected' : '';
                                    $label = trim(($c['fName'] ?? '') . ' ' . ($c['lName'] ?? ''));
                                    if (!empty($c['role'])) {
                                        $label .= " ({$c['role']})";
                                    }
                                ?>
                                    <option value="<?= (int)$c['id'] ?>" <?= $selected ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Optional. Saved for future messaging/alerts and historical tracking.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Status / Timing</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input
                                    type="date"
                                    id="due_date"
                                    name="due_date"
                                    value="<?= h($task['due_date'] ?? '') ?>"
                                    class="form-control"
                                >
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="corrected_date" class="form-label">Completed/Corrected Date</label>
                                <input
                                    type="date"
                                    id="corrected_date"
                                    name="corrected_date"
                                    value="<?= h($task['corrected_date'] ?? '') ?>"
                                    class="form-control"
                                >
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <?php
                                    $statuses = ['open','in_progress','complete','overdue','deferred'];
                                    foreach ($statuses as $s):
                                        $sel = (($task['status'] ?? '') === $s) ? 'selected' : '';
                                    ?>
                                        <option value="<?= h($s) ?>" <?= $sel ?>>
                                            <?= h(ucwords(str_replace('_', ' ', $s))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="priority" class="form-label">Priority</label>
                                <select name="priority" id="priority" class="form-select">
                                    <?php
                                    $priorities = ['urgent','moderate','low','recommendation'];
                                    foreach ($priorities as $p):
                                        $sel = (($task['priority'] ?? '') === $p) ? 'selected' : '';
                                    ?>
                                        <option value="<?= h($p) ?>" <?= $sel ?>>
                                            <?= h(ucfirst($p)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="recurrence_interval" class="form-label">Recurring Interval (optional)</label>
                                <input
                                    type="text"
                                    id="recurrence_interval"
                                    name="recurrence_interval"
                                    value="<?= h($task['recurrence_interval'] ?? '') ?>"
                                    class="form-control"
                                    placeholder="e.g. Monthly"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Completion / Photos</div>

                        <div class="mb-3">
                            <label for="corrective_action" class="form-label">Corrective Action Taken</label>
                            <textarea
                                id="corrective_action"
                                name="corrective_action"
                                rows="3"
                                class="form-control"
                            ><?= h($task['corrective_action'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-0">
                            <label for="photos" class="form-label">Add Photos (optional)</label>
                            <input
                                type="file"
                                id="photos"
                                name="photos[]"
                                class="form-control"
                                accept=".jpg,.jpeg,.png,.gif,.webp"
                                multiple
                            >
                            <div class="form-text">
                                Up to 10 images, 10MB each. New photos will be added to existing ones.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="view_task.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="editTaskForm" class="btn btn-primary">
                Save Changes
            </button>
            <a href="view_task.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>
</div>
</body>
</html>