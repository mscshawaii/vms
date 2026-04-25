<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

function safeStr($v){ return trim((string)$v); }
function ensure_dir($path){ if(!is_dir($path)) mkdir($path, 0775, true); }

header('X-Content-Type-Options: nosniff');

$user_id   = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$log_id    = (int)($_POST['log_id'] ?? 0);
$vessel_id = (int)($_POST['vessel_id'] ?? 0);

if (!$log_id || !$vessel_id) {
    http_response_code(400);
    exit('Missing ids.');
}

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

$crew_ids = isset($_POST['crew_ids']) && is_array($_POST['crew_ids'])
    ? array_values(array_unique(array_filter(array_map('intval', $_POST['crew_ids']), fn($id) => $id > 0)))
    : [];

$save_mode   = $_POST['save_mode'] ?? 'submit';
$status      = ($save_mode === 'draft') ? 'draft' : 'submitted';
$submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;

// Signature payload from the form (base64 data URL)
$signatureDataUrl = $_POST['signature_png'] ?? '';
$signed_by_name = safeStr($_POST['signed_by_name'] ?? ''); // currently unused in schema

$pdo->beginTransaction();

try {
    // Confirm the log exists and belongs to this vessel
    $checkLog = $pdo->prepare("
        SELECT log_id
        FROM vessel_logs
        WHERE log_id = ?
          AND vessel_id = ?
        LIMIT 1
    ");
    $checkLog->execute([$log_id, $vessel_id]);

    if (!$checkLog->fetchColumn()) {
        throw new RuntimeException('Log not found for this vessel.');
    }

    // Validate selected crew against current vessel assignment rules
    if (!empty($crew_ids)) {
        $placeholders = implode(',', array_fill(0, count($crew_ids), '?'));

        $validCrewSql = "
            SELECT DISTINCT u.id
            FROM vessel_crew vc
            INNER JOIN users u
                ON u.id = vc.crew_id
            WHERE vc.vessel_id = ?
              AND vc.is_active = 1
              AND u.is_active = 1
              AND vc.counts_for_voyage_logs = 1
              AND vc.role IN ('Master', 'Deckhand')
              AND u.id IN ($placeholders)
        ";

        $params = array_merge([$vessel_id], $crew_ids);
        $validCrewStmt = $pdo->prepare($validCrewSql);
        $validCrewStmt->execute($params);
        $validCrewIds = array_map('intval', $validCrewStmt->fetchAll(PDO::FETCH_COLUMN));

        sort($crew_ids);
        sort($validCrewIds);

        if ($crew_ids !== $validCrewIds) {
            throw new RuntimeException('One or more selected crew members are not valid for this vessel log.');
        }
    }

    // Update the core log fields
    $stmt = $pdo->prepare("
        UPDATE vessel_logs SET
          status = :status,
          depart_dt = :depart_dt,
          origin_port = :origin_port,
          pre_checklist_id = :pre_checklist_id,
          return_dt = :return_dt,
          arrival_port = :arrival_port,
          passenger_count = :passenger_count,
          trip_summary = :trip_summary,
          engine_hours_port = :eh_port,
          engine_hours_stbd = :eh_stbd,
          post_checklist_id = :post_checklist_id,
          submitted_at = :submitted_at,
          updated_at = NOW()
        WHERE log_id = :log_id
          AND vessel_id = :vessel_id
    ");
    $stmt->execute([
        ':status' => $status,
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
        ':submitted_at' => $submittedAt,
        ':log_id' => $log_id,
        ':vessel_id' => $vessel_id
    ]);

    // Replace crew mapping
    $pdo->prepare("DELETE FROM vessel_log_crew WHERE log_id = ?")->execute([$log_id]);

    if ($crew_ids) {
        $ins = $pdo->prepare("
            INSERT INTO vessel_log_crew (log_id, user_id)
            VALUES (:log_id, :user_id)
        ");

        foreach ($crew_ids as $uid) {
            $ins->execute([
                ':log_id' => $log_id,
                ':user_id' => $uid
            ]);
        }
    }

    // Append media uploads
    if (!empty($_FILES['media_files']) && is_array($_FILES['media_files']['name'])) {
        $names = $_FILES['media_files']['name'];
        $tmp   = $_FILES['media_files']['tmp_name'];
        $types = $_FILES['media_files']['type'];
        $sizes = $_FILES['media_files']['size'];
        $errs  = $_FILES['media_files']['error'];

        $baseDir = __DIR__ . '/uploads/logs/' . $log_id;
        ensure_dir($baseDir);

        for ($i = 0; $i < count($names); $i++) {
            if ($errs[$i] !== UPLOAD_ERR_OK || !$tmp[$i]) continue;
            if (!preg_match('#^(image/|video/)#', (string)$types[$i])) continue;

            $ext = pathinfo($names[$i], PATHINFO_EXTENSION);
            $ext = $ext ? ('.' . preg_replace('/[^a-zA-Z0-9]+/', '', $ext)) : '';
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
                    ':uploaded_by' => $user_id ?: null
                ]);
            }
        }
    }

    // Save signature if provided
    if (is_string($signatureDataUrl) && strpos($signatureDataUrl, 'data:image/png;base64,') === 0) {
        $base64 = substr($signatureDataUrl, strlen('data:image/png;base64,'));
        $bin = base64_decode($base64, true);

        if ($bin !== false && strlen($bin) > 0) {
            $sigDir = __DIR__ . '/uploads/log_signatures';
            ensure_dir($sigDir);

            $fname = 'log_' . $log_id . '_' . date('Ymd_His') . '.png';
            $fsPath = $sigDir . '/' . $fname;

            if (file_put_contents($fsPath, $bin) !== false) {
                $webPath = 'uploads/log_signatures/' . $fname;

                $stmtS = $pdo->prepare("
                    UPDATE vessel_logs
                    SET signature_path = :path,
                        signed_by_user_id = :uid,
                        signed_at = NOW()
                    WHERE log_id = :log_id
                      AND vessel_id = :vessel_id
                ");
                $stmtS->execute([
                    ':path' => $webPath,
                    ':uid'  => $user_id ?: null,
                    ':log_id' => $log_id,
                    ':vessel_id' => $vessel_id
                ]);
            }
        }
    }

    $meterWarnings = [];
    vms_hour_apply_voyage_log_readings($pdo, $vessel_id, $log_id, $postedMeterReadings, $depart_dt, $return_dt, $meterWarnings);

    $pdo->commit();
    $redirect = 'Location: logs_list.php?vessel_id=' . $vessel_id;
    if (!empty($meterWarnings)) {
        $redirect .= '&meter_warning=' . urlencode($meterWarnings[0]);
    }
    header($redirect);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "Error updating log: " . htmlspecialchars($e->getMessage());
}
