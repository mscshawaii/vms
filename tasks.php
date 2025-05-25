<?php require 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>🧰 Corrective Actions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📋 Corrective Actions</h2>
    <a href="add_task.php" class="btn btn-success mb-3">➕ Add Corrective Action</a>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Corrective Action saved successfully.</div>
    <?php endif; ?>

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Title</th>
                <th>Due</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Corrective Action Taken</th>
                <th>Corrected Date</th>
                <th>Vessel</th>
                <th>Equipment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $tasks = $mysqli->query("
            SELECT t.*, v.vesselName, e.equipmentName
            FROM tasks t
            LEFT JOIN vessels v ON t.vessel_id = v.vessel_id
            LEFT JOIN equipment e ON t.equipment_id = e.eid
            ORDER BY t.due_date
        ");
        while ($task = $tasks->fetch_assoc()) {
            $row_class = match($task['status']) {
                'overdue' => 'table-danger',
                'deferred' => 'table-secondary',
                'complete' => 'table-success',
                'in_progress' => 'table-warning',
                default => '',
            };

            $corrective_display = $task['corrective_action'] 
                ? htmlspecialchars($task['corrective_action']) 
                : '<span class="text-muted">— <small class="text-warning">⚠️</small></span>';

            echo "<tr class='$row_class'>";
            echo "<td>" . htmlspecialchars($task['title']) . "</td>";
            echo "<td>" . htmlspecialchars($task['due_date']) . "</td>";
            echo "<td>" . htmlspecialchars($task['status']) . "</td>";
            echo "<td>" . htmlspecialchars($task['priority']) . "</td>";
            echo "<td>$corrective_display</td>";
            echo "<td>" . ($task['corrected_date'] ?? '—') . "</td>";

            echo "<td>" . htmlspecialchars($task['vesselName'] ?? '—') . "</td>";
            echo "<td>" . htmlspecialchars($task['equipmentName'] ?? '—') . "</td>";
            echo "<td>
                <a href='edit_task.php?id={$task['task_id']}' class='btn btn-sm btn-primary'>Edit</a>
                <a href='delete_task.php?id={$task['task_id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this corrective action?')\">Delete</a>
              </td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>
</body>
</html>
