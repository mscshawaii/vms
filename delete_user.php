<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

$user_id = intval($_GET['id'] ?? 0);

// Prevent deletion of yourself
if ($user_id == $_SESSION['user_id']) {
    echo "❌ You cannot delete your own account.";
    exit;
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$success = $stmt->execute([$user_id]);

if ($success) {
    header("Location: dashboard.php?success=user_deleted");
} else {
    echo "❌ Failed to delete user.";
}
exit;
