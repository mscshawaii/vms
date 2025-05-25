<?php
require 'db_connect.php';
$icr_id = intval($_GET['id'] ?? 0);

// Fetch ICR record
$stmt = $pdo->prepare("SELECT * FROM icrs WHERE icr_id = ?");
$stmt->execute([$icr_id]);
$icr = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch ICR steps
$step_stmt = $pdo->prepare("SELECT * FROM icr_steps WHERE icr_id = ? ORDER BY step_number");
$step_stmt->execute([$icr_id]);
$steps = $step_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit ICR Template</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
    function addStepRow() {
        const container = document.getElementById('steps');
        const index = container.children.length + 1;

        const row = document.createElement('div');
        row.className = 'mb-3 border rounded p-3';
        row.innerHTML = `
            <input type="hidden" name="step_id[]" value="new">
            <div class="row g-2 mb-2">
                <div class="col-md-2">
                    <label class="form-label">Step #</label>
                    <input name="step_number[]" type="number" class="form-control" value="${index}" required>
                </div>
                <div class="col-md-10">
                    <label class="form-label">Step Description</label>
                    <textarea name="step_description[]" class="form-control" required></textarea>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Deficiency Action (optional)</label>
                <textarea name="deficiency_action[]" class="form-control"></textarea>
            </div>
        `;
        container.appendChild(row);
    }
    </script>
</head>
<body class="p-4">
<div class="container">
    <h2>✏️ Edit ICR Template</h2>
    <form method="post" action="update_icr.php">
        <input type="hidden" name="icr_id" value="<?= $icr['icr_id'] ?>">

        <div class="mb-3">
            <label class="form-label">ICR Number</label>
            <input type="text" name="icr_number" class="form-control" value="<?= htmlspecialchars($icr['icr_number'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($icr['title'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Reference Text</label>
            <textarea name="reference_text" class="form-control"><?= htmlspecialchars($icr['reference_text'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Frequency</label>
            <select name="frequency" class="form-select" required>
                <?php foreach (['Weekly', 'Monthly', 'Quarterly', 'Annually'] as $freq): ?>
                    <option value="<?= $freq ?>" <?= $icr['frequency'] === $freq ? 'selected' : '' ?>><?= $freq ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr>
        <h5>📋 Steps</h5>
        <div id="steps">
            <?php foreach ($steps as $s): ?>
                <div class="mb-3 border rounded p-3">
                    <input type="hidden" name="step_id[]" value="<?= $s['step_id'] ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-md-2">
                            <label class="form-label">Step #</label>
                            <input type="number" name="step_number[]" class="form-control" value="<?= htmlspecialchars($s['step_number']) ?>" required>
                        </div>
                        <div class="col-md-10">
                            <label class="form-label">Step Description</label>
                            <textarea name="step_description[]" class="form-control" required><?= htmlspecialchars($s['step_description']) ?></textarea>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Deficiency Action</label>
                        <textarea name="deficiency_action[]" class="form-control"><?= htmlspecialchars($s['deficiency_action'] ?? '') ?></textarea>
                    </div>
                    <div class="text-end">
                        <a href="delete_icr_step.php?id=<?= $s['step_id'] ?>&icr_id=<?= $icr_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this step?')">✖ Delete Step</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-outline-secondary mb-3" onclick="addStepRow()">➕ Add Step</button>
        <br>
        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
        <a href="icr_templates.php" class="btn btn-secondary">← Back</a>
    </form>
</div>
</body>
</html>
