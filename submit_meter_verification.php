<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$vesselId = (int)($_POST['vessel_id'] ?? 0);
$taskId = (int)($_POST['task_id'] ?? 0);
$note = trim((string)($_POST['verification_note'] ?? ''));
$meterHours = vms_hour_parse_posted_meter_readings($_POST);

if ($vesselId <= 0) {
    http_response_code(422);
    exit('Missing vessel_id.');
}

try {
    $pdo->beginTransaction();

    foreach ($meterHours as $meterId => $value) {
        if ($value === '' || !is_numeric($value)) {
            throw new RuntimeException('Verification readings must be numeric.');
        }

        vms_hour_apply_verification_reading($pdo, (int)$meterId, (float)$value, $note !== '' ? $note : 'Monthly meter verification update.');
    }

    if ($taskId > 0) {
        $taskStmt = $pdo->prepare("
            UPDATE tasks
            SET status = 'complete',
                corrected_date = CURDATE(),
                completed_date = CURDATE(),
                corrective_action = CONCAT(COALESCE(corrective_action, ''), CASE WHEN COALESCE(corrective_action, '') = '' THEN '' ELSE '\n\n' END, ?),
                updated_at = CURRENT_TIMESTAMP
            WHERE task_id = ?
              AND task_type = 'meter_verification'
        ");
        $taskStmt->execute([$note !== '' ? $note : 'Monthly meter verification completed.', $taskId]);
    }

    $pdo->commit();
    header('Location: vessel_dashboard.php?vessel_id=' . $vesselId . '&success=meter_verification_saved');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to save meter verification: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
