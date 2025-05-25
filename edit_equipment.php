<?php
require 'db_connect.php';

$eid = intval($_GET['id'] ?? 0);

if (!$eid) {
    die("❌ Equipment ID missing.");
}

// Fetch the equipment
$stmt = $pdo->prepare("
    SELECT * 
    FROM equipment
    WHERE eid = ?
");
$stmt->execute([$eid]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die("❌ Equipment not found.");
}

$vessel_id = $equipment['vessel_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Equipment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>✏️ Edit Equipment</h2>

    <form action="update_equipment.php" method="post" class="row g-3">
        <input type="hidden" name="eid" value="<?= $eid ?>">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

        <div class="col-md-6">
            <label class="form-label">Equipment Name:</label>
            <input type="text" name="equipmentName" value="<?= htmlspecialchars($equipment['equipmentName']) ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Location:</label>
            <input type="text" name="equipmentLocation" value="<?= htmlspecialchars($equipment['equipmentLocation']) ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Expiration Date:</label>
            <input type="date" name="expDate" value="<?= htmlspecialchars($equipment['expDate']) ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Quantity:</label>
            <input type="number" name="quantity" value="<?= htmlspecialchars($equipment['quantity']) ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Unit:</label>
            <input type="text" name="unit" value="<?= htmlspecialchars($equipment['unit']) ?>" class="form-control">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#equipment" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
