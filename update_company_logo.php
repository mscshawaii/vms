<?php
require 'session_check.php';
require 'db_connect.php';

// Only MSCS Hawaii can change any logo
if (!isset($_SESSION['company_id'])) {
    die("Access denied.");
}

$company_id = $_SESSION['company_id'];

// Use the logged-in user's company ID
$uploadDir = 'uploads/logos/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    die("? Logo upload failed.");
}

$ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
$filename = uniqid('logo_') . '.' . $ext;
$target = $uploadDir . $filename;

if (move_uploaded_file($_FILES['logo']['tmp_name'], $target)) {
    $stmt = $pdo->prepare("UPDATE owners SET logo_path = ? WHERE owner_id = ?");
    $stmt->execute([$target, $company_id]);

    header("Location: dashboard.php?logo_updated=1");
    exit;
} else {
    die("? Logo upload failed.");
}
