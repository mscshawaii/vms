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
| Resolve vessel from equipment QR
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'equipment'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

/*
|--------------------------------------------------------------------------
| Resolve latest active Fire Equipment Servicing document
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, docName, docType, category
    FROM documents
    WHERE vessel_id = ?
      AND archived_at IS NULL
      AND (
            docName LIKE '%Fire Equipment Servicing%'
         OR docType LIKE '%Fire Equipment Servicing%'
         OR category LIKE '%Fire Equipment Servicing%'
      )
    ORDER BY uploaded_on DESC, id DESC
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    exit('Fire Equipment Servicing document not found.');
}

/*
|--------------------------------------------------------------------------
| Redirect to actual document viewer
|--------------------------------------------------------------------------
| If your live document page uses a different route, replace here.
*/
$return_to = 'public_equipment.php?code=' . urlencode($code);

header('Location: view_document.php?id=' . (int)$doc['id'] . '&return_to=' . urlencode($return_to));
exit;