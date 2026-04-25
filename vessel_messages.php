<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$thread_id = ensureVesselGeneralThread($pdo, $vessel_id, $currentUserId);
syncVesselThreadMembers($pdo, $vessel_id, $currentUserId);

$stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

$thread_title = ($vessel['vesselName'] ?? 'Vessel') . ' Messages';
$placeholder = "Post vessel update, maintenance note, or crew communication...";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($thread_title) ?> - VMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$back_link = "vessel_dashboard.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<?php include __DIR__ . '/partials/thread_panel.php'; ?>

</body>
</html>
