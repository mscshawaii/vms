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
    SELECT 
        q.vessel_id,
        q.asset_id,
        e.equipmentName,
        e.notes,
        e.equipment_type_id,
        e.equipment_subtype_id,
        es.name AS subtype_name
    FROM qr_links q
    INNER JOIN equipment e
        ON e.eid = q.asset_id
    LEFT JOIN equipment_subtype es
        ON es.id = e.equipment_subtype_id
    WHERE q.code = ?
      AND q.asset_type = 'equipment'
      AND q.is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    exit('QR code not found.');
}

$vessel_id     = (int)$item['vessel_id'];
$type_id       = (int)$item['equipment_type_id'];
$subtypeName   = strtolower(trim((string)($item['subtype_name'] ?? '')));
$equipmentName = strtolower(trim((string)($item['equipmentName'] ?? '')));
$notes         = strtolower(trim((string)($item['notes'] ?? '')));

$icr_id = 0;

/*
|--------------------------------------------------------------------------
| Determine applicable ICR
|--------------------------------------------------------------------------
| Portable = C 04 (13)
| Fixed CO2 = C 01 (11)
| Clean Agent / Fireboy = C 02 (12)
*/
if ($type_id === 14) {
    $icr_id = 13;
} elseif ($type_id === 15) {
    $isCleanAgent =
        strpos($subtypeName, 'clean') !== false ||
        strpos($subtypeName, 'fireboy') !== false ||
        strpos($equipmentName, 'clean agent') !== false ||
        strpos($equipmentName, 'fireboy') !== false ||
        strpos($notes, 'clean agent') !== false ||
        strpos($notes, 'fireboy') !== false;

    $icr_id = $isCleanAgent ? 12 : 11;
}

if ($icr_id <= 0) {
    http_response_code(400);
    exit('Unable to determine applicable ICR for this equipment.');
}

/*
|--------------------------------------------------------------------------
| Resolve assigned vessel_icr_id
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_icr_id
    FROM vessel_icrs
    WHERE vessel_id = ?
      AND icr_id = ?
    ORDER BY vessel_icr_id DESC
    LIMIT 1
");
$stmt->execute([$vessel_id, $icr_id]);
$vessel_icr_id = (int)$stmt->fetchColumn();

if ($vessel_icr_id <= 0) {
    http_response_code(404);
    exit('Assigned vessel ICR not found for this vessel and ICR.');
}

/*
|--------------------------------------------------------------------------
| Inspector
|--------------------------------------------------------------------------
*/
$inspector = '';
if (!empty($_SESSION['username'])) {
    $inspector = preg_replace('/\s+/', '', (string)$_SESSION['username']);
} elseif (!empty($_SESSION['fName']) || !empty($_SESSION['lName'])) {
    $inspector = preg_replace('/\s+/', '', trim(($_SESSION['fName'] ?? '') . ' ' . ($_SESSION['lName'] ?? '')));
} else {
    $inspector = 'Inspector';
}

$return_to = 'public_equipment.php?code=' . urlencode($code);

header(
    'Location: run_icr.php?vessel_id=' . $vessel_id .
    '&vessel_icr_id=' . $vessel_icr_id .
    '&icr_id=' . $icr_id .
    '&inspector=' . urlencode($inspector) .
    '&return_to=' . urlencode($return_to)
);
exit;