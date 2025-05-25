<?php
require 'db_connect.php'; // ✅ Updated to PDO style

$id = intval($_GET['id'] ?? 0);

// Fetch the task info
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("❌ Task not found.");
}

$vessel_id = $task['vessel_id']; // ✅ Grab the vessel_id for the redirect!
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Corrective Action</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>✏️ Edit Corrective Action</h2>

    <form method="post" action="update_task.php" class="row g-3">
        <input type="hidden" name="task_id" value="<?= intval($task['task_id']) ?>">
        <input type="hidden" name="vessel_id" value="<?= intval($vessel_id) ?>">

        <div class="col-md-6">
            <label class="form-label">Title:</label>
            <input type="text" name="title" value="<?= htmlspecialchars($task['title'] ?? '') ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Due Date:</label>
            <input type="date" name="due_date" value="<?= htmlspecialchars($task['due_date'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-12">
            <label class="form-label">Description:</label>
            <textarea name="description" class="form-control"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label">Status:</label>
            <select name="status" class="form-select">
                <?php
                $statuses = ['open', 'in_progress', 'complete', 'overdue', 'deferred'];
                foreach ($statuses as $s) {
                    $sel = ($task['status'] === $s) ? 'selected' : '';
                    echo "<option value='$s' $sel>$s</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Priority:</label>
            <select name="priority" class="form-select">
                <?php
                $priorities = ['urgent', 'moderate', 'low', 'recommendation'];
                foreach ($priorities as $p) {
                    $sel = ($task['priority'] === $p) ? 'selected' : '';
                    echo "<option value='$p' $sel>$p</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Recurring Interval:</label>
            <input type="text" name="recurrence_interval" value="<?= htmlspecialchars($task['recurrence_interval'] ?? '') ?>" class="form-control" placeholder="Optional">
        </div>

        <div class="col-md-6">
            <label class="form-label">Assign To (Crew Member):</label>
            <select name="assigned_to" class="form-select" required>
                <option value="">-- Select Crew Member --</option>
                <?php
                $crew = $pdo->query("SELECT crew_id, first_name, last_name, title FROM crew_members ORDER BY last_name, first_name")->fetchAll();
                foreach ($crew as $c) {
                    $fullName = "{$c['first_name']} {$c['last_name']} ({$c['title']})";
                    $selected = ($task['assigned_to'] == $c['crew_id']) ? 'selected' : '';
                    echo "<option value='{$c['crew_id']}' $selected>$fullName</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Corrective Action Taken:</label>
            <textarea name="corrective_action" class="form-control" rows="2"><?= htmlspecialchars($task['corrective_action'] ?? '') ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Corrected Date:</label>
            <input type="date" name="corrected_date" value="<?= htmlspecialchars($task['corrected_date'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="vessel_dashboard.php?vessel_id=<?= intval($vessel_id) ?>#tasks" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
