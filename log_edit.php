<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dval($dt){ return $dt ? date('Y-m-d\TH:i', strtotime($dt)) : ''; }
function buildChecklistReturnUrl(string $path, array $params): string { return $path . '?' . http_build_query($params); }
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

// --------- input ----------
$log_id = (int)($_GET['log_id'] ?? $_GET['id'] ?? $_POST['log_id'] ?? 0);
if (!$log_id) {
    http_response_code(400);
    exit("Missing log_id.");
}

// --------- load log ----------
$stmt = $pdo->prepare("SELECT * FROM vessel_logs WHERE log_id = ?");
$stmt->execute([$log_id]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$log) {
    http_response_code(404);
    exit("Log not found.");
}

$vessel_id = (int)$log['vessel_id'];
if ($vessel_id <= 0) {
    http_response_code(400);
    exit("Invalid vessel_id on log.");
}

$trackedMeters = vms_hour_get_tracked_meters_for_vessel($pdo, $vessel_id, true);

// --------- crew options for this vessel ----------
$crewOptions = [];
try {
    $sqlCrew = "
        SELECT DISTINCT
            u.id AS user_pk,
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
    ";
    $crewStmt = $pdo->prepare($sqlCrew);
    $crewStmt->execute([$vessel_id]);
    $crewOptions = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $crewOptions = [];
}

