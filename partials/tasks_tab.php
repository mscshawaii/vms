<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../db_connect.php';
}
if (!isset($vessel_id)) exit('Vessel ID missing');
?>

<h4>Corrective Actions</h4>
<a href="add_task.php?vessel_id=<?= $vessel_id ?>" class="btn btn-success mb-3">➕ Create Corrective Action for This Vessel</a>

<input type="text" id="taskSearch" class="form-control mb-3" placeholder="🔎 Search by Title, Status, or Equipment...">

<table class="table table-bordered table-striped" id="taskTable">
    <thead class="table-light">
        <tr>
            <th>Title</th>
            <th>Due</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Equipment</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $stmt = $pdo->prepare("
            SELECT t.*, t.vessel_icr_run_id, e.equipmentName, vi.icr_id, i.icr_number
            FROM tasks t
            LEFT JOIN equipment e ON t.equipment_id = e.eid
            LEFT JOIN vessel_icrs vi ON t.vessel_icr_id = vi.vessel_icr_id
            LEFT JOIN icrs i ON vi.icr_id = i.icr_id
            WHERE t.vessel_id = ?
            ORDER BY t.due_date
        ");
        $stmt->execute([$vessel_id]);

        $now = new DateTime();

        if ($stmt->rowCount() === 0) {
            echo "<tr><td colspan='6'>📭 No corrective actions assigned to this vessel.</td></tr>";
        } else {
            while ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $due = $task['due_date'];
                $dueClass = '';
                $dueDateObj = $due ? new DateTime($due) : null;

                if ($dueDateObj) {
                    $nowClone = clone $now;
                    if ($dueDateObj < $nowClone) {
                        $dueClass = 'table-danger';
                    } elseif ($dueDateObj <= $nowClone->modify('+7 days')) {
                        $dueClass = 'table-warning';
                    }
                }

                echo "<tr class='$dueClass'>";
                echo "<td>" . htmlspecialchars($task['title']) . "</td>";
                echo "<td>" . htmlspecialchars($due) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $task['status']))) . "</td>";
                echo "<td>" . htmlspecialchars(ucfirst($task['priority'])) . "</td>";
                echo "<td>" . htmlspecialchars($task['equipmentName'] ?? '—') . "</td>";
                echo "<td>
                        <a href='edit_task.php?id={$task['task_id']}' class='btn btn-sm btn-primary me-1'>Edit</a>
                        <a href='delete_task.php?id={$task['task_id']}&vessel_id=$vessel_id' 
                           class='btn btn-sm btn-danger me-1' 
                           onclick=\"return confirm('Are you sure you want to delete this corrective action?')\">Delete</a>";
                if (!empty($task['vessel_icr_run_id'])) {
                    echo "<a href='view_icr_run.php?run_id={$task['vessel_icr_run_id']}' 
                              class='btn btn-sm btn-outline-secondary' 
                              title='View completed ICR run'
                              target='_blank'>📄 ICR Run</a>";
                }
                echo "</td></tr>";
            }
        }
        ?>
    </tbody>
</table>

<script>
document.getElementById('taskSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#taskTable tbody tr').forEach(row => {
        const cells = Array.from(row.cells).map(td => td.textContent.toLowerCase());
        row.style.display = cells.some(text => text.includes(term)) ? '' : 'none';
    });
});
</script>
