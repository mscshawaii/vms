<?php
session_start();
require 'db_connect.php';

$code = trim($_GET['code'] ?? '');

if (!$code) {
    die("Missing code");
}

/*
|--------------------------------------------------------------------------
| Auth check
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user_id'])) {
    $_SESSION['post_login_redirect'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Resolve vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'drills'
      AND is_active = 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    die("QR not found");
}

header("Location: vessel_drill_qr_center.php?vessel_id=$vessel_id&code=" . urlencode($code));
exit;