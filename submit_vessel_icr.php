<?php
/**
 * submit_vessel_icr.php
 *
 * PURPOSE
 * -------
 * Performs a ONE-TIME, ADDITIVE copy of one or more ICR TEMPLATES into a VESSEL.
 *
 * DATA FLOW
 * ---------
 * icrs              → vessel_icrs
 * icr_steps         → vessel_icr_steps
 * icr_substeps      → vessel_icr_substeps
 *
 * CRITICAL RULES
 * --------------
 * - This file MUST NEVER:
 *   • DELETE vessel_icrs
 *   • DELETE vessel_icr_steps
 *   • DELETE vessel_icr_substeps
 *   • UPDATE existing vessel ICRs
 *   • Rebuild vessel inspections in bulk
 *
 * - Duplicate protection is REQUIRED.
 * - This file must remain vessel-scoped and additive.
 *
 * CONTEXT
 * -------
 * Once copied, the vessel ICR is authoritative.
 * Template changes DO NOT propagate automatically.
 */

require 'session_check.php';
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Invalid request method.');
}

$vessel_id = (int)($_POST['vessel_id'] ?? 0);

/*
 * Support both:
 * - old single-select form: icr_id
 * - new batch form: icr_ids[]
 */
$posted_ids = [];

if (!empty($_POST['icr_ids']) && is_array($_POST['icr_ids'])) {
    $posted_ids = $_POST['icr_ids'];
} elseif (!empty($_POST['icr_id'])) {
    $posted_ids = [$_POST['icr_id']];
}

$icr_ids = array_values(array_unique(array_filter(
    array_map('intval', $posted_ids),
    fn($id) => $id > 0
)));

if ($vessel_id <= 0 || empty($icr_ids)) {
    die('Missing vessel or ICR selection.');
}

try {
    $pdo->beginTransaction();

    $assigned_count = 0;
    $skipped_count = 0;

    // Prepared statements reused inside the loop
    $check = $pdo->prepare("
        SELECT 1
        FROM vessel_icrs
        WHERE vessel_id = ? 
        AND icr_id = ?
        AND is_removed = 0
        LIMIT 1
    ");
    
    $stmtTemplate = $pdo->prepare("
        SELECT icr_number, title, category_id, type_id, frequency
        FROM icrs
        WHERE icr_id = ?
        LIMIT 1
    ");

    $insertVesselIcr = $pdo->prepare("
        INSERT INTO vessel_icrs
        (vessel_id, icr_id, icr_number, title, category_id, type_id, frequency, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stepsStmt = $pdo->prepare("
        SELECT step_id, step_number, step_description, deficiency_action
        FROM icr_steps
        WHERE icr_id = ?
        ORDER BY step_number
    ");

    $insertStep = $pdo->prepare("
        INSERT INTO vessel_icr_steps
        (vessel_icr_id, master_step_id, step_number, step_description, deficiency_action, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $subStmt = $pdo->prepare("
        SELECT substep_code, description
        FROM icr_substeps
        WHERE step_id = ?
        ORDER BY substep_code
    ");

    $insertSubstep = $pdo->prepare("
        INSERT INTO vessel_icr_substeps
        (vessel_step_id, substep_code, description, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    foreach ($icr_ids as $icr_id) {
        // Duplicate protection: skip already-assigned ICRs
        $check->execute([$vessel_id, $icr_id]);
        if ($check->fetchColumn()) {
            $skipped_count++;
            continue;
        }

        // Fetch template ICR
        $stmtTemplate->execute([$icr_id]);
        $template = $stmtTemplate->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            throw new Exception("ICR template not found for icr_id={$icr_id}.");
        }

        // Insert vessel ICR
        $insertVesselIcr->execute([
            $vessel_id,
            $icr_id,
            $template['icr_number'],
            $template['title'],
            $template['category_id'],
            $template['type_id'],
            $template['frequency']
        ]);

        $vessel_icr_id = (int)$pdo->lastInsertId();

        // Fetch template steps
        $stepsStmt->execute([$icr_id]);
        $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($steps as $step) {
            // Insert vessel step
            $insertStep->execute([
                $vessel_icr_id,
                $step['step_id'],
                $step['step_number'],
                $step['step_description'],
                $step['deficiency_action']
            ]);

            $vessel_icr_step_id = (int)$pdo->lastInsertId();

            // Fetch template substeps
            $subStmt->execute([$step['step_id']]);
            $substeps = $subStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($substeps as $sub) {
                $insertSubstep->execute([
                    $vessel_icr_step_id,
                    $sub['substep_code'],
                    $sub['description']
                ]);
            }
        }

        $assigned_count++;
    }

    $pdo->commit();

    header("Location: vessel_icrs.php?vessel_id={$vessel_id}&assigned={$assigned_count}&skipped={$skipped_count}");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "Failed to add ICR(s) to vessel: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}