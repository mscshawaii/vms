<?php
require 'db_connect.php';
require 'session_check.php';

$run_id = intval($_GET['run_id'] ?? 0);
if (!$run_id) {
    die("❌ Invalid ICR run ID.");
}

// Fetch run info
$run_stmt = $pdo->prepare("
    SELECT r.run_id, r.run_date, r.inspector, i.icr_number, i.title, i.reference_text, v.vessel_id, v.vesselName
    FROM vessel_icr_runs r
    JOIN icrs i ON r.icr_id = i.icr_id
    JOIN vessels v ON r.vessel_id = v.vessel_id
    WHERE r.run_id = ?
");

$run_stmt->execute([$run_id]);
$run = $run_stmt->fetch(PDO::FETCH_ASSOC);

if (!$run) {
    die("❌ ICR run not found.");
}

// Fetch steps for this run
$steps_stmt = $pdo->prepare("
    SELECT vs.step_number, vs.step_description, rs.status, rs.comment
    FROM vessel_icr_step_status rs
    JOIN vessel_icr_steps vs ON rs.vessel_icr_step_id = vs.step_id
    WHERE rs.run_id = ?
    ORDER BY vs.step_number
");
$steps_stmt->execute([$run_id]);
$steps = $steps_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>View ICR Run</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
    <h3>📋 Inspection Report: <?= htmlspecialchars($run['icr_number']) ?> – <?= htmlspecialchars($run['title']) ?></h3>
    <p>
        <strong>Vessel:</strong> <?= htmlspecialchars($run['vesselName']) ?><br>
        <strong>Date:</strong> <?= htmlspecialchars($run['run_date']) ?><br>
        <strong>Inspector:</strong> <?= htmlspecialchars($run['inspector']) ?>
    </p>
    
    <?php if (!empty($run['reference_text'])): ?>
        <div class="alert alert-info">
            <strong>Reference:</strong><br>
            <?= nl2br(htmlspecialchars($run['reference_text'])) ?>
        </div>
    <?php endif; ?>

    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Step Description</th>
                <th>Status</th>
                <th>Comments</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($steps as $step): ?>
                <tr>
                    <td><?= htmlspecialchars($step['step_number']) ?></td>
                    <td><?= nl2br(htmlspecialchars($step['step_description'])) ?></td>
                    <td>
                        <?php
                        $status = strtolower($step['status']);
                        $badge = match ($status) {
                            'pass' => 'success',
                            'fail' => 'danger',
                            'n/a'  => 'secondary',
                            default => 'light'
                        };
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= strtoupper($status) ?></span>
                    </td>
                    <td><?= htmlspecialchars($step['comment'] ?? '--') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a href="vessel_dashboard.php?vessel_id=<?= $run['vessel_id'] ?>#tasks" class="btn btn-secondary">← Back to Vessel Dashboard</a>

</div>
</body>
</html>
