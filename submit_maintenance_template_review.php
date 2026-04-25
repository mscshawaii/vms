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

$sourceId = (int)($_POST['source_id'] ?? 0);
$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$reviewedBy = vms_template_current_user_id();

if ($sourceId <= 0 || $reviewedBy <= 0 || !vms_template_table_exists($pdo)) {
    http_response_code(422);
    exit('Missing review context.');
}

$templates = vms_template_get_templates_for_source($pdo, $sourceId);
$templateMap = [];
foreach ($templates as $template) {
    $templateMap[(int)$template['template_id']] = $template;
}

$serviceNames = $_POST['service_name'] ?? [];
$intervalHours = $_POST['interval_hours'] ?? [];
$intervalMonths = $_POST['interval_months'] ?? [];
$intervalBasis = $_POST['interval_basis'] ?? [];
$steps = $_POST['steps'] ?? [];
$sourceExcerpts = $_POST['source_excerpt'] ?? [];
$confidenceLabels = $_POST['confidence_label'] ?? [];
$reviewStatuses = $_POST['review_status'] ?? [];

try {
    $pdo->beginTransaction();

    foreach ($templateMap as $templateId => $current) {
        $serviceName = trim((string)($serviceNames[$templateId] ?? $current['service_name'] ?? ''));
        if ($serviceName === '') {
            $serviceName = trim((string)($current['service_name'] ?? ''));
        }

        $hoursRaw = trim((string)($intervalHours[$templateId] ?? ''));
        $monthsRaw = trim((string)($intervalMonths[$templateId] ?? ''));
        $hours = $hoursRaw !== '' ? (int)$hoursRaw : null;
        $months = $monthsRaw !== '' ? (int)$monthsRaw : null;
        $basis = trim((string)($intervalBasis[$templateId] ?? ''));
        $rowSteps = trim((string)($steps[$templateId] ?? ''));
        $excerpt = trim((string)($sourceExcerpts[$templateId] ?? ''));
        $confidence = trim((string)($confidenceLabels[$templateId] ?? ''));
        $status = trim((string)($reviewStatuses[$templateId] ?? ($current['review_status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'approved', 'rejected'], true)) {
            $status = 'draft';
        }

        $reviewedAt = null;
        $reviewedByValue = null;
        if ($status === 'approved' || $status === 'rejected') {
            $reviewedAt = date('Y-m-d H:i:s');
            $reviewedByValue = $reviewedBy;
        }

        $stmt = $pdo->prepare("
            UPDATE equipment_maintenance_templates
            SET service_name = ?,
                interval_hours = ?,
                interval_months = ?,
                interval_basis = ?,
                steps = ?,
                source_excerpt = ?,
                confidence_label = ?,
                review_status = ?,
                reviewed_by = ?,
                reviewed_at = ?,
                updated_at = NOW()
            WHERE template_id = ?
              AND source_id = ?
        ");
        $stmt->execute([
            $serviceName,
            $hours,
            $months,
            $basis !== '' ? $basis : null,
            $rowSteps !== '' ? $rowSteps : null,
            $excerpt !== '' ? $excerpt : null,
            $confidence !== '' ? $confidence : null,
            $status,
            $reviewedByValue,
            $reviewedAt,
            $templateId,
            $sourceId,
        ]);
    }

    $pdo->commit();
    $redirect = 'maintenance_template_extract.php?source_id=' . $sourceId . '&status=review_saved';
    if ($equipmentId > 0) {
        $redirect .= '&equipment_id=' . $equipmentId;
    }
    header('Location: ' . $redirect);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    exit('Failed to save maintenance draft review: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
