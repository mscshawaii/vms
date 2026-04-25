<?php
require 'session_check.php';
require 'db_connect.php';

// --- ERROR LOGGING ---
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);


// --- CSRF (matches hidden input in the Vessel Identification form) ---
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(400);
    exit('Invalid request');
}

$vessel_id = intval($_POST['vessel_id'] ?? 0);
if (!$vessel_id) {
    header("Location: vessel_dashboard.php?error=Missing vessel ID.");
    exit;
}

// --- Sanitize & collect input ---
$vesselName   = trim($_POST['vesselName'] ?? '');
$vesselON     = trim($_POST['vesselON'] ?? '');
$callSign     = trim($_POST['callSign'] ?? '');
$mmsi         = trim($_POST['mmsi'] ?? '');
$hailingPort  = trim($_POST['hailingPort'] ?? '');
$epirbHexId   = trim($_POST['epirbHexId'] ?? '');
$hin          = trim($_POST['hin'] ?? '');

// NEW: OCMI contact (nullable)
$ocmi_contact_id = null;
if (isset($_POST['ocmi_contact_id']) && $_POST['ocmi_contact_id'] !== '') {
    $tmp = (int)$_POST['ocmi_contact_id'];
    // validate contact exists and is active
    $chk = $pdo->prepare("SELECT contact_id FROM uscg_contacts WHERE contact_id = ? AND active = 1");
    $chk->execute([$tmp]);
    if ($chk->fetchColumn()) {
        $ocmi_contact_id = $tmp;
    }
}

// --- Photo upload (save to uploads/vessels/<vessel_id>/) ---
$photo_path = null;

if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png'];
    $mime_type = @mime_content_type($_FILES['photo']['tmp_name']);
    if (in_array($mime_type, $allowed_types, true)) {
        $dirFS  = __DIR__ . "/uploads/vessels/$vessel_id";
        if (!is_dir($dirFS)) { @mkdir($dirFS, 0755, true); }

        $original = basename($_FILES['photo']['name']);
        $safe     = preg_replace("/[^a-zA-Z0-9._-]/", "", pathinfo($original, PATHINFO_FILENAME));
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') $ext = 'jpg';

        $newName  = time() . "_" . $safe . "." . $ext;
        $targetFS = "$dirFS/$newName";

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFS)) {
            // Store RELATIVE path (portable)
            $photo_path = "uploads/vessels/$vessel_id/$newName";
        }
    } else {
        header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Invalid file type. Upload a JPG or PNG.");
        exit;
    }
}


// --- Build SQL dynamically to keep your existing behavior ---
$sql = "
    UPDATE vessels
    SET vesselName = ?,
        vesselON = ?,
        callSign = ?,
        mmsi = ?,
        hailingPort = ?,
        epirbHexId = ?,
        hin = ?
";

$params = [
    $vesselName,
    $vesselON,
    $callSign,
    $mmsi,
    $hailingPort,
    $epirbHexId,
    $hin
];

if ($ocmi_contact_id !== null) {
    $sql .= ", ocmi_contact_id = ?";
    $params[] = $ocmi_contact_id;
}

if ($photo_path) {
    $sql .= ", photo_path = ?";
    $params[] = $photo_path;
}

$sql .= " WHERE vessel_id = ?";
$params[] = $vessel_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);


header("Location: vessel_dashboard.php?vessel_id=$vessel_id&success=1");
exit;


