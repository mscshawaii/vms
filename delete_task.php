<?php
require 'session_check.php';
require 'db_connect.php';

$task_id = intval($_GET['id'] ?? 0);
$vessel_id = intval($_GET['vessel_id'] ?? 0);

// Validate
if ($task_id <= 0 || $vessel_id <= 0) {
    die("? Invalid request.");
}

// Delete the task
$stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ?");
if ($stmt->execute([$task_id])) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#tasks&deleted=1");
    exit;
} else {
    echo "? Error deleting task: " . $stmt->errorInfo()[2];
}
?>
