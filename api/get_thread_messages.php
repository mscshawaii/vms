<?php
require '../session_check.php';
require '../db_connect.php';
require '../includes/message_functions.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$threadId = (int)($_GET['thread_id'] ?? 0);

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

$messages = getThreadMessages($pdo, $threadId);

$out = array_map(function ($m) use ($userId) {
    $author = trim(($m['fName'] ?? '') . ' ' . ($m['lName'] ?? ''));
    if ($author === '') {
        $author = 'User #' . (int)$m['user_id'];
    }

    return [
        'message_id' => (int)$m['message_id'],
        'thread_id' => (int)$m['thread_id'],
        'parent_message_id' => $m['parent_message_id'] ? (int)$m['parent_message_id'] : null,
        'user_id' => (int)$m['user_id'],
        'author' => $author,
        'me' => ((int)$m['user_id'] === $userId),
        'body' => $m['body'],
        'created_at' => $m['created_at'],
        'edited_at' => $m['edited_at'],
        'message_type' => $m['message_type']
    ];
}, $messages);

echo json_encode(['ok' => true, 'messages' => $out]);