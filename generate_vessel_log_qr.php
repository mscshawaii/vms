<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = (int)($_GET['vessel_id'] ?? 0);

if ($vessel_id <= 0) {
    die('Missing vessel_id');
}

/*
|--------------------------------------------------------------------------
| Confirm vessel exists and user can access it
|--------------------------------------------------------------------------
*/
$role_id    = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($role_id === 1) {
    $stmt = $pdo->prepare("
        SELECT vessel_id
        FROM vessels
        WHERE vessel_id = ?
        LIMIT 1
    ");
    $stmt->execute([$vessel_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT vessel_id
        FROM vessels
        WHERE vessel_id = ?
          AND company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$vessel_id, $company_id]);
}

$valid_vessel_id = (int)$stmt->fetchColumn();

if ($valid_vessel_id <= 0) {
    die('Vessel not found or access denied');
}

/*
|--------------------------------------------------------------------------
| Create unique QR code token
|--------------------------------------------------------------------------
*/
function generateUniqueQrCode(PDO $pdo, int $length = 32): string
{
    do {
        $code = bin2hex(random_bytes($length / 2));

        $check = $pdo->prepare("
            SELECT COUNT(*)
            FROM qr_links
            WHERE code = ?
            LIMIT 1
        ");
        $check->execute([$code]);
        $exists = (int)$check->fetchColumn() > 0;

    } while ($exists);

    return $code;
}

$newCode   = generateUniqueQrCode($pdo, 32);
$createdBy = (int)($_SESSION['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Replace existing active vessel log QR for this vessel
|--------------------------------------------------------------------------
*/
try {
    $pdo->beginTransaction();

    $deactivate = $pdo->prepare("
        UPDATE qr_links
        SET is_active = 0
        WHERE vessel_id = ?
          AND asset_type = 'vessel_log'
          AND is_active = 1
    ");
    $deactivate->execute([$vessel_id]);

    $insert = $pdo->prepare("
        INSERT INTO qr_links (
            code,
            asset_type,
            asset_id,
            vessel_id,
            is_active,
            created_by,
            created_at
        ) VALUES (?, 'vessel_log', NULL, ?, 1, ?, NOW())
    ");
    $insert->execute([$newCode, $vessel_id, $createdBy]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Failed to generate Vessel Log QR: ' . $e->getMessage());
}

header('Location: vessel_qr_center.php?vessel_id=' . $vessel_id . '&success=vessel_log');
exit;