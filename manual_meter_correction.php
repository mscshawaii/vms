<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$meterId = (int)($_GET['meter_id'] ?? 0);
if ($meterId <= 0) {
    exit('Missing meter_id.');
}

$stmt = $pdo->prepare("
    SELECT hm.*, e.equipmentName, e.equipmentLocation
    FROM equipment_hour_meters hm
    INNER JOIN equipment e ON e.eid = hm.equipment_id
    WHERE hm.meter_id = ?
    LIMIT 1
");
$stmt->execute([$meterId]);
$meter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$meter) {
    exit('Meter not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Correct Meter - VMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">
</head>
<body>
<?php
$title = 'Correct Meter';
$back_link = 'equipment_detail.php?id=' . (int)$meter['equipment_id'];
include __DIR__ . '/partials/top_nav.php';
?>
<div class="app-page">
    <div class="app-container">
        <div class="vms-card">
            <h1 class="h4 mb-3">Authorized Meter Correction</h1>
            <p class="text-muted"><?= htmlspecialchars($meter['equipmentName'], ENT_QUOTES, 'UTF-8') ?> (current <?= htmlspecialchars((string)$meter['current_hours'], ENT_QUOTES, 'UTF-8') ?> hours)</p>

            <form method="post" action="submit_manual_meter_correction.php" class="row g-3">
                <input type="hidden" name="meter_id" value="<?= (int)$meterId ?>">

                <div class="col-md-4">
                    <label class="form-label">New Current Hours</label>
                    <input type="number" step="0.1" min="0" name="new_hours" class="form-control" value="<?= htmlspecialchars((string)$meter['current_hours'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Required Reason</label>
                    <textarea name="reason" class="form-control" rows="4" required placeholder="Explain the correction for the audit log."></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">Save Correction</button>
                    <a href="equipment_detail.php?id=<?= (int)$meter['equipment_id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
