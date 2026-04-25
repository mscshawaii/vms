<?php
require __DIR__ . '/db_connect.php';

$task_id = intval($_GET['id'] ?? 0);

// --- Load task + joins ---
$stmt = $pdo->prepare("
    SELECT
        t.*,
        u.fName AS assigned_fName,
        u.lName AS assigned_lName,
        cu.fName AS created_fName,
        cu.lName AS created_lName,
        e.equipmentName,
        v.vesselName,
        v.vesselON,
        v.hailingPort
    FROM tasks t
    LEFT JOIN users u  ON t.assigned_to = u.id
    LEFT JOIN users cu ON t.created_by  = cu.id
    LEFT JOIN equipment e ON t.equipment_id = e.eid
    LEFT JOIN vessels v ON t.vessel_id = v.vessel_id
    WHERE t.task_id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { die("❌ Corrective Action not found."); }

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dash($v){ return (isset($v) && $v !== '' && $v !== '0000-00-00') ? safe($v) : '—'; }
function fmt_date($d){
    if (!$d || $d === '0000-00-00') return '—';
    $ts = strtotime($d);
    return $ts ? date('M j, Y', $ts) : safe($d);
}
function badge_status($s){
    $map = [
        'open'        => 'secondary',
        'in_progress' => 'info',
        'overdue'     => 'warning',
        'deferred'    => 'dark',
        'complete'    => 'success',
    ];
    $class = $map[$s] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.safe(ucwords(str_replace('_',' ',$s))).'</span>';
}
function badge_priority($p){
    $map = [
        'urgent'         => 'danger',
        'moderate'       => 'primary',
        'low'            => 'secondary',
        'recommendation' => 'warning'
    ];
    $class = $map[strtolower((string)$p)] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.safe(ucfirst((string)$p)).'</span>';
}

// Load notify recipients
$notify_names = [];
try {
    $notifyStmt = $pdo->prepare("
        SELECT u.fName, u.lName
        FROM task_notification_recipients tnr
        INNER JOIN users u ON u.id = tnr.user_id
        WHERE tnr.task_id = ?
        ORDER BY u.lName, u.fName
    ");
    $notifyStmt->execute([$task_id]);
    while ($row = $notifyStmt->fetch(PDO::FETCH_ASSOC)) {
        $notify_names[] = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
    }
} catch (Throwable $e) {
    $notify_names = [];
}

// Convert absolute fs path → web path relative to app root
function webify_path($path) {
    if (!$path) return null;
    $p = str_replace('\\', '/', $path);

    $root = str_replace('\\', '/', realpath(__DIR__));
    $isAbsolute = (preg_match('#^[a-zA-Z]:/#', $p) || str_starts_with($p, '/'));

    if ($isAbsolute && str_starts_with($p, $root)) {
        $rel = ltrim(substr($p, strlen($root)), '/');
        return $rel;
    }

    $pos = strpos($p, 'uploads/');
    if ($pos !== false) return substr($p, $pos);

    return ltrim($p, '/');
}

function abs_url(string $rel): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($rel, '/');
}

function list_images_in($relDir) {
    $out = [];
    if (!$relDir) return $out;
    $absDir = __DIR__ . '/' . ltrim($relDir, '/');
    if (!is_dir($absDir)) return $out;

    $allowed = ['jpg','jpeg','png','gif','webp','bmp'];
    $files = @scandir($absDir);
    if (!$files) return $out;

    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;
        $abs = $absDir . '/' . $f;
        if (is_file($abs)) {
            $out[] = trim($relDir, '/') . '/' . $f;
        }
    }
    return $out;
}

// --- Collect attachments ---
$attachments = [];

// 1) task_photos
try {
    $pstmt = $pdo->prepare("SELECT file_path FROM task_photos WHERE task_id = ? ORDER BY id");
    $pstmt->execute([$task_id]);
    while ($row = $pstmt->fetch(PDO::FETCH_ASSOC)) {
        $src = webify_path($row['file_path'] ?? '');
        if (!$src) continue;
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true)) continue;
        $attachments[$src] = true;
    }
} catch (Throwable $e) {
}

// 2) uploads/tasks/{task_id}/
$taskDir = "uploads/tasks/{$task_id}";
foreach (list_images_in($taskDir) as $src) {
    $attachments[$src] = true;
}

