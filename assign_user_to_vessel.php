<?php require 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign User to Vessel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>👥 Assign User to Vessel</h2>

    <form method="post" action="submit_user_vessel_assignment.php" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Select Vessel:</label>
            <select name="vessel_id" class="form-select" required>
                <option value="">-- Select Vessel --</option>
                <?php
                $vessels = $mysqli->query("SELECT vessel_id, vesselName FROM vessels ORDER BY vesselName");
                while ($v = $vessels->fetch_assoc()) {
                    echo "<option value='{$v['vessel_id']}'>{$v['vesselName']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Select User:</label>
            <select name="user_id" class="form-select" required>
                <option value="">-- Select User --</option>
                <?php
                $users = $mysqli->query("SELECT id, name FROM users ORDER BY name");
                while ($u = $users->fetch_assoc()) {
                    echo "<option value='{$u['id']}'>{$u['name']}</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">➕ Assign</button>
        </div>
    </form>
</div>
</body>
</html>
