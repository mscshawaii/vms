<?php
// update_equipment.php - Updated 2025-05-25 06:20:10
require 'db_connect.php';

$eid = intval($_POST['eid'] ?? 0);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

if (!$eid || !$vessel_id) {
    die("❌ Missing equipment ID or vessel ID.");
}

$equipmentLocation = trim($_POST['equipmentLocation'] ?? '');
$type_id = intval($_POST['equipment_type_id'] ?? 0);
$subtype_id = intval($_POST['equipment_subtype_id'] ?? 0);
$expDate = (!empty($_POST['expDate'])) ? $_POST['expDate'] : null;
$quantity = intval($_POST['quantity'] ?? 0);
$unit = trim($_POST['unit'] ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$modelNumber = trim($_POST['modelNumber'] ?? '');
$serialNumber = trim($_POST['serialNumber'] ?? '');
$installDate = (!empty($_POST['installDate'])) ? $_POST['installDate'] : null;
$onBoardNotRequired = $_POST['onBoardNotRequired'] ?? null;
$notes = trim($_POST['notes'] ?? '');

// Fetch type and subtype names from lookup tables
$typeStmt = $pdo->prepare("SELECT name FROM equipment_type WHERE id = ?");
$typeStmt->execute([$type_id]);
$typeRow = $typeStmt->fetch(PDO::FETCH_ASSOC);
$typeName = $typeRow['name'] ?? '';

$subtypeStmt = $pdo->prepare("SELECT name FROM equipment_subtype WHERE id = ?");
$subtypeStmt->execute([$subtype_id]);
$subtypeRow = $subtypeStmt->fetch(PDO::FETCH_ASSOC);
$subtypeName = $subtypeRow['name'] ?? '';


// Auto-generate Equipment Name: e.g., "Fire Extinguisher - ABC Type - Port Aft"
$equipmentName = trim("$typeName - $subtypeName - $equipmentLocation");

// Update
$stmt = $pdo->prepare("
    UPDATE equipment 
    SET equipmentName = ?, equipmentLocation = ?, expDate = ?, quantity = ?, unit = ?, 
        equipment_type_id = ?, equipment_subtype_id = ?, manufacturer = ?, modelNumber = ?, 
        serialNumber = ?, installDate = ?, onBoardNotRequired = ?, notes = ?
    WHERE eid = ?
");


$stmt->execute([
    $equipmentName,
    $equipmentLocation,
    $expDate,
    $quantity,
    $unit,
    $type_id,
    $subtype_id,
    $manufacturer,
    $modelNumber,
    $serialNumber,
    $installDate,
    $onBoardNotRequired,
    $notes,
    $eid
]);


header("Location: vessel_dashboard.php?vessel_id=$vessel_id#equipment&success=equipment_updated");
exit;
