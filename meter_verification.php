<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (!vms_hour_user_can_manage_history()) {
    http_response_code(403);
    exit('Not authorized.');
}

$vesselId = (int)($_GET['vessel_id'] ?? 0);
$equipmentId = (int)($_GET['equipment_id'] ?? 0);
$taskId = (int)($_GET['task_id'] ?? 0);

if ($vesselId <= 0 && $equipmentId > 0) {
    $eqStmt = $pdo->prepare("SELECT vessel_id FROM equipment WHERE eid = ? LIMIT 1");
    $eqStmt->execute([$equipmentId]);
    $vesselId = (int)($eqStmt->fetchColumn() ?: 0);
}

if ($vesselId <= 0) {
    exit('Missing vessel_id.');
}

$meters = vms_hour_get_tracked_meters_for_vessel($pdo, $vesselId, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meter Verification - VMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">
</head>
<body>
<?php
$title = 'Meter Verification';
$back_link = 'vessel_dashboard.php?vessel_id=' . $vesselId;
include __DIR__ . '/partials/top_nav.php';
?>
<div class="app-page">
    <div class="app-container">
        <div class="vms-card">
            <h1 class="h4 mb-3">Monthly Meter Verification</h1>

            <form method="post" action="submit_meter_verification.php" class="row g-3">
                <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                <input type="hidden" name="task_id" value="<?= (int)$taskId ?>">

                <?php foreach ($meters as $meter): ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= htmlspecialchars($meter['equipmentName'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($meter['equipmentLocation'], ENT_QUOTES, 'UTF-8') ?>)</label>
                        <input type="number" step="0.1" min="0" name="meter_hours[<?= (int)$meter['meter_id'] ?>]" class="form-control" value="<?= htmlspecialchars((string)$meter['current_hours'], ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                <?php endforeach; ?>

                <div class="col-12">
                    <label class="form-label">Verification Note</label>
                    <textarea name="verification_note" class="form-control" rows="3" placeholder="Optional verification note"></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Verification</button>
                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vesselId ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
