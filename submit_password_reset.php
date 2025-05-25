<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

$new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?");
$stmt->execute([$new_password, $_POST['id']]);

header("Location: users_list.php?success=1");
exit;
