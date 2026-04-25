<?php
require 'db_connect.php';
require 'session_check.php';

$eid = intval($_GET['eid'] ?? 0);

if ($eid <= 0) {
    die("Missing or invalid equipment ID");
}

/*
|--------------------------------------------------------------------------
| LOAD EQUIPMENT + VESSEL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        e.eid,
        e.vessel_id,
        e.equipmentName,
        e.equipment_type_id,
        v.vesselName
    FROM equipment e
    INNER JOIN vessels v ON v.vessel_id = e.vessel_id
    WHERE e.eid = ?
    LIMIT 1
");
$stmt->execute([$eid]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die("Equipment not found");
}

$vessel_id = (int)$equipment['vessel_id'];

if ($vessel_id <= 0) {
    die("Equipment is not assigned to a vessel");
}

/*
|--------------------------------------------------------------------------
| OPTIONAL TYPE GUARD
|--------------------------------------------------------------------------
| Restrict QR generation to EPIRB-related equipment types only if desired.
| For now, this allows portable (14) and fixed (15).
*/
$allowedTypeIds = [14, 15];

if (!in_array((int)$equipment['equipment_type_id'], $allowedTypeIds, true)) {
    die("QR generation is not enabled for this equipment type");
}

/*
|--------------------------------------------------------------------------
| GENERATE UNIQUE CODE
|--------------------------------------------------------------------------
*/
function generateQrCode(PDO $pdo): string
{
    do {
        $code = strtoupper(bin2hex(random_bytes(8))); // 16-char code
        $check = $pdo->prepare("
            SELECT qr_link_id
            FROM qr_links
            WHERE code = ?
            LIMIT 1
        ");
        $check->execute([$code]);
        $exists = $check->fetchColumn();
    } while ($exists);

    return $code;
}

$newCode = generateQrCode($pdo);
$createdBy = $_SESSION['user_id'] ?? null;

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | DEACTIVATE ANY PRIOR ACTIVE QR FOR THIS EQUIPMENT
    |--------------------------------------------------------------------------
    */
    $deactivate = $pdo->prepare("
        UPDATE qr_links
        SET is_active = 0
        WHERE asset_type = 'equipment'
          AND asset_id = ?
          AND is_active = 1
    ");
    $deactivate->execute([$eid]);

    /*
    |--------------------------------------------------------------------------
    | INSERT NEW QR LINK
    |--------------------------------------------------------------------------
    */
    $insert = $pdo->prepare("
        INSERT INTO qr_links (
            code,
            asset_type,
            asset_id,
            vessel_id,
            is_active,
            created_by
        ) VALUES (
            ?,
            'equipment',
            ?,
            ?,
            1,
            ?
        )
    ");
    $insert->execute([
        $newCode,
        $eid,
        $vessel_id,
        $createdBy
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Failed to generate equipment QR: " . $e->getMessage());
}

header("Location: vessel_qr_center.php?vessel_id=" . $vessel_id . "&success=equipment");
exit;