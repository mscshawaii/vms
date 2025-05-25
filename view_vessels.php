<?php
require 'db_connect.php';
session_start();

$company_id = $_SESSION['company_id'] ?? null;
$role_id = $_SESSION['role_id'] ?? null;

// Fetch vessels based on role
if ($role_id == 1) {
    $sql = "SELECT v.vessel_id, v.vesselName, v.vesselON, v.hailingPort, v.callSign, v.mmsi, v.length, v.grossTons, o.company_name
            FROM vessels v
            LEFT JOIN owners o ON v.company_id = o.owner_id
            ORDER BY v.vesselName";
    $vessels = $pdo->query($sql)->fetchAll();
} else {
    $sql = "SELECT vessel_id, vesselName, vesselON, hailingPort, callSign, mmsi, length, grossTons
            FROM vessels
            WHERE company_id = ?
            ORDER BY vesselName";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$company_id]);
    $vessels = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Vessels</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-3">🛥️ Vessel List</h2>
    <a href="add_vessel.php?company_id=<?= $company_id ?>" class="btn btn-success mb-3">➕ Add New Vessel</a>

    <?php if (count($vessels) > 0): ?>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>ON</th>
                <th>Hailing Port</th>
                <th>Call Sign</th>
                <th>MMSI</th>
                <th>Length</th>
                <th>Gross Tons</th>
                <?php if ($role_id == 1): ?>
                    <th>Company</th>
                <?php endif; ?>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vessels as $row): ?>
            <tr>
                <td><?= $row['vessel_id'] ?></td>
                <td><?= htmlspecialchars($row['vesselName']) ?></td>
                <td><?= htmlspecialchars($row['vesselON']) ?></td>
                <td><?= htmlspecialchars($row['hailingPort']) ?></td>
                <td><?= htmlspecialchars($row['callSign']) ?></td>
                <td><?= htmlspecialchars($row['mmsi']) ?></td>
                <td><?= htmlspecialchars($row['length']) ?></td>
                <td><?= htmlspecialchars($row['grossTons']) ?></td>
                <?php if ($role_id == 1): ?>
                    <td><?= htmlspecialchars($row['company_name']) ?></td>
                <?php endif; ?>
                <td>
                    <a href="vessel_dashboard.php?vessel_id=<?= $row['vessel_id'] ?>" class="btn btn-sm btn-info">View</a>
                    <a href="edit_vessel.php?id=<?= $row['vessel_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="delete_vessel.php?id=<?= $row['vessel_id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert alert-warning">No vessels available for your view.</div>
    <?php endif; ?>
</div>
</body>
</html>
