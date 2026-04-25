<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$meterId = (int)($_POST['meter_id'] ?? 0);
$vesselId = (int)($_POST['vessel_id'] ?? 0);
$serviceName = trim((string)($_POST['service_name'] ?? ''));
$intervalHours = max(1, (int)($_POST['interval_hours'] ?? 0));
$advanceNoticeHours = max(0, (int)($_POST['advance_notice_hours'] ?? 0));
$isActive = ((int)($_POST['is_active'] ?? 1) === 1) ? 1 : 0;

if ($equipmentId <= 0 || $meterId <= 0 || $vesselId <= 0 || $serviceName === '') {
    http_response_code(422);
    exit('Missing required schedule fields.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO equipment_maintenance_schedules (
            meter_id, equipment_id, vessel_id, service_name, interval_hours,
            advance_notice_hours, is_active, last_completed_hours, next_due_hours
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL)
    ");
    $stmt->execute([
        $meterId,
        $equipmentId,
        $vesselId,
        $serviceName,
        $intervalHours,
        $advanceNoticeHours,
        $isActive,
    ]);

    $scheduleId = (int)$pdo->lastInsertId();
    vms_hour_recalculate_schedule($pdo, $scheduleId);
    vms_hour_ensure_schedule_task($pdo, $scheduleId);

    $pdo->commit();
    header('Location: equipment_detail.php?id=' . $equipmentId . '&success=schedule_added');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to save maintenance schedule: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
