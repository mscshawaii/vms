<?php
require 'db_connect.php';
require 'session_check.php';

$run_id = (int)($_GET['run_id'] ?? 0);
if ($run_id <= 0) { http_response_code(400); echo "Missing or invalid run_id."; exit; }

/* -------- Run header -------- */
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
if (!$run) { http_response_code(404); echo "ICR run not found."; exit; }

$vessel_icr_id = (int)($run['vessel_icr_id'] ?? 0);

/* -------- Steps (prefer vessel set; fallback to “status_join”) -------- */
$steps = [];
if ($vessel_icr_id > 0) {
    $q = $pdo->prepare("
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
    $q->execute([':run_id' => $run_id, ':vessel_icr_id' => $vessel_icr_id]);
    $steps = $q->fetchAll(PDO::FETCH_ASSOC);
}
if (!$steps) {
    $q = $pdo->prepare("
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
    $q->execute([$run_id]);
    $steps = $q->fetchAll(PDO::FETCH_ASSOC);
}

/* -------- Substeps (master-linked + vessel-only) grouped by vessel_step_id -------- */
$sub_by_vessel_step = [];

$sm = $pdo->prepare("
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
$sm->execute([$run_id]);
while ($r = $sm->fetch(PDO::FETCH_ASSOC)) {
    $sub_by_vessel_step[(int)$r['vessel_step_id']][] = $r;
}

try {
    $pdo->query("SELECT 1 FROM vessel_icr_substeps LIMIT 1");
    $sv = $pdo->prepare("
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
    $sv->execute([$run_id]);
    while ($r = $sv->fetch(PDO::FETCH_ASSOC)) {
        $sub_by_vessel_step[(int)$r['vessel_step_id']][] = $r;
    }
} catch (Throwable $e) {
    // ignore if table not present
}

/* -------- Preload ALL attachments for this run (DB first, then filesystem fallback) -------- */
$step_photos = [];            // [vessel_step_id] => [paths...]
$sub_photos_master = [];      // [vessel_step_id][icr_substep_id] => [paths...]
$sub_photos_vessel = [];      // [vessel_step_id][vessel_substep_id] => [paths...]

// Build a quick helper to check columns
function table_has_col(PDO $pdo, string $table, string $col): bool {
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$table, $col]);
        return (int)$q->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

$has_media_id       = table_has_col($pdo, 'icr_run_attachments', 'media_id');
$has_file_path_col  = table_has_col($pdo, 'icr_run_attachments', 'file_path');
$has_icr_sub_col    = table_has_col($pdo, 'icr_run_attachments', 'icr_substep_id');   // optional in your schema
$found_from_db      = false;

try {
    // Build SELECT with graceful fallbacks:
    // Prefer ma.file_path via media_id; else ira.file_path if present.
    $select = "SELECT ira.vessel_icr_step_id AS vessel_step_id";
    if ($has_icr_sub_col) $select .= ", ira.icr_substep_id";
    $select .= ", ira.vessel_substep_id";

    if ($has_media_id) {
        $select .= ", COALESCE(ma.file_path" . ($has_file_path_col ? ", ira.file_path" : "") . ") AS file_path";
    } else {
        $select .= ($has_file_path_col ? ", ira.file_path AS file_path" : ", NULL AS file_path");
    }

    $sql  = $select . " FROM icr_run_attachments ira";
    if ($has_media_id) $sql .= " LEFT JOIN media_attachments ma ON ma.id = ira.media_id";
    $sql .= " WHERE ira.run_id = ? ORDER BY ira.id";

    $st = $pdo->prepare($sql);
    $st->execute([$run_id]);

    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $vsid = (int)($row['vessel_step_id'] ?? 0);
        $path = (string)($row['file_path'] ?? '');
        if ($vsid <= 0 || $path === '') continue;

        $icr_sub = $has_icr_sub_col ? (int)($row['icr_substep_id'] ?? 0) : 0;
        $vsl_sub = isset($row['vessel_substep_id']) ? (int)$row['vessel_substep_id'] : 0;

        if ($icr_sub > 0) {
            $sub_photos_master[$vsid][$icr_sub][] = $path;
        } elseif ($vsl_sub > 0) {
            $sub_photos_vessel[$vsid][$vsl_sub][] = $path;
        } else {
            $step_photos[$vsid][] = $path;
        }
        $found_from_db = true;
    }
} catch (Throwable $e) {
    // ignore; we’ll try filesystem fallback
}

if (!$found_from_db) {
    /* ----- Filesystem fallback: scan /uploads/icr_runs/{run_id}/ and map by filename prefixes ----- */
    $dirRel = "uploads/icr_runs/" . $run_id;
    $dirAbs = __DIR__ . '/' . $dirRel;

    if (is_dir($dirAbs)) {
        // Build step_number -> vessel_step_id
        if (!isset($num_to_vsid)) {
            $num_to_vsid = [];
            foreach ($steps as $st) { $num_to_vsid[(int)$st['step_number']] = (int)$st['vessel_step_id']; }
        }

        // icr_step_id -> step_number
        $icr_id = (int)$run['icr_id'];
        $icr_step_to_num = [];
        $stq = $pdo->prepare("SELECT step_id, step_number FROM icr_steps WHERE icr_id = ?");
        $stq->execute([$icr_id]);
        while ($r = $stq->fetch(PDO::FETCH_ASSOC)) {
            $icr_step_to_num[(int)$r['step_id']] = (int)$r['step_number'];
        }

        // icr_substep_id -> step_number (via parent step)
        $icr_sub_to_num = [];
        if (!empty($icr_step_to_num)) {
            $step_ids = array_keys($icr_step_to_num);
            $in = implode(',', array_fill(0, count($step_ids), '?'));
            $ssq = $pdo->prepare("SELECT substep_id, step_id FROM icr_substeps WHERE step_id IN ($in)");
            $ssq->execute($step_ids);
            while ($r = $ssq->fetch(PDO::FETCH_ASSOC)) {
                $icr_sub_to_num[(int)$r['substep_id']] = $icr_step_to_num[(int)$r['step_id']] ?? null;
            }
        }

        // vessel_substep_id -> vessel_step_id
        $vessel_sub_to_vsid = [];
        try {
            $vsid_list = array_values($num_to_vsid);
            if (!empty($vsid_list)) {
                $vsq = $pdo->prepare("SELECT substep_id, vessel_step_id FROM vessel_icr_substeps WHERE vessel_step_id IN (" .
                                    implode(',', array_map('intval', $vsid_list)) . ")");
                $vsq->execute();
                while ($r = $vsq->fetch(PDO::FETCH_ASSOC)) {
                    $vessel_sub_to_vsid[(int)$r['substep_id']] = (int)$r['vessel_step_id'];
                }
            }
        } catch (Throwable $e) { /* table may not exist; skip */ }

        // scan dir
        $files = @scandir($dirAbs) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp','bmp'], true)) continue;

            $relPath = $dirRel . '/' . $f;

            // step_123_*.jpg  => icr_step_id=123
            if (preg_match('/^step_(\d+)_/i', $f, $m)) {
                $icr_step_id = (int)$m[1];
                $stepNum = $icr_step_to_num[$icr_step_id] ?? null;
                if ($stepNum !== null) {
                    $vsid = $num_to_vsid[$stepNum] ?? null;
                    if ($vsid) $step_photos[$vsid][] = $relPath;
                }
                continue;
            }

            // subM_456_*.jpg  => icr_substep_id=456
            if (preg_match('/^subM_(\d+)_/i', $f, $m)) {
                $icr_sub_id = (int)$m[1];
                $stepNum = $icr_sub_to_num[$icr_sub_id] ?? null;
                if ($stepNum !== null) {
                    $vsid = $num_to_vsid[$stepNum] ?? null;
                    if ($vsid) $sub_photos_master[$vsid][$icr_sub_id][] = $relPath;
                }
                continue;
            }

            // subV_789_*.jpg  => vessel_substep_id=789
            if (preg_match('/^subV_(\d+)_/i', $f, $m)) {
                $v_sub_id = (int)$m[1];
                $vsid = $vessel_sub_to_vsid[$v_sub_id] ?? null;
                if ($vsid) $sub_photos_vessel[$vsid][$v_sub_id][] = $relPath;
                continue;
            }
        }
    }
}

/* Optional debug: append &debug=1 to the URL */
$debug_mode = !empty($_GET['debug']);
if ($debug_mode) {
    echo '<div class="no-print alert alert-info" style="white-space:pre-wrap; font-family:ui-monospace,monospace">';
    echo "Attachments summary for run_id={$run_id}\n";
    echo "Step photos keys: " . json_encode(array_keys($step_photos)) . "\n";
    $m_counts = []; foreach ($sub_photos_master as $vsid=>$arr){$c=0; foreach($arr as $k=>$v){$c+=count($v);} $m_counts[$vsid]=$c;}
    echo "Master sub-step photo counts: " . json_encode($m_counts) . "\n";
    $v_counts = []; foreach ($sub_photos_vessel as $vsid=>$arr){$c=0; foreach($arr as $k=>$v){$c+=count($v);} $v_counts[$vsid]=$c;}
    echo "Vessel sub-step photo counts: " . json_encode($v_counts) . "\n";
    echo "</div>";
}


/* -------- Pull "Supporting regulation" lines from tasks for this run -------- */
function extract_reg_line(?string $desc): ?string {
    if (!$desc) return null;
    $lines = preg_split('/\R/', $desc);
    foreach ($lines as $ln) {
        if (stripos($ln, 'Supporting regulation:') !== false) {
            return trim($ln);
        }
    }
    return null;
}

$num_to_vsid = [];             // map step_number -> vessel_step_id
foreach ($steps as $st) { $num_to_vsid[(int)$st['step_number']] = (int)$st['vessel_step_id']; }

$reg_by_step = [];             // [vessel_step_id] => "Supporting regulation: …"
$reg_by_sub  = [];             // [vessel_step_id][SUBCODE] => "Supporting regulation: …"

try {
    $tx = $pdo->prepare("SELECT title, description FROM tasks WHERE vessel_icr_run_id = ?");
    $tx->execute([$run_id]);
    while ($t = $tx->fetch(PDO::FETCH_ASSOC)) {
        $title = (string)($t['title'] ?? '');
        if ($title === '') continue;

        if (preg_match('/Step\s+(\d+)\s*([A-Za-z])?(?=\b|:)/i', $title, $m)) {
            $stepNum = (int)$m[1];
            $subCode = !empty($m[2]) ? strtoupper($m[2]) : null;
            $vsid    = $num_to_vsid[$stepNum] ?? null;
            $regLine = extract_reg_line($t['description'] ?? '');

            if ($vsid && $regLine) {
                if ($subCode) {
                    if (!isset($reg_by_sub[$vsid][$subCode])) {
                        $reg_by_sub[$vsid][$subCode] = $regLine;
                    }
                } else {
                    if (!isset($reg_by_step[$vsid])) {
                        $reg_by_step[$vsid] = $regLine;
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    // fail soft
}

/* -------- Helpers -------- */
function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Match the viewer’s path normalizer to ensure images load both from
   relative 'uploads/...' and absolute local paths like 'C:\laragon\www\...'. */
function webify_path($path) {
    if (!$path) return null;
    $p = str_replace('\\', '/', $path);
    $root = str_replace('\\', '/', realpath(__DIR__)); // app root

    // Absolute under app root → strip the root
    $isAbsWin = (bool)preg_match('#^[a-zA-Z]:/#', $p);
    if (($isAbsWin || str_starts_with($p, '/')) && str_starts_with($p, $root)) {
        $rel = ltrim(substr($p, strlen($root)), '/');
        return $rel;
    }

    // If it contains 'uploads/', clip from there
    $pos = strpos($p, 'uploads/');
    if ($pos !== false) return substr($p, $pos);

    // Otherwise treat as already relative
    return ltrim($p, '/');
}

function label_status($s){
    $s = strtolower(trim((string)$s));
    return match ($s) {
        'p','pass' => 'PASS',
        'f','fail' => 'FAIL',
        'na','n/a' => 'N/A',
        default    => '—',
    };
}

/* Optional logo autodetect */
$logo_paths = [
    __DIR__ . '/uploads/logos/68093a02c24b4_MSCS_Logo_Color.png',
    __DIR__ . '/uploads/logos/mscs_logo.png',
    __DIR__ . '/assets/img/logo.png',
];
$logo_file = null;
foreach ($logo_paths as $p) { if (is_file($p)) { $logo_file = $p; break; } }
$logo_src = $logo_file ? str_replace(__DIR__.'/', '', $logo_file) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print ICR Run • <?= safe($run['icr_number']) ?> – <?= safe($run['title']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
  --muted:#6c757d; --line:#dee2e6;
  --pass-bg:#e6f4ea; --pass-fg:#0f5132;
  --fail-bg:#f8d7da; --fail-fg:#842029;
  --na-bg:#e2e3e5;   --na-fg:#41464b;
}
body{ color:#111; padding:24px; }
.no-print{ margin-bottom:12px; }
.header-box{ padding-bottom:10px; border-bottom:1px solid var(--line); margin-bottom:10px; }
.summary{ margin-top:8px; }
.summary table{ width:100%; max-width:520px; }
.summary th{ width:28%; color:var(--muted); font-weight:600; padding-right:8px; }
.summary td{ white-space:nowrap; }
h1.title{ font-size:18px; margin:0; text-align:center; }
h2.sub{ font-size:13px; font-weight:400; margin:0; text-align:center; color:#333; }
.results h3{ font-size:14px; margin:14px 0 8px; }

table.run { width:100%; border-collapse:collapse; }
.run th, .run td { border:1px solid var(--line); padding:6px 8px; vertical-align:top; }
.run th{ background:#f0f3f5; }
.col-num{ width:6%; font-family: ui-monospace, Menlo, Consolas, monospace; font-weight:600; }
.col-desc{ width:58%; }
.col-stat{ width:10%; text-align:center; }
.col-comm{ width:26%; }

tr.step.zebra td.col-desc{ background:#fcfdff; }
tr.sub td.col-desc{ background:#f8f9fb; }

.badge-stat{
  display:inline-block; min-width:40px; padding:2px 6px; font-weight:700; border-radius:4px;
}
.stat-pass{ background:var(--pass-bg); color:var(--pass-fg); }
.stat-fail{ background:var(--fail-bg); color:var(--fail-fg); }
.stat-na  { background:var(--na-bg);   color:var(--na-fg); }

.badge-vessel{ background:#e7f1ff; border:1px solid #cfe2ff; color:#0b5ed7; border-radius:6px; padding:2px 6px; font-size:12px; }

/* attachment thumbnails inside comment cells */
.attach-grid { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.attach-grid img { width:84px; height:84px; object-fit:cover; border:1px solid #ddd; border-radius:4px; background:#fafafa; }

.small-muted{ color:#6c757d; font-size: .9rem; margin-top:4px; }

@media print{
  .no-print{ display:none !important; }
  body{ padding:0; }
  @page { margin: 12mm; }
  .header-box{ border:none; }
}
</style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="no-print d-flex gap-2">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  <button class="btn btn-outline-dark" onclick="window.close()">✖ Close</button>
</div>

<!-- Header -->
<div class="header-box">
  <div class="d-flex align-items-center gap-3">
    <?php if ($logo_src): ?>
      <img src="<?= safe($logo_src) ?>" alt="Logo" style="height:52px">
    <?php endif; ?>
    <div class="flex-grow-1">
      <h1 class="title">Inspection Report (Run Results)</h1>
      <h2 class="sub"><?= safe($run['icr_number']) ?> – <?= safe($run['title']) ?></h2>
    </div>
  </div>

  <!-- Summary block BELOW header/logo -->
  <div class="summary mt-2">
    <table>
      <tr><th>Vessel</th><td><?= safe($run['vesselName']) ?></td></tr>
      <tr><th>Date</th>  <td><?= safe($run['run_date']) ?></td></tr>
      <tr><th>Inspector</th><td><?= safe($run['inspector']) !== '' ? safe($run['inspector']) : '—' ?></td></tr>
      <tr><th>ICR</th>   <td><?= safe($run['icr_number']) ?> – <?= safe($run['title']) ?></td></tr>
    </table>
  </div>
</div>

<!-- Reference -->
<?php if (!empty($run['reference_text'])): ?>
  <div class="mb-3">
    <strong>Authorization / Reference</strong><br>
    <?= nl2br(safe($run['reference_text'])) ?>
  </div>
<?php endif; ?>

<!-- Results -->
<div class="results">
  <h3>Results</h3>
  <table class="run">
    <thead>
      <tr>
        <th class="col-num">Step # </th>
        <th class="col-desc">Step / Sub-step Description</th>
        <th class="col-stat">Status</th>
        <th class="col-comm">Comments</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $row = 0;
      foreach ($steps as $step):
        $row++;
        $zebra = ($row % 2 === 0) ? ' zebra' : '';
        $num   = (int)$step['step_number'];
        $stat  = label_status($step['status'] ?? '');
        $badgeClass = ($stat === 'PASS') ? 'stat-pass' : (($stat === 'FAIL') ? 'stat-fail' : (($stat === 'N/A') ? 'stat-na' : ''));
        $comm = trim((string)($step['comment'] ?? ''));
        $vsid = (int)$step['vessel_step_id'];

        $photos_step = $step_photos[$vsid] ?? [];
        $reg_step    = $reg_by_step[$vsid] ?? null;
      ?>
        <tr class="step<?= $zebra ?>">
          <td class="col-num"><?= $num ?></td>
          <td class="col-desc"><?= nl2br(safe($step['step_description'])) ?></td>
          <td class="col-stat">
            <?php if ($stat !== '—'): ?>
              <span class="badge-stat <?= $badgeClass ?>"><?= $stat ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="col-comm">
            <?= $comm !== '' ? nl2br(safe($comm)) : '—' ?>

            <?php if (!empty($photos_step)): ?>
              <div class="attach-grid">
                <?php foreach ($photos_step as $p): $src = webify_path($p); if (!$src) continue; ?>
                  <img src="<?= safe($src) ?>" alt="attachment">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($reg_step): ?>
              <div class="small-muted"><?= safe($reg_step) ?></div>
            <?php endif; ?>
          </td>
        </tr>

        <?php
          $subs = $sub_by_vessel_step[$vsid] ?? [];
          if ($subs):
            foreach ($subs as $sub):
              $scode = strtoupper((string)$sub['substep_code']);
              $sstat = label_status($sub['status'] ?? '');
              $sBadge = ($sstat === 'PASS') ? 'stat-pass' : (($sstat === 'FAIL') ? 'stat-fail' : (($sstat === 'N/A') ? 'stat-na' : ''));
              $scomm = trim((string)($sub['comment'] ?? ''));
              $sub_id = (int)$sub['sub_id'];
              $srcTag = (string)($sub['src'] ?? 'master');

              // Sub-step photos
              if ($srcTag === 'vessel') {
                  $photos_sub = $sub_photos_vessel[$vsid][$sub_id] ?? [];
              } else {
                  $photos_sub = $sub_photos_master[$vsid][$sub_id] ?? [];
              }

              $reg_sub = $reg_by_sub[$vsid][$scode] ?? null;
        ?>
          <tr class="sub">
            <td class="col-num"><?= $num . safe($scode) ?></td>
            <td class="col-desc">
              <?= nl2br(safe($sub['description'])) ?>
              <?php if ($srcTag === 'vessel'): ?>
                <span class="badge-vessel ms-2">vessel-only</span>
              <?php endif; ?>
            </td>
            <td class="col-stat">
              <?php if ($sstat !== '—'): ?>
                <span class="badge-stat <?= $sBadge ?>"><?= $sstat ?></span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="col-comm">
              <?= $scomm !== '' ? nl2br(safe($scomm)) : '—' ?>

              <?php if (!empty($photos_sub)): ?>
                <div class="attach-grid">
                  <?php foreach ($photos_sub as $p): $src = webify_path($p); if (!$src) continue; ?>
                    <img src="<?= safe($src) ?>" alt="attachment">
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if ($reg_sub): ?>
                <div class="small-muted"><?= safe($reg_sub) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>

      <?php endforeach; ?>

      <?php if (!$steps): ?>
        <tr><td colspan="4" class="text-center text-muted">No results recorded for this run.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
