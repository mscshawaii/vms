<?php
require 'db.php';

$assignment_id = intval($_GET['id'] ?? 0);
$vessel_id = intval($_GET['vessel'] ?? 0);

if ($assignment_id && $vessel_id) {
    $stmt = $mysqli->prepare("DELETE FROM vessel_crew WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $assignment_id);
        $stmt->execute();
        $stmt->close();
    } else {
        die("❌ Failed to prepare delete: " . $mysqli->error);
    }
}

header("Location: vessel_dashboard.php?id=$vessel_id#crew");
exit;
