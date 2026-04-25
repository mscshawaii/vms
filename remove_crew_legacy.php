<?php
require 'db_connect.php';

$assignment_id = intval($_GET['id'] ?? 0);
$vessel_id     = intval($_GET['vessel'] ?? 0);

if (!$assignment_id || !$vessel_id) {
    exit("Missing required data.");
}

try {
    $stmt = $pdo->prepare("DELETE FROM vessel_crew WHERE id = ?");
    $stmt->execute([$assignment_id]);
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#crewModal");
    exit;
} catch (PDOException $e) {
    echo "Error removing crew member: " . $e->getMessage();
    exit;
}
