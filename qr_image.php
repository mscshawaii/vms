<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/session_check.php';

$appConfig = require __DIR__ . '/config_app.php';
$qrBaseUrl = $appConfig['qr_base_url'];

/*
 * IMPORTANT:
 * This file returns raw PNG data only.
 * Do not allow warnings/notices to be printed to the browser,
 * or the QR image will break.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing code');
}

$stmt = $pdo->prepare("
    SELECT asset_type
    FROM qr_links
    WHERE code = ?
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$assetType = $stmt->fetchColumn();

if (!$assetType) {
    http_response_code(404);
    exit('QR code not found');
}

$qrLib = __DIR__ . '/lib/phpqrcode/qrlib.php';
if (!file_exists($qrLib)) {
    http_response_code(500);
    exit('QR library not found: ' . $qrLib);
}

/*
 * Make sure cache folder exists.
 */
$cacheDir = __DIR__ . '/lib/phpqrcode/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

require_once $qrLib;

if ($assetType === 'epirb') {
    $qrUrl = $qrBaseUrl . '/public_epirb.php?code=' . urlencode($code);
} elseif ($assetType === 'equipment') {
    $qrUrl = $qrBaseUrl . '/public_equipment.php?code=' . urlencode($code);
} elseif ($assetType === 'drills') {
    $qrUrl = $qrBaseUrl . '/public_drills.php?code=' . urlencode($code);
} elseif ($assetType === 'vessel_log') {
    $qrUrl = $qrBaseUrl . '/public_vessel_log.php?code=' . urlencode($code);
} else {
    http_response_code(400);
    exit('Unsupported QR asset type');
}

/*
 * Clear any accidental buffered output before sending PNG headers.
 */
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

QRcode::png($qrUrl, false, QR_ECLEVEL_M, 8, 2);
exit;
