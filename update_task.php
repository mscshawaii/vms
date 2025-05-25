<?php
require 'db_connect.php'; // ✅ Use PDO version

$id = intval($_POST['task_id']);

// ✅ Sanitize and fallback defaults
$title = trim($_POST['title'] ?? '');
$description = $_POST['description'] ?? null;
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$status = $_POST['status'] ?? 'open';
$priority = $_POST['priority'] ?? 'moderate';
$recurrence_interval_raw = $_POST['recurrence_interval'] ?? null;

// ✅ ENUM validation for recurrence_interval
$valid_intervals = ['weekly', 'monthly', 'quarterly', 'annually'];
$recurrence_interval = in_array($recurrence_interval_raw, $valid_intervals) ? $recurrence_interval_raw : null;

// ✅ is_recurring flag
$is_recurring = $recurrence_interval ? 1 : 0;

// ✅ Optional fields
$assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
$corrective_action = $_POST['corrective_action'] ?? null;
$corrected_date = !empty($_POST['corrected_date']) ? $_POST['corrected_date'] : null;

// ✅ Update task
$stmt = $pdo->prepare("
    UPDATE tasks SET 
        title = ?, 
        description = ?, 
        due_date = ?, 
        status = ?, 
        priority = ?, 
        is_recurring = ?, 
        recurrence_interval = ?, 
        assigned_to = ?,
        corrective_action = ?,
        corrected_date = ?
    WHERE task_id = ?
");

$success = $stmt->execute([
    $title,
    $description,
    $due_date,
    $status,
    $priority,
    $is_recurring,
    $recurrence_interval,
    $assigned_to,
    $corrective_action,
    $corrected_date,
    $id
]);

if ($success) {
    // ✅ Get vessel_id to redirect
    $vessel_stmt = $pdo->prepare("SELECT vessel_id FROM tasks WHERE task_id = ?");
    $vessel_stmt->execute([$id]);
    $vessel_id = $vessel_stmt->fetchColumn();

    if ($vessel_id) {
        header("Location: vessel_dashboard.php?vessel_id=$vessel_id#tasks&success=task_updated");
    } else {
        header("Location: dashboard.php");
    }
    exit;
} else {
    echo "❌ Update failed.";
}
?>
