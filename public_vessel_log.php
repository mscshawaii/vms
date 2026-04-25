<?php
require 'db_connect.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/*
|--------------------------------------------------------------------------
| Resolve QR / vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'vessel_log'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

/*
|--------------------------------------------------------------------------
| Vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    http_response_code(404);
    exit('Vessel not found.');
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vessel Log QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .hero-card,
        .info-card {
            border: 1px solid #e5e5e5;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="hero-card p-4 mb-4">
        <div class="text-muted mb-1">Vessel Log QR</div>
        <h2 class="mb-1"><?= h($vessel['vesselName']) ?></h2>
        <div class="text-muted">
            <?php if (!empty($vessel['vesselON'])): ?>
                ON <?= h($vessel['vesselON']) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-card p-4">
        <h5 class="mb-3">Create Vessel Log Entry</h5>
        <p class="text-muted mb-3">
            Scan this QR code to quickly access the vessel log entry page for this vessel.
            Login is required to continue.
        </p>

        <a href="open_vessel_log_qr.php?code=<?= urlencode($code) ?>" class="btn btn-primary w-100">
            Open Vessel Log
        </a>
    </div>

</div>
</body>
</html>