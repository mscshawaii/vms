<?php
require 'session_check.php';
require 'db_connect.php';

// Allow MSCS Admin and Company Admin
if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2], true)) {
    echo "Access denied.";
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);
$session_user_id = (int)($_SESSION['user_id'] ?? 0);
$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs = ($session_company_id === 1);

if ($user_id <= 0) {
    http_response_code(400);
    exit('Invalid user ID.');
}

// Prevent deletion of yourself
if ($user_id === $session_user_id) {
    echo "❌ You cannot delete your own account.";
    exit;
}

// Fetch target user
$stmt = $pdo->prepare("SELECT id, company_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    exit('User not found.');
}

// Company admins may only delete users in their own company
if (!$is_mscs && (int)$target['company_id'] !== $session_company_id) {
    http_response_code(403);
    exit('Access denied.');
}

$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$success = $stmt->execute([$user_id]);

if ($success) {
    header("Location: manage_users.php?success=user_deleted");
    exit;
} else {
    echo "❌ Failed to delete user.";
    exit;
}