<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_GET['vessel_id'] ?? 0);
$icr_id = intval($_GET['icr_id'] ?? 0);

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

if (!isAdmin()) {
    die("❌ Access denied. Only admin users can edit vessel-specific ICR steps.");
}

$stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel_name = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT icr_number, title FROM icrs WHERE icr_id = ?");
$stmt->execute([$icr_id]);
$icr_info = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT vessel_icr_id FROM vessel_icrs WHERE vessel_id = ? AND icr_id = ?");
$stmt->execute([$vessel_id, $icr_id]);
$vessel_icr_id = $stmt->fetchColumn();

if (!$vessel_icr_id) {
    die("❌ Vessel-specific ICR assignment not found.");
}

$stmt = $pdo->prepare("
    SELECT step_id, step_number, step_description
    FROM vessel_icr_steps
    WHERE vessel_icr_id = ?
    ORDER BY step_number
");
$stmt->execute([$vessel_icr_id]);
$steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit ICR Steps for <?= htmlspecialchars($vessel_name) ?> – <?= htmlspecialchars($icr_info['icr_number']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <div class="alert alert-warning">
        ⚠️ This is a vessel-specific ICR procedure. Changes here will only affect this vessel. Please proceed with caution.
    </div>

    <h2>Edit ICR Steps for <?= htmlspecialchars($vessel_name) ?> – <?= htmlspecialchars($icr_info['icr_number']) ?>: <?= htmlspecialchars($icr_info['title']) ?></h2>

    <button type="button" class="btn btn-outline-danger mb-3" onclick="toggleEdit()">🔓 Enable Editing</button>

    <form method="post" action="submit_vessel_icr_steps.php">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">
        <input type="hidden" name="icr_id" value="<?= $icr_id ?>">
        <input type="hidden" name="vessel_icr_id" value="<?= $vessel_icr_id ?>">

        <div id="stepsContainer">
            <?php foreach ($steps as $step): ?>
                <div class="mb-3 row">
                    <input type="hidden" name="step_id[]" value="<?= $step['step_id'] ?>">
                    <div class="col-md-2">
                        <input type="text" name="step_number[]" class="form-control" value="<?= htmlspecialchars($step['step_number']) ?>" required readonly>
                    </div>
                    <div class="col-md-9">
                        <input type="text" name="step_description[]" class="form-control" value="<?= htmlspecialchars($step['step_description']) ?>" required readonly>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.row').remove()" disabled>✖</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-secondary mb-3" onclick="addStep()" disabled id="addStepBtn">➕ Add Step</button>
        <br>
        <button type="submit" class="btn btn-primary" disabled id="saveBtn">💾 Save Steps</button>
        <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#icrs" class="btn btn-secondary">← Cancel</a>
    </form>
</div>

<script>
function toggleEdit() {
    document.querySelectorAll('#stepsContainer input').forEach(el => el.removeAttribute('readonly'));
    document.querySelectorAll('.btn-danger').forEach(btn => btn.removeAttribute('disabled'));
    document.getElementById('addStepBtn').removeAttribute('disabled');
    document.getElementById('saveBtn').removeAttribute('disabled');
}

function addStep() {
    const container = document.getElementById('stepsContainer');
    const row = document.createElement('div');
    row.className = 'mb-3 row';
    row.innerHTML = `
        <input type="hidden" name="step_id[]" value="new">
        <div class="col-md-2">
            <input type="text" name="step_number[]" class="form-control" required>
        </div>
        <div class="col-md-9">
            <input type="text" name="step_description[]" class="form-control" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.row').remove()">✖</button>
        </div>
    `;
    container.appendChild(row);
}
</script>
</body>
</html>
