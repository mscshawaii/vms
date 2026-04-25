<?php
declare(strict_types=1);

require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/checklist_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$action = trim((string)($_POST['action'] ?? ''));
$vesselId = (int)($_POST['vessel_id'] ?? 0);
$templateId = (int)($_POST['template_id'] ?? 0);
$runType = checklist_normalize_run_type($_POST['run_type'] ?? '');
$companyId = (int)($_SESSION['company_id'] ?? 0);
$canAccessAllVessels = ($companyId === 1);
$userId = (int)($_SESSION['user_id'] ?? ($_SESSION['id'] ?? 0));
$vesselChecklistItemId = (int)($_POST['vessel_checklist_item_id'] ?? 0);
$templateItemId = (int)($_POST['template_item_id'] ?? 0);
$itemLabel = trim((string)($_POST['item_label'] ?? ''));
$sortOrder = (int)($_POST['sort_order'] ?? 0);

if ($vesselId <= 0 || $templateId <= 0 || $runType === null) {
    http_response_code(400);
    exit('Invalid request.');
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

$redirectTo = 'checklist_manage_items.php?vessel_id=' . $vesselId . '&type=' . urlencode($runType);

if ($action === 'add') {
    if ($itemLabel === '') {
        http_response_code(400);
        exit('Item label is required.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO checklist_vessel_items (
            vessel_id,
            template_id,
            item_label,
            sort_order,
            is_active,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :vessel_id,
            :template_id,
            :item_label,
            :sort_order,
            1,
            :created_by,
            NOW(),
            NULL
        )
    ");
    $stmt->execute([
        ':vessel_id' => $vesselId,
        ':template_id' => $templateId,
        ':item_label' => mb_substr($itemLabel, 0, 255),
        ':sort_order' => $sortOrder,
        ':created_by' => $userId > 0 ? $userId : null,
    ]);

    header('Location: ' . $redirectTo);
    exit;
}

if (in_array($action, ['suppress_core', 'restore_core'], true)) {
    $coreItem = checklist_get_core_item_by_id($pdo, $templateItemId);
    if (!$coreItem || (int)$coreItem['template_id'] !== $templateId) {
        http_response_code(404);
        exit('Core checklist item not found.');
    }

    if ($action === 'suppress_core') {
        $stmt = $pdo->prepare("
            INSERT INTO checklist_vessel_item_suppressions (
                vessel_id,
                template_id,
                template_item_id,
                is_active,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :vessel_id,
                :template_id,
                :template_item_id,
                1,
                :created_by,
                NOW(),
                NULL
            )
            ON DUPLICATE KEY UPDATE
                is_active = 1,
                updated_at = NOW()
        ");
        $stmt->execute([
            ':vessel_id' => $vesselId,
            ':template_id' => $templateId,
            ':template_item_id' => $templateItemId,
            ':created_by' => $userId > 0 ? $userId : null,
        ]);

        header('Location: ' . $redirectTo);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE checklist_vessel_item_suppressions
        SET is_active = 0,
            updated_at = NOW()
        WHERE vessel_id = :vessel_id
          AND template_id = :template_id
          AND template_item_id = :template_item_id
        LIMIT 1
    ");
    $stmt->execute([
        ':vessel_id' => $vesselId,
        ':template_id' => $templateId,
        ':template_item_id' => $templateItemId,
    ]);

    header('Location: ' . $redirectTo);
    exit;
}

if (!in_array($action, ['update', 'deactivate'], true)) {
    http_response_code(400);
    exit('Invalid action.');
}

$existingItem = checklist_get_vessel_item_by_id($pdo, $vesselChecklistItemId);
if (!$existingItem) {
    http_response_code(404);
    exit('Checklist item not found.');
}

if ((int)$existingItem['vessel_id'] !== $vesselId || (int)$existingItem['template_id'] !== $templateId) {
    http_response_code(404);
    exit('Checklist item not found.');
}

if ($action === 'update') {
    if ($itemLabel === '') {
        http_response_code(400);
        exit('Item label is required.');
    }

    $stmt = $pdo->prepare("
        UPDATE checklist_vessel_items
        SET item_label = :item_label,
            sort_order = :sort_order,
            updated_at = NOW()
        WHERE vessel_checklist_item_id = :vessel_checklist_item_id
          AND vessel_id = :vessel_id
          AND template_id = :template_id
        LIMIT 1
    ");
    $stmt->execute([
        ':item_label' => mb_substr($itemLabel, 0, 255),
        ':sort_order' => $sortOrder,
        ':vessel_checklist_item_id' => $vesselChecklistItemId,
        ':vessel_id' => $vesselId,
        ':template_id' => $templateId,
    ]);

    header('Location: ' . $redirectTo);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE checklist_vessel_items
    SET is_active = 0,
        updated_at = NOW()
    WHERE vessel_checklist_item_id = :vessel_checklist_item_id
      AND vessel_id = :vessel_id
      AND template_id = :template_id
    LIMIT 1
");
$stmt->execute([
    ':vessel_checklist_item_id' => $vesselChecklistItemId,
    ':vessel_id' => $vesselId,
    ':template_id' => $templateId,
]);

header('Location: ' . $redirectTo);
exit;
