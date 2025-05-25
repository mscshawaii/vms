<?php
require 'db_connect.php';

// Helper to clean values
function clean($val) {
    return isset($val) && trim($val) !== '' ? htmlspecialchars(trim($val)) : null;
}

// Handle file upload
if (!isset($_FILES['docFile']) || $_FILES['docFile']['error'] !== UPLOAD_ERR_OK) {
    die("❌ File upload failed.");
}

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$ext = pathinfo($_FILES['docFile']['name'], PATHINFO_EXTENSION);
$newname = uniqid('doc_') . '.' . $ext;
$target_path = $upload_dir . $newname;

if (!move_uploaded_file($_FILES['docFile']['tmp_name'], $target_path)) {
    die("❌ Failed to move uploaded file.");
}

// Extract & sanitize form data
$docType = clean($_POST['docType'] ?? '');
$docName = clean($_POST['docName'] ?? '');
$related_to = clean($_POST['related_to'] ?? '');
$category = clean($_POST['category'] ?? null);
$issueDate = !empty($_POST['issueDate']) ? $_POST['issueDate'] : null;
$expDate = !empty($_POST['expDate']) ? $_POST['expDate'] : null;
$notes = clean($_POST['notes'] ?? null);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

// Use docType as docName if not "Other"
if (strtolower($docType) !== 'other') {
    $docName = $docType;
}

// Insert into DB
$stmt = $pdo->prepare("INSERT INTO documents (
    docName, docType, category, related_to, issueDate, expDate, file_path, notes, vessel_id
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $docName, $docType, $category, $related_to,
    $issueDate, $expDate, $target_path, $notes, $vessel_id
]);

// Redirect to vessel dashboard
header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#documents");
exit;
?>
