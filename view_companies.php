<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

// Fetch companies with vessel counts
$stmt = $pdo->query("
    SELECT o.*, COUNT(v.vessel_id) AS vessel_count
    FROM owners o
    LEFT JOIN vessels v ON o.owner_id = v.company_id
    GROUP BY o.owner_id
    ORDER BY o.company_name
");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Companies</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Companies</h2>
        <a href="dashboard.php" class="btn btn-secondary">? Back to Dashboard</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Company deleted successfully.</div>
    <?php endif; ?>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Company Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Vessels</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($companies as $company): ?>
            <tr>
                <td><?= htmlspecialchars($company['company_name']) ?></td>
                <td><?= htmlspecialchars($company['contact_name']) ?></td>
                <td><?= htmlspecialchars($company['email']) ?></td>
                <td><?= htmlspecialchars($company['phone']) ?></td>
                <td><?= $company['vessel_count'] ?></td>
                <td>
                    <?php if ($company['vessel_count'] == 0): ?>
                        <a href="delete_company.php?id=<?= $company['owner_id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this company?');">
                           Delete
                        </a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