// --------- existing crew on this log ----------
$crewMap = $pdo->prepare("
    SELECT user_id
    FROM vessel_log_crew
    WHERE log_id = ?
");
$crewMap->execute([$log_id]);
$existingCrew = array_map('intval', $crewMap->fetchAll(PDO::FETCH_COLUMN));

// --------- existing media ----------
$mediaStmt = $pdo->prepare("
    SELECT
        media_id,
        file_path,
        mime_type,
        file_size,
        uploaded_by,
        uploaded_at
    FROM vessel_log_media
    WHERE log_id = ?
    ORDER BY uploaded_at DESC, media_id DESC
");
$mediaStmt->execute([$log_id]);
$media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

$formStateKey = trim((string)($_GET['form_state_key'] ?? $_POST['form_state_key'] ?? ''));
if ($formStateKey === '') {
    $formStateKey = generateFormStateKey();
}

$preChecklistId = (int)($log['pre_checklist_id'] ?? 0);
$postChecklistId = (int)($log['post_checklist_id'] ?? 0);
$returnedChecklistRunId = (int)($_GET['checklist_run_id'] ?? 0);
$returnedChecklistType = trim((string)($_GET['checklist_type'] ?? ''));
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
    'depart_dt' => (string)($storedFormState['depart_dt'] ?? dval($log['depart_dt'] ?? null)),
    'origin_port' => (string)($storedFormState['origin_port'] ?? ($log['origin_port'] ?? '')),
    'passenger_count' => (string)($storedFormState['passenger_count'] ?? (string)($log['passenger_count'] ?? '')),
    'crew_ids' => array_values(array_unique(array_map('intval', is_array($storedFormState['crew_ids'] ?? null) ? $storedFormState['crew_ids'] : $existingCrew))),
    'return_dt' => (string)($storedFormState['return_dt'] ?? dval($log['return_dt'] ?? null)),
    'arrival_port' => (string)($storedFormState['arrival_port'] ?? ($log['arrival_port'] ?? '')),
    'engine_hours_port' => (string)($storedFormState['engine_hours_port'] ?? (string)($log['engine_hours_port'] ?? '')),
    'engine_hours_stbd' => (string)($storedFormState['engine_hours_stbd'] ?? (string)($log['engine_hours_stbd'] ?? '')),
    'meter_hours' => vms_hour_log_form_values($storedFormState),
    'trip_summary' => (string)($storedFormState['trip_summary'] ?? ($log['trip_summary'] ?? '')),
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

        $returnTo = buildChecklistReturnUrl('log_edit.php', [
            'log_id' => $log_id,
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

$preChecklistReturnTo = buildChecklistReturnUrl('log_edit.php', [
    'log_id' => $log_id,
    'pre_checklist_id' => $preChecklistId,
    'post_checklist_id' => $postChecklistId,
    'form_state_key' => $formStateKey,
]);

$postChecklistReturnTo = buildChecklistReturnUrl('log_edit.php', [
    'log_id' => $log_id,
    'pre_checklist_id' => $preChecklistId,
    'post_checklist_id' => $postChecklistId,
    'form_state_key' => $formStateKey,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Voyage Log - VMS</title>

  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0d6efd">

  <link rel="icon" href="/assets/vms-icon-192.png">
  <link rel="apple-touch-icon" href="/assets/vms-icon-192.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/vms-mobile.css" rel="stylesheet">

  <style>
    .logs-shell { background:#f4f7fb; min-height:100vh; }

    .logs-header {
      display:flex;
      justify-content:space-between;
      flex-wrap:wrap;
      gap:12px;
      margin-bottom:16px;
    }

    .logs-title { font-size:1.6rem; font-weight:700; margin:0; }
    .logs-sub { color:#6b7280; }

    .logs-actions { display:flex; gap:8px; flex-wrap:wrap; }

    .logs-actions .btn {
      border-radius:12px;
      min-height:42px;
    }

    .logs-form .form-control,
    .logs-form .form-select,
    .logs-form .btn {
      border-radius:12px;
    }
  </style>
</head>

<body>
<?php
$title = 'Edit Voyage Log';
$back_link = 'logs_list.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="logs-shell">
  <div class="app-page">
    <div class="app-container">

      <div class="vms-card">
        <div class="logs-header">
          <div>
            <h1 class="logs-title">
              Edit Voyage Log #<?= (int)$log_id ?>
              <?php if (($log['status'] ?? '') === 'draft'): ?>
                <span class="badge text-bg-warning ms-2">Draft</span>
              <?php endif; ?>
            </h1>
            <p class="logs-sub">Update voyage details, crew, and notes</p>
          </div>

          <div class="logs-actions">
            <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
              Back to Logs
            </a>
            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
              Vessel
            </a>
          </div>
        </div>
      </div>

      <div class="vms-card logs-form">
        <form id="logEditForm" method="post" action="log_update.php" enctype="multipart/form-data">
          <input type="hidden" name="log_id" value="<?= (int)$log_id ?>">
          <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
          <input type="hidden" name="form_state_key" value="<?= h($formStateKey) ?>">
          <input type="hidden" name="pre_checklist_id" value="<?= (int)$preChecklistId ?>">
          <input type="hidden" name="post_checklist_id" value="<?= (int)$postChecklistId ?>">
          <input type="hidden" name="signature_png" id="signature_png">

          <div class="row g-3">

            <div class="col-md-3">
              <label class="form-label">Departure</label>
              <input type="datetime-local" class="form-control" name="depart_dt"
                     value="<?= h($formValues['depart_dt']) ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Origin</label>
              <input type="text" class="form-control" name="origin_port"
                     value="<?= h($formValues['origin_port']) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
              <div class="d-grid w-100">
                <button class="btn btn-outline-secondary w-100"
                        type="submit"
                        name="open_checklist"
                        value="pre_underway"
                        formaction="log_edit.php?log_id=<?= (int)$log_id ?>"
                        formnovalidate>
                  <?= $preChecklistId > 0 ? 'Replace Pre-Checklist' : 'Pre-Checklist' ?>
                </button>
                <div class="form-text">
                  <?= $preChecklistId > 0 ? 'Selected run #' . (int)$preChecklistId : 'Not selected' ?>
                </div>
              </div>
            </div>

            <div class="col-md-3">
              <label class="form-label">Passengers</label>
              <input type="number" class="form-control" name="passenger_count"
                     value="<?= h($formValues['passenger_count']) ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Crew</label>
              <select name="crew_ids[]" class="form-select" multiple size="6">
                <?php foreach ($crewOptions as $c):
                  $pk = (int)$c['user_pk']; ?>
                  <option value="<?= $pk ?>" <?= in_array($pk, $formValues['crew_ids'], true) ? 'selected' : '' ?>>
                    <?= h(trim(($c['lName'] ?? '') . ', ' . ($c['fName'] ?? ''))) ?>
                    <?php if (!empty($c['role'])): ?> (<?= h($c['role']) ?>)<?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Return</label>
              <input type="datetime-local" class="form-control" name="return_dt"
                     value="<?= h($formValues['return_dt']) ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Arrival</label>
              <input type="text" class="form-control" name="arrival_port"
                     value="<?= h($formValues['arrival_port']) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
              <div class="d-grid w-100">
                <button class="btn btn-outline-secondary w-100"
                        type="submit"
                        name="open_checklist"
                        value="post_underway"
                        formaction="log_edit.php?log_id=<?= (int)$log_id ?>"
                        formnovalidate>
                  <?= $postChecklistId > 0 ? 'Replace Post-Checklist' : 'Post-Checklist' ?>
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
                        <?php $meterId = (int)$meter['meter_id']; ?>
                        <div class="col-md-6">
                          <label class="form-label">
                            <?= h($meter['equipmentName']) ?>
                            <span class="text-muted">(<?= h($meter['equipmentLocation']) ?>, current <?= h(number_format((float)$meter['current_hours'], 1)) ?>)</span>
                          </label>
                          <input
                            type="number"
                            step="0.1"
                            min="0"
                            class="form-control tracked-meter-input"
                            data-current-hours="<?= h((string)$meter['current_hours']) ?>"
                            name="meter_hours[<?= $meterId ?>]"
                            value="<?= h((string)($formValues['meter_hours'][$meterId] ?? '')) ?>"
                            required
                          >
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="col-12">
                <div class="alert alert-info mb-0">
                  No active hour-tracked propulsion engines or generators are configured for this vessel.
                </div>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label">Trip Notes</label>
              <textarea class="form-control" name="trip_summary" rows="4"><?= h($formValues['trip_summary']) ?></textarea>
            </div>

            <div class="col-12">
              <div class="row g-3">
                <div class="col-md-6 d-grid">
                  <span class="d-grid" data-bs-toggle="tooltip" title="Under development">
                    <button type="button" class="btn btn-outline-secondary btn-lg w-100" disabled>Report a Marine Casualty</button>
                  </span>
                </div>

                <div class="col-md-6 d-grid">
                  <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>&origin=log&log_id=<?= (int)$log_id ?>"
                     class="btn btn-outline-primary btn-lg w-100">
                    Report a Corrective Action Requirement
                  </a>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label">Add Media</label>
              <input class="form-control" type="file" name="media_files[]" accept="image/*,video/*" multiple>
              <div class="form-text">Optional: uploads are appended to this log; existing media listed below.</div>
            </div>

            <?php if ($media): ?>
              <div class="col-12">
                <label class="form-label">Existing Media</label>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($media as $m): ?>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= h($m['file_path']) ?>">
                      <?= h(basename($m['file_path'])) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <div class="col-12">
              <label class="form-label">Signature</label>
              <div class="border rounded p-2 bg-light">
                <canvas id="sigCanvas" width="800" height="140" class="w-100"
                        style="background:#fff; border:1px solid #ccc; touch-action:none;"></canvas>
                <div class="mt-2 d-flex gap-2">
                  <input type="text" class="form-control" name="signed_by_name" placeholder="Printed name (optional)">
                  <button type="button" class="btn btn-outline-secondary" id="btnClearSig">Clear</button>
                </div>
                <div class="form-text">Sign with mouse or touch. Signature will be timestamped on save.</div>
              </div>
            </div>

          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" name="save_mode" value="draft" class="btn btn-secondary">Save Draft</button>
            <button type="submit" name="save_mode" value="submit" class="btn btn-primary">Submit Log</button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  }

  const form   = document.getElementById('logEditForm');
  const canvas = document.getElementById('sigCanvas');
  const hidden = document.getElementById('signature_png');
  if (!form || !canvas || !hidden) return;

  const ctx = canvas.getContext('2d');
  let drawing = false, last = null;

  function relPos(e){
    const r = canvas.getBoundingClientRect();
    if (e.touches && e.touches.length) {
      return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
    }
    return { x: e.clientX - r.left, y: e.clientY - r.top };
  }

  function start(e){ drawing = true; last = relPos(e); e.preventDefault(); }
  function move(e){
    if(!drawing) return;
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
  function end(){ drawing = false; last = null; }

  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);

  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove',  move,  {passive:false});
  canvas.addEventListener('touchend',   end);

  const clearBtn = document.getElementById('btnClearSig');
  if (clearBtn) {
    clearBtn.addEventListener('click', () => ctx.clearRect(0,0,canvas.width,canvas.height));
  }

  form.addEventListener('submit', () => {
    const blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;
    hidden.value = (canvas.toDataURL() !== blank.toDataURL()) ? canvas.toDataURL('image/png') : '';
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
});
</script>
</body>
</html>
