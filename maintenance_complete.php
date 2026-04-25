<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

$scheduleId = (int)($_GET['schedule_id'] ?? 0);
if ($scheduleId <= 0) {
    exit('Missing schedule_id.');
}

$stmt = $pdo->prepare("
    SELECT s.*, hm.current_hours, e.equipmentName, e.equipmentLocation
    FROM equipment_maintenance_schedules s
    INNER JOIN equipment_hour_meters hm ON hm.meter_id = s.meter_id
    INNER JOIN equipment e ON e.eid = s.equipment_id
    WHERE s.schedule_id = ?
    LIMIT 1
");
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$schedule) {
    exit('Maintenance schedule not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Maintenance - VMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">
</head>
<body>
<?php
$title = 'Complete Maintenance';
$back_link = 'equipment_detail.php?id=' . (int)$schedule['equipment_id'];
include __DIR__ . '/partials/top_nav.php';
?>
<div class="app-page">
    <div class="app-container">
        <div class="vms-card">
            <h1 class="h4 mb-3">Complete Maintenance</h1>
            <p class="text-muted"><?= htmlspecialchars($schedule['equipmentName'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($schedule['service_name'], ENT_QUOTES, 'UTF-8') ?></p>

            <form method="post" action="submit_maintenance_complete.php" class="row g-3">
                <input type="hidden" name="schedule_id" value="<?= (int)$scheduleId ?>">

                <div class="col-md-4">
                    <label class="form-label">Completion Date</label>
                    <input type="date" name="completion_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Completion Hours</label>
                    <input type="number" step="0.1" min="0" name="completion_hours" class="form-control" value="<?= htmlspecialchars((string)$schedule['current_hours'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Performed By</label>
                    <input type="text" name="performed_by" class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="note" class="form-control" rows="4" placeholder="Optional completion notes"></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-success">Save Completion</button>
                    <a href="equipment_detail.php?id=<?= (int)$schedule['equipment_id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
