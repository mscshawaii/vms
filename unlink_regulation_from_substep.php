<?php
require 'db_connect.php';
require 'session_check.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $link_id = (int)($input['link_id'] ?? 0);

    if ($link_id <= 0) {
        throw new Exception('Missing link ID.');
    }

    $del = $pdo->prepare("
        DELETE FROM icr_template_substep_regulations
        WHERE icr_template_substep_regulation_id = ?
    ");
    $del->execute([$link_id]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}