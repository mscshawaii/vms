<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/session_check.php';

$id        = (int)($_GET['id'] ?? 0);
$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$action    = $_GET['action'] ?? 'archive'; // 'archive' | 'unarchive'

if ($id <= 0 || $vessel_id <= 0) { http_response_code(400); exit('Bad request'); }

if ($action === 'unarchive') {
    $sql = "UPDATE documents SET archived_at = NULL WHERE id = ? AND related_to='vessel' AND vessel_id = ?";
} else {
    $sql = "UPDATE documents SET archived_at = NOW() WHERE id = ? AND related_to='vessel' AND vessel_id = ?";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $vessel_id]);

// Bounce back to the tab; preserve anchor so it reopens on Documents
header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#documents");
exit;