// 3) ICR-based photos
$icrRunId = intval($task['vessel_icr_run_id'] ?? 0);
$title    = (string)($task['title'] ?? '');

$stepNum = null;
$subCode = null;
if (preg_match('/Step\s+(\d+)\s*([A-Za-z])?(?=\b|:)/i', $title, $m)) {
    $stepNum = (int)$m[1];
    if (!empty($m[2])) $subCode = strtoupper($m[2]);
}

try {
    if ($icrRunId > 0 && $stepNum !== null) {
        $q = $pdo->prepare("SELECT vessel_icr_id, icr_id FROM vessel_icr_runs WHERE run_id = ?");
        $q->execute([$icrRunId]);
        if ($run = $q->fetch(PDO::FETCH_ASSOC)) {
            $vessel_icr_id = (int)($run['vessel_icr_id'] ?? 0);
            $icr_id        = (int)($run['icr_id'] ?? 0);

            $vessel_icr_step_id = null;
            if ($vessel_icr_id > 0) {
                $s = $pdo->prepare("
                    SELECT step_id
                    FROM vessel_icr_steps
                    WHERE vessel_icr_id = ? AND step_number = ?
                    LIMIT 1
                ");
                $s->execute([$vessel_icr_id, $stepNum]);
                $vessel_icr_step_id = (int)$s->fetchColumn() ?: null;
            }

            $icr_step_id = null;
            if ($icr_id > 0) {
                $is = $pdo->prepare("
                    SELECT step_id
                    FROM icr_steps
                    WHERE icr_id = ? AND step_number = ?
                    LIMIT 1
                ");
                $is->execute([$icr_id, $stepNum]);
                $icr_step_id = (int)$is->fetchColumn() ?: null;
            }

            if ($vessel_icr_step_id) {
                if ($subCode !== null) {
                    $vessel_substep_id = null;
                    try {
                        $vs = $pdo->prepare("
                            SELECT substep_id
                            FROM vessel_icr_substeps
                            WHERE vessel_step_id = ? AND UPPER(substep_code) = ?
                            LIMIT 1
                        ");
                        $vs->execute([$vessel_icr_step_id, $subCode]);
                        $vessel_substep_id = (int)$vs->fetchColumn() ?: null;
                    } catch (Throwable $e) {
                        $vessel_substep_id = null;
                    }

                    if ($vessel_substep_id) {
                        $ra = $pdo->prepare("
                            SELECT file_path
                            FROM icr_run_attachments
                            WHERE run_id = ?
                              AND vessel_icr_step_id = ?
                              AND vessel_substep_id = ?
                            ORDER BY id
                        ");
                        $ra->execute([$icrRunId, $vessel_icr_step_id, $vessel_substep_id]);
                        while ($row = $ra->fetch(PDO::FETCH_ASSOC)) {
                            $src = webify_path($row['file_path'] ?? '');
                            if (!$src) continue;
                            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true)) continue;
                            $attachments[$src] = true;
                        }
                    } elseif (!empty($icr_step_id)) {
                        $icr_substep_id = null;
                        $ms = $pdo->prepare("
                            SELECT substep_id
                            FROM icr_substeps
                            WHERE step_id = ? AND UPPER(substep_code) = ?
                            LIMIT 1
                        ");
                        $ms->execute([$icr_step_id, $subCode]);
                        $icr_substep_id = (int)$ms->fetchColumn() ?: null;

                        if ($icr_substep_id) {
                            $ra = $pdo->prepare("
                                SELECT file_path
                                FROM icr_run_attachments
                                WHERE run_id = ?
                                  AND vessel_icr_step_id = ?
                                  AND icr_substep_id = ?
                                ORDER BY id
                            ");
                            $ra->execute([$icrRunId, $vessel_icr_step_id, $icr_substep_id]);
                            while ($row = $ra->fetch(PDO::FETCH_ASSOC)) {
                                $src = webify_path($row['file_path'] ?? '');
                                if (!$src) continue;
                                $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                                if (!in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true)) continue;
                                $attachments[$src] = true;
                            }
                        }
                    }
                } else {
                    $ra = $pdo->prepare("
                        SELECT file_path
                        FROM icr_run_attachments
                        WHERE run_id = ?
                          AND vessel_icr_step_id = ?
                          AND icr_substep_id IS NULL
                          AND vessel_substep_id IS NULL
                        ORDER BY id
                    ");
                    $ra->execute([$icrRunId, $vessel_icr_step_id]);
                    while ($row = $ra->fetch(PDO::FETCH_ASSOC)) {
                        $src = webify_path($row['file_path'] ?? '');
                        if (!$src) continue;
                        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true)) continue;
                        $attachments[$src] = true;
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
}

$images = array_keys($attachments);
sort($images);

$assignedName = trim(($task['assigned_fName'] ?? '') . ' ' . ($task['assigned_lName'] ?? ''));
$createdName  = trim(($task['created_fName'] ?? '') . ' ' . ($task['created_lName'] ?? ''));
$vessel_id    = (int)($task['vessel_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Corrective Action</title>
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
        .detail-card,
        .attachment-card {
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
        .detail-row + .detail-row {
            margin-top: .85rem;
            padding-top: .85rem;
            border-top: 1px solid #eef1f4;
        }
        .detail-label {
            font-size: .84rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #6b7280;
            margin-bottom: .2rem;
            font-weight: 700;
        }
        .detail-value {
            font-size: .98rem;
            color: #212529;
        }
        .img-tile {
            border: 1px solid #dee2e6;
            border-radius: .9rem;
            overflow: hidden;
            background: #fff;
            height: 100%;
        }
        .img-tile img {
            width: 100%;
            height: 220px;
            object-fit: contain;
            background: #f8f9fa;
        }
        .img-tile .cap {
            font-size: .9rem;
            padding: .65rem .75rem;
            border-top: 1px solid #eee;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
$title = 'View Corrective Action';
$back_link = 'vessel_tasks.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="tasks-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1"><?= safe($task['title'] ?? 'Corrective Action') ?></h1>
                            <p class="tasks-meta">
                                <?= safe($task['vesselName'] ?? '—') ?>
                                <?php if (!empty($task['vesselON'])): ?>
                                    · Official No. <?= safe($task['vesselON']) ?>
                                <?php endif; ?>
                                <?php if (!empty($task['hailingPort'])): ?>
                                    · Hailing Port: <?= safe($task['hailingPort']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?= badge_status($task['status'] ?? '') ?>
                            <?= badge_priority($task['priority'] ?? '') ?>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                        <a href="edit_task.php?id=<?= (int)$task_id ?>" class="btn btn-primary">
                            Edit
                        </a>
                        <?php if (($task['task_type'] ?? 'corrective_action') === 'hour_maintenance' && !empty($task['related_schedule_id'])): ?>
                            <a href="maintenance_complete.php?schedule_id=<?= (int)$task['related_schedule_id'] ?>" class="btn btn-outline-success">
                                Complete Maintenance
                            </a>
                            <a href="maintenance_backfill.php?schedule_id=<?= (int)$task['related_schedule_id'] ?>" class="btn btn-outline-primary">
                                Backfill
                            </a>
                        <?php elseif (($task['task_type'] ?? 'corrective_action') === 'meter_verification'): ?>
                            <a href="meter_verification.php?vessel_id=<?= (int)$vessel_id ?>&task_id=<?= (int)$task_id ?>" class="btn btn-outline-primary">
                                Open Verification
                            </a>
                        <?php endif; ?>
                        <a href="task_discussion.php?task_id=<?= (int)$task_id ?>" class="btn btn-outline-dark">
                            Discussion
                        </a>
                        <?php if (!empty($task['vessel_icr_run_id'])): ?>
                            <a href="view_icr_run.php?run_id=<?= (int)$task['vessel_icr_run_id'] ?>" target="_blank" class="btn btn-outline-secondary">
                                ICR Run
                            </a>
                        <?php endif; ?>
                        <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                            Back to Corrective Actions
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm detail-card mb-3">
                        <div class="card-body">
                            <div class="section-title">Task Details</div>

                            <div class="detail-row">
                                <div class="detail-label">Description</div>
                                <div class="detail-value"><?= !empty($task['description']) ? nl2br(safe($task['description'])) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Notes / Running History</div>
                                <div class="detail-value"><?= !empty($task['notes']) ? nl2br(safe($task['notes'])) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Supporting Regulation</div>
                                <div class="detail-value">
                                    <?= !empty($task['supporting_regulations']) ? nl2br(safe($task['supporting_regulations'])) : '—' ?>
                                </div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Corrective Action Taken</div>
                                <div class="detail-value">
                                    <?= !empty($task['corrective_action']) ? nl2br(safe($task['corrective_action'])) : '—' ?>
                                </div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Notify / Keep Informed</div>
                                <div class="detail-value">
                                    <?= !empty($notify_names) ? safe(implode(', ', $notify_names)) : '—' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm attachment-card mb-3">
                        <div class="card-body">
                            <div class="section-title">Photo Attachments</div>

                            <?php if (!empty($images)): ?>
                                <div class="row g-3">
                                    <?php foreach ($images as $src): ?>
                                        <?php $url = abs_url($src); ?>
                                        <div class="col-12 col-sm-6">
                                            <div class="img-tile">
                                                <a href="<?= safe($url) ?>" target="_blank" rel="noopener">
                                                    <img src="<?= safe($url) ?>" alt="attachment">
                                                </a>
                                                <div class="cap"><?= safe(basename($src)) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted">No photos attached.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="card shadow-sm detail-card mb-3">
                        <div class="card-body">
                            <div class="section-title">Assignment / Status</div>

                            <div class="detail-row">
                                <div class="detail-label">Assigned To</div>
                                <div class="detail-value"><?= $assignedName !== '' ? safe($assignedName) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Created By</div>
                                <div class="detail-value"><?= $createdName !== '' ? safe($createdName) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Equipment</div>
                                <div class="detail-value"><?= !empty($task['equipmentName']) ? safe($task['equipmentName']) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value"><?= badge_status($task['status'] ?? '') ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Task Type</div>
                                <div class="detail-value"><?= safe($task['task_type'] ?? 'corrective_action') ?></div>
                            </div>

                            <?php if (!empty($task['cycle_due_hours'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Cycle Due Hours</div>
                                    <div class="detail-value"><?= safe($task['cycle_due_hours']) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($task['due_state'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Due State</div>
                                    <div class="detail-value"><?= safe($task['due_state']) ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <div class="detail-label">Priority</div>
                                <div class="detail-value"><?= badge_priority($task['priority'] ?? '') ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Recurring</div>
                                <div class="detail-value">
                                    <?= !empty($task['is_recurring']) ? 'Yes' : 'No' ?>
                                    <?php if (!empty($task['recurrence_interval'])): ?>
                                        (<?= safe($task['recurrence_interval']) ?>)
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm detail-card mb-3">
                        <div class="card-body">
                            <div class="section-title">Dates</div>

                            <div class="detail-row">
                                <div class="detail-label">Created At</div>
                                <div class="detail-value"><?= dash($task['created_at'] ?? '') ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Due Date</div>
                                <div class="detail-value"><?= fmt_date($task['due_date'] ?? '') ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Completed / Corrected Date</div>
                                <div class="detail-value"><?= fmt_date($task['corrected_date'] ?? '') ?></div>
                            </div>

                            <?php if (!empty($task['completed_date'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Completed Date</div>
                                    <div class="detail-value"><?= fmt_date($task['completed_date']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($task['vessel_icr_run_id'])): ?>
                        <div class="card shadow-sm detail-card mb-3">
                            <div class="card-body">
                                <div class="section-title">ICR Linkage</div>

                                <div class="detail-row">
                                    <div class="detail-label">ICR Run ID</div>
                                    <div class="detail-value">#<?= (int)$task['vessel_icr_run_id'] ?></div>
                                </div>

                                <div class="detail-row">
                                    <a href="view_icr_run.php?run_id=<?= (int)$task['vessel_icr_run_id'] ?>" target="_blank" class="btn btn-outline-secondary w-100">
                                        Open ICR Run
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <a href="edit_task.php?id=<?= (int)$task_id ?>" class="btn btn-primary">
                Edit
            </a>
            <a href="task_discussion.php?task_id=<?= (int)$task_id ?>" class="btn btn-outline-dark">
                Discussion
            </a>
            <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                Back to Corrective Actions
            </a>
        </div>
    </div>
</div>
</body>
</html>
