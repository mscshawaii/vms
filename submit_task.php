<?php
require 'session_check.php';
require 'db_connect.php';

// Sanitize helper
function clean($value) {
    return isset($value) ? trim($value) : null;
}

// Grab and sanitize POST data
$vessel_id         = intval($_POST['vessel_id'] ?? 0);
$title             = clean($_POST['title'] ?? '');
$due_date          = clean($_POST['due_date'] ?? '');
$description       = clean($_POST['description'] ?? '');
$priority          = clean($_POST['priority'] ?? 'moderate');
$equipment_id      = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
$assigned_to       = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
$recurrence        = clean($_POST['recurrence_interval'] ?? 'none');
$corrective_action = clean($_POST['corrective_action'] ?? null);
$corrected_date    = !empty($_POST['corrected_date']) ? $_POST['corrected_date'] : null;

// Default to 'open' status
$status = 'open';

// Prepare insert
$stmt = $pdo->prepare("
    INSERT INTO tasks (
        vessel_id, title, due_date, description, priority,
        equipment_id, assigned_to, recurrence_interval,
        corrective_action, completed_date, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$success = $stmt->execute([
    $vessel_id, $title, $due_date, $description, $priority,
    $equipment_id, $assigned_to, $recurrence,
    $corrective_action, $corrected_date, $status
]);

if ($success) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#tasks");
    exit;
} else {
    echo "? Failed to save corrective action: " . $stmt->errorInfo()[2];
}
?>
