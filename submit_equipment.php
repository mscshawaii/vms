<?php
require 'db_connect.php';


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

    $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $newname = uniqid('eq_') . '.' . $ext;
    $target_path = $upload_dir . $newname;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
        $photo_path = $target_path;
    } else {
        die("? Failed to move uploaded file.");
    }
}

// Collect and sanitize input
$category_id = $_POST['category_id'] ?? null;
$type_id = $_POST['type_id'] ?? null;
$subtype_id = $_POST['subtype_id'] ?? null;
$vessel_id = $_POST['vessel_id'] ?? null;

$equipment_name = clean($_POST['equipment_name'] ?? '');
$location = clean($_POST['location'] ?? '');
$manufacturer = clean($_POST['manufacturer'] ?? '');
$model = clean($_POST['model'] ?? '');
$serial_number = clean($_POST['serial_number'] ?? '');

$install_date = !empty($_POST['install_date']) ? $_POST['install_date'] : null;
$expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

$quantity = $_POST['quantity'] ?? null;
$unit = clean($_POST['unit'] ?? '');
$notes = clean($_POST['notes'] ?? '');
$onboard_requirement = ($_POST['onboard_requirement'] ?? '') === 'Yes' ? 1 : 0;

// Insert into DB
$sql = "
    INSERT INTO equipment (
        category_id, type_id, subtype_id, equipmentName, equipmentLocation,
        manufacturer, modelNumber, serialNumber, installDate, expDate,
        quantity, unit, notes, onBoardNotRequired, photo_path, vessel_id
    ) VALUES (
        :category_id, :type_id, :subtype_id, :equipment_name, :location,
        :manufacturer, :model, :serial_number, :install_date, :expiration_date,
        :quantity, :unit, :notes, :onboard_requirement, :photo_path, :vessel_id
    )
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':category_id' => $category_id,
        ':type_id' => $type_id,
        ':subtype_id' => $subtype_id,
        ':equipment_name' => $equipment_name,
        ':location' => $location,
        ':manufacturer' => $manufacturer,
        ':model' => $model,
        ':serial_number' => $serial_number,
        ':install_date' => $install_date,
        ':expiration_date' => $expiration_date,
        ':quantity' => $quantity,
        ':unit' => $unit,
        ':notes' => $notes,
        ':onboard_requirement' => $onboard_requirement,
        ':photo_path' => $photo_path,
        ':vessel_id' => $vessel_id
    ]);

    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#equipment");
    exit;

} catch (PDOException $e) {
    die("? Failed to add equipment: " . $e->getMessage());
}
?>
