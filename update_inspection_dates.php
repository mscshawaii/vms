<?php
require 'session_check.php';
require 'db_connect.php';

$logged_in_company = $_SESSION['company_id'] ?? 0;
$role_id = $_SESSION['role_id'] ?? 0;
$vessel_id = intval($_POST['vessel_id'] ?? 0);

// Fetch the actual company that owns this vessel
$stmt = $pdo->prepare("SELECT company_id FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch();

if (!$vessel) {
    die("❌ Vessel not found.");
}

// Only MSCS (company_id = 1) can edit any vessel
if ($logged_in_company !== 1 && $vessel['company_id'] !== $logged_in_company) {
    die("❌ Access denied: You can't modify this vessel.");
}

// Handle date fields (optional)
$lastInspection = !empty($_POST['lastInspection']) ? $_POST['lastInspection'] : null;
$nextScheduledInspection = !empty($_POST['nextScheduledInspection']) ? $_POST['nextScheduledInspection'] : null;
$lastDrydock = !empty($_POST['lastDrydock']) ? $_POST['lastDrydock'] : null;
$nextDrydock = !empty($_POST['nextDrydock']) ? $_POST['nextDrydock'] : null;
$nextUnstep = !empty($_POST['nextUnstep']) ? $_POST['nextUnstep'] : null;

// Perform update
$update = $pdo->prepare("UPDATE vessels SET
    lastInspection = :lastInspection,
    nextScheduledInspection = :nextScheduledInspection,
    lastDrydock = :lastDrydock,
    nextDrydock = :nextDrydock,
    nextUnstep = :nextUnstep
    WHERE vessel_id = :vessel_id
");

$update->execute([
    ':lastInspection' => $lastInspection,
    ':nextScheduledInspection' => $nextScheduledInspection,
    ':lastDrydock' => $lastDrydock,
    ':nextDrydock' => $nextDrydock,
    ':nextUnstep' => $nextUnstep,
    ':vessel_id' => $vessel_id
]);

header("Location: vessel_dashboard.php?vessel_id=$vessel_id&success=inspection_updated");
exit;
?>
