<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

$scheduleId = (int)($_POST['schedule_id'] ?? 0);
$completionDate = trim((string)($_POST['completion_date'] ?? ''));
$completionHours = vms_hour_decimal_or_null($_POST['completion_hours'] ?? null);
$performedBy = trim((string)($_POST['performed_by'] ?? ''));
$note = trim((string)($_POST['note'] ?? ''));

if ($scheduleId <= 0 || $completionDate === '' || $completionHours === null) {
    http_response_code(422);
    exit('Missing required completion fields.');
}

try {
    $pdo->beginTransaction();

    $taskStmt = $pdo->prepare("
        SELECT task_id
        FROM tasks
        WHERE related_schedule_id = ?
          AND task_type = 'hour_maintenance'
          AND status IN ('open', 'in_progress', 'overdue')
        ORDER BY task_id DESC
        LIMIT 1
    ");
    $taskStmt->execute([$scheduleId]);
    $taskId = (int)($taskStmt->fetchColumn() ?: 0);

    if ($taskId > 0) {
        vms_hour_complete_maintenance_task($pdo, $taskId, $completionDate, (float)$completionHours, $note, $performedBy !== '' ? $performedBy : null);
    } else {
        $scheduleStmt = $pdo->prepare("SELECT equipment_id FROM equipment_maintenance_schedules WHERE schedule_id = ? LIMIT 1");
        $scheduleStmt->execute([$scheduleId]);
        $equipmentId = (int)($scheduleStmt->fetchColumn() ?: 0);
        vms_hour_backfill_maintenance($pdo, $scheduleId, $completionDate, (float)$completionHours, $note !== '' ? $note : 'Completion recorded without open task.', $performedBy !== '' ? $performedBy : null);
        $pdo->commit();
        header('Location: equipment_detail.php?id=' . $equipmentId . '&success=maintenance_completed');
        exit;
    }

    $scheduleStmt = $pdo->prepare("SELECT equipment_id FROM equipment_maintenance_schedules WHERE schedule_id = ? LIMIT 1");
    $scheduleStmt->execute([$scheduleId]);
    $equipmentId = (int)($scheduleStmt->fetchColumn() ?: 0);

    $pdo->commit();
    header('Location: equipment_detail.php?id=' . $equipmentId . '&success=maintenance_completed');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to complete maintenance: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
