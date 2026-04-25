<?php
require '../session_check.php';
require '../db_connect.php';
require '../includes/message_functions.php';
require '../includes/onesignal_service.php';
require '../includes/push_recipient_functions.php';
require '../includes/message_link_functions.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$threadId = (int)($input['thread_id'] ?? 0);
$body = trim((string)($input['body'] ?? ''));
$parentMessageId = !empty($input['parent_message_id']) ? (int)$input['parent_message_id'] : null;

if ($userId <= 0 || $threadId <= 0 || $body === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing thread_id or body.']);
    exit;
}

if (!userCanAccessThread($pdo, $threadId, $userId)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

try {
    // 1) Save the message first
    $messageId = postThreadMessage($pdo, $threadId, $userId, $body, $parentMessageId);

    // 2) Get sender name for response + push text
    $stmt = $pdo->prepare("
        SELECT u.fName, u.lName
        FROM users u
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    $author = trim(($u['fName'] ?? '') . ' ' . ($u['lName'] ?? ''));
    if ($author === '') {
        $author = 'User';
    }

    // 3) Look up thread/company context
    // Adjust table/column names here only if your actual thread table differs
    $stmt = $pdo->prepare("
        SELECT t.thread_id, t.company_id, t.title
        FROM message_threads t
        WHERE t.thread_id = ?
        LIMIT 1
    ");
    $stmt->execute([$threadId]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4) Resolve recipients
    // Current simple rule: everyone in same company except sender
    $recipientExternalIds = [];
    if ($thread && !empty($thread['company_id'])) {
        $recipientExternalIds = vms_get_company_thread_recipient_user_ids(
            $pdo,
            (int)$thread['company_id'],
            $userId
        );
    }

    // 5) Build notification
    $pushTitle = 'Company Message';

    $preview = trim(preg_replace('/\s+/', ' ', $body));
    $preview = mb_substr($preview, 0, 120);
    if (mb_strlen($body) > 120) {
        $preview .= '…';
    }

    $pushBody = $author . ': ' . $preview;

    $deepLink = vms_get_thread_deep_link($pdo, $threadId);

    // 6) Send push, but do not break message posting if push fails
    $pushResult = null;

    if (!empty($recipientExternalIds)) {
        try {
            $pushResult = vms_send_push_external_ids(
                $recipientExternalIds,
                $pushTitle,
                $pushBody,
                $deepLink,
                [
                    'type' => 'company_message',
                    'thread_id' => $threadId,
                    'message_id' => $messageId,
                    'parent_message_id' => $parentMessageId,
                ]
            );
        } catch (Throwable $pushError) {
            error_log('OneSignal push exception in post_thread_message.php: ' . $pushError->getMessage());
        }
    }

    echo json_encode([
        'ok' => true,
        'message' => [
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'parent_message_id' => $parentMessageId,
            'user_id' => $userId,
            'author' => $author,
            'me' => true,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
            'edited_at' => null,
            'message_type' => 'user'
        ],
        'push' => [
            'attempted' => !empty($recipientExternalIds),
            'recipient_count' => count($recipientExternalIds),
            'ok' => is_array($pushResult) ? (bool)($pushResult['ok'] ?? false) : false
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}