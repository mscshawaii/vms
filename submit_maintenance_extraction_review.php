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

$action = trim((string)($_POST['action'] ?? 'save_review'));
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

$redirect = 'maintenance_extraction_review.php?run_id=' . urlencode($runId) . '&source_id=' . $sourceId;
if ($equipmentId > 0) {
    $redirect .= '&equipment_id=' . $equipmentId;
}

try {
    $pdo->beginTransaction();

    if ($action === 'add_row') {
        $inserted = vms_template_insert_extraction_row($pdo, $source, $runId, [
            'item_name' => trim((string)($_POST['item_name'] ?? '')),
            'action_name' => trim((string)($_POST['action_name'] ?? '')),
            'combined_step' => trim((string)($_POST['combined_step'] ?? '')),
            'interval_label' => trim((string)($_POST['interval_label'] ?? '')),
            'interval_hours' => trim((string)($_POST['interval_hours'] ?? '')),
            'interval_months' => trim((string)($_POST['interval_months'] ?? '')),
            'interval_basis' => trim((string)($_POST['interval_basis'] ?? '')),
            'marked_cell_value' => trim((string)($_POST['marked_cell_value'] ?? '')),
            'source_excerpt' => trim((string)($_POST['source_excerpt'] ?? '')),
            'footnote_refs' => trim((string)($_POST['footnote_refs'] ?? '')),
            'confidence_label' => trim((string)($_POST['confidence_label'] ?? 'Manual review entry')),
        ], $userId);

        $pdo->commit();
        $_SESSION['maintenance_extraction_review_flash'] = [
            'type' => $inserted > 0 ? 'success' : 'warning',
            'message' => $inserted > 0 ? 'Review row added.' : 'No review row was added.',
        ];
        header('Location: ' . $redirect);
        exit;
    }

    $rowIds = array_keys((array)($_POST['review_status'] ?? []));
    foreach ($rowIds as $rowId) {
        $rowId = (int)$rowId;
        if ($rowId <= 0) {
            continue;
        }

        vms_template_update_extraction_row($pdo, $rowId, $source, [
            'item_name' => $_POST['item_name'][$rowId] ?? '',
            'action_name' => $_POST['action_name'][$rowId] ?? '',
            'combined_step' => $_POST['combined_step'][$rowId] ?? '',
            'interval_label' => $_POST['interval_label'][$rowId] ?? '',
            'interval_hours' => $_POST['interval_hours'][$rowId] ?? '',
            'interval_months' => $_POST['interval_months'][$rowId] ?? '',
            'interval_basis' => $_POST['interval_basis'][$rowId] ?? '',
            'marked_cell_value' => $_POST['marked_cell_value'][$rowId] ?? '',
            'source_excerpt' => $_POST['source_excerpt'][$rowId] ?? '',
            'footnote_refs' => $_POST['footnote_refs'][$rowId] ?? '',
            'confidence_label' => $_POST['confidence_label'][$rowId] ?? '',
            'review_status' => $_POST['review_status'][$rowId] ?? 'pending',
        ], $userId);
    }

    $rows = vms_template_get_extraction_rows($pdo, $runId);
    $summary = vms_template_get_extraction_review_summary($rows);
    vms_template_update_extraction_run($pdo, $runId, [
        'status' => $summary['accepted'] > 0 ? 'reviewed' : 'pending_review',
    ]);

    $pdo->commit();
    $_SESSION['maintenance_extraction_review_flash'] = [
        'type' => 'success',
        'message' => 'Extracted row review saved.',
    ];
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to save extraction review: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
