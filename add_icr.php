<?php require 'db_connect.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add ICR Template</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script>
    function addStepRow() {
        const container = document.getElementById('steps');
        const index = container.children.length + 1;

        const row = document.createElement('div');
        row.className = 'mb-3 border rounded p-3';
        row.innerHTML = `
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
    <h2>➕ Add ICR Template</h2>
    <form method="post" action="submit_icr.php">
        <div class="mb-3">
            <label class="form-label">ICR Number</label>
            <input type="text" name="icr_number" class="form-control" placeholder="e.g. A 01" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Authorization / Reference Text</label>
            <textarea name="reference_text" class="form-control" placeholder="46 CFR 122.320 or other reference..." rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Frequency</label>
            <select name="frequency" class="form-select" required>
                <option value="">-- Select Frequency --</option>
                <option value="Weekly">Weekly</option>
                <option value="Monthly">Monthly</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Annually">Annually</option>
            </select>
        </div>

        <hr>
        <h5>📋 Inspection Steps</h5>
        <div id="steps">
            <!-- Initial Step -->
            <div class="mb-3 border rounded p-3">
                <div class="row g-2 mb-2">
                    <div class="col-md-2">
                        <label class="form-label">Step #</label>
                        <input name="step_number[]" type="number" class="form-control" value="1" required>
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
            </div>
        </div>

        <button type="button" class="btn btn-outline-secondary mb-3" onclick="addStepRow()">➕ Add Another Step</button>
        <br>
        <button type="submit" class="btn btn-primary">💾 Save ICR Template</button>
        <a href="icr_templates.php" class="btn btn-secondary">← Back</a>
    </form>
</div>
</body>
</html>
