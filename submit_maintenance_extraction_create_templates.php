<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_template_extraction_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!vms_template_user_can_manage()) {
    http_response_code(403);
    exit('Not authorized.');
}

$runId = trim((string)($_POST['run_id'] ?? ''));
$sourceId = (int)($_POST['source_id'] ?? 0);
$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$userId = vms_template_current_user_id();

if ($runId === '' || $sourceId <= 0 || $userId <= 0) {
    http_response_code(422);
    exit('Missing run or user context.');
}

$source = vms_template_get_source($pdo, $sourceId);
if (!$source) {
    http_response_code(404);
    exit('Saved source not found.');
}

$reviewRedirect = 'maintenance_extraction_review.php?run_id=' . urlencode($runId) . '&source_id=' . $sourceId;
if ($equipmentId > 0) {
    $reviewRedirect .= '&equipment_id=' . $equipmentId;
}

try {
    $pdo->beginTransaction();
    $result = vms_template_create_templates_from_accepted_rows($pdo, $source, $runId, $userId);
    $pdo->commit();

    $templateRedirect = 'maintenance_template_extract.php?source_id=' . $sourceId . '&status=extract_complete';
    if ($equipmentId > 0) {
        $templateRedirect .= '&equipment_id=' . $equipmentId;
    }
    $_SESSION['maintenance_template_extract_flash'] = [
        'type' => $result['inserted_template_count'] > 0 ? 'success' : 'warning',
        'message' => $result['inserted_template_count'] > 0
            ? 'Draft templates created from accepted extracted rows.'
            : 'No draft templates were created from the accepted rows.',
        'debug' => [
            'raw_candidate_count' => (int)$result['accepted_count'],
            'grouped_row_count' => (int)$result['grouped_count'],
            'grouped_draft_count' => (int)$result['inserted_template_count'],
        ],
    ];
    header('Location: ' . $templateRedirect);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['maintenance_extraction_review_flash'] = [
        'type' => 'warning',
        'message' => 'Unable to create draft templates: ' . $e->getMessage(),
    ];
    header('Location: ' . $reviewRedirect);
    exit;
}
