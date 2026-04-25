<?php
require 'db_connect.php';
require 'session_check.php';

$code = trim($_GET['code'] ?? $_POST['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/* Resolve QR */
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

/* Load vessel */
$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON, epirbHexId
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

/* Load battery */
$stmt = $pdo->prepare("
    SELECT *
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 22
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$battery = $stmt->fetch(PDO::FETCH_ASSOC);

/* Load HRU */
$stmt = $pdo->prepare("
    SELECT *
    FROM equipment
    WHERE vessel_id = ?
      AND equipment_type_id = 9
      AND equipment_subtype_id = 23
    ORDER BY expDate DESC, eid DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$hru = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $epirbHexId = trim($_POST['epirbHexId'] ?? '');

    $battery_manufacturer = trim($_POST['battery_manufacturer'] ?? '');
    $battery_model        = trim($_POST['battery_model'] ?? '');
    $battery_serial       = trim($_POST['battery_serial'] ?? '');
    $battery_install      = trim($_POST['battery_install'] ?? '') ?: null;
    $battery_exp          = trim($_POST['battery_exp'] ?? '') ?: null;
    $battery_location     = trim($_POST['battery_location'] ?? '');
    $battery_notes        = trim($_POST['battery_notes'] ?? '');

    $hru_manufacturer = trim($_POST['hru_manufacturer'] ?? '');
    $hru_model        = trim($_POST['hru_model'] ?? '');
    $hru_serial       = trim($_POST['hru_serial'] ?? '');
    $hru_install      = trim($_POST['hru_install'] ?? '') ?: null;
    $hru_exp          = trim($_POST['hru_exp'] ?? '') ?: null;
    $hru_location     = trim($_POST['hru_location'] ?? '');
    $hru_notes        = trim($_POST['hru_notes'] ?? '');

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE vessels
            SET epirbHexId = ?
            WHERE vessel_id = ?
        ");
        $stmt->execute([$epirbHexId, $vessel_id]);

        if (!empty($battery['eid'])) {
            $stmt = $pdo->prepare("
                UPDATE equipment
                SET manufacturer = ?, modelNumber = ?, serialNumber = ?, installDate = ?, expDate = ?, equipmentLocation = ?, notes = ?
                WHERE eid = ?
            ");
            $stmt->execute([
                $battery_manufacturer,
                $battery_model,
                $battery_serial,
                $battery_install,
                $battery_exp,
                $battery_location,
                $battery_notes,
                $battery['eid']
            ]);
        }

        if (!empty($hru['eid'])) {
            $stmt = $pdo->prepare("
                UPDATE equipment
                SET manufacturer = ?, modelNumber = ?, serialNumber = ?, installDate = ?, expDate = ?, equipmentLocation = ?, notes = ?
                WHERE eid = ?
            ");
            $stmt->execute([
                $hru_manufacturer,
                $hru_model,
                $hru_serial,
                $hru_install,
                $hru_exp,
                $hru_location,
                $hru_notes,
                $hru['eid']
            ]);
        }

        $pdo->commit();

        header("Location: vessel_qr_center.php?vessel_id={$vessel_id}&success=epirb_updated");
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error updating EPIRB info: " . htmlspecialchars($e->getMessage()));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edit EPIRB Info</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">

    <h2>Edit EPIRB Info</h2>
    <div class="text-muted mb-3">
        <?= htmlspecialchars($vessel['vesselName']) ?> (ON <?= htmlspecialchars($vessel['vesselON']) ?>)
    </div>

    <form method="post">
        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">

        <div class="card mb-3">
            <div class="card-header"><strong>Vessel EPIRB</strong></div>
            <div class="card-body">
                <label class="form-label">HEX ID</label>
                <input type="text" name="epirbHexId" class="form-control"
                       value="<?= htmlspecialchars($vessel['epirbHexId'] ?? '') ?>">
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Battery</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" name="battery_manufacturer" class="form-control" value="<?= htmlspecialchars($battery['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input type="text" name="battery_model" class="form-control" value="<?= htmlspecialchars($battery['modelNumber'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial</label>
                    <input type="text" name="battery_serial" class="form-control" value="<?= htmlspecialchars($battery['serialNumber'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Install Date</label>
                    <input type="date" name="battery_install" class="form-control" value="<?= htmlspecialchars($battery['installDate'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiration Date</label>
                    <input type="date" name="battery_exp" class="form-control" value="<?= htmlspecialchars($battery['expDate'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="battery_location" class="form-control" value="<?= htmlspecialchars($battery['equipmentLocation'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="battery_notes" class="form-control" rows="2"><?= htmlspecialchars($battery['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>HRU</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" name="hru_manufacturer" class="form-control" value="<?= htmlspecialchars($hru['manufacturer'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input type="text" name="hru_model" class="form-control" value="<?= htmlspecialchars($hru['modelNumber'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial</label>
                    <input type="text" name="hru_serial" class="form-control" value="<?= htmlspecialchars($hru['serialNumber'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Install Date</label>
                    <input type="date" name="hru_install" class="form-control" value="<?= htmlspecialchars($hru['installDate'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiration Date</label>
                    <input type="date" name="hru_exp" class="form-control" value="<?= htmlspecialchars($hru['expDate'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="hru_location" class="form-control" value="<?= htmlspecialchars($hru['equipmentLocation'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="hru_notes" class="form-control" rows="2"><?= htmlspecialchars($hru['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a class="btn btn-secondary" href="vessel_qr_center.php?vessel_id=<?= (int)$vessel_id ?>">Cancel</a>
        </div>
    </form>

</div>
</body>
</html>