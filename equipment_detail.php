<?php
require 'db_connect.php';

$equipment_id = intval($_GET['id'] ?? 0);

// Fetch equipment
$stmt = $pdo->prepare("
    SELECT e.*, 
           cat.name AS category_name, 
           typ.name AS type_name, 
           sub.name AS subtype_name,
           v.vesselName
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type typ ON e.type_id = typ.id
    LEFT JOIN equipment_subtype sub ON e.subtype_id = sub.id
    LEFT JOIN vessels v ON e.vessel_id = v.vessel_id
    WHERE e.eid = ?
");
$stmt->execute([$equipment_id]);
$equipment = $stmt->fetch();

if (!$equipment) {
    die("❌ Equipment not found.");
}

// Helper to display values safely
function safe($value) {
    return isset($value) && $value !== '' ? htmlspecialchars($value) : '—';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Equipment Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>🔍 Equipment Details</h2>

    <table class="table table-bordered">
        <tr><th>Vessel</th><td><?= safe($equipment['vesselName']) ?></td></tr>
        <tr><th>Category</th><td><?= safe($equipment['category_name']) ?></td></tr>
        <tr><th>Type</th><td><?= safe($equipment['type_name']) ?></td></tr>
        <tr><th>Subtype</th><td><?= safe($equipment['subtype_name']) ?></td></tr>
        <tr><th>Name</th><td><?= safe($equipment['equipmentName']) ?></td></tr>
        <tr><th>Location</th><td><?= safe($equipment['equipmentLocation']) ?></td></tr>
        <tr><th>Manufacturer</th><td><?= safe($equipment['manufacturer']) ?></td></tr>
        <tr><th>Model</th><td><?= safe($equipment['modelNumber']) ?></td></tr>
        <tr><th>Serial Number</th><td><?= safe($equipment['serialNumber']) ?></td></tr>
        <tr><th>Install Date</th><td><?= safe($equipment['installDate']) ?></td></tr>
        <tr><th>Expiration Date</th><td><?= safe($equipment['expDate']) ?></td></tr>
        <tr><th>Quantity</th><td><?= safe($equipment['quantity']) ?></td></tr>
        <tr><th>Unit</th><td><?= safe($equipment['unit']) ?></td></tr>
        <tr><th>Onboard Required</th><td><?= $equipment['onBoardNotRequired'] == 0 ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br(safe($equipment['notes'])) ?></td></tr>
        <tr>
            <th>Photo</th>
            <td>
                <?php if (!empty($equipment['photo_path'])): ?>
                    <img src="<?= $equipment['photo_path'] ?>" alt="Equipment Photo" style="max-width: 300px;" class="img-thumbnail">
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <a href="vessel_dashboard.php?vessel_id=<?= $equipment['vessel_id'] ?>#equipment" class="btn btn-secondary">← Back to Vessel Dashboard</a>
</div>
</body>
</html>
