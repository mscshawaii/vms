<?php
if (!function_exists('message_h')) {
    function message_h($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ensureTaskThread')) {
    function ensureTaskThread(PDO $pdo, int $taskId, int $createdBy = 0): int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'task_thread'
              AND related_id = ?
            LIMIT 1
        ");
        $stmt->execute([$taskId]);
        $threadId = (int)($stmt->fetchColumn() ?: 0);

        if ($threadId > 0) {
            return $threadId;
        }

        $taskStmt = $pdo->prepare("
            SELECT task_id, vessel_id, title
            FROM tasks
            WHERE task_id = ?
            LIMIT 1
        ");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new RuntimeException("Task not found for thread creation.");
        }

        $title = 'CAR Discussion: ' . ($task['title'] ?? ('Task #' . $taskId));

        $ins = $pdo->prepare("
            INSERT INTO message_threads (
                thread_type,
                related_id,
                company_id,
                vessel_id,
                title,
                channel_slug,
                is_channel,
                is_default,
                is_locked,
                is_archived,
                created_by,
                created_at
            )
            SELECT
                'task_thread',
                ?,
                v.company_id,
                t.vessel_id,
                ?,
                NULL,
                0,
                0,
                0,
                0,
                ?,
                NOW()
            FROM tasks t
            INNER JOIN vessels v ON v.vessel_id = t.vessel_id
            WHERE t.task_id = ?
            LIMIT 1
        ");
        $ins->execute([$taskId, $title, $createdBy, $taskId]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('syncTaskThreadMembers')) {
    function syncTaskThreadMembers(PDO $pdo, int $taskId, int $addedBy = 0): int
    {
        $threadId = ensureTaskThread($pdo, $taskId, $addedBy);

        $taskStmt = $pdo->prepare("
            SELECT t.task_id, t.vessel_id, t.assigned_to, t.created_by, v.company_id
            FROM tasks t
            INNER JOIN vessels v ON v.vessel_id = t.vessel_id
            WHERE t.task_id = ?
            LIMIT 1
        ");
        $taskStmt->execute([$taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            throw new RuntimeException("Task not found for member sync.");
        }

        $memberIds = [];

        // task creator if schema has it and value exists
        if (!empty($task['created_by'])) {
            $memberIds[] = (int)$task['created_by'];
        }

        // assigned user
        if (!empty($task['assigned_to'])) {
            $memberIds[] = (int)$task['assigned_to'];
        }

        // notify recipients
        try {
            $notifyStmt = $pdo->prepare("
                SELECT user_id
                FROM task_notification_recipients
                WHERE task_id = ?
            ");
            $notifyStmt->execute([$taskId]);
            foreach ($notifyStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
                $memberIds[] = (int)$uid;
            }
        } catch (Throwable $e) {
            // safe fallback if table is unavailable in some environment
        }

        // dedupe/filter
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), fn($v) => $v > 0)));

        if (empty($memberIds)) {
            return $threadId;
        }

        // Keep only active users assigned to this vessel
        $validStmt = $pdo->prepare("
            SELECT DISTINCT u.id
            FROM vessel_crew vc
            INNER JOIN users u ON u.id = vc.crew_id
            WHERE vc.vessel_id = ?
              AND vc.is_active = 1
              AND u.is_active = 1
        ");
        $validStmt->execute([(int)$task['vessel_id']]);
        $validIds = array_map('intval', $validStmt->fetchAll(PDO::FETCH_COLUMN));
        $validSet = array_flip($validIds);

        $memberIds = array_values(array_filter($memberIds, fn($id) => isset($validSet[$id])));

        if (empty($memberIds)) {
            return $threadId;
        }

        $ins = $pdo->prepare("
            INSERT IGNORE INTO message_thread_members (
                thread_id,
                user_id,
                member_role,
                notifications_enabled,
                muted,
                joined_at,
                added_by
            ) VALUES (?, ?, 'member', 1, 0, NOW(), ?)
        ");

        foreach ($memberIds as $uid) {
            $ins->execute([$threadId, $uid, $addedBy ?: null]);
        }

        return $threadId;
    }
}

if (!function_exists('getTaskThreadId')) {
    function getTaskThreadId(PDO $pdo, int $taskId): ?int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'task_thread'
              AND related_id = ?
            LIMIT 1
        ");
        $stmt->execute([$taskId]);
        $threadId = $stmt->fetchColumn();

        return $threadId ? (int)$threadId : null;
    }
}

if (!function_exists('getThreadMessageCount')) {
    function getThreadMessageCount(PDO $pdo, int $threadId): int
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages
            WHERE thread_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$threadId]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('userCanAccessThread')) {
    function userCanAccessThread(PDO $pdo, int $threadId, int $userId): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM message_thread_members
            WHERE thread_id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$threadId, $userId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('getThreadMessages')) {
    function getThreadMessages(PDO $pdo, int $threadId): array
    {
        $stmt = $pdo->prepare("
            SELECT
                m.message_id,
                m.thread_id,
                m.parent_message_id,
                m.user_id,
                m.body,
                m.message_type,
                m.created_at,
                m.edited_at,
                u.fName,
                u.lName
            FROM messages m
            LEFT JOIN users u ON u.id = m.user_id
            WHERE m.thread_id = ?
              AND m.deleted_at IS NULL
            ORDER BY m.created_at ASC, m.message_id ASC
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('postThreadMessage')) {
    function postThreadMessage(PDO $pdo, int $threadId, int $userId, string $body, ?int $parentMessageId = null): int
    {
        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException("Message body cannot be empty.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (
                thread_id,
                parent_message_id,
                user_id,
                body,
                message_type,
                created_at
            ) VALUES (?, ?, ?, ?, 'user', NOW())
        ");
        $stmt->execute([$threadId, $parentMessageId, $userId, $body]);

        $messageId = (int)$pdo->lastInsertId();

        $upd = $pdo->prepare("
            UPDATE message_threads
            SET last_message_at = NOW(),
                updated_at = NOW()
            WHERE thread_id = ?
        ");
        $upd->execute([$threadId]);

        return $messageId;
    }
}

if (!function_exists('getLatestThreadMessageId')) {
    function getLatestThreadMessageId(PDO $pdo, int $threadId): ?int
    {
        $stmt = $pdo->prepare("
            SELECT MAX(message_id)
            FROM messages
            WHERE thread_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$threadId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }
}

if (!function_exists('getThreadUnreadCount')) {
    function getThreadUnreadCount(PDO $pdo, int $threadId, int $userId): int
    {
        $memberStmt = $pdo->prepare("
            SELECT last_read_message_id
            FROM message_thread_members
            WHERE thread_id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $memberStmt->execute([$threadId, $userId]);
        $lastRead = $memberStmt->fetchColumn();
        $lastRead = $lastRead ? (int)$lastRead : 0;

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM messages
            WHERE thread_id = ?
              AND deleted_at IS NULL
              AND user_id <> ?
              AND message_id > ?
        ");
        $stmt->execute([$threadId, $userId, $lastRead]);

        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('markThreadRead')) {
    function markThreadRead(PDO $pdo, int $threadId, int $userId): void
    {
        $latestId = getLatestThreadMessageId($pdo, $threadId);

        $stmt = $pdo->prepare("
            UPDATE message_thread_members
            SET last_read_message_id = ?,
                last_read_at = NOW()
            WHERE thread_id = ?
              AND user_id = ?
        ");
        $stmt->execute([$latestId, $threadId, $userId]);
    }
}

if (!function_exists('ensureVesselGeneralThread')) {
    function ensureVesselGeneralThread(PDO $pdo, int $vesselId, int $createdBy = 0): int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'vessel_channel'
              AND related_id = ?
              AND is_default = 1
            LIMIT 1
        ");
        $stmt->execute([$vesselId]);
        $threadId = (int)($stmt->fetchColumn() ?: 0);

        if ($threadId > 0) {
            return $threadId;
        }

        $vesselStmt = $pdo->prepare("
            SELECT vessel_id, vesselName, company_id
            FROM vessels
            WHERE vessel_id = ?
            LIMIT 1
        ");
        $vesselStmt->execute([$vesselId]);
        $vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vessel) {
            throw new RuntimeException("Vessel not found for vessel thread creation.");
        }

        $title = ($vessel['vesselName'] ?? 'Vessel') . ' - General';

        $ins = $pdo->prepare("
            INSERT INTO message_threads (
                thread_type,
                related_id,
                company_id,
                vessel_id,
                title,
                channel_slug,
                is_channel,
                is_default,
                is_locked,
                is_archived,
                created_by,
                created_at
            ) VALUES (
                'vessel_channel',
                ?,
                ?,
                ?,
                ?,
                'general',
                1,
                1,
                0,
                0,
                ?,
                NOW()
            )
        ");
        $ins->execute([
            $vesselId,
            (int)$vessel['company_id'],
            $vesselId,
            $title,
            $createdBy
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('syncVesselThreadMembers')) {
    function syncVesselThreadMembers(PDO $pdo, int $vesselId, int $addedBy = 0): int
    {
        $threadId = ensureVesselGeneralThread($pdo, $vesselId, $addedBy);

        $vesselStmt = $pdo->prepare("
            SELECT vessel_id, company_id
            FROM vessels
            WHERE vessel_id = ?
            LIMIT 1
        ");
        $vesselStmt->execute([$vesselId]);
        $vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);

        if (!$vessel) {
            throw new RuntimeException("Vessel not found for vessel member sync.");
        }

        $memberIds = [];

        // Active vessel-assigned users
        $crewStmt = $pdo->prepare("
            SELECT DISTINCT u.id
            FROM vessel_crew vc
            INNER JOIN users u ON u.id = vc.crew_id
            WHERE vc.vessel_id = ?
              AND vc.is_active = 1
              AND u.is_active = 1
        ");
        $crewStmt->execute([$vesselId]);
        foreach ($crewStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $memberIds[] = (int)$uid;
        }

        // Optional: add active users in company with elevated roles later if desired.
        // For now keep it simple and vessel-centered.

        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), fn($v) => $v > 0)));

        if (empty($memberIds)) {
            return $threadId;
        }

        $ins = $pdo->prepare("
            INSERT IGNORE INTO message_thread_members (
                thread_id,
                user_id,
                member_role,
                notifications_enabled,
                muted,
                joined_at,
                added_by
            ) VALUES (?, ?, 'member', 1, 0, NOW(), ?)
        ");

        foreach ($memberIds as $uid) {
            $ins->execute([$threadId, $uid, $addedBy ?: null]);
        }

        return $threadId;
    }
}

if (!function_exists('getVesselThreadId')) {
    function getVesselThreadId(PDO $pdo, int $vesselId): ?int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'vessel_channel'
              AND related_id = ?
              AND is_default = 1
            LIMIT 1
        ");
        $stmt->execute([$vesselId]);
        $threadId = $stmt->fetchColumn();

        return $threadId ? (int)$threadId : null;
    }
}

if (!function_exists('ensureCompanyGeneralThread')) {
    function ensureCompanyGeneralThread(PDO $pdo, int $companyId, int $createdBy = 0): int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'company_channel'
              AND related_id = ?
              AND is_default = 1
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $threadId = (int)($stmt->fetchColumn() ?: 0);

        if ($threadId > 0) {
            return $threadId;
        }

        $companyStmt = $pdo->prepare("
            SELECT owner_id, company_name
            FROM owners
            WHERE owner_id = ?
            LIMIT 1
        ");
        $companyStmt->execute([$companyId]);
        $company = $companyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$company) {
            throw new RuntimeException("Company not found for company thread creation.");
        }

        $title = ($company['company_name'] ?? 'Company') . ' - General';

        $ins = $pdo->prepare("
            INSERT INTO message_threads (
                thread_type,
                related_id,
                company_id,
                vessel_id,
                title,
                channel_slug,
                is_channel,
                is_default,
                is_locked,
                is_archived,
                created_by,
                created_at
            ) VALUES (
                'company_channel',
                ?,
                ?,
                NULL,
                ?,
                'general',
                1,
                1,
                0,
                0,
                ?,
                NOW()
            )
        ");
        $ins->execute([
            $companyId,
            $companyId,
            $title,
            $createdBy
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('syncCompanyThreadMembers')) {
    function syncCompanyThreadMembers(PDO $pdo, int $companyId, int $addedBy = 0): int
    {
        $threadId = ensureCompanyGeneralThread($pdo, $companyId, $addedBy);

        $memberIds = [];

        $userStmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE company_id = ?
              AND is_active = 1
        ");
        $userStmt->execute([$companyId]);

        foreach ($userStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $memberIds[] = (int)$uid;
        }

        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), fn($v) => $v > 0)));

        if (empty($memberIds)) {
            return $threadId;
        }

        $ins = $pdo->prepare("
            INSERT IGNORE INTO message_thread_members (
                thread_id,
                user_id,
                member_role,
                notifications_enabled,
                muted,
                joined_at,
                added_by
            ) VALUES (?, ?, 'member', 1, 0, NOW(), ?)
        ");

        foreach ($memberIds as $uid) {
            $ins->execute([$threadId, $uid, $addedBy ?: null]);
        }

        return $threadId;
    }
}

if (!function_exists('getCompanyThreadId')) {
    function getCompanyThreadId(PDO $pdo, int $companyId): ?int
    {
        $stmt = $pdo->prepare("
            SELECT thread_id
            FROM message_threads
            WHERE thread_type = 'company_channel'
              AND related_id = ?
              AND is_default = 1
            LIMIT 1
        ");
        $stmt->execute([$companyId]);
        $threadId = $stmt->fetchColumn();

        return $threadId ? (int)$threadId : null;
    }
}