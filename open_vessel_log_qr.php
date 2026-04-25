<?php
session_start();
require 'db_connect.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/*
|--------------------------------------------------------------------------
| Auth check with redirect preservation
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user_id']) && empty($_SESSION['id'])) {
    $_SESSION['post_login_redirect'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
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
      AND asset_type = 'vessel_log'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

header('Location: vessel_log_create.php?vessel_id=' . $vessel_id);
exit;