<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';

$task_id = (int)($_GET['task_id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$thread_id = ensureTaskThread($pdo, $task_id, $currentUserId);
syncTaskThreadMembers($pdo, $task_id, $currentUserId);

$stmt = $pdo->prepare("
    SELECT title, vessel_id
    FROM tasks
    WHERE task_id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

$thread_title = $task['title'] ?? 'Task Discussion';
$placeholder = "Add update, troubleshooting note, or completion detail...";
$vessel_id = (int)($task['vessel_id'] ?? 0);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($thread_title) ?> - VMS</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$back_link = $vessel_id > 0
    ? "vessel_dashboard.php?vessel_id=" . $vessel_id . "#tasksModal"
    : "dashboard.php";
include __DIR__ . '/partials/top_nav.php';
?>

<?php include __DIR__ . '/partials/thread_panel.php'; ?>

</body>
</html>
