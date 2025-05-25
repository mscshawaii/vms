
<?php
require 'db_connect.php';
require 'session_check.php';

// Sanitize and collect inputs
$vessel_id = intval($_POST['vessel_id'] ?? 0);
$icr_id = intval($_POST['icr_id'] ?? 0);
$vessel_icr_id = intval($_POST['vessel_icr_id'] ?? 0);
$step_ids = $_POST['step_id'] ?? [];
$step_numbers = $_POST['step_number'] ?? [];
$step_descriptions = $_POST['step_description'] ?? [];

if (!$vessel_id || !$icr_id || !$vessel_icr_id || count($step_numbers) !== count($step_descriptions)) {
    die("❌ Invalid input.");
}

// Begin transaction
$pdo->beginTransaction();

try {
    // Delete any removed steps (ones that exist in DB but not in submitted IDs)
    $existing_stmt = $pdo->prepare("SELECT step_id FROM vessel_icr_steps WHERE vessel_icr_id = ?");
    $existing_stmt->execute([$vessel_icr_id]);
    $existing_ids = $existing_stmt->fetchAll(PDO::FETCH_COLUMN);

    $submitted_existing_ids = array_filter($step_ids, fn($id) => $id !== 'new');
    $to_delete = array_diff($existing_ids, $submitted_existing_ids);

    if (!empty($to_delete)) {
        $delete_stmt = $pdo->prepare("DELETE FROM vessel_icr_steps WHERE step_id = ?");
        foreach ($to_delete as $id) {
            $delete_stmt->execute([$id]);
        }
    }

    // Save all submitted steps
    for ($i = 0; $i < count($step_numbers); $i++) {
        $number = trim($step_numbers[$i]);
        $description = trim($step_descriptions[$i]);
        $step_id = $step_ids[$i];

        if ($step_id === 'new') {
            // New step
            $insert = $pdo->prepare("INSERT INTO vessel_icr_steps (vessel_icr_id, step_number, step_description) VALUES (?, ?, ?)");
            $insert->execute([$vessel_icr_id, $number, $description]);
        } else {
            // Update existing step
            $update = $pdo->prepare("UPDATE vessel_icr_steps SET step_number = ?, step_description = ? WHERE step_id = ?");
            $update->execute([$number, $description, $step_id]);
        }
    }

    $pdo->commit();
    header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#icrs");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("❌ Failed to save ICR steps: " . $e->getMessage());
}
?>
