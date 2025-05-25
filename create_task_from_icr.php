<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$run_id = intval($_POST['icr_run_id'] ?? 0);
$step_label = trim($_POST['step_label'] ?? '');
$step_text = trim($_POST['step_text'] ?? '');
$comment = trim($_POST['comment'] ?? '');

// Fallback if missing required info
if (!$vessel_id || !$step_label || !$step_text) {
    die("❌ Missing required fields.");
}

// Format task title and description
$title = "ICR Step Failed – {$step_label}";
$description = "{$step_text}\n\nInspector Comment: {$comment}";

// Default values (customize as needed)
$priority = 'moderate';
$status = 'open';
$assigned_to = null; // Or assign to someone if logic exists
$due_date = date('Y-m-d', strtotime('+7 days'));

$stmt = $pdo->prepare("
    INSERT INTO tasks (
        title, description, vessel_id, status, priority, due_date, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
");

if ($stmt->execute([$title, $description, $vessel_id, $status, $priority, $due_date])) {
    header("Location: vessel_dashboard.php?vessel_id={$vessel_id}&success=task_created#tasks");
    exit;
} else {
    echo "❌ Failed to create task.";
}
?>
