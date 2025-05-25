<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

// File upload handler
function handleUpload($field, $folder = 'uploads/') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $filename = $folder . uniqid($field . '_') . '.' . $ext;

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    move_uploaded_file($_FILES[$field]['tmp_name'], $filename);
    return $filename;
}

// Sanitize date fields
function sanitizeDate($val) {
    return (!empty($val) && $val !== '') ? $val : null;
}

// Company restriction
$is_mscs = ($_SESSION['company_id'] == 1);
$company_id = $is_mscs ? intval($_POST['company_id']) : $_SESSION['company_id'];

// Inputs
$user_id   = intval($_POST['id']);
$fName     = trim($_POST['fName']);
$lName     = trim($_POST['lName']); // <-- fixed
$phone     = trim($_POST['phoneNumber']);
$email     = trim($_POST['email']);
$username  = trim($_POST['username']);
$role_id   = intval($_POST['role_id']);
$new_password = trim($_POST['new_password'] ?? '');

// Dates
$mmc         = sanitizeDate($_POST['mmc'] ?? null);
$mmc_medical = sanitizeDate($_POST['mmc_medical'] ?? null);
$fa          = sanitizeDate($_POST['fa'] ?? null);
$mrop        = sanitizeDate($_POST['mrop'] ?? null);

// File uploads (null if no new file uploaded)
$mmc_path         = handleUpload('mmc_path');
$mmc_medical_path = handleUpload('mmc_medical_path');
$fa_path          = handleUpload('fa_path');
$mrop_path        = handleUpload('mrop_path'); // <-- fixed

// Get current file paths (in case we don't re-upload)
$current = $pdo->prepare("SELECT mmc_path, mmc_medical_path, fa_path, mrop_path FROM users WHERE id = ?");
$current->execute([$user_id]);
$existing = $current->fetch(PDO::FETCH_ASSOC);

// Final values
$mmc_path         = $mmc_path         ?: $existing['mmc_path'];
$mmc_medical_path = $mmc_medical_path ?: $existing['mmc_medical_path'];
$fa_path          = $fa_path          ?: $existing['fa_path'];
$mrop_path        = $mrop_path        ?: $existing['mrop_path']; // <-- fixed

// Update user
$stmt = $pdo->prepare("
    UPDATE users SET 
        fName = ?, lName = ?, phoneNumber = ?, email = ?, username = ?,
        company_id = ?, role_id = ?,
        mmc = ?, mmc_medical = ?, fa = ?, mrop = ?,
        mmc_path = ?, mmc_medical_path = ?, fa_path = ?, mrop_path = ?
    WHERE id = ?
");

$success = $stmt->execute([
    $fName, $lName, $phone, $email, $username,
    $company_id, $role_id,
    $mmc, $mmc_medical, $fa, $mrop,
    $mmc_path, $mmc_medical_path, $fa_path, $mrop_path,
    $user_id
]);

if ($success && !empty($new_password)) {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $pwd_stmt = $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?");
    $pwd_stmt->execute([$hashed, $user_id]);
}

if ($success) {
    header("Location: dashboard.php?success=user_updated");
    exit;
} else {
    echo "? Failed to update user: " . $stmt->errorInfo()[2];
}