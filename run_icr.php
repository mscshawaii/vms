<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_GET['vessel_id'] ?? 0);
$icr_id = intval($_GET['icr_id'] ?? 0);
$inspector = trim($_GET['inspector'] ?? 'Unknown');

// Fetch vessel and ICR info
$vessel_stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
$vessel_stmt->execute([$vessel_id]);
$vessel = $vessel_stmt->fetch(PDO::FETCH_ASSOC);

$icr_stmt = $pdo->prepare("SELECT icr_number, title, reference_text FROM icrs WHERE icr_id = ?");
$icr_stmt->execute([$icr_id]);
$icr = $icr_stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel || !$icr) {
    die("❌ Invalid vessel or ICR.");
}

// Fetch vessel-specific ICR assignment
$vessel_icr_stmt = $pdo->prepare("SELECT vessel_icr_id FROM vessel_icrs WHERE vessel_id = ? AND icr_id = ?");
$vessel_icr_stmt->execute([$vessel_id, $icr_id]);
$vessel_icr_id = $vessel_icr_stmt->fetchColumn();

if (!$vessel_icr_id) {
    die("❌ Vessel-specific ICR assignment not found.");
}

// Fetch vessel-specific steps
$steps_stmt = $pdo->prepare("
    SELECT step_id, step_number, step_description 
    FROM vessel_icr_steps 
    WHERE vessel_icr_id = ?
    ORDER BY step_number
");
$steps_stmt->execute([$vessel_icr_id]);
$steps = $steps_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Run ICR Inspection</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📋 ICR Inspection: <?= htmlspecialchars($icr['icr_number']) ?> – <?= htmlspecialchars($vessel['vesselName']) ?></h2>
    <p><strong>Title:</strong> <?= htmlspecialchars($icr['title']) ?></p>
    <p><strong>Reference:</strong> <?= nl2br(htmlspecialchars($icr['reference_text'])) ?></p>
    <p><strong>Inspector:</strong> <?= htmlspecialchars($inspector) ?></p>

    <form method="post" action="submit_icr_run.php">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">
        <input type="hidden" name="icr_id" value="<?= $icr_id ?>">
        <input type="hidden" name="vessel_icr_id" value="<?= $vessel_icr_id ?>">
        <input type="hidden" name="inspector" value="<?= htmlspecialchars($inspector) ?>">

        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 50%">Inspection Step</th>
                    <th style="width: 15%">Status</th>
                    <th>Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($steps as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['step_number']) ?></td>
                        <td><?= nl2br(htmlspecialchars($s['step_description'])) ?></td>
                        <td>
                            <input type="hidden" name="step_id[]" value="<?= $s['step_id'] ?>">
                            <select name="status[]" class="form-select">
                                <option value="pass">✔️ Pass</option>
                                <option value="fail">❌ Fail</option>
                                <option value="n/a">➖ N/A</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="comment[]" class="form-control" placeholder="Optional notes">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-primary">✅ Submit Inspection Results</button>
    </form>
</div>
</body>
</html>
