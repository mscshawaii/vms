<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$meterId = (int)($_POST['meter_id'] ?? 0);
$newHours = vms_hour_decimal_or_null($_POST['new_hours'] ?? null);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($meterId <= 0 || $newHours === null || $reason === '') {
    http_response_code(422);
    exit('Missing required correction fields.');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT equipment_id FROM equipment_hour_meters WHERE meter_id = ? LIMIT 1");
    $stmt->execute([$meterId]);
    $equipmentId = (int)($stmt->fetchColumn() ?: 0);

    vms_hour_apply_manual_correction($pdo, $meterId, (float)$newHours, $reason);
    $pdo->commit();
    header('Location: equipment_detail.php?id=' . $equipmentId . '&success=meter_corrected');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to correct meter: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
