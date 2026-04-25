<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$completionDate = trim((string)($_POST['completion_date'] ?? ''));
$completionHours = vms_hour_decimal_or_null($_POST['completion_hours'] ?? null);
$performedBy = trim((string)($_POST['performed_by'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

if ($scheduleId <= 0 || $completionDate === '' || $completionHours === null || $note === '') {
    http_response_code(422);
    exit('Missing required backfill fields.');
}

try {
    $pdo->beginTransaction();
    vms_hour_backfill_maintenance($pdo, $scheduleId, $completionDate, (float)$completionHours, $note, $performedBy !== '' ? $performedBy : null);

    $stmt = $pdo->prepare("SELECT equipment_id FROM equipment_maintenance_schedules WHERE schedule_id = ? LIMIT 1");
    $stmt->execute([$scheduleId]);
    $equipmentId = (int)($stmt->fetchColumn() ?: 0);

    $pdo->commit();
    header('Location: equipment_detail.php?id=' . $equipmentId . '&success=maintenance_backfilled');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to backfill maintenance: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
