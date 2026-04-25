<?php
require 'session_check.php';
require 'db_connect.php';

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$code = $_GET['code'] ?? '';

/*
|--------------------------------------------------------------------------
| Vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found");
}

/*
|--------------------------------------------------------------------------
| Drill ICRs
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        vi.vessel_icr_id,
        vi.icr_id,
        COALESCE(vi.icr_number, i.icr_number) AS icr_number,
        COALESCE(vi.title, i.title) AS title,
        COALESCE(vi.frequency, i.frequency) AS frequency,
        i.drill_type
    FROM vessel_icrs vi
    LEFT JOIN icrs i ON i.icr_id = vi.icr_id
    WHERE vi.vessel_id = ?
      AND COALESCE(vi.icr_number, i.icr_number) LIKE 'K%'
    ORDER BY icr_number
");
$stmt->execute([$vessel_id]);
$drills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inspector = $_SESSION['username'] ?? 'Inspector';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Drill Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h2><?= h($vessel['vesselName']) ?> Drill Center</h2>

            <a href="public_drills.php?code=<?= urlencode($code) ?>" class="btn btn-outline-secondary mt-2">
                ← Return to QR Page
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><strong>Run Drill</strong></div>
        <div class="card-body">

            <?php foreach ($drills as $d): ?>
                <div class="border rounded p-3 mb-3">
                    <strong><?= h($d['icr_number']) ?> — <?= h($d['title']) ?></strong><br>
                    <small class="text-muted"><?= h($d['drill_type']) ?> · <?= h($d['frequency']) ?></small>

                    <a href="run_icr.php?vessel_id=<?= $vessel_id ?>
                        &vessel_icr_id=<?= $d['vessel_icr_id'] ?>
                        &icr_id=<?= $d['icr_id'] ?>
                        &inspector=<?= urlencode($inspector) ?>
                        &return_to=<?= urlencode('vessel_drill_qr_center.php?vessel_id=' . $vessel_id . '&code=' . $code) ?>"
                       class="btn btn-primary w-100 mt-2">
                        Run Drill
                    </a>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

</div>
</body>
</html>