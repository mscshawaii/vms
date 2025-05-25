<?php if (!isset($vessel_id)) exit('Vessel ID missing'); ?>

<h4>Crew Assignments</h4>
<a href="crew_members.php?vessel_id=<?= $vessel_id ?>" class="btn btn-outline-primary mb-3">👥 Manage All Crew Members</a>

<!-- Assign Crew Form -->
<form method="post" action="assign_crew.php" class="row g-2 align-items-end mb-4">
    <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">
    <div class="col-md-4">
        <label>Select Crew Member:</label>
        <select name="crew_id" class="form-select" required>
            <option value="">-- Select --</option>
            <?php
            $crewStmt = $pdo->query("SELECT crew_id, first_name, last_name, title FROM crew_members ORDER BY last_name, first_name");
            while ($c = $crewStmt->fetch(PDO::FETCH_ASSOC)) {
                $fullName = "{$c['first_name']} {$c['last_name']}";
                echo "<option value='{$c['crew_id']}'>{$fullName} ({$c['title']})</option>";
            }
            ?>
        </select>
    </div>
    <div class="col-md-3">
        <label>Role (optional):</label>
        <input type="text" name="role" class="form-control">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary">➕ Assign</button>
    </div>
</form>

<!-- Assigned Crew Table -->
<table class="table table-bordered">
    <thead class="table-light">
        <tr>
            <th>Name</th>
            <th>Title</th>
            <th>Role</th>
            <th>Assigned On</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $crewStmt = $pdo->prepare("
        SELECT vc.id, cm.first_name, cm.last_name, cm.title, vc.role, vc.assigned_on
        FROM vessel_crew vc
        LEFT JOIN crew_members cm ON vc.crew_id = cm.crew_id
        WHERE vc.vessel_id = ?
        ORDER BY cm.last_name, cm.first_name
    ");
    $crewStmt->execute([$vessel_id]);

    while ($row = $crewStmt->fetch(PDO::FETCH_ASSOC)) {
        $name = "{$row['first_name']} {$row['last_name']}";
        echo "<tr>
            <td>{$name}</td>
            <td>{$row['title']}</td>
            <td>{$row['role']}</td>
            <td>{$row['assigned_on']}</td>
            <td>
                <a href='remove_crew.php?id={$row['id']}&vessel={$vessel_id}' class='btn btn-sm btn-danger' onclick=\"return confirm('Unassign this crew member?')\">Remove</a>
            </td>
        </tr>";
    }
    ?>
    </tbody>
</table>
