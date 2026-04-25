<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_GET['vessel_id'] ?? 0);

if ($vessel_id <= 0) {
    die("Missing vessel_id");
}

/* Confirm vessel exists */
$check = $pdo->prepare("
    SELECT vessel_id
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$check->execute([$vessel_id]);

if (!$check->fetchColumn()) {
    die("Vessel not found");
}

/* Look for existing active EPIRB QR */
$stmt = $pdo->prepare("
    SELECT qr_link_id, code
    FROM qr_links
    WHERE vessel_id = ?
      AND asset_type = 'epirb'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $code = bin2hex(random_bytes(6));

    $insert = $pdo->prepare("
        INSERT INTO qr_links (code, asset_type, vessel_id, created_by)
        VALUES (?, 'epirb', ?, ?)
    ");
    $insert->execute([
        $code,
        $vessel_id,
        $_SESSION['user_id'] ?? null
    ]);
}

header("Location: vessel_qr_center.php?vessel_id=" . $vessel_id . "&success=epirb_qr");
exit;