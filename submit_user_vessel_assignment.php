<?php
require 'db.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);

if ($vessel_id && $user_id) {
    // Prevent duplicate entries
    $check = $mysqli->prepare("SELECT * FROM vessel_users WHERE vessel_id = ? AND user_id = ?");
    $check->bind_param("ii", $vessel_id, $user_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows === 0) {
        $stmt = $mysqli->prepare("INSERT INTO vessel_users (vessel_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $vessel_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: assign_user_to_vessel.php");
exit;
