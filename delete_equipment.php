<?php
require 'db_connect.php';

$eid = intval($_GET['id'] ?? 0);

if (!$eid) {
    die("❌ Equipment ID missing.");
}

// Get vessel ID first
$stmt = $pdo->prepare("SELECT vessel_id FROM equipment WHERE eid = ?");
$stmt->execute([$eid]);
$vessel_id = $stmt->fetchColumn();

if (!$vessel_id) {
    die("❌ Vessel ID not found.");
}

// Delete the equipment
$del = $pdo->prepare("DELETE FROM equipment WHERE eid = ?");
$del->execute([$eid]);

header("Location: vessel_dashboard.php?vessel_id=$vessel_id#equipment&success=equipment_deleted");
exit;
