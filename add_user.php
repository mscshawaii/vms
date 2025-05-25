<?php
require 'session_check.php';
require 'db_connect.php';

// Only allow admin users
if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

// Determine company scope
$is_mscs = ($_SESSION['company_id'] == 1); // MSCS = owner_id 1

// Fetch roles
$roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetchAll();

// Fetch companies only if MSCS
if ($is_mscs) {
    $companies = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name")->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">👤 Create New User</h2>

    <form action="submit_user.php" method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-md-6">
            <label class="form-label">First Name:</label>
            <input type="text" name="fName" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Last Name:</label>
            <input type="text" name="lName" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone Number:</label>
            <input type="text" name="phoneNumber" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Email:</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <?php if ($is_mscs): ?>
            <div class="col-md-6">
                <label class="form-label">Assign to Company:</label>
                <select name="company_id" class="form-select" required>
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= $company['owner_id'] ?>"><?= htmlspecialchars($company['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="company_id" value="<?= $_SESSION['company_id'] ?>">
        <?php endif; ?>

        <div class="col-md-6">
            <label class="form-label">Role:</label>
            <select name="role_id" class="form-select" required>
                <option value="">-- Select Role --</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Username:</label>
            <input type="text" name="username" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Password:</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <hr class="mt-4">

        <div class="col-md-4">
            <label class="form-label">MMC Expiration Date:</label>
            <input type="date" name="mmc" class="form-control">
        </div>
        <div class="col-md-8">
            <label class="form-label">MMC Document:</label>
            <input type="file" name="mmc_path" class="form-control">
        </div>

        <div class="col-md-4">
             <label class="form-label">MMC Medical Expiration Date:</label>
              <input type="date" name="mmc_medical" class="form-control">
        </div>
        <div class="col-md-8">
            <label class="form-label">MMC Medical Certificate Document:</label>
            <input type="file" name="mmc_medical_path" class="form-control">
        </div>


        <div class="col-md-4">
            <label class="form-label">First Aid Expiration Date:</label>
            <input type="date" name="fa" class="form-control">
        </div>
        <div class="col-md-8">
            <label class="form-label">First Aid Document:</label>
            <input type="file" name="fa_path" class="form-control">
        </div>

        <div class="col-md-4">
            <label class="form-label">MROP Issuance Date:</label>
            <input type="date" name="mrop" class="form-control">
        </div>
        <div class="col-md-8">
            <label class="form-label">MROP Document:</label>
            <input type="file" name="mrop_path" class="form-control">
        </div>

        <div class="col-12 mt-4">
            <button type="submit" class="btn btn-primary">💾 Create User</button>
            <a href="dashboard.php" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
