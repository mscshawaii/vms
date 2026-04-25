<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

$eid = (int)($_GET['id'] ?? 0);
if ($eid <= 0) {
    die('Invalid equipment ID.');
}

$stmt = $pdo->prepare("SELECT eid, vessel_id, photo_path FROM equipment WHERE eid = ?");
$stmt->execute([$eid]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die('Equipment not found.');
}

$photoPath = $equipment['photo_path'] ?? null;

if (!empty($photoPath)) {
    $fsPath = __DIR__ . '/' . ltrim(str_replace('\\', '/', $photoPath), '/');
    if (is_file($fsPath)) {
        @unlink($fsPath);
    }
}

$upd = $pdo->prepare("UPDATE equipment SET photo_path = NULL WHERE eid = ?");
$upd->execute([$eid]);

header("Location: equipment_detail.php?id=" . $eid . "&success=photo_deleted");
exit;
