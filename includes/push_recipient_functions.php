<?php
declare(strict_types=1);

/**
 * Basic company thread recipients:
 * - all active users in the same company
 * - exclude sender
 *
 * You can tighten this later by vessel, role, thread membership, etc.
 */
if (!function_exists('vms_get_company_thread_recipient_user_ids')) {
    function vms_get_company_thread_recipient_user_ids(PDO $pdo, int $companyId, int $excludeUserId = 0): array
    {
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            WHERE u.company_id = ?
        ";

        $params = [$companyId];

        if ($excludeUserId > 0) {
            $sql .= " AND u.id <> ? ";
            $params[] = $excludeUserId;
        }

        $sql .= " ORDER BY u.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}