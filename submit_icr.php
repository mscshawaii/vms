<?php
require 'db_connect.php'; // PDO version

$icr_number = trim($_POST['icr_number']);
$title = trim($_POST['title']);
$reference_text = trim($_POST['reference_text']);
$frequency = $_POST['frequency'] ?? null;

$step_numbers = $_POST['step_number'] ?? [];
$step_descriptions = $_POST['step_description'] ?? [];
$deficiency_actions = $_POST['deficiency_action'] ?? [];

if (!$icr_number || !$title || !$frequency || count($step_descriptions) === 0) {
    die("❌ ICR Number, Title, Frequency, and at least one Step are required.");
}

try {
    $pdo->beginTransaction();

    // Insert into icrs table
    $stmt = $pdo->prepare("
        INSERT INTO icrs (icr_number, title, reference_text, frequency)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$icr_number, $title, $reference_text, $frequency]);
    $icr_id = $pdo->lastInsertId();

    // Insert steps
    $step_stmt = $pdo->prepare("
        INSERT INTO icr_steps (icr_id, step_number, step_description, deficiency_action)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($step_descriptions as $i => $desc) {
        $number = (int)($step_numbers[$i] ?? ($i + 1));
        $def_action = $deficiency_actions[$i] ?? null;
        $step_stmt->execute([$icr_id, $number, $desc, $def_action]);
    }

    $pdo->commit();
    header("Location: icr_templates.php?success=1");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Failed to save ICR: " . $e->getMessage());
}
?>
