<?php
require 'session_check.php';
require 'db_connect.php';

$id = intval($_POST['id'] ?? 0);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

$docType = trim($_POST['docType'] ?? '');
$docName = trim($_POST['docName'] ?? '');
$issueDate = $_POST['issueDate'] ?? null;
$expDate = $_POST['expDate'] ?? null;
$notes = trim($_POST['notes'] ?? '');

// Initialize for file update
$file_path = null;

// Handle file upload if present
if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploads_dir = 'uploads';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    $tmp_name = $_FILES['file']['tmp_name'];
    $basename = basename($_FILES['file']['name']);
    $safe_basename = preg_replace("/[^a-zA-Z0-9._-]/", "", $basename);
    $file_path = "$uploads_dir/" . time() . "_" . $safe_basename;

    if (!move_uploaded_file($tmp_name, $file_path)) {
        header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Failed to upload file.");
        exit;
    }
}

// Build SQL dynamically based on if file is uploaded
$sql = "
    UPDATE documents 
    SET docType = ?, docName = ?, issueDate = ?, expDate = ?, notes = ?
    " . ($file_path ? ", file_path = ?" : "") . "
    WHERE id = ?
";

$params = [$docType, $docName, $issueDate, $expDate, $notes];
if ($file_path) {
    $params[] = $file_path;
}
$params[] = $id;

// Execute update
$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id#documents&success=document_updated");
    exit;
} else {
    header("Location: vessel_dashboard.php?vessel_id=$vessel_id&error=Failed to update document.");
    exit;
}
?>
