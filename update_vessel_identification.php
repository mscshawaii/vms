<?php
require 'session_check.php';
require 'db_connect.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
if (!$vessel_id) {
    header("Location: vessel_dashboard.php?error=Missing vessel ID.");
    exit;
}

// Sanitize & collect input
$vesselName   = trim($_POST['vesselName'] ?? '');
$vesselON     = trim($_POST['vesselON'] ?? '');
$callSign     = trim($_POST['callSign'] ?? '');
$mmsi         = trim($_POST['mmsi'] ?? '');
$hailingPort  = trim($_POST['hailingPort'] ?? '');
$epirbHexId   = trim($_POST['epirbHexId'] ?? '');
$hin          = trim($_POST['hin'] ?? '');

$photo_path = null;

// Handle photo upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $mime_type = mime_content_type($_FILES['photo']['tmp_name']);

    if (in_array($mime_type, $allowed_types)) {
        $uploads_dir = 'uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }

        $original_name = basename($_FILES['photo']['name']);
        $safe_name     = preg_replace("/[^a-zA-Z0-9._-]/", "", pathinfo($original_name, PATHINFO_FILENAME));
        $extension     = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $new_filename  = time() . "_" . $safe_name . "." . $extension;
        $target        = "$uploads_dir/$new_filename";

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
            $photo_path = $target;
        }
    } else {
        header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Invalid file type. Please upload an image.");
        exit;
    }
}

// Build SQL and parameter list
$sql = "
    UPDATE vessels 
    SET vesselName = ?, vesselON = ?, callSign = ?, mmsi = ?, hailingPort = ?, epirbHexId = ?, hin = ?
    " . ($photo_path ? ", photo_path = ?" : "") . "
    WHERE vessel_id = ?
";

$params = [$vesselName, $vesselON, $callSign, $mmsi, $hailingPort, $epirbHexId, $hin];
if ($photo_path) {
    $params[] = $photo_path;
}
$params[] = $vessel_id;

// Execute update
$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id&success=1");
    exit;
} else {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Failed to update vessel info.");
    exit;
}
?>
