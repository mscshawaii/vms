<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

function safeStr($v){ return trim((string)$v); }
function ensure_dir($path){ if(!is_dir($path)) mkdir($path, 0775, true); }

header('X-Content-Type-Options: nosniff');

$user_id   = $_SESSION['id'] ?? null;
$vessel_id = intval($_POST['vessel_id'] ?? 0);
if (!$vessel_id) { http_response_code(400); exit('Missing vessel_id'); }

$depart_dt = $_POST['depart_dt'] ?: null;
$return_dt = $_POST['return_dt'] ?: null;

$origin_port  = mb_substr(safeStr($_POST['origin_port'] ?? ''), 0, 120);
$arrival_port = mb_substr(safeStr($_POST['arrival_port'] ?? ''), 0, 120);
$passenger_count = strlen($_POST['passenger_count'] ?? '') ? (int)$_POST['passenger_count'] : null;

$trip_summary = $_POST['trip_summary'] ?? null;
$engine_hours_port = strlen($_POST['engine_hours_port'] ?? '') ? (float)$_POST['engine_hours_port'] : null;
$engine_hours_stbd = strlen($_POST['engine_hours_stbd'] ?? '') ? (float)$_POST['engine_hours_stbd'] : null;
$postedMeterReadings = vms_hour_parse_posted_meter_readings($_POST);
$legacyMeterFields = vms_hour_derive_legacy_engine_fields($pdo, $vessel_id, $postedMeterReadings);
if ($legacyMeterFields['engine_hours_port'] !== null) {
  $engine_hours_port = (float)$legacyMeterFields['engine_hours_port'];
}
if ($legacyMeterFields['engine_hours_stbd'] !== null) {
  $engine_hours_stbd = (float)$legacyMeterFields['engine_hours_stbd'];
}
$pre_checklist_id = (int)($_POST['pre_checklist_id'] ?? 0);
$post_checklist_id = (int)($_POST['post_checklist_id'] ?? 0);
$pre_checklist_id = $pre_checklist_id > 0 ? $pre_checklist_id : null;
$post_checklist_id = $post_checklist_id > 0 ? $post_checklist_id : null;

$casualty_flag = intval($_POST['casualty_flag'] ?? 0) === 1 ? 1 : 0;
$signed_by_name = mb_substr(safeStr($_POST['signed_by_name'] ?? ''), 0, 120);
$signature_png  = $_POST['signature_png'] ?? null;

$crew_ids = isset($_POST['crew_ids']) && is_array($_POST['crew_ids']) ? array_map('intval', $_POST['crew_ids']) : [];

$save_mode   = $_POST['save_mode'] ?? 'submit';
$status      = ($save_mode === 'draft') ? 'draft' : 'submitted';
$submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;

