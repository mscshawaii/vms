<?php
declare(strict_types=1);

require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/checklist_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$vesselId = (int)($_POST['vessel_id'] ?? 0);
$templateId = (int)($_POST['template_id'] ?? 0);
$runType = checklist_normalize_run_type($_POST['run_type'] ?? '');
$companyId = (int)($_SESSION['company_id'] ?? 0);
$canAccessAllVessels = ($companyId === 1);
$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$responses = $_POST['responses'] ?? [];
$returnTo = checklist_normalize_return_to($_POST['return_to'] ?? '', $vesselId);

if ($vesselId <= 0 || $templateId <= 0 || $runType === null) {
    http_response_code(400);
    exit('Invalid checklist submission.');
}

$vessel = checklist_get_accessible_vessel($pdo, $vesselId, $canAccessAllVessels, $companyId);
if (!$vessel) {
    http_response_code(404);
    exit('Access denied or vessel not found.');
}

$template = checklist_get_template_by_type($pdo, $runType);
if (!$template || (int)$template['template_id'] !== $templateId) {
    http_response_code(400);
    exit('Checklist template mismatch.');
}

$coreItems = checklist_get_template_items($pdo, $templateId);
$suppressedCoreItemIds = checklist_get_suppressed_core_item_ids($pdo, $vesselId, $templateId);
$vesselItems = checklist_get_vessel_items($pdo, $vesselId, $templateId);
if (empty($coreItems) && empty($vesselItems)) {
    http_response_code(400);
    exit('Checklist has no active items.');
}

if (!is_array($responses)) {
    http_response_code(400);
    exit('Invalid checklist responses.');
}

$allowedValues = ['complete', 'not_complete', 'na'];

$coreItemMap = [];
foreach ($coreItems as $item) {
    $itemId = (int)$item['template_item_id'];
    if (in_array($itemId, $suppressedCoreItemIds, true)) {
        continue;
    }
    $coreItemMap[$itemId] = $item;
}

$vesselItemMap = [];
foreach ($vesselItems as $item) {
    $itemId = (int)$item['vessel_checklist_item_id'];
    $vesselItemMap[$itemId] = $item;
}

$expectedResponseKeys = [];
foreach (array_keys($coreItemMap) as $itemId) {
    $expectedResponseKeys['core:' . $itemId] = [
        'source' => 'core',
        'source_id' => $itemId,
    ];
}
foreach (array_keys($vesselItemMap) as $itemId) {
    $expectedResponseKeys['vessel:' . $itemId] = [
        'source' => 'vessel',
        'source_id' => $itemId,
    ];
}

foreach ($responses as $responseKey => $value) {
    if (!is_string($responseKey) || checklist_parse_response_key($responseKey) === null) {
        http_response_code(400);
        exit('Invalid checklist responses.');
    }
}

$responseRows = [];
foreach ($expectedResponseKeys as $responseKey => $itemMeta) {
    $value = $responses[$responseKey] ?? null;
    if (!is_string($value) || !in_array($value, $allowedValues, true)) {
        http_response_code(400);
        exit('Each checklist item requires a valid response.');
    }

    $responseRows[] = [
        'source' => $itemMeta['source'],
        'source_id' => (int)$itemMeta['source_id'],
        'response_value' => $value,
    ];
}

if (count($responses) !== count($expectedResponseKeys)) {
    http_response_code(400);
    exit('Invalid checklist responses.');
}

$pdo->beginTransaction();

try {
    $runStmt = $pdo->prepare("
        INSERT INTO checklist_runs (
            template_id,
            vessel_id,
            log_id,
            run_type,
            status,
            created_by,
            created_at
        ) VALUES (
            :template_id,
            :vessel_id,
            NULL,
            :run_type,
            'completed',
            :created_by,
            NOW()
        )
    ");

    $runStmt->execute([
        ':template_id' => $templateId,
        ':vessel_id' => $vesselId,
        ':run_type' => $runType,
        ':created_by' => $userId > 0 ? $userId : null,
    ]);

    $checklistRunId = (int)$pdo->lastInsertId();

    $itemStmt = $pdo->prepare("
        INSERT INTO checklist_run_items (
            checklist_run_id,
            template_item_id,
            vessel_checklist_item_id,
            response_value,
            response_note
        ) VALUES (
            :checklist_run_id,
            :template_item_id,
            :vessel_checklist_item_id,
            :response_value,
            NULL
        )
    ");

    foreach ($responseRows as $responseRow) {
        $itemStmt->execute([
            ':checklist_run_id' => $checklistRunId,
            ':template_item_id' => $responseRow['source'] === 'core' ? (int)$responseRow['source_id'] : null,
            ':vessel_checklist_item_id' => $responseRow['source'] === 'vessel' ? (int)$responseRow['source_id'] : null,
            ':response_value' => $responseRow['response_value'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to save checklist.');
}

$redirectUrl = checklist_append_query_params($returnTo, [
    'checklist_run_id' => $checklistRunId,
    'checklist_type' => $runType,
]);

header('Location: ' . $redirectUrl);
exit;
