<?php
require 'session_check.php';
require 'db_connect.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Restrict access to MSCS Hawaii only (owner_id = 1)
if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

// Sanitize inputs
$company_name = trim($_POST['company_name'] ?? '');
$contact_name = trim($_POST['contact_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$logo_path = null;

// Handle logo upload
if (!empty($_FILES['logo']['name'])) {
    $uploadDir = 'uploads/logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid() . "_" . basename($_FILES['logo']['name']);
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
        $logo_path = $targetPath;
        echo "? Logo uploaded to: $logo_path<br>";
    } else {
        echo "?? Failed to upload logo.<br>";
    }
}

// Insert into database
$stmt = $pdo->prepare("
    INSERT INTO owners (company_name, contact_name, email, phone, address, logo_path)
    VALUES (?, ?, ?, ?, ?, ?)
");

try {
    $stmt->execute([
        $company_name,
        $contact_name,
        $email,
        $phone,
        $address,
        $logo_path
    ]);

    echo "? Company added successfully.<br>";
    echo "<a href='dashboard.php'>Back to Dashboard</a>";
    exit;

} catch (PDOException $e) {
    echo "? Database error: " . $e->getMessage();
    exit;
}
?>
