<?php
require 'session_check.php';
require 'db_connect.php';

// Restrict to MSCS / admins
if (($_SESSION['company_id'] ?? null) != 1 && !($_SESSION['is_admin'] ?? false)) {
    http_response_code(403);
    exit('Not authorized.');
}

$vessel_id = (int)($_GET['vessel_id'] ?? $_GET['id'] ?? $_POST['vessel_id'] ?? $_POST['id'] ?? 0);
if ($vessel_id <= 0) {
    exit('Invalid vessel ID.');
}

$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    exit('Vessel not found.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim((string)($_POST['confirm_text'] ?? ''));

    if ($confirm !== 'DELETE') {
        $error = 'Type DELETE to confirm.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM vessels WHERE vessel_id = ?");
        if ($stmt->execute([$vessel_id])) {
            header("Location: manage_vessels.php?delete=success");
            exit;
        } else {
            $error = 'Failed to delete vessel.';
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
    <title>Delete Vessel</title>
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
$title = 'Delete Vessel';
$back_link = 'manage_vessels.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="vessels-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <h1 class="h3 mb-1 text-danger">Delete Vessel</h1>
                    <p class="page-meta"><?= h($vessel['vesselName']) ?></p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form id="deleteVesselForm" method="post">
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                <div class="card shadow-sm action-card mb-4">
                    <div class="card-body">
                        <div class="alert alert-danger">
                            You are about to permanently delete <strong><?= h($vessel['vesselName']) ?></strong>.
                        </div>

                        <div class="mb-0">
                            <label for="confirm_text" class="form-label">Type <code>DELETE</code> to confirm</label>
                            <input type="text" name="confirm_text" id="confirm_text" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2">
                    <a href="manage_vessels.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Vessel</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="deleteVesselForm" class="btn btn-danger">Delete Vessel</button>
            <a href="manage_vessels.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>