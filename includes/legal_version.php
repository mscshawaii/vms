<?php
declare(strict_types=1);

const VMS_LEGAL_VERSION = '2026-04-05.1';
const VMS_LEGAL_LAST_UPDATED = 'April 5, 2026';

function vms_get_client_ip(): ?string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }

        $parts = explode(',', $value);
        $ip = trim($parts[0]);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return null;
}

function vms_user_has_current_legal_ack(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_legal_acknowledgements
        WHERE user_id = ?
          AND legal_version = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, VMS_LEGAL_VERSION]);

    return (bool)$stmt->fetchColumn();
}

function vms_record_legal_ack(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO user_legal_acknowledgements (
            user_id,
            legal_version,
            accepted_at,
            ip_address,
            user_agent
        ) VALUES (?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            accepted_at = VALUES(accepted_at),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent)
    ");

    $stmt->execute([
        $userId,
        VMS_LEGAL_VERSION,
        vms_get_client_ip(),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}