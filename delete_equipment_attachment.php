<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

$attachment_id = (int)($_GET['attachment_id'] ?? 0);
if ($attachment_id <= 0) {
    die('Invalid attachment ID.');
}

$stmt = $pdo->prepare("
    SELECT ef.equipment_file_id, ef.eid, ef.file_path, e.photo_path
    FROM equipment_files ef
    INNER JOIN equipment e ON e.eid = ef.eid
    WHERE ef.equipment_file_id = ?
    LIMIT 1
");
$stmt->execute([$attachment_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die('Attachment not found.');
}

$eid = (int)$row['eid'];
$filePath = $row['file_path'] ?? '';
$photoPath = $row['photo_path'] ?? '';

// delete physical file
if (!empty($filePath)) {
    $fsPath = __DIR__ . '/' . ltrim(str_replace('\\', '/', $filePath), '/');
    if (is_file($fsPath)) {
        @unlink($fsPath);
    }
}

try {
    $pdo->beginTransaction();

    // if this attachment is also the current primary photo, clear it
    if (!empty($photoPath) && $photoPath === $filePath) {
        $clear = $pdo->prepare("UPDATE equipment SET photo_path = NULL WHERE eid = ?");
        $clear->execute([$eid]);
    }

    $del = $pdo->prepare("DELETE FROM equipment_files WHERE equipment_file_id = ?");
    $del->execute([$attachment_id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Failed to delete attachment: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

header("Location: equipment_detail.php?id=" . $eid . "&success=attachment_deleted");
exit;
