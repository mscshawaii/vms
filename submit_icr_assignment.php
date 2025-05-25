<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    die("Access denied.");
}

$vessel_id = intval($_POST['vessel_id']);
$assigned_icrs = $_POST['assigned_icrs'] ?? [];

// Delete old vessel_icrs and vessel_icr_steps
$pdo->prepare("DELETE FROM vessel_icrs WHERE vessel_id = ?")->execute([$vessel_id]);
$pdo->prepare("
    DELETE vs 
    FROM vessel_icr_steps vs 
    INNER JOIN vessel_icrs vi ON vs.vessel_icr_id = vi.vessel_icr_id 
    WHERE vi.vessel_id = ?
")->execute([$vessel_id]);

if (!empty($assigned_icrs)) {
    foreach ($assigned_icrs as $icr_id) {
        $icr_id = intval($icr_id);

        // Fetch master ICR
        $icr = $pdo->prepare("SELECT icr_number, title, category_id, type_id, frequency FROM icrs WHERE icr_id = ?");
        $icr->execute([$icr_id]);
        $master_icr = $icr->fetch(PDO::FETCH_ASSOC);

        if ($master_icr) {
            // Insert into vessel_icrs
            $insert_icr = $pdo->prepare("
                INSERT INTO vessel_icrs (vessel_id, icr_id, icr_number, title, category_id, type_id, frequency, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insert_icr->execute([
                $vessel_id,
                $icr_id,
                $master_icr['icr_number'],
                $master_icr['title'],
                $master_icr['category_id'],
                $master_icr['type_id'],
                $master_icr['frequency']
            ]);

            $vessel_icr_id = $pdo->lastInsertId();

            // Fetch master steps
            $steps = $pdo->prepare("
                SELECT step_number, step_description, deficiency_action 
                FROM icr_steps 
                WHERE icr_id = ?
            ");
            $steps->execute([$icr_id]);
            $all_steps = $steps->fetchAll(PDO::FETCH_ASSOC);

            // Insert into vessel_icr_steps
            $insert_step = $pdo->prepare("
                INSERT INTO vessel_icr_steps (vessel_icr_id, step_number, step_description, deficiency_action, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            foreach ($all_steps as $step) {
                $insert_step->execute([
                    $vessel_icr_id,
                    $step['step_number'],
                    $step['step_description'],
                    $step['deficiency_action']
                ]);
            }
        }
    }
}

header("Location: icr_assignments.php?vessel_id=$vessel_id&updated=1");
exit;
?>
