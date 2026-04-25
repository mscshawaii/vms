<?php
declare(strict_types=1);

if (!function_exists('vms_get_thread_deep_link')) {
    function vms_get_thread_deep_link(PDO $pdo, int $threadId): string
    {
        $stmt = $pdo->prepare("
            SELECT thread_id, thread_type, related_id, vessel_id
            FROM message_threads
            WHERE thread_id = ?
            LIMIT 1
        ");
        $stmt->execute([$threadId]);
        $thread = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            return '/auth_redirect.php?to=' . urlencode('/dashboard.php');
        }

        $type = $thread['thread_type'] ?? '';
        $relatedId = (int)($thread['related_id'] ?? 0);
        $vesselId = (int)($thread['vessel_id'] ?? 0);

        switch ($type) {

            case 'company_channel':
                $target = '/company_messages.php?thread_id=' . $threadId;
                break;

            case 'vessel_channel':
                $target = '/vessel_messages.php?vessel_id=' . $vesselId;
                break;

            case 'task_thread':
                $target = '/task_discussion.php?task_id=' . $relatedId;
                break;

            default:
                $target = '/dashboard.php';
                break;
        }

        return '/auth_redirect.php?to=' . urlencode($target);
    }
}