<?php
require 'session_check.php';
require 'db_connect.php';

$attachment_id = (int)($_GET['attachment_id'] ?? 0);
if ($attachment_id <= 0) {
    die('Invalid attachment ID.');
}

$stmt = $pdo->prepare("
    SELECT equipment_file_id, eid, file_path
    FROM equipment_files
    WHERE equipment_file_id = ?
    LIMIT 1
");
$stmt->execute([$attachment_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die('Attachment not found.');
}

$eid = (int)$file['eid'];
$filePath = $file['file_path'];

if (empty($filePath)) {
    die('Invalid file path.');
}

// set as primary photo
$upd = $pdo->prepare("UPDATE equipment SET photo_path = ? WHERE eid = ?");
$upd->execute([$filePath, $eid]);

header("Location: equipment_detail.php?id=" . $eid . "&success=primary_set");
exit;