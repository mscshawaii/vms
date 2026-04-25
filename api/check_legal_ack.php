<?php
session_start();
require '../db_connect.php';
require_once __DIR__ . '/../includes/legal_version.php';

header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');

if ($username === '') {
    echo json_encode(['needs_ack' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['needs_ack' => false]);
    exit;
}

$userId = (int)$user['id'];

$needsAck = !vms_user_has_current_legal_ack($pdo, $userId);

echo json_encode([
    'needs_ack' => $needsAck
]);