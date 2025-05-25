<?php
session_start();
require 'db_connect.php';

$username = $_POST['username'];
$password = $_POST['password'];

// Fetch user by username
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user_id early for logging
$user_id = $user ? $user['id'] : null;

// Brute-force lockout check (5 failed attempts in 10 mins)
if ($user_id) {
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM login_attempts
        WHERE user_id = ? AND success = 0 AND attempt_time > (NOW() - INTERVAL 10 MINUTE)
    ");
    $check->execute([$user_id]);
    if ($check->fetchColumn() >= 5) {
        echo "Too many failed login attempts. Try again in 10 minutes.";
        exit;
    }
}

// Validate password
$success = ($user && password_verify($password, $user['pword'])) ? 1 : 0;


// Log the attempt
if ($user_id) {
    $log = $pdo->prepare("INSERT INTO login_attempts (user_id, success) VALUES (?, ?)");
    $log->execute([$user_id, $success]);
}

if ($success) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['last_active'] = time();

    if (isset($_POST['remember_me'])) {
        setcookie("remember_me", $user['id'], time() + (86400 * 30), "/");
    }

    header("Location: dashboard.php");
    exit;
} else {

    header("Location: login.php?error=1");
    exit;
}
