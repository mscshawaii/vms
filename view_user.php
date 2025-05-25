<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

$user_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT u.*, r.role_name, o.company_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN owners o ON u.company_id = o.owner_id
    WHERE u.id = ?");
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
    <title>View User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h3>👤 User Details: <?= htmlspecialchars($user['fName'] . ' ' . $user['lName']) ?></h3>
    <table class="table table-bordered w-75 mt-3">
        <tr><th>Username</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
        <tr><th>Phone</th><td><?= htmlspecialchars($user['phoneNumber']) ?></td></tr>
        <tr><th>Company</th><td><?= htmlspecialchars($user['company_name']) ?></td></tr>
        <tr><th>Role</th><td><?= htmlspecialchars($user['role_name']) ?></td></tr>
        <tr><th>MMC Exp</th><td><?= htmlspecialchars($user['mmc'] ?? '—') ?></td></tr>
        <tr><th>Medical Cert Exp</th><td><?= htmlspecialchars($user['mmc_medical'] ?? '—') ?></td></tr>
        <tr><th>First Aid Exp</th><td><?= htmlspecialchars($user['fa'] ?? '—') ?></td></tr>
        <tr><th>MROP Issued</th><td><?= htmlspecialchars($user['mrop'] ?? '—') ?></td></tr>
        <tr>
            <th>Documents</th>
            <td>
                <?php if ($user['mmc_file']): ?><a href="<?= $user['mmc_file'] ?>" target="_blank">📄 MMC</a><br><?php endif; ?>
                <?php if ($user['fa_file']): ?><a href="<?= $user['fa_file'] ?>" target="_blank">🚑 FA</a><br><?php endif; ?>
                <?php if ($user['mrop_file']): ?><a href="<?= $user['mrop_file'] ?>" target="_blank">📡 MROP</a><?php endif; ?>
                <?= (!$user['mmc_file'] && !$user['fa_file'] && !$user['mrop_file']) ? '—' : '' ?>
            </td>
        </tr>
    </table>
    <a href="dashboard.php" class="btn btn-secondary">← Back</a>
</div>
</body>
</html>
