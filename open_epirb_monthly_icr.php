<?php
require __DIR__ . '/db_connect.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    $_SESSION['post_login_redirect'] = $_SERVER['REQUEST_URI'];
}
require __DIR__ . '/session_check.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/* Resolve QR */
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'epirb'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

/* Get vessel-specific EPIRB monthly assignment */
$stmt = $pdo->prepare("
    SELECT vessel_icr_id, vessel_id, icr_id
    FROM vessel_icrs
    WHERE vessel_id = ?
      AND icr_id = 19
      AND is_removed = 0
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('No EPIRB monthly ICR is assigned to this vessel.');
}

$vessel_icr_id = (int)$row['vessel_icr_id'];
$icr_id        = (int)$row['icr_id'];

header("Location: run_icr.php?vessel_icr_id={$vessel_icr_id}&vessel_id={$vessel_id}&icr_id={$icr_id}");
exit;
