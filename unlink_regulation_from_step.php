<?php
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$linkId = (int)($data['link_id'] ?? 0);

if ($linkId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid link id.'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM icr_template_step_regulations
    WHERE icr_template_step_regulation_id = ?
");
$stmt->execute([$linkId]);

echo json_encode(['success' => true]);