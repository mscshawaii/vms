<?php
require 'db_connect.php';

$doc_id = intval($_GET['id'] ?? 0);

// Fetch vessel_id first
$stmt = $pdo->prepare("SELECT vessel_id FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$vessel_id = $stmt->fetchColumn();

// If not found, just go home
if (!$vessel_id) {
    header("Location: dashboard.php");
    exit;
}

// Delete document
$delStmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
$delStmt->execute([$doc_id]);

header("Location: vessel_dashboard.php?vessel_id=$vessel_id#documents&success=deleted");
exit;
