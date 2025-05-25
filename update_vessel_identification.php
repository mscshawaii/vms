<?php
require 'session_check.php';
require 'db_connect.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);

// Update fields
$vesselON = trim($_POST['vesselON'] ?? '');
$callSign = trim($_POST['callSign'] ?? '');
$mmsi = trim($_POST['mmsi'] ?? '');
$hailingPort = trim($_POST['hailingPort'] ?? '');
$epirbHexId = trim($_POST['epirbHexId'] ?? '');
$hin = trim($_POST['hin'] ?? '');

$photo_path = null;

// Handle photo upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array(mime_content_type($_FILES['photo']['tmp_name']), $allowed_types)) {
        $uploads_dir = 'uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }

        $tmp_name = $_FILES['photo']['tmp_name'];
        $name = basename($_FILES['photo']['name']);
        $safe_name = preg_replace("/[^a-zA-Z0-9._-]/", "", pathinfo($name, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $new_filename = time() . "_" . $safe_name . "." . $extension;
        $target = "$uploads_dir/$new_filename";

        if (move_uploaded_file($tmp_name, $target)) {
            $photo_path = $target;
        }
    } else {
        header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Invalid file type. Please upload an image.");
        exit;
    }
}

// Build dynamic SQL
$sql = "
    UPDATE vessels 
    SET vesselON = ?, callSign = ?, mmsi = ?, hailingPort = ?, epirbHexId = ?, hin = ?
    " . ($photo_path ? ", photo_path = ?" : "") . "
    WHERE vessel_id = ?
";

$params = [$vesselON, $callSign, $mmsi, $hailingPort, $epirbHexId, $hin];
if ($photo_path) {
    $params[] = $photo_path;
}
$params[] = $vessel_id;

$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id&success=1");
    exit;
} else {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Failed to update vessel info.");
    exit;
}
?>
