<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'session_check.php';
require 'db_connect.php';

$vessel_id = isset($_GET['vessel_id']) ? intval($_GET['vessel_id']) : null;
if (!$vessel_id) {
    die("Vessel not specified.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Corrective Action</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">? Add Corrective Action</h2>

    <form action="submit_task.php" method="post" class="row g-3">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

        <div class="col-md-6">
            <label class="form-label">Task Title:</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Due Date:</label>
            <input type="date" name="due_date" class="form-control" required>
        </div>

        <div class="col-12">
            <label class="form-label">Description:</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Describe the task or issue..."></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label">Priority:</label>
            <select name="priority" class="form-select">
                <option value="urgent">1 - Urgent</option>
                <option value="moderate" selected>2 - Moderate</option>
                <option value="low">3 - Low</option>
                <option value="recommendation">4 - Recommendation</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assign to Equipment:</label>
            <select name="equipment_id" class="form-select">
                <option value="">-- Select Equipment --</option>
                <?php
                $stmt = $pdo->prepare("SELECT eid, equipmentName FROM equipment WHERE vessel_id = ? ORDER BY equipmentName");
                $stmt->execute([$vessel_id]);
                while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$e['eid']}'>" . htmlspecialchars($e['equipmentName']) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assigned To (Crew Member):</label>
            <select name="assigned_to" class="form-select">
                <option value="">-- Select Crew Member --</option>
                <?php
                $crew = $pdo->query("SELECT crew_id, first_name, last_name, title FROM crew_members ORDER BY last_name, first_name");
                while ($c = $crew->fetch(PDO::FETCH_ASSOC)) {
                    $name = "{$c['first_name']} {$c['last_name']} ({$c['title']})";
                    echo "<option value='{$c['crew_id']}'>{$name}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Recurring Interval:</label>
            <select name="recurrence_interval" class="form-select">
                <option value="none">None</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annually">Annually</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Corrective Action Taken:</label>
            <textarea name="corrective_action" class="form-control" placeholder="Describe corrective actions (optional)"></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Corrected Date:</label>
            <input type="date" name="corrected_date" class="form-control">
        </div>

        <div class="col-12 mt-3">
            <button type="submit" class="btn btn-primary">?? Save Corrective Action</button>
            <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#tasks" class="btn btn-secondary">? Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
