<?php
require 'db.php';

$vessels = $mysqli->query("SELECT vid, vesselName FROM vessels ORDER BY vesselName");
$templates = $mysqli->query("SELECT icr_id, code, title FROM icrs ORDER BY code");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply ICR to Vessel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📋 Apply ICR to Vessel</h2>
    <form method="get" action="run_icr.php" class="row g-3">
        <div class="col-md-6">
            <label>Select Vessel:</label>
            <select name="vessel_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php while ($v = $vessels->fetch_assoc()): ?>
                    <option value="<?= $v['vid'] ?>"><?= $v['vesselName'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label>Select ICR Template:</label>
            <select name="icr_id" class="form-select" required>
                <option value="">-- Select --</option>
                <?php while ($t = $templates->fetch_assoc()): ?>
                    <option value="<?= $t['icr_id'] ?>"><?= $t['code'] ?> - <?= $t['title'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label>Inspector:</label>
            <input type="text" name="inspector" class="form-control" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">🧾 Start ICR Inspection</button>
        </div>
    </form>
</div>
</body>
</html>
