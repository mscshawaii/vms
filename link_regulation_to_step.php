<?php
require 'db_connect.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$step_id = (int)($input['step_id'] ?? 0);
$regulation_id = (int)($input['regulation_id'] ?? 0);
$paragraph_id = isset($input['paragraph_id']) && $input['paragraph_id'] !== ''
    ? (int)$input['paragraph_id']
    : null;

if ($step_id <= 0 || $regulation_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid step_id or regulation_id'
    ]);
    exit;
}

try {
    if ($paragraph_id) {
        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM icr_template_step_regulations
            WHERE icr_template_step_id = ?
              AND regulation_section_id = ?
              AND regulation_paragraph_id = ?
        ");
        $check->execute([$step_id, $regulation_id, $paragraph_id]);
    } else {
        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM icr_template_step_regulations
            WHERE icr_template_step_id = ?
              AND regulation_section_id = ?
              AND regulation_paragraph_id IS NULL
        ");
        $check->execute([$step_id, $regulation_id]);
    }

    if ($check->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'That regulation link already exists for this step.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO icr_template_step_regulations
        (
            icr_template_step_id,
            regulation_section_id,
            regulation_paragraph_id,
            reference_type,
            display_order,
            note_override,
            created_at
        )
        VALUES (?, ?, ?, 'requirement', 1, NULL, NOW())
    ");

    $stmt->execute([$step_id, $regulation_id, $paragraph_id]);

    echo json_encode([
        'success' => true
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}