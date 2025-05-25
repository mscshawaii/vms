<?php
require 'session_check.php';
require 'db_connect.php';

// Restrict to MSCS Hawaii
if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

function safe($str) {
    return htmlspecialchars($str ?? '—');
}

// Fetch vessels
$vessels = $pdo->query("SELECT vessel_id, vesselName FROM vessels ORDER BY vesselName")->fetchAll();

// Fetch ICR templates
$icrs = $pdo->query("SELECT icr_id, icr_number, title FROM icrs ORDER BY icr_number")->fetchAll();

// Get vessel selected
$selected_vessel = isset($_GET['vessel_id']) ? intval($_GET['vessel_id']) : null;
$assigned_icrs = [];

if ($selected_vessel) {
    $stmt = $pdo->prepare("SELECT icr_id FROM vessel_icrs WHERE vessel_id = ?");
    $stmt->execute([$selected_vessel]);
    $assigned_icrs = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'icr_id');
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign ICRs to Vessels</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📋 Assign ICRs to Vessels</h2>

    <form method="get" class="mb-4">
        <label for="vessel_id" class="form-label">Select Vessel:</label>
        <select name="vessel_id" id="vessel_id" class="form-select" onchange="this.form.submit()">
            <option value="">-- Choose Vessel --</option>
            <?php foreach ($vessels as $v): ?>
                <option value="<?= $v['vessel_id'] ?>" <?= $selected_vessel == $v['vessel_id'] ? 'selected' : '' ?>>
                    <?= safe($v['vesselName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selected_vessel): ?>
        <h4 class="mb-3">🔧 Assign or Remove ICRs for <?= safe($vessels[array_search($selected_vessel, array_column($vessels, 'vessel_id'))]['vesselName']) ?></h4>

        <form method="post" action="submit_icr_assignment.php">
            <input type="hidden" name="vessel_id" value="<?= $selected_vessel ?>">

            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Assign</th>
                        <th>ICR Number</th>
                        <th>Title</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($icrs as $i): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="assigned_icrs[]" value="<?= $i['icr_id'] ?>"
                                    <?= in_array($i['icr_id'], $assigned_icrs) ? 'checked' : '' ?>>
                            </td>
                            <td><?= safe($i['icr_number']) ?></td>
                            <td><?= safe($i['title']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary">💾 Save Assignments</button>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
