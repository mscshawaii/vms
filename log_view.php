<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/checklist_functions.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

// ---------- Helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$colExists = function(string $table, string $col) use ($pdo): bool {
  $q = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  $q->execute([$col]);
  return (bool)$q->fetch();
};
$norm = function(string $s): string { return strtolower(preg_replace('/[^a-z0-9]/', '', $s)); };

function guess_is_image(string $path, ?string $mime): bool {
  if ($mime && stripos($mime, 'image/') === 0) return true;
  return (bool)preg_match('/\.(jpe?g|png|gif|webp|bmp|tiff?)$/i', $path);
}
function guess_is_video(string $path, ?string $mime): bool {
  if ($mime && stripos($mime, 'video/') === 0) return true;
  return (bool)preg_match('/\.(mp4|m4v|mov|webm|ogv|ogg)$/i', $path);
}

// ---------- Params ----------
$log_id = intval($_GET['log_id'] ?? ($_GET['id'] ?? 0));
if (!$log_id) { http_response_code(400); exit("Missing log_id."); }

// ---------- Load log ----------
$stmt = $pdo->prepare("
  SELECT l.*, v.vesselName
  FROM vessel_logs l
  JOIN vessels v ON v.vessel_id = l.vessel_id
  WHERE l.log_id = ?
");
$stmt->execute([$log_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$log) { http_response_code(404); exit("Log not found."); }

// ---------- Crew (schema-tolerant) ----------
$usersColsStmt = $pdo->query("SHOW COLUMNS FROM `users`");
$usersCols = $usersColsStmt ? $usersColsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$usersPk = null;
foreach (['id','user_id','uid','userid'] as $cand) {
  foreach ($usersCols as $raw) { if ($norm($raw) === $norm($cand)) { $usersPk = $raw; break 2; } }
}
if (!$usersPk) { $usersPk = $usersCols[0] ?? 'id'; } // fallback

$hasRole = $colExists('users', 'role');

$vlcColsStmt = $pdo->query("SHOW COLUMNS FROM `vessel_log_crew`");
$vlcCols = $vlcColsStmt ? $vlcColsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$vlcUserCol = null;
foreach (['user_id','crew_user_id','uid','userid'] as $cand) {
  foreach ($vlcCols as $raw) { if ($norm($raw) === $norm($cand)) { $vlcUserCol = $raw; break 2; } }
}
if (!$vlcUserCol) {
  foreach ($vlcCols as $raw) { if (preg_match('/user[_-]?id$/i', $raw)) { $vlcUserCol = $raw; break; } }
}

$crew = [];
if ($vlcUserCol) {
  $sqlCrew = "
    SELECT u.fName, u.lName" . ($hasRole ? ", u.role" : "") . "
    FROM vessel_log_crew lc
    JOIN users u ON u.`$usersPk` = lc.`$vlcUserCol`
    WHERE lc.log_id = ?
    ORDER BY u.lName, u.fName
  ";
  $crewStmt = $pdo->prepare($sqlCrew);
  $crewStmt->execute([$log_id]);
  $crew = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- Media (thumbnails / video previews) ----------
$mediaHasMime = $colExists('vessel_log_media', 'mime_type');
if ($mediaHasMime) {
  $mediaStmt = $pdo->prepare("SELECT file_path, mime_type FROM vessel_log_media WHERE log_id = ? ORDER BY uploaded_at DESC");
} else {
  $mediaStmt = $pdo->prepare("SELECT file_path, NULL AS mime_type FROM vessel_log_media WHERE log_id = ? ORDER BY uploaded_at DESC");
}
$mediaStmt->execute([$log_id]);
$media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

$preChecklist = null;
$postChecklist = null;

$preChecklistId = (int)($log['pre_checklist_id'] ?? 0);
if ($preChecklistId > 0) {
  $preChecklist = [
    'header' => checklist_get_run_header($pdo, $preChecklistId),
    'summary' => checklist_get_run_response_summary($pdo, $preChecklistId),
  ];
}

$postChecklistId = (int)($log['post_checklist_id'] ?? 0);
if ($postChecklistId > 0) {
  $postChecklist = [
    'header' => checklist_get_run_header($pdo, $postChecklistId),
    'summary' => checklist_get_run_response_summary($pdo, $postChecklistId),
  ];
}

$trackedReadings = [];
if (vms_hour_table_exists($pdo, 'equipment_hour_readings')) {
  $readingStmt = $pdo->prepare("
    SELECT r.reading_hours, e.equipmentName, e.equipmentLocation, hm.tracked_class
    FROM equipment_hour_readings r
    INNER JOIN equipment e ON e.eid = r.equipment_id
    INNER JOIN equipment_hour_meters hm ON hm.meter_id = r.meter_id
    WHERE r.vessel_log_id = ?
    ORDER BY FIELD(hm.tracked_class, 'propulsion_engine', 'generator'), hm.display_order ASC, e.equipmentLocation ASC
  ");
  $readingStmt->execute([$log_id]);
  $trackedReadings = $readingStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>View Log #<?= (int)$log_id ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= h($log['vesselName']) ?> — Voyage Log #<?= (int)$log_id ?></h4>
    <div>
      <a class="btn btn-outline-secondary" href="logs_list.php?vessel_id=<?= (int)$log['vessel_id'] ?>">Back to Logs</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Trip</div>
        <div class="card-body">
          <div><strong>Departure:</strong> <?= h($log['depart_dt'] ?: '—') ?></div>
          <div><strong>Origin:</strong> <?= h($log['origin_port'] ?: '—') ?></div>
          <div><strong>Return:</strong> <?= h($log['return_dt'] ?: '—') ?></div>
          <div><strong>Arrival:</strong> <?= h($log['arrival_port'] ?: '—') ?></div>
          <div><strong>Passengers:</strong> <?= is_null($log['passenger_count']) ? '—' : (int)$log['passenger_count'] ?></div>

          <?php
            $status = $log['status'] ?? null;
            $badge  = ($status === 'draft') ? 'text-bg-secondary' : (($status === 'submitted') ? 'text-bg-success' : 'text-bg-light');
          ?>
          <div class="mt-2">
            <strong>Status:</strong>
            <span class="badge <?= $badge ?>"><?= h($status ? ucfirst($status) : '—') ?></span>
          </div>

          <?php if (!empty($log['submitted_at'])): ?>
            <div><strong>Submitted:</strong> <?= h($log['submitted_at']) ?></div>
          <?php endif; ?>

          <?php if (!empty($log['casualty_flag'])): ?>
            <div class="mt-2">⚠️ Casualty noted</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Engines</div>
        <div class="card-body">
          <div><strong>Port hours:</strong> <?= h((string)($log['engine_hours_port'] ?? '')) ?></div>
          <div><strong>Starboard hours:</strong> <?= h((string)($log['engine_hours_stbd'] ?? '')) ?></div>
          <?php if ($trackedReadings): ?>
            <hr>
            <?php foreach ($trackedReadings as $reading): ?>
              <div>
                <strong><?= h($reading['equipmentName']) ?> (<?= h($reading['equipmentLocation']) ?>):</strong>
                <?= h((string)$reading['reading_hours']) ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">Checklists</div>
        <div class="card-body">
          <?php
            $checklistCards = [
              'Pre-Underway' => $preChecklist,
              'Post-Underway' => $postChecklist,
            ];
          ?>
          <div class="row g-3">
            <?php foreach ($checklistCards as $label => $checklist): ?>
              <div class="col-md-6">
                <div><strong><?= h($label) ?>:</strong></div>
                <?php if (empty($checklist['header'])): ?>
                  <div class="text-muted">Not completed</div>
                <?php else: ?>
                  <div>Run #<?= (int)$checklist['header']['checklist_run_id'] ?></div>
                  <div><?= h($checklist['header']['template_name'] ?? $checklist['header']['run_type'] ?? '') ?></div>
                  <div class="text-muted">
                    Complete: <?= (int)($checklist['summary']['complete'] ?? 0) ?> |
                    Not Complete: <?= (int)($checklist['summary']['not_complete'] ?? 0) ?> |
                    N/A: <?= (int)($checklist['summary']['na'] ?? 0) ?>
                  </div>
                  <div class="mt-1">
                    <a href="checklist_view.php?checklist_run_id=<?= (int)$checklist['header']['checklist_run_id'] ?>" class="small">
                      View Checklist
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">Crew Onboard</div>
        <div class="card-body">
          <?php if (!$crew): ?>
            <span class="text-muted">—</span>
          <?php else: ?>
            <ul class="mb-0">
              <?php foreach ($crew as $c): ?>
                <li>
                  <?= h(($c['lName'] ?? '').', '.($c['fName'] ?? '')) ?>
                  <?= (!empty($c['role']) ? ' ('.h($c['role']).')' : '') ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">Trip Summary / Notes</div>
        <div class="card-body">
          <pre class="mb-0" style="white-space:pre-wrap"><?= h($log['trip_summary'] ?: '—') ?></pre>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header">Media</div>
        <div class="card-body d-flex flex-wrap gap-3">
          <?php if (!$media): ?>
            <span class="text-muted">—</span>
          <?php else: ?>
            <?php foreach ($media as $m):
              $path = (string)$m['file_path'];
              $mime = $m['mime_type'] ?? null;

              if (guess_is_image($path, $mime)): ?>
                <a href="<?= h($path) ?>" target="_blank" class="text-decoration-none">
                  <img src="<?= h($path) ?>" class="img-thumbnail"
                       style="width: 200px; height: 130px; object-fit: cover;" alt="media">
                </a>
              <?php elseif (guess_is_video($path, $mime)): ?>
                <video src="<?= h($path) ?>" width="220" height="140" controls preload="metadata" style="display:block;">
                  Sorry, your browser can’t play this video.
                </video>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= h($path) ?>">
                  <?= h(basename($path)) ?>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($log['signature_path'])): ?>
      <div class="col-12">
        <div class="card">
          <div class="card-header">Signature</div>
          <div class="card-body">
            <div>
              <img src="<?= h($log['signature_path']) ?>" style="max-width:100%; height:auto; border:1px solid #ddd;"/>
            </div>
            <?php if (!empty($log['signed_at'])): ?>
              <div class="mt-2"><strong>Signed At:</strong> <?= h($log['signed_at']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
