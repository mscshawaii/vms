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

$stmt = $pdo->prepare("
    SELECT q.asset_id
    FROM qr_links q
    WHERE q.code = ?
      AND q.asset_type = 'equipment'
      AND q.is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$eid = (int)$stmt->fetchColumn();

if ($eid <= 0) {
    http_response_code(404);
    exit('Equipment record not found.');
}

$return_to = 'public_equipment.php?code=' . urlencode($code);

header('Location: edit_equipment.php?id=' . $eid . '&return_to=' . urlencode($return_to));
exit;