<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

function password_ok($p) {
    if (strlen($p) < 10) return false;
    $classes = 0;
    $classes += preg_match('/[a-z]/', $p) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $p) ? 1 : 0;
    $classes += preg_match('/\d/', $p) ? 1 : 0;
    $classes += preg_match('/[\W_]/', $p) ? 1 : 0;
    return $classes >= 3;
}

$user_id = $_POST['id'] ?? null;
$new_password_raw = $_POST['new_password'] ?? '';

if (!$user_id || !$new_password_raw) {
    header("Location: users_list.php?error=missing");
    exit;
}

// 🔒 Enforce same password rules as user reset flow
if (!password_ok($new_password_raw)) {
    header("Location: users_list.php?error=weak_password");
    exit;
}

try {
    $hash = password_hash($new_password_raw, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?");
    $stmt->execute([$hash, $user_id]);

    header("Location: users_list.php?success=1");
    exit;

} catch (Throwable $e) {
    header("Location: users_list.php?error=failed");
    exit;
}