<?php
require 'db_connect.php';

$icr_id = intval($_POST['icr_id']);
$icr_number = trim($_POST['icr_number']);
$title = trim($_POST['title']);
$reference_text = trim($_POST['reference_text']);
$frequency = trim($_POST['frequency']);

// Step inputs
$step_ids = $_POST['step_id'] ?? [];
$step_numbers = $_POST['step_number'] ?? [];
$step_descriptions = $_POST['step_description'] ?? [];
$deficiency_actions = $_POST['deficiency_action'] ?? [];

// Update the ICR template
$update_icr = $pdo->prepare("UPDATE icrs SET icr_number = ?, title = ?, reference_text = ?, frequency = ? WHERE icr_id = ?");
$update_icr->execute([$icr_number, $title, $reference_text, $frequency, $icr_id]);

// Loop through each step
for ($i = 0; $i < count($step_descriptions); $i++) {
    $step_id = $step_ids[$i];
    $number = intval($step_numbers[$i]);
    $description = trim($step_descriptions[$i]);
    $def_action = trim($deficiency_actions[$i]);

    if ($step_id === "new") {
        // New step
        $insert = $pdo->prepare("INSERT INTO icr_steps (icr_id, step_number, step_description, deficiency_action) VALUES (?, ?, ?, ?)");
        $insert->execute([$icr_id, $number, $description, $def_action]);
    } else {
        // Update existing step
        $update = $pdo->prepare("UPDATE icr_steps SET step_number = ?, step_description = ?, deficiency_action = ? WHERE step_id = ?");
        $update->execute([$number, $description, $def_action, intval($step_id)]);
    }
}

header("Location: icr_templates.php?updated=1");
exit;
?>
