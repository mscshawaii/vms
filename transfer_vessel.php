<?php
require 'session_check.php';
require 'db_connect.php';

// Ensure only MSCS users (company_id = 1) can access this
if ($_SESSION['company_id'] != 1) {
    die("❌ Access denied.");
}

// Get vessel ID from URL
$vessel_id = intval($_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    die("❌ Invalid vessel ID.");
}

// Fetch current vessel info
$stmt = $pdo->prepare("SELECT vesselName, company_id FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("❌ Vessel not found.");
}

// Fetch all companies
$companies = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);

// If form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_company_id = intval($_POST['new_company_id'] ?? 0);

    if ($new_company_id <= 0) {
        $error = "Please select a valid company.";
    } else {
        $update = $pdo->prepare("UPDATE vessels SET company_id = ? WHERE vessel_id = ?");
        if ($update->execute([$new_company_id, $vessel_id])) {
            header("Location: dashboard.php?transfer=success");
            exit;
        } else {
            $error = "Failed to transfer vessel.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transfer Vessel – <?= htmlspecialchars($vessel['vesselName']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">Transfer Vessel: <?= htmlspecialchars($vessel['vesselName']) ?></h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="new_company_id" class="form-label">Select New Company</label>
            <select name="new_company_id" id="new_company_id" class="form-select" required>
                <option value="">— Select Company —</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= $company['owner_id'] ?>" <?= $company['owner_id'] == $vessel['company_id'] ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($company['company_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Transfer Vessel</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
