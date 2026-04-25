<?php
require 'db_connect.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }

$stmt = $pdo->prepare("SELECT mime_type, blob_data, filename FROM media_attachments WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit; }

$mime = $row['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($row['blob_data']));
header('Content-Disposition: inline; filename="' . ($row['filename'] ?? ('file_'.$id)) . '"');
echo $row['blob_data'];
