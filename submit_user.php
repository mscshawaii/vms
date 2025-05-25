<?php
require 'session_check.php';
require 'db_connect.php';


// Only allow admin users
if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

// Upload handler
function handleUpload($field, $folder = 'uploads/') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $filename = $folder . uniqid($field . '_') . '.' . $ext;

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    return move_uploaded_file($_FILES[$field]['tmp_name'], $filename) ? $filename : null;
}

// Convert empty date inputs to null
function sanitizeDate($value) {
    return (!empty($value) && $value !== '') ? $value : null;
}

// Company logic
$is_mscs = ($_SESSION['company_id'] == 1);
$company_id = $is_mscs
    ? intval($_POST['company_id'])
    : $_SESSION['company_id'];

// Force override POST value to prevent tampering
$_POST['company_id'] = $company_id;

// Sanitize form inputs
$fName    = trim($_POST['fName']);
$lName    = trim($_POST['lName']);
$phone    = trim($_POST['phoneNumber']);
$email    = trim($_POST['email']);
$username = trim($_POST['username']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role_id  = intval($_POST['role_id']);

// Credential Dates (null if empty)
$mmc_date         = sanitizeDate($_POST['mmc'] ?? null);
$fa_date          = sanitizeDate($_POST['fa'] ?? null);
$mrop_date        = sanitizeDate($_POST['mrop'] ?? null);
$mmc_medical_date = sanitizeDate($_POST['mmc_medical'] ?? null);

// File Uploads (null if not present)
$mmc_path         = handleUpload('mmc_path');
$fa_path          = handleUpload('fa_path');
$mrop_path        = handleUpload('mrop_path');
$mmc_medical_path = handleUpload('mmc_medical_path');

// Insert user into database
$stmt = $pdo->prepare("
    INSERT INTO users (
        fName, lName, phoneNumber, email, username, pword, role_id, company_id,
        mmc, fa, mrop, mmc_medical,
        mmc_path, fa_path, mrop_path, mmc_medical_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$success = $stmt->execute([
    $fName, $lName, $phone, $email, $username, $password,
    $role_id, $company_id,
    $mmc_date, $fa_date, $mrop_date, $mmc_medical_date,
    $mmc_path, $fa_path, $mrop_path, $mmc_medical_path
]);

if ($success) {
    header("Location: dashboard.php?success=user_created");
    exit;
} else {
    echo "❌ Failed to create user: " . $stmt->errorInfo()[2];
}
