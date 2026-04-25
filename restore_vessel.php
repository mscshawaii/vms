<?php
require 'session_check.php';
require 'db_connect.php';

// Only MSCS Hawaii (owner_id=1) or admins can restore
if (($_SESSION['company_id'] ?? null) != 1 && !($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    exit('Not authorized.');
}

$vessel_id = isset($_POST['vessel_id']) ? (int)$_POST['vessel_id'] : 0;
if ($vessel_id <= 0) exit('Invalid vessel.');

$stmt = $pdo->prepare("UPDATE vessels SET is_active = 1, archived_at = NULL WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);

$show = $_GET['show'] ?? 'archived';
header('Location: dashboard.php?show=' . urlencode($show));
exit;
