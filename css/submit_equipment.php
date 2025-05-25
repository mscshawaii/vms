<?php
require 'db.php';

// Helper: sanitize input
function clean($input) {
    return htmlspecialchars(trim($input));
}

// Handle file upload
$photo_path = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = basename($_FILES['photo']['name']);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $newname = uniqid('eq_') . '.' . $ext;
    $target_path = $upload_dir . $newname;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
        $photo_path = $target_path;
    }
}

// Prepare fields
$category_id = $_POST['category_id'] ?? null;
$type_id = $_POST['type_id'] ?? null;
$subtype_id = $_POST['subtype_id'] ?? null;
$equipment_name = clean($_POST['equipment_name'] ?? '');
$location = clean($_POST['location'] ?? '');
$manufacturer = clean($_POST['manufacturer'] ?? '');
$model = clean($_POST['model'] ?? '');
$serial_number = clean($_POST['serial_number'] ?? '');
$install_date = $_POST['install_date'] ?? null;
$expiration_date = $_POST['expiration_date'] ?? null;
$quantity = $_POST['quantity'] ?? null;
$unit = clean($_POST['unit'] ?? '');
$notes = clean($_POST['notes'] ?? '');
$onboard_requirement = clean($_POST['onboard_requirement'] ?? '');

// Insert
$stmt = $mysqli->prepare("
    INSERT INTO equipment (
        category_id, type_id, subtype_id, equipment_name, location,
        manufacturer, model, serial_number, install_date, expiration_date,
        quantity, unit, notes, onboard_requirement, photo_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iiisssssssdssss",
    $category_id, $type_id, $subtype_id, $equipment_name, $location,
    $manufacturer, $model, $serial_number, $install_date, $expiration_date,
    $quantity, $unit, $notes, $onboard_requirement, $photo_path
);

if ($stmt->execute()) {
    echo "✅ Equipment successfully added.";
    echo "<br><a href='index.php'>Add Another</a>";
} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
?>