$pdo->beginTransaction();
try {
  $sigPath = null; $signedAt = null; $signedByUser = null;
  if ($signature_png && preg_match('#^data:image/png;base64,#', $signature_png)) {
    $signedAt = date('Y-m-d H:i:s');
    $signedByUser = $user_id ?: null;
  }

  $stmt = $pdo->prepare("
    INSERT INTO vessel_logs
      (vessel_id, depart_dt, origin_port, pre_checklist_id, return_dt, arrival_port,
       passenger_count, trip_summary, engine_hours_port, engine_hours_stbd, post_checklist_id,
       casualty_flag, signed_by_user_id, signed_at, signature_path, created_by, created_at, submitted_at)
    VALUES
      (:vessel_id, :depart_dt, :origin_port, :pre_checklist_id, :return_dt, :arrival_port,
       :passenger_count, :trip_summary, :eh_port, :eh_stbd, :post_checklist_id,
       :casualty_flag, :signed_by_user_id, :signed_at, :signature_path, :created_by, NOW(), :submitted_at)
  ");

  $stmt->execute([
    ':vessel_id' => $vessel_id,
    ':depart_dt' => $depart_dt ? date('Y-m-d H:i:s', strtotime($depart_dt)) : null,
    ':origin_port' => $origin_port ?: null,
    ':pre_checklist_id' => $pre_checklist_id,
    ':return_dt' => $return_dt ? date('Y-m-d H:i:s', strtotime($return_dt)) : null,
    ':arrival_port' => $arrival_port ?: null,
    ':passenger_count' => $passenger_count,
    ':trip_summary' => $trip_summary ?: null,
    ':eh_port' => $engine_hours_port,
    ':eh_stbd' => $engine_hours_stbd,
    ':post_checklist_id' => $post_checklist_id,
    ':casualty_flag' => $casualty_flag,
    ':signed_by_user_id' => $signedByUser,
    ':signed_at' => $signedAt,
    ':signature_path' => null,
    ':created_by' => $user_id,
    ':submitted_at' => $submittedAt,
  ]);

  $log_id = (int)$pdo->lastInsertId();

  // Save signature PNG
  if ($signature_png && preg_match('#^data:image/png;base64,#', $signature_png)) {
    $data = base64_decode(substr($signature_png, strpos($signature_png, ',') + 1));
    $baseDir = __DIR__ . '/uploads/logs/' . $log_id;
    ensure_dir($baseDir);
    $sigPathFS = $baseDir . '/signature.png';
    file_put_contents($sigPathFS, $data);
    $sigPathWeb = 'uploads/logs/' . $log_id . '/signature.png';
    $pdo->prepare("UPDATE vessel_logs SET signature_path = :p WHERE log_id = :id")
        ->execute([':p' => $sigPathWeb, ':id' => $log_id]);
  }

  // Media uploads
  if (!empty($_FILES['media_files']) && is_array($_FILES['media_files']['name'])) {
    $names = $_FILES['media_files']['name'];
    $tmp   = $_FILES['media_files']['tmp_name'];
    $types = $_FILES['media_files']['type'];
    $sizes = $_FILES['media_files']['size'];
    $errs  = $_FILES['media_files']['error'];

    $baseDir = __DIR__ . '/uploads/logs/' . $log_id;
    ensure_dir($baseDir);

    for ($i=0; $i<count($names); $i++) {
      if ($errs[$i] !== UPLOAD_ERR_OK || !$tmp[$i]) continue;
      if (!preg_match('#^(image/|video/)#', (string)$types[$i])) continue;

      $ext = pathinfo($names[$i], PATHINFO_EXTENSION);
      $ext = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]+/','', $ext)) : '';
      $newName = uniqid('m_', true) . $ext;
      $destFS = $baseDir . '/' . $newName;
      if (move_uploaded_file($tmp[$i], $destFS)) {
        $webPath = 'uploads/logs/' . $log_id . '/' . $newName;
        $stmtM = $pdo->prepare("
          INSERT INTO vessel_log_media (log_id, file_path, mime_type, file_size, uploaded_by)
          VALUES (:log_id, :file_path, :mime_type, :file_size, :uploaded_by)
        ");
        $stmtM->execute([
          ':log_id' => $log_id,
          ':file_path' => $webPath,
          ':mime_type' => $types[$i],
          ':file_size' => (int)$sizes[$i],
          ':uploaded_by' => $user_id
        ]);
      }
    }
  }

  // Crew mapping
  if ($crew_ids) {
    $ins = $pdo->prepare("INSERT INTO vessel_log_crew (log_id, user_id) VALUES (:log_id, :user_id)");
    foreach ($crew_ids as $uid) {
      if ($uid > 0) $ins->execute([':log_id' => $log_id, ':user_id' => $uid]);
    }
  }

  $meterWarnings = [];
  vms_hour_apply_voyage_log_readings($pdo, $vessel_id, $log_id, $postedMeterReadings, $depart_dt, $return_dt, $meterWarnings);

  $pdo->commit();
$redirect = 'Location: logs_list.php?vessel_id=' . $vessel_id . '&log_saved=1';
if (!empty($meterWarnings)) {
  $redirect .= '&meter_warning=' . urlencode($meterWarnings[0]);
}
header($redirect);
exit;

} catch (Exception $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo "Error saving log: " . htmlspecialchars($e->getMessage());
}
