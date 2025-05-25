<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

$user_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found.";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
<h2>Reset Password for <?= htmlspecialchars($user['username']) ?></h2>

<form action="submit_password_reset.php" method="post">
    <input type="hidden" name="id" value="<?= $user_id ?>">

    <label>New Password:</label><br>
    <input type="password" name="new_password" required><br><br>

    <button type="submit">Update Password</button>
</form>

<p><a href="users_list.php">← Back to User List</a></p>

</body>
</html>
