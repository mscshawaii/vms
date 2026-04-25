<?php
require '../session_check.php';
require '../db_connect.php';
require '../includes/message_functions.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$threadId = (int)($input['thread_id'] ?? 0);

if ($userId <= 0 || $threadId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing thread_id or user session.']);
    exit;
}

if (!userCanAccessThread($pdo, $threadId, $userId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

try {
    markThreadRead($pdo, $threadId, $userId);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}