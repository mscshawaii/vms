<?php
require 'db_connect.php';
require 'session_check.php';

$run_id = intval($_GET['run_id'] ?? 0);
$debug  = !empty($_GET['debug']);
if (!$run_id) { die("❌ Invalid ICR run ID."); }

/* Run header (may have NULL vessel_icr_id) */
$run_stmt = $pdo->prepare("
    SELECT r.run_id, r.run_date, r.inspector,
           r.vessel_icr_id, r.icr_id,
           i.icr_number, i.title, i.reference_text,
           v.vessel_id, v.vesselName
    FROM vessel_icr_runs r
    JOIN icrs    i ON r.icr_id    = i.icr_id
    JOIN vessels v ON r.vessel_id = v.vessel_id
    WHERE r.run_id = ?
");

$run_stmt->execute([$run_id]);
$run = $run_stmt->fetch(PDO::FETCH_ASSOC);
if (!$run) { die("❌ ICR run not found."); }

/* Drill participants (only populated for drill-type ICRs) */
$drill_type = null;
$participants = [];

try {
    $dt = $pdo->prepare("SELECT drill_type FROM icrs WHERE icr_id = ? LIMIT 1");
    $dt->execute([(int)$run['icr_id']]);
    $drill_type = $dt->fetchColumn();

    if (!empty($drill_type)) {
        $pstmt = $pdo->prepare("
            SELECT
                cd.crew_user_id,
                cd.drill_type,
                cd.drill_date,
                u.fName,
                u.lName
            FROM crew_drills cd
            LEFT JOIN users u
                ON u.id = cd.crew_user_id
            WHERE cd.icr_run_id = ?
            ORDER BY u.lName, u.fName
        ");
        $pstmt->execute([$run_id]);
        $participants = $pstmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $participants = [];
}

$vessel_icr_id = (int)($run['vessel_icr_id'] ?? 0);

/* If vessel_icr_id is missing, try to derive it from status tables */
if ($vessel_icr_id <= 0) {
    // 1) from step status
    $q = $pdo->prepare("
        SELECT DISTINCT vs.vessel_icr_id
        FROM vessel_icr_step_status rs
        JOIN vessel_icr_steps vs ON vs.step_id = rs.vessel_icr_step_id
        WHERE rs.run_id = ?
        LIMIT 1
    ");
    $q->execute([$run_id]);
    $vessel_icr_id = (int)$q->fetchColumn();

    // 2) from substep status, if still not found
    if ($vessel_icr_id <= 0) {
        try {
            $q = $pdo->prepare("
                SELECT DISTINCT vs.vessel_icr_id
                FROM vessel_icr_substep_status ss
                JOIN vessel_icr_substeps vss ON vss.substep_id = ss.vessel_substep_id
                JOIN vessel_icr_steps vs     ON vs.step_id     = vss.vessel_step_id
                WHERE ss.run_id = ?
                LIMIT 1
            ");
            $q->execute([$run_id]);
            $vessel_icr_id = (int)$q->fetchColumn();
        } catch (Throwable $e) {
            // table may not exist; ignore
        }
    }
}

/* Map step_number -> icr_step_id (needed to attach step photos named step_[icr_step_id]) */
$icr_step_id_by_num = [];
if (!empty($run['icr_id'])) {
    $m = $pdo->prepare("SELECT step_id, step_number FROM icr_steps WHERE icr_id = ?");
    $m->execute([(int)$run['icr_id']]);
    while ($r2 = $m->fetch(PDO::FETCH_ASSOC)) {
        $icr_step_id_by_num[(int)$r2['step_number']] = (int)$r2['step_id'];
    }
}

/* Load steps */
$steps = [];
$steps_source = 'unknown';

if ($vessel_icr_id > 0) {
    // Preferred: all vessel steps for assignment (LEFT JOIN status for this run)
    $steps_stmt = $pdo->prepare("
        SELECT vs.step_id AS vessel_step_id,
               vs.step_number,
               vs.step_description,
               rs.status,
               rs.comment
        FROM vessel_icr_steps vs
        LEFT JOIN vessel_icr_step_status rs
               ON rs.vessel_icr_step_id = vs.step_id
              AND rs.run_id = :run_id
        WHERE vs.vessel_icr_id = :vessel_icr_id
        ORDER BY vs.step_number
    ");
    $steps_stmt->execute([':run_id' => $run_id, ':vessel_icr_id' => $vessel_icr_id]);
    $steps = $steps_stmt->fetchAll(PDO::FETCH_ASSOC);
    $steps_source = 'vessel_icr_steps';
}

if (!$steps) {
    // Fallback: only steps that have a status in this run (handles NULL vessel_icr_id)
    $steps_stmt = $pdo->prepare("
        SELECT vs.step_id AS vessel_step_id,
               vs.step_number,
               vs.step_description,
               rs.status,
               rs.comment
        FROM vessel_icr_step_status rs
        JOIN vessel_icr_steps vs ON rs.vessel_icr_step_id = vs.step_id
        WHERE rs.run_id = ?
        ORDER BY vs.step_number
    ");
    $steps_stmt->execute([$run_id]);
    $steps = $steps_stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($steps) $steps_source = 'status_join';
}

/* Substep results — BOTH master and vessel-only, grouped by vessel_step_id */
$sub_by_vessel_step = [];

/* Master-linked substeps */
$sub_master_stmt = $pdo->prepare("
    SELECT ss.vessel_icr_step_id AS vessel_step_id,
           'master' AS src,
           ss.icr_substep_id AS sub_id,
           ss.status,
           ss.comment,
           s.substep_code,
           s.description
    FROM vessel_icr_substep_status ss
    JOIN icr_substeps s ON ss.icr_substep_id = s.substep_id
    WHERE ss.run_id = ?
");
$sub_master_stmt->execute([$run_id]);
while ($r3 = $sub_master_stmt->fetch(PDO::FETCH_ASSOC)) {
    $vsid = (int)$r3['vessel_step_id'];
    $sub_by_vessel_step[$vsid][] = $r3;
}

/* Vessel-only substeps (if table exists) */
try {
    $pdo->query("SELECT 1 FROM vessel_icr_substeps LIMIT 1");
    $sub_vessel_stmt = $pdo->prepare("
        SELECT ss.vessel_icr_step_id AS vessel_step_id,
               'vessel' AS src,
               ss.vessel_substep_id AS sub_id,
               ss.status,
               ss.comment,
               vss.substep_code,
               vss.description
        FROM vessel_icr_substep_status ss
        JOIN vessel_icr_substeps vss ON ss.vessel_substep_id = vss.substep_id
        WHERE ss.run_id = ?
    ");
    $sub_vessel_stmt->execute([$run_id]);
    while ($r4 = $sub_vessel_stmt->fetch(PDO::FETCH_ASSOC)) {
        $vsid = (int)$r4['vessel_step_id'];
        $sub_by_vessel_step[$vsid][] = $r4;
    }
} catch (Throwable $e) {
    // ignore if vessel substeps table isn't present
}

/* Sort substeps by code within each step */
foreach ($sub_by_vessel_step as $vsid => &$rows) {
    usort($rows, function($a,$b){
        return strcmp(strtoupper($a['substep_code']), strtoupper($b['substep_code']));
    });
}
unset($rows);

/* ---------------- Load attachments saved for this run ----------------
   We show:
   - Step-level images: by vessel_step_id + scope='step'
   - Substep images (master): by vessel_step_id + icr_substep_id
   - Substep images (vessel): by vessel_step_id + vessel_substep_id
--------------------------------------------------------------------- */
$att_steps = [];               // [vessel_step_id]            => [paths...]
$att_sub_master = [];          // [vessel_step_id][icr_sub]   => [paths...]
$att_sub_vessel = [];          // [vessel_step_id][vessel_sub]=> [paths...]

function ra_webify($p) {
    if (!$p) return null;
    $p = str_replace('\\','/',$p);
    // If it already contains 'uploads/', keep relative from there
    $pos = strpos($p, 'uploads/');
    if ($pos !== false) return substr($p, $pos);
    // Otherwise assume it’s already relative
    return ltrim($p, '/');
}

try {
    $ra = $pdo->prepare("
        SELECT vessel_icr_step_id, icr_substep_id, vessel_substep_id, scope, file_path, mime_type
        FROM icr_run_attachments
        WHERE run_id = ?
        ORDER BY id
    ");
    $ra->execute([$run_id]);
    while ($r = $ra->fetch(PDO::FETCH_ASSOC)) {
        $vsid  = (int)($r['vessel_icr_step_id'] ?? 0);
        $msid  = isset($r['icr_substep_id']) ? (int)$r['icr_substep_id'] : null;
        $vsSid = isset($r['vessel_substep_id']) ? (int)$r['vessel_substep_id'] : null;
        $scope = strtolower((string)($r['scope'] ?? ''));
        $path  = ra_webify($r['file_path'] ?? '');
        if (!$vsid || !$path) continue;

        // images only
        $mime = strtolower((string)($r['mime_type'] ?? ''));
        if ($mime !== '' && strpos($mime, 'image/') !== 0) continue;

        if ($scope === 'step' && $msid === null && $vsSid === null) {
            $att_steps[$vsid][] = $path;
        } elseif ($msid !== null) {
            $att_sub_master[$vsid][$msid][] = $path;
        } elseif ($vsSid !== null) {
            $att_sub_vessel[$vsid][$vsSid][] = $path;
        }
    }
} catch (Throwable $e) {
    // attachments table may not exist yet; skip silently
}

/* simple renderer used below */
function render_gallery(array $paths): string {
    if (empty($paths)) return '';
    $out = '<div class="row g-2 mt-2">';
    foreach ($paths as $src) {
        $esc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
        $base = htmlspecialchars(basename($src), ENT_QUOTES, 'UTF-8');
        $out .= '
          <div class="col-6 col-lg-3">
            <div class="img-tile">
              <a href="'.$esc.'" target="_blank" rel="noopener">
                <img src="'.$esc.'" alt="attachment">
              </a>
              <div class="cap">'.$base.'</div>
            </div>
          </div>';
    }
    $out .= '</div>';
    return $out;
}

/* --------- Pull related tasks for this run (to surface regulation notes) --------- */
$tasks_by_key = []; // 'step:NUM' or 'sub:NUMCODE' => [ [title, description, id], ... ]
$tstmt = $pdo->prepare("
    SELECT task_id, title, description
    FROM tasks
    WHERE vessel_icr_run_id = ?
");
$tstmt->execute([$run_id]);
while ($t = $tstmt->fetch(PDO::FETCH_ASSOC)) {
    $title = (string)$t['title'];
    if (preg_match('/Step\s+(\d+)([A-Z])?:/i', $title, $m)) {
        $num = (int)$m[1];
        $code = strtoupper($m[2] ?? '');
        $key = $code === '' ? "step:$num" : "sub:$num$code";
        $tasks_by_key[$key][] = $t;
    }
}

/* ---------- Load photos saved by submit_icr_run (no DB cols needed) ---------- */
$photos_step = [];   // [icr_step_id] => [relpaths...]
$photos_subM = [];   // [icr_substep_id] => [relpaths...]
$photos_subV = [];   // [vessel_substep_id] => [relpaths...]

$uploadDirAbs = __DIR__ . '/uploads/icr_runs/' . $run_id;
$uploadDirRel = 'uploads/icr_runs/' . $run_id; // served by web root

if (is_dir($uploadDirAbs)) {
    $files = scandir($uploadDirAbs) ?: [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $abs = $uploadDirAbs . DIRECTORY_SEPARATOR . $f;
        if (!is_file($abs)) continue;
        // classify by filename prefix
        if (preg_match('/^step_(\d+)_/i', $f, $m)) {
            $sid = (int)$m[1];
            $photos_step[$sid][] = $uploadDirRel . '/' . $f;
        } elseif (preg_match('/^subM_(\d+)_/i', $f, $m)) {
            $mid = (int)$m[1];
            $photos_subM[$mid][] = $uploadDirRel . '/' . $f;
        } elseif (preg_match('/^subV_(\d+)_/i', $f, $m)) {
            $vid = (int)$m[1];
            $photos_subV[$vid][] = $uploadDirRel . '/' . $f;
        }
    }
}

/* UI helpers */
function badge_for_status(?string $s): string {
    $s = strtolower(trim((string)$s));
    return match($s) {
        'pass','p' => 'success',
        'fail','f' => 'danger',
        'na','n/a' => 'secondary',
        default    => 'light'
    };
}

/* Tiny helper to extract a "Supporting regulation: ..." line from task description */
function extract_reg_line(?string $desc): ?string {
    if (!$desc) return null;
    if (preg_match('/^Supporting regulation:\s*(.+)$/im', $desc, $m)) {
        return trim($m[1]);
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ICR Run • <?= htmlspecialchars($run['icr_number']) ?> – <?= htmlspecialchars($run['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .view-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .view-card {
            border: 0;
            border-radius: 1rem;
        }

        .summary-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .4rem .8rem;
            font-size: .85rem;
            font-weight: 600;
            background: #fff;
            border: 1px solid #dee2e6;
        }

        .step-card {
            border: 0;
            border-radius: 1rem;
            background: #fff;
            overflow: hidden;
        }

        .step-pass {
            border-left: 5px solid #198754;
        }

        .step-fail {
            border-left: 5px solid #dc3545;
        }

        .step-na {
            border-left: 5px solid #6c757d;
        }

        .step-number-badge {
            min-width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #e7f1ff;
            color: #0b5ed7;
            font-weight: 700;
            font-family: ui-monospace, Menlo, Consolas, monospace;
        }

        .substep-card {
            background: #f8f9fa;
            border-left: 3px solid #dee2e6;
            border-radius: .75rem;
            padding: 1rem;
        }

        .badge-vessel {
            background: #e7f1ff;
            color: #0b5ed7;
            border: 1px solid #cfe2ff;
        }

        .thumbs img {
            max-height: 80px;
            margin-right: 6px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .img-tile {
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
        }

        .img-tile img {
            width: 100%;
            height: 160px;
            object-fit: contain;
            background: #f8f9fa;
        }

        .img-tile .cap {
            font-size: .85rem;
            padding: .4rem;
            border-top: 1px solid #eee;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .section-title {
            font-size: .9rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: .75rem;
        }

        .run-meta {
            color: #6b7280;
            margin: 0;
        }

        .sticky-view-actions {
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
$title = 'ICR Run';
$back_link = 'vessel_icrs.php?vessel_id=' . (int)$run['vessel_id'];
include __DIR__ . '/partials/top_nav.php';

/* quick summary counts from the existing $steps structure */
$passCount = 0;
$failCount = 0;
$naCount = 0;

foreach ($steps as $step) {
    $s = strtolower(trim((string)($step['status'] ?? '')));
    if (in_array($s, ['pass', 'p'], true)) $passCount++;
    elseif (in_array($s, ['fail', 'f'], true)) $failCount++;
    elseif (in_array($s, ['na', 'n/a'], true)) $naCount++;

    $subs = $sub_by_vessel_step[(int)$step['vessel_step_id']] ?? [];
    foreach ($subs as $sub) {
        $ss = strtolower(trim((string)($sub['status'] ?? '')));
        if (in_array($ss, ['pass', 'p'], true)) $passCount++;
        elseif (in_array($ss, ['fail', 'f'], true)) $failCount++;
        elseif (in_array($ss, ['na', 'n/a'], true)) $naCount++;
    }
}
?>

<div class="view-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="card view-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">
                                <?= htmlspecialchars($run['icr_number']) ?> – <?= htmlspecialchars($run['title']) ?>
                            </h1>
                            <p class="run-meta">
                                <?= htmlspecialchars($run['vesselName']) ?>
                                · <?= htmlspecialchars($run['run_date']) ?>
                                · Inspector: <?= htmlspecialchars($run['inspector']) ?>
                            </p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="print_icr_run.php?run_id=<?= urlencode((int)$run['run_id']) ?>"
                               target="_blank" rel="noopener"
                               class="btn btn-outline-dark">
                                Print
                            </a>
                            <a href="vessel_icrs.php?vessel_id=<?= (int)$run['vessel_id'] ?>"
                               class="btn btn-outline-secondary">
                                Back to ICRs
                            </a>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="summary-chip">Pass: <?= $passCount ?></span>
                        <span class="summary-chip">Fail: <?= $failCount ?></span>
                        <span class="summary-chip">N/A: <?= $naCount ?></span>
                        <?php if ($debug): ?>
                            <span class="summary-chip">Source: <?= htmlspecialchars($steps_source) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($run['reference_text'])): ?>
                <div class="alert alert-info">
                    <strong>Reference</strong><br>
                    <?= nl2br(htmlspecialchars($run['reference_text'])) ?>
                </div>
            <?php endif; ?>

                        <?php if (!empty($drill_type)): ?>
                <div class="card view-card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="section-title mb-2">Drill Participation</div>

                        <div class="mb-2">
                            <strong>Drill Type:</strong>
                            <?= htmlspecialchars((string)$drill_type, ENT_QUOTES, 'UTF-8') ?>
                        </div>

                        <?php if (!empty($participants)): ?>
                            <div class="small text-muted mb-2">
                                <?= count($participants) ?> participant<?= count($participants) === 1 ? '' : 's' ?> recorded
                            </div>

                            <div class="row g-2">
                                <?php foreach ($participants as $person): ?>
                                    <div class="col-12 col-md-6 col-lg-4">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fw-semibold">
                                                <?= htmlspecialchars(trim(($person['fName'] ?? '') . ' ' . ($person['lName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                            <div class="small text-muted">
                                                Drill date: <?= htmlspecialchars((string)($person['drill_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">
                                No participants were recorded for this drill run.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($failCount > 0): ?>
                <div class="card view-card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="section-title mb-2">Corrective Actions</div>
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$run['vessel_id'] ?>&icr_run_id=<?= (int)$run['run_id'] ?>#tasks"
                           class="btn btn-warning">
                            View Corrective Actions
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="section-title">Inspection Results</div>

            <?php foreach ($steps as $step): ?>
                <?php
                    $badge = badge_for_status($step['status'] ?? null);
                    $vessel_step_id = (int)$step['vessel_step_id'];
                    $subs = $sub_by_vessel_step[$vessel_step_id] ?? [];

                    $num = (int)$step['step_number'];
                    $icr_step_id = $icr_step_id_by_num[$num] ?? 0;

                    $relatedTasks = $tasks_by_key["step:$num"] ?? [];
                    $regLine = null;
                    foreach ($relatedTasks as $t) {
                        $regLine = extract_reg_line($t['description'] ?? '');
                        if ($regLine) break;
                    }

                    $stepStatus = strtolower(trim((string)($step['status'] ?? '')));
                    $stepClass = 'step-na';
                    if (in_array($stepStatus, ['pass', 'p'], true)) {
                        $stepClass = 'step-pass';
                    } elseif (in_array($stepStatus, ['fail', 'f'], true)) {
                        $stepClass = 'step-fail';
                    }
                ?>

                <div class="card step-card shadow-sm mb-3 <?= $stepClass ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                            <div class="d-flex gap-3">
                                <span class="step-number-badge"><?= $num ?></span>
                                <div>
                                    <div class="fw-semibold"><?= nl2br(htmlspecialchars($step['step_description'])) ?></div>
                                </div>
                            </div>

                            <div>
                                <?php if (!empty($step['status'])): ?>
                                    <span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars((string)$step['status'])) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border">—</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (($step['comment'] ?? '') !== ''): ?>
                            <div class="mb-2 text-muted">
                                <?= nl2br(htmlspecialchars($step['comment'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($regLine): ?>
                            <div class="mb-2 small text-muted">
                                <strong>Supporting regulation:</strong> <?= htmlspecialchars($regLine) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($icr_step_id && !empty($photos_step[$icr_step_id])): ?>
                            <div class="mt-2 thumbs">
                                <?php foreach ($photos_step[$icr_step_id] as $p): ?>
                                    <a href="<?= htmlspecialchars($p) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($p) ?>" alt="Step photo">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $stepPhotos = $att_steps[$vessel_step_id] ?? [];
                        if (!empty($stepPhotos)) {
                            echo '<div class="mt-3"><strong class="small">Step photo(s)</strong>';
                            echo render_gallery($stepPhotos);
                            echo '</div>';
                        }
                        ?>

                        <?php if (!empty($relatedTasks)): ?>
                            <div class="mt-3 small">
                                <em>Related corrective action(s):</em>

                                <div class="mt-2 d-grid gap-2">
                                    <?php foreach ($relatedTasks as $idx => $t): ?>
                                        <?php
                                            $taskCollapseId = 'task_step_' . $vessel_step_id . '_' . $idx;
                                        ?>
                                        <div class="border rounded p-2 bg-light">
                                            <div class="fw-semibold mb-1"><?= htmlspecialchars($t['title']) ?></div>

                                            <?php
                                                $quickReg = extract_reg_line($t['description'] ?? '');
                                                if ($quickReg):
                                            ?>
                                                <div class="small text-muted mb-2">
                                                    <strong>Supporting regulation:</strong> <?= htmlspecialchars($quickReg) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($t['description'])): ?>
                                                <button class="btn btn-sm btn-outline-secondary"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#<?= htmlspecialchars($taskCollapseId) ?>"
                                                        aria-expanded="false"
                                                        aria-controls="<?= htmlspecialchars($taskCollapseId) ?>">
                                                    Show Corrective Action Details
                                                </button>

                                                <div class="collapse mt-2" id="<?= htmlspecialchars($taskCollapseId) ?>">
                                                    <div class="border rounded p-2 bg-white" style="white-space: pre-wrap;">
                                                        <?= htmlspecialchars($t['description']) ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($subs): ?>
                            <div class="mt-4">
                                <div class="section-title">Sub-steps</div>

                                <?php foreach ($subs as $sub): ?>
                                    <?php
                                        $code   = strtoupper((string)$sub['substep_code']);
                                        $sbadge = badge_for_status($sub['status'] ?? null);

                                        $key = "sub:$num$code";
                                        $relatedSubTasks = $tasks_by_key[$key] ?? [];
                                        $regSubLine = null;
                                        foreach ($relatedSubTasks as $t2) {
                                            $regSubLine = extract_reg_line($t2['description'] ?? '');
                                            if ($regSubLine) break;
                                        }

                                        $subPhotos = [];
                                        if (($sub['src'] ?? '') === 'master') {
                                            $subPhotos = $photos_subM[(int)$sub['sub_id']] ?? [];
                                        } else {
                                            $subPhotos = $photos_subV[(int)$sub['sub_id']] ?? [];
                                        }
                                    ?>

                                    <div class="substep-card mb-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="fw-semibold">
                                                    <span class="mono"><?= $num . htmlspecialchars($code) ?></span>
                                                    <?= nl2br(htmlspecialchars($sub['description'])) ?>
                                                    <?php if (($sub['src'] ?? '') === 'vessel'): ?>
                                                        <span class="badge badge-vessel ms-2">vessel-only</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div>
                                                <?php if (!empty($sub['status'])): ?>
                                                    <span class="badge bg-<?= $sbadge ?>"><?= strtoupper(htmlspecialchars((string)$sub['status'])) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark border">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if (($sub['comment'] ?? '') !== ''): ?>
                                            <div class="mt-2 text-muted">
                                                <?= nl2br(htmlspecialchars($sub['comment'])) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($regSubLine): ?>
                                            <div class="mt-2 small text-muted">
                                                <strong>Supporting regulation:</strong> <?= htmlspecialchars($regSubLine) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($subPhotos)): ?>
                                            <div class="mt-2 thumbs">
                                                <?php foreach ($subPhotos as $p2): ?>
                                                    <a href="<?= htmlspecialchars($p2) ?>" target="_blank" rel="noopener">
                                                        <img src="<?= htmlspecialchars($p2) ?>" alt="Sub-step photo">
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($relatedSubTasks)): ?>
                                            <div class="mt-2 small">
                                                <em>Related corrective action(s):</em>

                                                <div class="mt-2 d-grid gap-2">
                                                    <?php foreach ($relatedSubTasks as $idx => $t3): ?>
                                                        <?php
                                                            $subTaskCollapseId = 'task_sub_' . $vessel_step_id . '_' . $sub['src'] . '_' . $sub['sub_id'] . '_' . $idx;
                                                        ?>
                                                        <div class="border rounded p-2 bg-light">
                                                            <div class="fw-semibold mb-1"><?= htmlspecialchars($t3['title']) ?></div>

                                                            <?php
                                                                $quickSubReg = extract_reg_line($t3['description'] ?? '');
                                                                if ($quickSubReg):
                                                            ?>
                                                                <div class="small text-muted mb-2">
                                                                    <strong>Supporting regulation:</strong> <?= htmlspecialchars($quickSubReg) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($t3['description'])): ?>
                                                                <button class="btn btn-sm btn-outline-secondary"
                                                                        type="button"
                                                                        data-bs-toggle="collapse"
                                                                        data-bs-target="#<?= htmlspecialchars($subTaskCollapseId) ?>"
                                                                        aria-expanded="false"
                                                                        aria-controls="<?= htmlspecialchars($subTaskCollapseId) ?>">
                                                                    Show Corrective Action Details
                                                                </button>

                                                                <div class="collapse mt-2" id="<?= htmlspecialchars($subTaskCollapseId) ?>">
                                                                    <div class="border rounded p-2 bg-white" style="white-space: pre-wrap;">
                                                                        <?= htmlspecialchars($t3['description']) ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        $isVessel = isset($sub['src']) && $sub['src'] === 'vessel';

                                        if ($isVessel) {
                                            $vessel_substep_id = null;
                                            try {
                                                $codeLookup = strtoupper((string)$sub['substep_code']);
                                                $vs = $pdo->prepare("
                                                    SELECT substep_id
                                                    FROM vessel_icr_substeps
                                                    WHERE vessel_step_id = ? AND UPPER(substep_code) = ?
                                                    LIMIT 1
                                                ");
                                                $vs->execute([$vessel_step_id, $codeLookup]);
                                                $vessel_substep_id = (int)$vs->fetchColumn() ?: null;
                                            } catch (Throwable $e) {
                                                $vessel_substep_id = null;
                                            }

                                            $photos = ($vessel_substep_id !== null)
                                                ? ($att_sub_vessel[$vessel_step_id][$vessel_substep_id] ?? [])
                                                : [];

                                            if (!empty($photos)) {
                                                echo '<div class="mt-3"><strong class="small">Sub-step photo(s)</strong>';
                                                echo render_gallery($photos);
                                                echo '</div>';
                                            }
                                        } else {
                                            $icr_substep_id = null;
                                            try {
                                                $codeLookup = strtoupper((string)$sub['substep_code']);
                                                $get_icr_step = $pdo->prepare("SELECT step_id FROM icr_steps WHERE icr_id = ? AND step_number = ? LIMIT 1");
                                                $get_icr_step->execute([(int)$run['icr_id'], (int)$step['step_number']]);
                                                $resolved_icr_step_id = (int)$get_icr_step->fetchColumn();
                                                if ($resolved_icr_step_id) {
                                                    $get_icr_sub = $pdo->prepare("SELECT substep_id FROM icr_substeps WHERE step_id = ? AND UPPER(substep_code) = ? LIMIT 1");
                                                    $get_icr_sub->execute([$resolved_icr_step_id, $codeLookup]);
                                                    $icr_substep_id = (int)$get_icr_sub->fetchColumn() ?: null;
                                                }
                                            } catch (Throwable $e) {
                                                $icr_substep_id = null;
                                            }

                                            $photos = ($icr_substep_id !== null)
                                                ? ($att_sub_master[$vessel_step_id][$icr_substep_id] ?? [])
                                                : [];

                                            if (!empty($photos)) {
                                                echo '<div class="mt-3"><strong class="small">Sub-step photo(s)</strong>';
                                                echo render_gallery($photos);
                                                echo '</div>';
                                            }
                                        }
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$steps): ?>
                <div class="card view-card shadow-sm">
                    <div class="card-body text-center text-muted">
                        No steps found for this run.
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="sticky-view-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <?php if ($failCount > 0): ?>
                <a href="vessel_dashboard.php?vessel_id=<?= (int)$run['vessel_id'] ?>&icr_run_id=<?= (int)$run['run_id'] ?>#tasks"
                   class="btn btn-warning">
                    View Corrective Actions
                </a>
            <?php endif; ?>

            <a href="print_icr_run.php?run_id=<?= urlencode((int)$run['run_id']) ?>"
               target="_blank" rel="noopener"
               class="btn btn-outline-dark">
                Print
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>