<?php
require 'db.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$crew_id = intval($_POST['crew_id'] ?? 0);
$role = trim($_POST['role'] ?? '');

if ($vessel_id && $crew_id) {
    $stmt = $mysqli->prepare("INSERT INTO vessel_crew (vessel_id, crew_id, role, assigned_on) VALUES (?, ?, ?, CURDATE())");

    if (!$stmt) {
        die("❌ Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("iis", $vessel_id, $crew_id, $role);
    $stmt->execute();
    $stmt->close();
}

header("Location: vessel_dashboard.php?id=$vessel_id#crew");
exit;

