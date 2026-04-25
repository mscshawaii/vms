<?php
require 'db_connect.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/* Resolve QR code */
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'epirb'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

/* Vessel */
$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON, hailingPort, epirbHexId
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

/* Battery */
$stmt = $pdo->prepare("
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate, notes
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 22
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$battery = $stmt->fetch(PDO::FETCH_ASSOC);

/* HRU */
$stmt = $pdo->prepare("
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate, notes
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 23
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$hru = $stmt->fetch(PDO::FETCH_ASSOC);

/* EPIRB Registration Expiration */
$stmt = $pdo->prepare("
    SELECT expDate
    FROM documents
    WHERE vessel_id = ?
      AND archived_at IS NULL
      AND related_to = 'vessel'
      AND (
            docType = 'EPIRB Registration'
            OR docType = 'EPRIB Registration'
            OR category = 'EPIRB Registration'
          )
    ORDER BY
        CASE WHEN expDate IS NULL THEN 1 ELSE 0 END,
        expDate DESC,
        uploaded_on DESC,
        id DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$epirbRegistrationExp = $stmt->fetchColumn();

/* Completed EPIRB ICRs - last 365 days */
$stmt = $pdo->prepare("
    SELECT
        r.run_id,
        r.run_date,
        r.inspector,
        r.finalized_at,
        COALESCE(vi.title, i.title) AS icr_title,
        COALESCE(vi.icr_number, i.icr_number) AS icr_number
    FROM vessel_icr_runs r
    LEFT JOIN vessel_icrs vi ON r.vessel_icr_id = vi.vessel_icr_id
    LEFT JOIN icrs i ON r.icr_id = i.icr_id
    WHERE r.vessel_id = ?
      AND r.icr_id = 19
      AND r.save_state = 'final'
      AND r.finalized_at >= (NOW() - INTERVAL 365 DAY)
    ORDER BY r.finalized_at DESC
");
$stmt->execute([$vessel_id]);
$completed_icrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>EPIRB Record</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3 bg-light">
<div class="container">

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="mb-1">EPIRB Record</h2>
            <div class="text-muted">
                <?= htmlspecialchars($vessel['vesselName']) ?>
                · ON <?= htmlspecialchars($vessel['vesselON']) ?>
                <?php if (!empty($vessel['hailingPort'])): ?>
                    · <?= htmlspecialchars($vessel['hailingPort']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>EPIRB Information</strong></div>
        <div class="card-body">
            <div class="row g-3">
            <div class="col-md-3">
                <strong>HEX ID</strong><br>
                <?= htmlspecialchars($vessel['epirbHexId'] ?: 'Not set') ?>
            </div>
            <div class="col-md-3">
                <strong>Battery Expiration</strong><br>
                <?= htmlspecialchars($battery['expDate'] ?? 'Unknown') ?>
            </div>
            <div class="col-md-3">
                <strong>HRU Expiration</strong><br>
                <?= htmlspecialchars($hru['expDate'] ?? 'Unknown') ?>
            </div>
            <div class="col-md-3">
                <strong>Registration Expiration</strong><br>
                <?= htmlspecialchars($epirbRegistrationExp ?: 'Unknown') ?>
            </div>
        </div>

            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <a class="btn btn-primary"
                   href="open_epirb_monthly_icr.php?code=<?= urlencode($code) ?>">
                    Log Monthly EPIRB Test
                </a>

                <a class="btn btn-outline-secondary"
                   href="epirb_edit.php?code=<?= urlencode($code) ?>">
                    Edit EPIRB Info
                </a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header">
            <strong>EPIRB Tests - Last 365 Days</strong>
        </div>
        <div class="card-body">
            <?php if (!$completed_icrs): ?>
                <div class="text-muted">No finalized ICRs found for this vessel in the last 365 days.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>ICR</th>
                                <th>Inspector</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($completed_icrs as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['run_date'] ?? '') ?></td>
                                <td>
                                    <?= htmlspecialchars($row['icr_number'] ?? '') ?>
                                    <?php if (!empty($row['icr_title'])): ?>
                                        — <?= htmlspecialchars($row['icr_title']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['inspector'] ?? 'Unknown') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>