<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$oldEquipmentId = (int)($_POST['old_equipment_id'] ?? 0);
$equipmentNameInput = trim((string)($_POST['equipmentName'] ?? ''));
$location = trim((string)($_POST['equipmentLocation'] ?? ''));
$installedHours = vms_hour_decimal_or_null($_POST['installed_hours'] ?? null);
$manufacturer = trim((string)($_POST['manufacturer'] ?? ''));
$modelNumber = trim((string)($_POST['modelNumber'] ?? ''));
$serialNumber = trim((string)($_POST['serialNumber'] ?? ''));
$installDate = trim((string)($_POST['installDate'] ?? ''));
$expDate = trim((string)($_POST['expDate'] ?? ''));
$copySchedules = ((int)($_POST['copy_schedules'] ?? 1) === 1);
$replacementNote = trim((string)($_POST['replacement_note'] ?? ''));

if ($oldEquipmentId <= 0 || $location === '') {
    http_response_code(422);
    exit('Missing required replacement fields.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM equipment WHERE eid = ? LIMIT 1");
    $stmt->execute([$oldEquipmentId]);
    $oldEquipment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldEquipment) {
        throw new RuntimeException('Original equipment not found.');
    }
    if ((int)($oldEquipment['vessel_id'] ?? 0) <= 0 || (int)($oldEquipment['is_active'] ?? 1) !== 1) {
        throw new RuntimeException('Only active equipment can be replaced.');
    }

    $typeStmt = $pdo->prepare("SELECT name FROM equipment_type WHERE id = ? LIMIT 1");
    $typeStmt->execute([(int)$oldEquipment['equipment_type_id']]);
    $typeName = trim((string)($typeStmt->fetchColumn() ?: ''));

    $subtypeStmt = $pdo->prepare("SELECT name FROM equipment_subtype WHERE id = ? LIMIT 1");
    $subtypeStmt->execute([(int)$oldEquipment['equipment_subtype_id']]);
    $subtypeName = trim((string)($subtypeStmt->fetchColumn() ?: ''));

    $oldMeter = vms_hour_get_meter_by_equipment($pdo, $oldEquipmentId);

    $parts = [];
    if ($typeName !== '') $parts[] = $typeName;
    if ($subtypeName !== '') $parts[] = $subtypeName;
    if ($location !== '') $parts[] = $location;
    $defaultEquipmentName = implode(' - ', $parts);
    $equipmentName = $equipmentNameInput !== '' ? $equipmentNameInput : $defaultEquipmentName;

    $insert = $pdo->prepare("
        INSERT INTO equipment (
            equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, installDate,
            expDate, quantity, unit, notes, onBoardNotRequired, vessel_id, is_active,
            category_id, type_id, subtype_id, equipment_type_id, equipment_subtype_id, photo_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, NULL)
    ");
    $insert->execute([
        $equipmentName,
        $location,
        $manufacturer !== '' ? $manufacturer : $oldEquipment['manufacturer'],
        $modelNumber !== '' ? $modelNumber : $oldEquipment['modelNumber'],
        $serialNumber !== '' ? $serialNumber : null,
        $installDate !== '' ? $installDate : null,
        $expDate !== '' ? $expDate : null,
        $oldEquipment['quantity'],
        $oldEquipment['unit'],
        $replacementNote !== '' ? $replacementNote : null,
        $oldEquipment['onBoardNotRequired'],
        $oldEquipment['vessel_id'],
        $oldEquipment['category_id'],
        $oldEquipment['type_id'],
        $oldEquipment['subtype_id'],
        $oldEquipment['equipment_type_id'],
        $oldEquipment['equipment_subtype_id'],
    ]);

    $newEquipmentId = (int)$pdo->lastInsertId();

    $retireStmt = $pdo->prepare("
        UPDATE equipment
        SET is_active = 0,
            retired_at = NOW(),
            retirement_reason = ?,
            replaced_by_eid = ?
        WHERE eid = ?
    ");
    $retireStmt->execute([
        $replacementNote !== '' ? $replacementNote : 'Replaced',
        $newEquipmentId,
        $oldEquipmentId,
    ]);

    if ($oldMeter) {
        if ($installedHours === null) {
            throw new RuntimeException('Installed meter reading is required when replacing tracked equipment.');
        }

        $tracking = [
            'hour_tracked' => 1,
            'tracked_class' => vms_hour_equipment_tracking_class((int)$oldEquipment['equipment_type_id'], (int)$oldEquipment['equipment_subtype_id']),
            'display_order' => (int)($oldMeter['display_order'] ?? 0),
            'meter_active' => 1,
            'baseline_hours' => (float)$installedHours,
        ];
        vms_hour_sync_equipment_meter($pdo, $newEquipmentId, (int)$oldEquipment['vessel_id'], $location, $tracking);
        vms_hour_replace_equipment($pdo, $oldEquipmentId, $newEquipmentId, (float)$installedHours, $copySchedules);
    }

    $pdo->commit();
    header('Location: equipment_detail.php?id=' . $newEquipmentId . '&success=equipment_replaced');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to replace equipment: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
