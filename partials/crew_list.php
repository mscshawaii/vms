<?php
require __DIR__ . '/../db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$vessel_id = intval($_GET['vessel_id'] ?? 0);
if (!$vessel_id) {
    echo "<p class='text-danger'>No vessel selected.</p>";
    exit;
}

$company_id = $_SESSION['company_id'] ?? null;
$is_mscs = $company_id == 1;

if ($is_mscs) {
    $stmt = $mysqli->prepare("
        SELECT vc.id, u.fName, u.lName, vc.role, vc.assigned_on
        FROM vessel_crew vc
        LEFT JOIN users u ON vc.crew_id = u.id
        WHERE vc.vessel_id = ?
        ORDER BY u.lName, u.fName
    ");
    $stmt->bind_param("i", $vessel_id);
} else {
    $stmt = $mysqli->prepare("
        SELECT vc.id, u.fName, u.lName, vc.role, vc.assigned_on
        FROM vessel_crew vc
        LEFT JOIN users u ON vc.crew_id = u.id
        WHERE vc.vessel_id = ? AND u.company_id = ?
        ORDER BY u.lName, u.fName
    ");
    $stmt->bind_param("ii", $vessel_id, $company_id);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table table-bordered">
    <thead class="table-light">
        <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Assigned On</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php
            $name = ($row['fName'] ?? null) && ($row['lName'] ?? null)
                ? htmlspecialchars("{$row['fName']} {$row['lName']}")
                : "<em>Unknown User</em>";
            $role = htmlspecialchars($row['role'] ?? '');
            $assignedOn = htmlspecialchars($row['assigned_on'] ?? '');
            $id = (int)$row['id'];
        ?>
        <tr>
            <td><?= $name ?></td>
            <td><?= $role ?></td>
            <td><?= $assignedOn ?></td>
            <td>
                <button class="btn btn-sm btn-danger" onclick="removeCrew(<?= $id ?>, <?= $vessel_id ?>)">
                    Remove
                </button>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
