<?php
require 'session_check.php';
require 'db_connect.php';

// Ensure only MSCS users (company_id = 1) can access this
if (($_SESSION['company_id'] ?? 0) != 1) {
    die("❌ Access denied.");
}

$vessel_id = (int)($_GET['vessel_id'] ?? $_POST['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    die("❌ Invalid vessel ID.");
}

// Fetch current vessel info
$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName, company_id
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("❌ Vessel not found.");
}

// Fetch all companies
$companies = $pdo->query("
    SELECT owner_id, company_name
    FROM owners
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_company_id = (int)($_POST['new_company_id'] ?? 0);

    if ($new_company_id <= 0) {
        $error = "Please select a valid company.";
    } elseif ($new_company_id === (int)$vessel['company_id']) {
        $error = "Please select a different company.";
    } else {
        $update = $pdo->prepare("UPDATE vessels SET company_id = ? WHERE vessel_id = ?");
        if ($update->execute([$new_company_id, $vessel_id])) {
            header("Location: manage_vessels.php?transfer=success");
            exit;
        } else {
            $error = "Failed to transfer vessel.";
        }
    }
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Transfer Vessel</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .vessels-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .action-card {
            border: 0;
            border-radius: 1rem;
        }
        .page-meta {
            color: #6b7280;
            margin: 0;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
<?php
$title = 'Transfer Vessel';
$back_link = 'manage_vessels.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="vessels-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <h1 class="h3 mb-1">Transfer Vessel</h1>
                    <p class="page-meta"><?= h($vessel['vesselName']) ?></p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form id="transferVesselForm" method="post">
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                <div class="card shadow-sm action-card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="new_company_id" class="form-label">Select New Company</label>
                            <select name="new_company_id" id="new_company_id" class="form-select" required>
                                <option value="">— Select Company —</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= (int)$company['owner_id'] ?>" <?= ((int)$company['owner_id'] === (int)$vessel['company_id']) ? 'disabled' : '' ?>>
                                        <?= h($company['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-warning mb-0">
                            Transfer will move this vessel to a different company while keeping the vessel record intact.
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2">
                    <a href="manage_vessels.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Transfer Vessel</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="transferVesselForm" class="btn btn-primary">Transfer Vessel</button>
            <a href="manage_vessels.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>