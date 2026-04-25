<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_source_finder_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$approvedBy = (int)($_SESSION['user_id'] ?? 0);
if ($approvedBy <= 0) {
    http_response_code(403);
    exit('You must be logged in to save a source.');
}

$equipmentId = (int)($_POST['equipment_id'] ?? 0);
$payload = [
    'equipment_id' => $equipmentId > 0 ? $equipmentId : null,
    'equipment_type' => trim((string)($_POST['equipment_type'] ?? '')),
    'manufacturer' => trim((string)($_POST['manufacturer'] ?? '')),
    'model' => trim((string)($_POST['model'] ?? '')),
    'serial_or_year' => trim((string)($_POST['serial_or_year'] ?? '')),
    'title' => trim((string)($_POST['title'] ?? '')),
    'source_url' => trim((string)($_POST['source_url'] ?? '')),
    'source_domain' => trim((string)($_POST['source_domain'] ?? '')),
    'source_type' => trim((string)($_POST['source_type'] ?? '')),
    'confidence_label' => trim((string)($_POST['confidence_label'] ?? '')),
    'notes' => trim((string)($_POST['notes'] ?? '')),
];

$redirectParams = [
    'equipment_id' => $equipmentId > 0 ? $equipmentId : null,
    'equipment_type' => $payload['equipment_type'] !== '' ? $payload['equipment_type'] : null,
    'manufacturer' => $payload['manufacturer'] !== '' ? $payload['manufacturer'] : null,
    'model' => $payload['model'] !== '' ? $payload['model'] : null,
    'serial_year' => $payload['serial_or_year'] !== '' ? $payload['serial_or_year'] : null,
    'search' => 1,
];

try {
    $result = vms_source_finder_save_source($pdo, $payload, $approvedBy);
    $redirectParams['saved'] = $result['created'] ? '1' : 'exists';
    if (!$result['created'] && !empty($result['existing']['source_id'])) {
        $redirectParams['source_id'] = (int)$result['existing']['source_id'];
    }
    header('Location: maintenance_source_finder.php?' . http_build_query(array_filter($redirectParams, static fn($v) => $v !== null && $v !== '')));
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    exit('Failed to save source: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
