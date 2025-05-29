<?php
require 'db_connect.php';

$eid = intval($_GET['id'] ?? 0);

if (!$eid) {
    die("❌ Equipment ID missing.");
}

// Fetch the equipment
$stmt = $pdo->prepare("SELECT * FROM equipment WHERE eid = ?");
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

    <form action="update_equipment.php" method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="eid" value="<?= $eid ?>">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

        <div class="col-md-6">
            <label class="form-label">Equipment Name</label>
            <input type="text" name="equipmentName" value="<?= htmlspecialchars($equipment['equipmentName'] ?? '') ?>" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Location</label>
            <input type="text" name="equipmentLocation" value="<?= htmlspecialchars($equipment['equipmentLocation'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Manufacturer</label>
            <input type="text" name="manufacturer" value="<?= htmlspecialchars($equipment['manufacturer'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Model</label>
            <input type="text" name="modelNumber" value="<?= htmlspecialchars($equipment['modelNumber'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Serial Number</label>
            <input type="text" name="serialNumber" value="<?= htmlspecialchars($equipment['serialNumber'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Install Date</label>
            <input type="date" name="installDate" value="<?= htmlspecialchars($equipment['installDate'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Expiration Date</label>
            <input type="date" name="expDate" value="<?= htmlspecialchars($equipment['expDate'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-3">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" value="<?= htmlspecialchars($equipment['quantity'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" value="<?= htmlspecialchars($equipment['unit'] ?? '') ?>" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Onboard Requirement</label>
            <select name="onBoardNotRequired" class="form-select">
                <option value="">-- Select --</option>
                <option value="0" <?= ($equipment['onBoardNotRequired'] ?? '') == 0 ? 'selected' : '' ?>>Yes</option>
                <option value="1" <?= ($equipment['onBoardNotRequired'] ?? '') == 1 ? 'selected' : '' ?>>No</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($equipment['notes'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Photo (Optional)</label>
            <input type="file" name="photo" class="form-control">
            <?php if (!empty($equipment['photo_path'])): ?>
                <div class="mt-2">
                    <img src="<?= htmlspecialchars($equipment['photo_path']) ?>" alt="Current Photo" style="max-height: 150px;">
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#equipment" class="btn btn-secondary">← Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
