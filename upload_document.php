<?php
require 'db.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$docType = trim($_POST['docType'] ?? '');
$docName = trim($_POST['docName'] ?? '');
$issueDate = $_POST['issueDate'] ?? null;
$expDate = $_POST['expDate'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$related_to = 'vessel';

$file_path = '';
if (isset($_FILES['docFile']) && $_FILES['docFile']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = basename($_FILES['docFile']['name']);
    $targetPath = $uploadDir . uniqid() . '_' . $filename;

    if (move_uploaded_file($_FILES['docFile']['tmp_name'], $targetPath)) {
        $file_path = $mysqli->real_escape_string($targetPath);
    }
}

$stmt = $mysqli->prepare("
    INSERT INTO documents (vessel_id, docType, docName, issueDate, expDate, file_path, notes, related_to)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("❌ Prepare failed: " . $mysqli->error);
}

$stmt->bind_param("isssssss", $vessel_id, $docType, $docName, $issueDate, $expDate, $file_path, $notes, $related_to);
$stmt->execute();
$stmt->close();

header("Location: vessel_dashboard.php?id=$vessel_id#documents");
exit;
