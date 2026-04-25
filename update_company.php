<?php
require 'session_check.php';
require 'db_connect.php';

if (($_SESSION['company_id'] ?? 0) != 1) {
    echo "Access denied.";
    exit;
}

$owner_id = intval($_POST['owner_id'] ?? 0);
if ($owner_id <= 0) { die("Invalid company id."); }

// Collect fields
$company_name = trim($_POST['company_name'] ?? '');
$contact_name = trim($_POST['contact_name'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone        = trim($_POST['phone'] ?? '');
$address      = trim($_POST['address'] ?? '');
$primary_uid  = isset($_POST['primary_contact_user_id']) && $_POST['primary_contact_user_id'] !== '' ? intval($_POST['primary_contact_user_id']) : null;
$alt_uid      = isset($_POST['alt_contact_user_id']) && $_POST['alt_contact_user_id'] !== '' ? intval($_POST['alt_contact_user_id']) : null;
$clear_logo   = isset($_POST['clear_logo']) ? true : false;

if ($company_name === '') {
    die("Company name is required.");
}

// Load current company to get existing logo_path
$stmt = $pdo->prepare("SELECT logo_path FROM owners WHERE owner_id = ?");
$stmt->execute([$owner_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current) { die("Company not found."); }
$logo_path = $current['logo_path'] ?? null;

// Handle logo upload or clear
if ($clear_logo) {
    $logo_path = null;
} else if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['logo_file']['tmp_name'];
    $name = $_FILES['logo_file']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // very simple extension allowlist; tighten if desired
    $allowed = ['png','jpg','jpeg','gif','webp','svg'];
    if (!in_array($ext, $allowed, true)) {
        die("Unsupported logo file type.");
    }

    // Ensure upload dir exists (relative to app root)
    $uploadDir = __DIR__ . '/uploads/logos';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    // Unique filename
    $newName = uniqid('logo_', true) . '.' . $ext;
    $destFs  = $uploadDir . '/' . $newName;
    $destWeb = '/uploads/logos/' . $newName;

    if (!move_uploaded_file($tmp, $destFs)) {
        die("Failed to save uploaded logo.");
    }

    $logo_path = $destWeb;
}

// Build nullable array helper
$vals = [
    $company_name,
    ($contact_name !== '' ? $contact_name : null),
    ($email !== '' ? $email : null),
    ($phone !== '' ? $phone : null),
    ($address !== '' ? $address : null),
    $primary_uid,
    $alt_uid,
    ($logo_path !== '' ? $logo_path : null),
    $owner_id
];

$sql = "
    UPDATE owners
    SET company_name = ?,
        contact_name = ?,
        email        = ?,
        phone        = ?,
        address      = ?,
        primary_contact_user_id = ?,
        alt_contact_user_id     = ?,
        logo_path    = ?
    WHERE owner_id = ?
";

$ok = $pdo->prepare($sql)->execute($vals);
if (!$ok) {
    die("Update failed.");
}

header("Location: view_companies.php?updated=1");
exit;
