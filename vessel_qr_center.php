<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/session_check.php';

$appConfig = require __DIR__ . '/config_app.php';
$qrBaseUrl = $appConfig['qr_base_url'];

$vessel_id = intval($_GET['vessel_id'] ?? 0);

if ($vessel_id <= 0) {
    die("Missing vessel_id");
}

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/
$portableTypeIds = [14];
$fixedTypeIds    = [15];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function buildInClause(array $values): string
{
    if (empty($values)) {
        return '';
    }
    return implode(',', array_fill(0, count($values), '?'));
}

function fetchEquipmentQrCodes(PDO $pdo, array $equipmentIds): array
{
    if (empty($equipmentIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($equipmentIds), '?'));

    $stmt = $pdo->prepare("
        SELECT asset_id, code
        FROM qr_links
        WHERE asset_type = 'equipment'
          AND is_active = 1
          AND asset_id IN ($placeholders)
    ");
    $stmt->execute($equipmentIds);

    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $map[(int)$row['asset_id']] = $row['code'];
    }

    return $map;
}

/*
|--------------------------------------------------------------------------
| VESSEL INFO
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vesselName, vesselON, epirbHexId
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found");
}

/*
|--------------------------------------------------------------------------
| EPIRB BATTERY
|--------------------------------------------------------------------------
*/
$batteryStmt = $pdo->prepare("
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 22
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$batteryStmt->execute([$vessel_id]);
$battery = $batteryStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| EPIRB HRU
|--------------------------------------------------------------------------
*/
$hruStmt = $pdo->prepare("
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 23
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$hruStmt->execute([$vessel_id]);
$hru = $hruStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| EXISTING EPIRB QR
|--------------------------------------------------------------------------
*/
$q = $pdo->prepare("
    SELECT code
    FROM qr_links
    WHERE vessel_id = ?
      AND asset_type = 'epirb'
      AND is_active = 1
    LIMIT 1
");
$q->execute([$vessel_id]);
$qrCode = $q->fetchColumn();

$publicUrl = $qrCode
    ? $qrBaseUrl . '/public_epirb.php?code=' . urlencode($qrCode)
    : null;

/*
|--------------------------------------------------------------------------
| EXISTING DRILL QR
|--------------------------------------------------------------------------
*/
$drillQrStmt = $pdo->prepare("
    SELECT code
    FROM qr_links
    WHERE vessel_id = ?
      AND asset_type = 'drills'
      AND is_active = 1
    LIMIT 1
");
$drillQrStmt->execute([$vessel_id]);
$drillQrCode = $drillQrStmt->fetchColumn();

$drillPublicUrl = $drillQrCode
    ? $qrBaseUrl . '/public_drills.php?code=' . urlencode($drillQrCode)
    : null;

/*
|--------------------------------------------------------------------------
| EXISTING VESSEL LOG QR
|--------------------------------------------------------------------------
*/
$vesselLogQrStmt = $pdo->prepare("
    SELECT code
    FROM qr_links
    WHERE vessel_id = ?
      AND asset_type = 'vessel_log'
      AND is_active = 1
    LIMIT 1
");
$vesselLogQrStmt->execute([$vessel_id]);
$vesselLogQrCode = $vesselLogQrStmt->fetchColumn();

$vesselLogPublicUrl = $vesselLogQrCode
    ? $qrBaseUrl . '/public_vessel_log.php?code=' . urlencode($vesselLogQrCode)
    : null;

/*
|--------------------------------------------------------------------------
| ASSIGNED DRILL ICRS (K*)
|--------------------------------------------------------------------------
*/
$drillListStmt = $pdo->prepare("
    SELECT
        vi.vessel_icr_id,
        vi.icr_id,
        COALESCE(vi.icr_number, i.icr_number) AS icr_number,
        COALESCE(vi.title, i.title) AS title,
        COALESCE(vi.frequency, i.frequency) AS frequency,
        i.drill_type
    FROM vessel_icrs vi
    LEFT JOIN icrs i
        ON i.icr_id = vi.icr_id
    WHERE vi.vessel_id = ?
      AND COALESCE(vi.icr_number, i.icr_number) LIKE 'K%'
    ORDER BY COALESCE(vi.icr_number, i.icr_number), COALESCE(vi.title, i.title)
");
$drillListStmt->execute([$vessel_id]);
$assignedDrills = $drillListStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PORTABLE EXTINGUISHERS
|--------------------------------------------------------------------------
*/
$portableExtinguishers = [];
$portableSql = "
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id IN (" . buildInClause($portableTypeIds) . ")
    ORDER BY equipmentLocation ASC, equipmentName ASC, eid ASC
";
$portableStmt = $pdo->prepare($portableSql);
$portableStmt->execute(array_merge([$vessel_id], $portableTypeIds));
$portableExtinguishers = $portableStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FIXED SYSTEMS / FIXED EXTINGUISHERS
|--------------------------------------------------------------------------
*/
$fixedExtinguishers = [];
$fixedSql = "
    SELECT eid, equipmentName, equipmentLocation, manufacturer, modelNumber, serialNumber, expDate
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id IN (" . buildInClause($fixedTypeIds) . ")
    ORDER BY equipmentLocation ASC, equipmentName ASC, eid ASC
";
$fixedStmt = $pdo->prepare($fixedSql);
$fixedStmt->execute(array_merge([$vessel_id], $fixedTypeIds));
$fixedExtinguishers = $fixedStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FETCH QR CODE MAPS FOR EQUIPMENT
|--------------------------------------------------------------------------
*/
$allEquipmentIds = [];

foreach ($portableExtinguishers as $item) {
    $allEquipmentIds[] = (int)$item['eid'];
}
foreach ($fixedExtinguishers as $item) {
    $allEquipmentIds[] = (int)$item['eid'];
}

$equipmentQrMap = fetchEquipmentQrCodes($pdo, $allEquipmentIds);

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/
$success = $_GET['success'] ?? '';
$successMessage = '';

if ($success === 'epirb') {
    $successMessage = 'EPIRB QR code is ready.';
} elseif ($success === 'equipment') {
    $successMessage = 'Equipment QR code is ready.';
} elseif ($success === 'drills') {
    $successMessage = 'Drill Center QR code is ready.';
} elseif ($success === 'vessel_log') {
    $successMessage = 'Vessel Log QR code is ready.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>QR Code Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .qr-preview {
            max-width: 280px;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }
        .qr-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .qr-preview-sm {
            max-width: 160px;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 10px;
            background: #fff;
        }
        .qr-preview-sm img {
            width: 100%;
            height: auto;
            display: block;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            word-break: break-all;
        }
        .equipment-card {
            height: 100%;
            border: 1px solid #e3e3e3;
            border-radius: 12px;
            padding: 16px;
            background: #fff;
        }
        .drill-chip {
            display: inline-block;
            margin: 0 8px 8px 0;
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #f8f9fa;
            font-size: 0.92rem;
        }
    </style>
</head>
<body class="p-4">
<div class="container">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="mb-1">QR Code Center</h2>
            <div class="text-muted">
                <?= htmlspecialchars($vessel['vesselName']) ?> (ON <?= htmlspecialchars($vessel['vesselON']) ?>)
            </div>
        </div>
        <div>
            <a class="btn btn-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>">
                Back to Vessel Dashboard
            </a>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <strong>EPIRB</strong>
        </div>
        <div class="card-body">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <strong>HEX ID</strong><br>
                    <?= htmlspecialchars($vessel['epirbHexId'] ?: 'Not set') ?>
                </div>
                <div class="col-md-4">
                    <strong>Battery Expiration</strong><br>
                    <?= htmlspecialchars($battery['expDate'] ?? 'Unknown') ?>
                </div>
                <div class="col-md-4">
                    <strong>HRU Expiration</strong><br>
                    <?= htmlspecialchars($hru['expDate'] ?? 'Unknown') ?>
                </div>
            </div>

            <?php if ($qrCode): ?>
                <div class="row g-4 align-items-start">
                    <div class="col-md-4">
                        <div class="qr-preview">
                            <img src="qr_image.php?code=<?= urlencode($qrCode) ?>" alt="EPIRB QR Code">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <strong>QR Code Status</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>

                        <div class="mb-3">
                            <strong>Public URL</strong><br>
                            <span class="mono"><?= htmlspecialchars($publicUrl) ?></span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" target="_blank"
                               href="public_epirb.php?code=<?= urlencode($qrCode) ?>">
                                View Public Page
                            </a>

                            <a class="btn btn-outline-dark" target="_blank"
                               href="qr_image.php?code=<?= urlencode($qrCode) ?>">
                                Open QR Image
                            </a>

                            <a class="btn btn-outline-success"
                               href="generate_epirb_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                                Regenerate QR
                            </a>

                            <a class="btn btn-outline-secondary"
                               href="epirb_edit.php?code=<?= urlencode($qrCode) ?>">
                                Edit EPIRB Info
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No EPIRB QR code has been created for this vessel yet.
                </div>

                <a class="btn btn-success"
                   href="generate_epirb_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                    Generate EPIRB QR
                </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Drill Center</strong>
        </div>
        <div class="card-body">

            <div class="mb-3">
                <strong>Assigned Drill ICRs</strong><br>
                <?php if (!$assignedDrills): ?>
                    <span class="text-muted">No drill ICRs assigned to this vessel.</span>
                <?php else: ?>
                    <div class="mt-2">
                        <?php foreach ($assignedDrills as $drill): ?>
                            <span class="drill-chip">
                                <?= htmlspecialchars($drill['icr_number'] ?: 'K?') ?>
                                <?php if (!empty($drill['title'])): ?>
                                    — <?= htmlspecialchars($drill['title']) ?>
                                <?php endif; ?>
                                <?php if (!empty($drill['drill_type'])): ?>
                                    (<?= htmlspecialchars($drill['drill_type']) ?>)
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($drillQrCode): ?>
                <div class="row g-4 align-items-start">
                    <div class="col-md-4">
                        <div class="qr-preview">
                            <img src="qr_image.php?code=<?= urlencode($drillQrCode) ?>" alt="Drill Center QR Code">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <strong>QR Code Status</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>

                        <div class="mb-3">
                            <strong>Public URL</strong><br>
                            <span class="mono"><?= htmlspecialchars($drillPublicUrl) ?></span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" target="_blank"
                               href="public_drills.php?code=<?= urlencode($drillQrCode) ?>">
                                View Public Page
                            </a>

                            <a class="btn btn-outline-dark" target="_blank"
                               href="qr_image.php?code=<?= urlencode($drillQrCode) ?>">
                                Open QR Image
                            </a>

                            <a class="btn btn-outline-success"
                               href="generate_drills_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                                Regenerate QR
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No Drill Center QR code has been created for this vessel yet.
                </div>

                <a class="btn btn-success"
                   href="generate_drills_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                    Generate Drill Center QR
                </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Vessel Log</strong>
        </div>
        <div class="card-body">

            <div class="mb-3 text-muted">
                Scan this QR code to log in and open the vessel log entry page for this vessel.
            </div>

            <?php if ($vesselLogQrCode): ?>
                <div class="row g-4 align-items-start">
                    <div class="col-md-4">
                        <div class="qr-preview">
                            <img src="qr_image.php?code=<?= urlencode($vesselLogQrCode) ?>" alt="Vessel Log QR Code">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="mb-3">
                            <strong>QR Code Status</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>

                        <div class="mb-3">
                            <strong>Public URL</strong><br>
                            <span class="mono"><?= htmlspecialchars($vesselLogPublicUrl) ?></span>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary" target="_blank"
                               href="public_vessel_log.php?code=<?= urlencode($vesselLogQrCode) ?>">
                                View Public Page
                            </a>

                            <a class="btn btn-outline-dark" target="_blank"
                               href="qr_image.php?code=<?= urlencode($vesselLogQrCode) ?>">
                                Open QR Image
                            </a>

                            <a class="btn btn-outline-success"
                               href="generate_vessel_log_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                                Regenerate QR
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No Vessel Log QR code has been created for this vessel yet.
                </div>

                <a class="btn btn-success"
                   href="generate_vessel_log_qr.php?vessel_id=<?= (int)$vessel_id ?>">
                    Generate Vessel Log QR
                </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Portable Fire Extinguishers</strong>
        </div>
        <div class="card-body">

            <?php if (!$portableExtinguishers): ?>
                <div class="alert alert-secondary mb-0">
                    No portable fire extinguishers found for this vessel.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($portableExtinguishers as $item): ?>
        <?php
        $eid = (int)$item['eid'];
        $itemQrCode = $equipmentQrMap[$eid] ?? null;
        $itemPublicUrl = $itemQrCode
            ? $qrBaseUrl . '/public_equipment.php?code=' . urlencode($itemQrCode)
            : null;
        ?>
                        <div class="col-lg-6">
                            <div class="equipment-card">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <div class="mb-2">
                                            <strong><?= htmlspecialchars($item['equipmentName'] ?: 'Unnamed Equipment') ?></strong>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Location:</strong>
                                            <?= htmlspecialchars($item['equipmentLocation'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Manufacturer:</strong>
                                            <?= htmlspecialchars($item['manufacturer'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Model:</strong>
                                            <?= htmlspecialchars($item['modelNumber'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Serial:</strong>
                                            <?= htmlspecialchars($item['serialNumber'] ?: '—') ?>
                                        </div>

                                        <div class="mb-3">
                                            <strong>Expiration:</strong>
                                            <?= htmlspecialchars($item['expDate'] ?: '—') ?>
                                        </div>

                                        <?php if ($itemQrCode): ?>
                                            <div class="mb-2">
                                                <strong>Status:</strong>
                                                <span class="badge bg-success">QR Active</span>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Public URL</strong><br>
                                                <span class="mono small"><?= htmlspecialchars($itemPublicUrl) ?></span>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2">
                                                <a class="btn btn-sm btn-primary" target="_blank"
                                                   href="public_equipment.php?code=<?= urlencode($itemQrCode) ?>">
                                                    View Public Page
                                                </a>

                                                <a class="btn btn-sm btn-outline-dark" target="_blank"
                                                   href="qr_image.php?code=<?= urlencode($itemQrCode) ?>">
                                                    Open QR Image
                                                </a>

                                                <a class="btn btn-sm btn-outline-success"
                                                   href="generate_equipment_qr.php?eid=<?= $eid ?>">
                                                    Regenerate QR
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3">
                                                <strong>Status:</strong>
                                                <span class="badge bg-warning text-dark">No QR Yet</span>
                                            </div>

                                            <a class="btn btn-sm btn-success"
                                               href="generate_equipment_qr.php?eid=<?= $eid ?>">
                                                Generate QR
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-5">
                                        <?php if ($itemQrCode): ?>
                                            <div class="qr-preview-sm">
                                                <img src="qr_image.php?code=<?= urlencode($itemQrCode) ?>" alt="Equipment QR Code">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Fixed Fire Extinguishing Systems</strong>
        </div>
        <div class="card-body">

            <?php if (!$fixedExtinguishers): ?>
                <div class="alert alert-secondary mb-0">
                    No fixed fire extinguishing systems found for this vessel.
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($fixedExtinguishers as $item): ?>
        <?php
        $eid = (int)$item['eid'];
        $itemQrCode = $equipmentQrMap[$eid] ?? null;
        $itemPublicUrl = $itemQrCode
            ? $qrBaseUrl . '/public_equipment.php?code=' . urlencode($itemQrCode)
            : null;
        ?>
                        <div class="col-lg-6">
                            <div class="equipment-card">
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <div class="mb-2">
                                            <strong><?= htmlspecialchars($item['equipmentName'] ?: 'Unnamed Equipment') ?></strong>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Location:</strong>
                                            <?= htmlspecialchars($item['equipmentLocation'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Manufacturer:</strong>
                                            <?= htmlspecialchars($item['manufacturer'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Model:</strong>
                                            <?= htmlspecialchars($item['modelNumber'] ?: '—') ?>
                                        </div>

                                        <div class="mb-1">
                                            <strong>Serial:</strong>
                                            <?= htmlspecialchars($item['serialNumber'] ?: '—') ?>
                                        </div>

                                        <div class="mb-3">
                                            <strong>Expiration:</strong>
                                            <?= htmlspecialchars($item['expDate'] ?: '—') ?>
                                        </div>

                                        <?php if ($itemQrCode): ?>
                                            <div class="mb-2">
                                                <strong>Status:</strong>
                                                <span class="badge bg-success">QR Active</span>
                                            </div>

                                            <div class="mb-3">
                                                <strong>Public URL</strong><br>
                                                <span class="mono small"><?= htmlspecialchars($itemPublicUrl) ?></span>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2">
                                                <a class="btn btn-sm btn-primary" target="_blank"
                                                   href="public_equipment.php?code=<?= urlencode($itemQrCode) ?>">
                                                    View Public Page
                                                </a>

                                                <a class="btn btn-sm btn-outline-dark" target="_blank"
                                                   href="qr_image.php?code=<?= urlencode($itemQrCode) ?>">
                                                    Open QR Image
                                                </a>

                                                <a class="btn btn-sm btn-outline-success"
                                                   href="generate_equipment_qr.php?eid=<?= $eid ?>">
                                                    Regenerate QR
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3">
                                                <strong>Status:</strong>
                                                <span class="badge bg-warning text-dark">No QR Yet</span>
                                            </div>

                                            <a class="btn btn-sm btn-success"
                                               href="generate_equipment_qr.php?eid=<?= $eid ?>">
                                                Generate QR
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-5">
                                        <?php if ($itemQrCode): ?>
                                            <div class="qr-preview-sm">
                                                <img src="qr_image.php?code=<?= urlencode($itemQrCode) ?>" alt="Equipment QR Code">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>
