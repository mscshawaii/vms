<?php
require 'session_check.php';
require 'db_connect.php';

// Only allow admins
if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

// Optional: show a success message after add/edit
$success = isset($_GET['success']) ? "User action completed successfully." : "";

// Fetch all users with their company and role names
$stmt = $pdo->query("
    SELECT u.id, u.fName, u.lName, u.username, u.email, u.phoneNumber, o.company_name, r.role_name
    FROM users u
    LEFT JOIN owners o ON u.company_id = o.owner_id
    LEFT JOIN roles r ON u.role_id = r.role_id
    ORDER BY u.lName, u.fName
");

$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User List</title>
</head>
<body>
<h2>User Management</h2>

<?php if ($success): ?>
    <p style="color:green;"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Company</th>
        <th>Role</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['fName'] . ' ' . $user['lName']) ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['phoneNumber']) ?></td>
            <td><?= htmlspecialchars($user['company_name']) ?></td>
            <td><?= htmlspecialchars($user['role_name']) ?></td>
            <td>
                <a href="edit_user.php?id=<?= $user['id'] ?>">Edit</a> |
                <a href="reset_password.php?id=<?= $user['id'] ?>">Reset Password</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<p><a href="add_user.php">➕ Add New User</a></p>

</body>
</html>
