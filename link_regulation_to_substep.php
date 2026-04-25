<?php
require 'db_connect.php';
require 'session_check.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $substep_id = (int)($input['substep_id'] ?? 0);
    $regulation_id = (int)($input['regulation_id'] ?? 0);
    $paragraph_id = isset($input['paragraph_id']) && $input['paragraph_id'] !== null && $input['paragraph_id'] !== ''
        ? (int)$input['paragraph_id']
        : null;

    if ($substep_id <= 0 || $regulation_id <= 0) {
        throw new Exception('Missing required inputs.');
    }

    $checkSub = $pdo->prepare("SELECT COUNT(*) FROM icr_substeps WHERE substep_id = ?");
    $checkSub->execute([$substep_id]);
    if ((int)$checkSub->fetchColumn() === 0) {
        throw new Exception('Sub-step not found.');
    }

    $checkReg = $pdo->prepare("SELECT COUNT(*) FROM regulation_sections WHERE regulation_section_id = ?");
    $checkReg->execute([$regulation_id]);
    if ((int)$checkReg->fetchColumn() === 0) {
        throw new Exception('Regulation not found.');
    }

    if ($paragraph_id !== null) {
        $checkPara = $pdo->prepare("
            SELECT COUNT(*)
            FROM regulation_paragraphs
            WHERE regulation_paragraph_id = ?
              AND regulation_section_id = ?
        ");
        $checkPara->execute([$paragraph_id, $regulation_id]);
        if ((int)$checkPara->fetchColumn() === 0) {
            throw new Exception('Paragraph does not belong to the selected regulation.');
        }
    }

    $dup = $pdo->prepare("
        SELECT COUNT(*)
        FROM icr_template_substep_regulations
        WHERE icr_template_substep_id = ?
          AND regulation_section_id = ?
          AND IFNULL(regulation_paragraph_id, 0) = IFNULL(?, 0)
    ");
    $dup->execute([$substep_id, $regulation_id, $paragraph_id]);

    if ((int)$dup->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This regulation is already linked to the sub-step.'
        ]);
        exit;
    }

    $orderStmt = $pdo->prepare("
        SELECT COALESCE(MAX(display_order), 0) + 1
        FROM icr_template_substep_regulations
        WHERE icr_template_substep_id = ?
    ");
    $orderStmt->execute([$substep_id]);
    $nextOrder = (int)$orderStmt->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO icr_template_substep_regulations
            (icr_template_substep_id, regulation_section_id, regulation_paragraph_id, reference_type, display_order)
        VALUES
            (?, ?, ?, 'requirement', ?)
    ");
    $ins->execute([$substep_id, $regulation_id, $paragraph_id, $nextOrder]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}