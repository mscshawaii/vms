<?php
declare(strict_types=1);

require '../session_check.php';
require '../db_connect.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$externalId = trim((string)($input['external_id'] ?? ''));
$onesignalId = trim((string)($input['onesignal_id'] ?? ''));
$subscriptionId = trim((string)($input['subscription_id'] ?? ''));
$platform = trim((string)($input['platform'] ?? 'web'));
$isActive = !empty($input['is_active']) ? 1 : 0;

if ($externalId === '') {
    $externalId = (string)$userId;
}

if ($externalId !== (string)$userId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'External ID mismatch']);
    exit;
}

if ($subscriptionId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing subscription_id']);
    exit;
}

try {
    $check = $pdo->prepare("
        SELECT id
        FROM user_push_subscriptions
        WHERE provider = 'onesignal'
          AND external_id = ?
          AND subscription_id = ?
        LIMIT 1
    ");
    $check->execute([$externalId, $subscriptionId]);
    $existingId = (int)($check->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $stmt = $pdo->prepare("
            UPDATE user_push_subscriptions
            SET
                user_id = ?,
                onesignal_id = ?,
                platform = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $userId,
            $onesignalId !== '' ? $onesignalId : null,
            $platform !== '' ? $platform : null,
            $isActive,
            $existingId
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_push_subscriptions (
                user_id,
                provider,
                external_id,
                onesignal_id,
                subscription_id,
                platform,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, 'onesignal', ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $externalId,
            $onesignalId !== '' ? $onesignalId : null,
            $subscriptionId,
            $platform !== '' ? $platform : null,
            $isActive
        ]);
    }

    echo json_encode([
        'ok' => true,
        'user_id' => $userId,
        'external_id' => $externalId,
        'subscription_id' => $subscriptionId,
        'is_active' => $isActive
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}