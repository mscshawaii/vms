<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_source_finder_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!vms_source_finder_user_can_manage_library()) {
    http_response_code(403);
    exit('Not authorized.');
}

$sourceId = (int)($_POST['source_id'] ?? 0);
$newStatus = trim((string)($_POST['new_status'] ?? ''));
$returnQuery = trim((string)($_POST['return_query'] ?? ''));

if ($sourceId <= 0 || !in_array($newStatus, ['active', 'inactive'], true)) {
    http_response_code(422);
    exit('Invalid source status request.');
}

if (!vms_source_finder_table_exists($pdo, 'equipment_manual_sources')) {
    http_response_code(500);
    exit('Source library table is not available.');
}

$stmt = $pdo->prepare("
    UPDATE equipment_manual_sources
    SET is_active = ?, updated_at = NOW()
    WHERE source_id = ?
");
$stmt->execute([$newStatus === 'active' ? 1 : 0, $sourceId]);

$redirect = 'equipment_manual_library.php';
if ($returnQuery !== '') {
    $redirect .= '?' . ltrim($returnQuery, '?&');
    $redirect .= '&status_saved=1';
} else {
    $redirect .= '?status_saved=1';
}

header('Location: ' . $redirect);
exit;
