<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$equipmentId = (int)($_GET['id'] ?? 0);
if ($equipmentId <= 0) {
    exit('Missing equipment id.');
}

$stmt = $pdo->prepare("
    SELECT e.*, et.name AS type_name, es.name AS subtype_name
    FROM equipment e
    LEFT JOIN equipment_type et ON et.id = e.equipment_type_id
    LEFT JOIN equipment_subtype es ON es.id = e.equipment_subtype_id
    WHERE e.eid = ?
    LIMIT 1
");
$stmt->execute([$equipmentId]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipment) {
    exit('Equipment not found.');
}

$meter = vms_hour_get_meter_by_equipment($pdo, $equipmentId);
if ((int)($equipment['vessel_id'] ?? 0) <= 0 || (int)($equipment['is_active'] ?? 1) !== 1) {
    exit('Only active equipment can be replaced.');
}

$replacementExpDate = '';
if (!empty($equipment['expDate']) && $equipment['expDate'] !== '0000-00-00') {
    $replacementExpDate = (string)$equipment['expDate'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Replace Equipment - VMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">
</head>
<body>
<?php
$title = 'Replace Equipment';
$back_link = 'equipment_detail.php?id=' . $equipmentId;
include __DIR__ . '/partials/top_nav.php';
?>
<div class="app-page">
    <div class="app-container">
        <div class="vms-card">
            <h1 class="h4 mb-3">Replace Equipment</h1>
            <p class="text-muted"><?= htmlspecialchars($equipment['equipmentName'], ENT_QUOTES, 'UTF-8') ?></p>

            <form method="post" action="submit_equipment_replacement.php" class="row g-3">
                <input type="hidden" name="old_equipment_id" value="<?= (int)$equipmentId ?>">

                <div class="col-md-6">
                    <label class="form-label">New Equipment Name</label>
                    <input type="text" name="equipmentName" class="form-control" value="<?= htmlspecialchars((string)$equipment['equipmentName'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Replacement Location</label>
                    <input type="text" name="equipmentLocation" class="form-control" value="<?= htmlspecialchars((string)$equipment['equipmentLocation'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <?php if ($meter): ?>
                    <div class="col-md-6">
                        <label class="form-label">Installed Meter Reading</label>
                        <input type="number" step="0.1" min="0" name="installed_hours" class="form-control" value="<?= htmlspecialchars((string)$meter['current_hours'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Manufacturer</label>
                    <input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars((string)$equipment['manufacturer'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input type="text" name="modelNumber" class="form-control" value="<?= htmlspecialchars((string)$equipment['modelNumber'], ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serialNumber" class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Install Date</label>
                    <input type="date" name="installDate" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Expiration Date</label>
                    <input type="date" name="expDate" class="form-control" value="<?= htmlspecialchars($replacementExpDate, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <?php if ($meter): ?>
                    <div class="col-md-4">
                        <label class="form-label">Copy Schedules</label>
                        <select name="copy_schedules" class="form-select">
                            <option value="1" selected>Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Replacement Note</label>
                    <input type="text" name="replacement_note" class="form-control" placeholder="Optional">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-warning">Create Replacement</button>
                    <a href="equipment_detail.php?id=<?= (int)$equipmentId ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
