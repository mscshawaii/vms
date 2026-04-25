<?php
require 'db_connect.php';

function normalize_steps_from_post(): array {
    if (!empty($_POST['steps']) && is_array($_POST['steps'])) {
        $out = [];
        foreach ($_POST['steps'] as $s) {
            $out[] = [
                'number' => isset($s['number']) ? trim((string)$s['number']) : '',
                'description' => isset($s['description']) ? trim((string)$s['description']) : '',
                'deficiency_action' => isset($s['deficiency_action']) ? trim((string)$s['deficiency_action']) : null,
                'substeps' => array_values(array_map(function ($sub) {
                    return [
                        'code' => isset($sub['code']) ? trim((string)$sub['code']) : '',
                        'description' => isset($sub['description']) ? trim((string)$sub['description']) : '',
                        'deficiency_action' => isset($sub['deficiency_action']) ? trim((string)$sub['deficiency_action']) : null,
                    ];
                }, is_array($s['substeps'] ?? []) ? $s['substeps'] : []))
            ];
        }
        return $out;
    }
    return [];
}

try {
    $icr_number     = trim($_POST['icr_number'] ?? '');
    $title          = trim($_POST['title'] ?? '');
    $reference_text = isset($_POST['reference_text']) ? trim((string)$_POST['reference_text']) : null;
    $frequency      = trim($_POST['frequency'] ?? '');
    $drill_type     = trim($_POST['drill_type'] ?? '');

    // Normalize drill_type to NULL if blank
    $drill_type = ($drill_type === '') ? null : $drill_type;

    // Drill header fields (optional)
    $drill = $_POST['drill'] ?? [];
    if (!is_array($drill)) $drill = [];

    $steps = normalize_steps_from_post();

    $hasAtLeastOneStep = false;
    foreach ($steps as $s) {
        if ($s['description'] !== '') { $hasAtLeastOneStep = true; break; }
    }

    if ($icr_number === '' || $title === '' || $frequency === '' || !$hasAtLeastOneStep) {
        http_response_code(422);
        throw new Exception('ICR Number, Title, Frequency, and at least one Step are required.');
    }

    $pdo->beginTransaction();

    // Insert into icrs (now includes drill_type)
    $stmt = $pdo->prepare("
        INSERT INTO icrs (icr_number, title, reference_text, frequency, drill_type)
        VALUES (:icr_number, :title, :reference_text, :frequency, :drill_type)
    ");
    $stmt->execute([
        ':icr_number'     => $icr_number,
        ':title'          => $title,
        ':reference_text' => $reference_text,
        ':frequency'      => $frequency,
        ':drill_type'     => $drill_type
    ]);
    $icr_id = (int)$pdo->lastInsertId();

    // If this is a drill, insert drill template header fields
    if ($drill_type !== null) {
        $insDrill = $pdo->prepare("
            INSERT INTO icr_drill_templates
              (icr_id, regulatory_references, drill_name, operating_condition, purpose,
               performance_objective, safety_limitations, scenario_description,
               roles_captain, roles_crew, evaluation_guidance)
            VALUES
              (:icr_id, :reg_refs, :drill_name, :op_cond, :purpose,
               :obj, :safety, :scenario,
               :roles_capt, :roles_crew, :eval)
        ");

        $insDrill->execute([
            ':icr_id'      => $icr_id,
            ':reg_refs'    => trim($drill['regulatory_references'] ?? '') ?: null,
            ':drill_name'  => trim($drill['drill_name'] ?? '') ?: null,
            ':op_cond'     => trim($drill['operating_condition'] ?? '') ?: null,
            ':purpose'     => trim($drill['purpose'] ?? '') ?: null,
            ':obj'         => trim($drill['performance_objective'] ?? '') ?: null,
            ':safety'      => trim($drill['safety_limitations'] ?? '') ?: null,
            ':scenario'    => trim($drill['scenario_description'] ?? '') ?: null,
            ':roles_capt'  => trim($drill['roles_captain'] ?? '') ?: null,
            ':roles_crew'  => trim($drill['roles_crew'] ?? '') ?: null,
            ':eval'        => trim($drill['evaluation_guidance'] ?? '') ?: null,
        ]);
    }

    // Insert steps
    $insStep = $pdo->prepare("
        INSERT INTO icr_steps (icr_id, step_number, step_description, deficiency_action)
        VALUES (:icr_id, :step_number, :step_description, :def_action)
    ");

    // Insert substeps
    $insSub = $pdo->prepare("
        INSERT INTO icr_substeps (step_id, substep_code, description, deficiency_action)
        VALUES (:step_id, :code, :description, :def_action)
    ");

    foreach ($steps as $s) {
        if ($s['description'] === '') continue;

$stepCounter = 0;

foreach ($steps as $s) {
    if (trim($s['description'] ?? '') === '') continue;

    $stepCounter++;

    $insStep->execute([
        ':icr_id'           => $icr_id,
        ':step_number'      => $stepCounter, // <-- ALWAYS NOT NULL
        ':step_description' => $s['description'],
        ':def_action'       => !empty($s['deficiency_action']) ? $s['deficiency_action'] : null
    ]);
    $step_id = (int)$pdo->lastInsertId();

    foreach (($s['substeps'] ?? []) as $sub) {
        // Keep your existing substep insert logic...
    }
}

        $step_id = (int)$pdo->lastInsertId();

        foreach ($s['substeps'] as $sub) {
            if (($sub['description'] === '' || $sub['description'] === null) && ($sub['code'] === '' || $sub['code'] === null)) continue;

            $insSub->execute([
                ':step_id'     => $step_id,
                ':code'        => $sub['code'] ?: null,
                ':description' => $sub['description'] ?: '',
                ':def_action'  => $sub['deficiency_action'] ?: null
            ]);
        }
    }

    $pdo->commit();
    header('Location: icr_templates.php?msg=template_saved');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo '<pre style="white-space:pre-wrap;">Error saving ICR: ' . htmlspecialchars($e->getMessage()) . "\n\nPOST KEYS:\n" . htmlspecialchars(print_r(array_keys($_POST), true)) . '</pre>';
}
