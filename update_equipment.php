<?php
require 'db_connect.php';

$eid = intval($_POST['eid'] ?? 0);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

if (!$eid || !$vessel_id) {
    die("❌ Missing equipment ID or vessel ID.");
}

$equipmentName = trim($_POST['equipmentName'] ?? '');
$equipmentLocation = trim($_POST['equipmentLocation'] ?? '');
$expDate = $_POST['expDate'] ?? null;
$quantity = intval($_POST['quantity'] ?? 0);
$unit = trim($_POST['unit'] ?? '');

$stmt = $pdo->prepare("
    UPDATE equipment 
    SET equipmentName = ?, equipmentLocation = ?, expDate = ?, quantity = ?, unit = ?
    WHERE eid = ?
");
$stmt->execute([
    $equipmentName,
    $equipmentLocation,
    $expDate,
    $quantity,
    $unit,
    $eid
]);

header("Location: vessel_dashboard.php?vessel_id=$vessel_id#equipment&success=equipment_updated");
exit;
