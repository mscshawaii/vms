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
        die("❌ Failed to move uploaded file.");
    }
}

// Collect and sanitize input
$category_id = $_POST['category_id'] ?? null;
$equipment_type_id = $_POST['equipment_type_id'] ?? null;
$equipment_subtype_id = $_POST['equipment_subtype_id'] ?? null;
$vessel_id = $_POST['vessel_id'] ?? null;

$equipmentName = clean($_POST['equipment_name'] ?? '');
$equipmentLocation = clean($_POST['equipmentLocation'] ?? '');
$manufacturer = clean($_POST['manufacturer'] ?? '');
$modelNumber = clean($_POST['modelNumber'] ?? '');
$serialNumber = clean($_POST['serialNumber'] ?? '');

$installDate = !empty($_POST['installDate']) ? $_POST['installDate'] : null;
$expDate = !empty($_POST['expDate']) ? $_POST['expDate'] : null;

$quantity = $_POST['quantity'] ?? null;
$unit = clean($_POST['unit'] ?? '');
$notes = clean($_POST['notes'] ?? '');
$onBoardNotRequired = ($_POST['onBoardNotRequired'] ?? '') === '1' ? 1 : 0;

// Insert into DB
$sql = "
    INSERT INTO equipment (
        category_id, equipment_type_id, equipment_subtype_id, equipmentName, equipmentLocation,
        manufacturer, modelNumber, serialNumber, installDate, expDate,
        quantity, unit, notes, onBoardNotRequired, photo_path, vessel_id
    ) VALUES (
        :category_id, :equipment_type_id, :equipment_subtype_id, :equipmentName, :equipmentLocation,
        :manufacturer, :modelNumber, :serialNumber, :installDate, :expDate,
        :quantity, :unit, :notes, :onBoardNotRequired, :photo_path, :vessel_id
    )
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':category_id' => $category_id,
        ':equipment_type_id' => $equipment_type_id,
        ':equipment_subtype_id' => $equipment_subtype_id,
        ':equipmentName' => $equipmentName,
        ':equipmentLocation' => $equipmentLocation,
        ':manufacturer' => $manufacturer,
        ':modelNumber' => $modelNumber,
        ':serialNumber' => $serialNumber,
        ':installDate' => $installDate,
        ':expDate' => $expDate,
        ':quantity' => $quantity,
        ':unit' => $unit,
        ':notes' => $notes,
        ':onBoardNotRequired' => $onBoardNotRequired,
        ':photo_path' => $photo_path,
        ':vessel_id' => $vessel_id
    ]);

    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#equipment&success=equipment_added");
    exit;

} catch (PDOException $e) {
    die("❌ Failed to add equipment: " . $e->getMessage());
}
?>
