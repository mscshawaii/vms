<?php
require 'session_check.php';
require 'db_connect.php';

// Only admins allowed
if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id'])) {
    echo "No user ID specified.";
    exit;
}

$user_id = intval($_GET['id']);

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "User not found.";
    exit;
}

// MSCS logic
$is_mscs = ($_SESSION['company_id'] == 1);

// Fetch options
$companies = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name")->fetchAll();
$roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>✏️ Edit User: <?= htmlspecialchars($user['fName'] . ' ' . $user['lName']) ?></h2>

    <form action="update_user.php" method="post" enctype="multipart/form-data" class="row g-3 mt-3">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">

        <div class="col-md-6">
            <label class="form-label">First Name:</label>
            <input type="text" name="fName" value="<?= htmlspecialchars($user['fName']) ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Last Name:</label>
            <input type="text" name="lName" value="<?= htmlspecialchars($user['lName']) ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone Number:</label>
            <input type="text" name="phoneNumber" value="<?= htmlspecialchars($user['phoneNumber']) ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Username:</label>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="form-control" required>
        </div>

        <?php if ($is_mscs): ?>
            <div class="col-md-6">
                <label class="form-label">Company:</label>
                <select name="company_id" class="form-select" required>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['owner_id'] ?>" <?= $user['company_id'] == $c['owner_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="company_id" value="<?= $_SESSION['company_id'] ?>">
        <?php endif; ?>

        <div class="col-md-6">
            <label class="form-label">Role:</label>
            <select name="role_id" class="form-select" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['role_id'] ?>" <?= $user['role_id'] == $r['role_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Credential Dates -->
        <div class="col-md-3">
            <label class="form-label">MMC Expiration:</label>
            <input type="date" name="mmc" class="form-control" value="<?= $user['mmc'] ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">MMC Medical:</label>
            <input type="date" name="mmc_medical" class="form-control" value="<?= $user['mmc_medical'] ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">First Aid Exp:</label>
            <input type="date" name="fa" class="form-control" value="<?= $user['fa'] ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">MROP Issued:</label>
            <input type="date" name="mrop" class="form-control" value="<?= $user['mrop'] ?>">
        </div>

        <!-- File Uploads -->
        <div class="col-md-6">
            <label class="form-label">MMC Document:</label>
            <input type="file" name="mmc_path" class="form-control">
            <?php if (!empty($user['mmc_path'])): ?>
                <small class="text-muted">Current: <a href="<?= $user['mmc_path'] ?>" target="_blank">View</a></small>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">MMC Medical Doc:</label>
            <input type="file" name="mmc_medical_path" class="form-control">
            <?php if (!empty($user['mmc_medical_path'])): ?>
                <small class="text-muted">Current: <a href="<?= $user['mmc_medical_path'] ?>" target="_blank">View</a></small>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">First Aid Document:</label>
            <input type="file" name="fa_path" class="form-control">
            <?php if (!empty($user['fa_path'])): ?>
                <small class="text-muted">Current: <a href="<?= $user['fa_path'] ?>" target="_blank">View</a></small>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label class="form-label">MROP Document:</label>
            <input type="file" name="mrop_path" class="form-control">
            <?php if (!empty($user['mrop_path'])): ?>
                <small class="text-muted">Current: <a href="<?= $user['mrop_path'] ?>" target="_blank">View</a></small>
            <?php endif; ?>
        </div>

<label>New Password (leave blank to keep existing):</label><br>
<input type="password" name="new_password" class="form-control"><br>


        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="dashboard.php" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
