<?php
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

function clean($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function null_if_empty($value) {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function int_or_null($value) {
    return ($value === '' || $value === null) ? null : (int)$value;
}

function decimal_or_null($value) {
    return ($value === '' || $value === null) ? null : $value;
}

function looks_like_fire_extinguisher($category_id, $equipment_name, $type_name = '', $subtype_name = '') {
    $haystack = strtolower(trim($equipment_name . ' ' . $type_name . ' ' . $subtype_name));
    return ((int)$category_id === 3) && (
        strpos($haystack, 'portable extinguisher') !== false ||
        strpos($haystack, 'fixed extinguisher') !== false ||
        strpos($haystack, 'extinguisher') !== false ||
        strpos($haystack, 'suppression') !== false
    );
}

function normalize_uploads_array(array $files): array {
    $normalized = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $normalized;
    }

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $normalized[] = [
            'name'     => $files['name'][$i] ?? '',
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function ext_lower(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function is_image_ext(string $ext): bool {
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
}

function ensure_upload_dir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

function store_equipment_file(PDO $pdo, int $eid, string $filePath, string $originalName, ?int $uploadedBy = null): void {
    static $schema = null;

    if ($schema === null) {
        $schema = [];
        $stmt = $pdo->query("SHOW COLUMNS FROM equipment_files");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $schema[strtolower($col['Field'])] = $col['Field'];
            }
        }
    }

    if (!$schema) {
        throw new RuntimeException('equipment_files table not found or unreadable.');
    }

    $values = [];
    $params = [];

    $map = [
        'eid'            => $eid,
        'equipment_id'   => $eid,
        'file_path'      => $filePath,
        'filepath'       => $filePath,
        'path'           => $filePath,
        'file_name'      => $originalName,
        'filename'       => $originalName,
        'original_name'  => $originalName,
        'file_type'      => mime_content_type(__DIR__ . '/' . ltrim($filePath, '/')) ?: null,
        'mime_type'      => mime_content_type(__DIR__ . '/' . ltrim($filePath, '/')) ?: null,
        'uploaded_by'    => $uploadedBy,
        'uploaded_by_id' => $uploadedBy,
    ];

    foreach ($map as $logical => $value) {
        if (isset($schema[$logical])) {
            $values[] = $schema[$logical];
            $params[] = $value;
        }
    }

    if (!$values) {
        throw new RuntimeException('equipment_files table columns did not match expected names.');
    }

    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $columns = implode(',', $values);

    $sql = "INSERT INTO equipment_files ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

$eid = intval($_POST['eid'] ?? 0);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

if (!$eid || !$vessel_id) {
    die("❌ Missing equipment ID or vessel ID.");
}

$category_id        = int_or_null($_POST['category_id'] ?? null);
$type_id            = int_or_null($_POST['equipment_type_id'] ?? null);
$subtype_id         = int_or_null($_POST['equipment_subtype_id'] ?? null);

$equipmentLocation  = clean($_POST['equipmentLocation'] ?? '');
$manufacturer       = clean($_POST['manufacturer'] ?? '');
$modelNumber        = clean($_POST['modelNumber'] ?? '');
$serialNumber       = clean($_POST['serialNumber'] ?? '');
$installDate        = null_if_empty($_POST['installDate'] ?? null);
$expDate            = null_if_empty($_POST['expDate'] ?? null);
$quantity           = int_or_null($_POST['quantity'] ?? null);
$unit               = null_if_empty($_POST['unit'] ?? null);
$onBoardNotRequired = ($_POST['onBoardNotRequired'] ?? '') === '1' ? 1 : 0;
$notes              = clean($_POST['notes'] ?? '');
$equipmentIsActive  = (string)($_POST['hour_meter_active'] ?? '1') === '1' ? 1 : 0;

/* Lookup type/subtype names */
$typeStmt = $pdo->prepare("SELECT name FROM equipment_type WHERE id = ?");
$typeStmt->execute([$type_id]);
$typeName = (string)($typeStmt->fetchColumn() ?: '');

$subtypeStmt = $pdo->prepare("SELECT name FROM equipment_subtype WHERE id = ?");
$subtypeStmt->execute([$subtype_id]);
$subtypeName = (string)($subtypeStmt->fetchColumn() ?: '');

/* Auto-generate equipment name */
$parts = [];
if ($typeName !== '') $parts[] = $typeName;
if ($subtypeName !== '') $parts[] = $subtypeName;
if ($equipmentLocation !== '') $parts[] = $equipmentLocation;
$equipmentName = trim(implode(' - ', $parts));

/* Optional multi-file upload */
$storedFiles = [];
$newPrimaryPhoto = null;

if (isset($_FILES['equipment_files'])) {
    $uploads = normalize_uploads_array($_FILES['equipment_files']);

    if ($uploads) {
        $upload_dir = __DIR__ . '/uploads/';
        ensure_upload_dir($upload_dir);

        foreach ($uploads as $file) {
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                die("❌ One or more uploaded files failed.");
            }

            $ext = ext_lower($file['name']);
            $newname = uniqid('eq_', true) . ($ext ? '.' . $ext : '');
            $target_path = $upload_dir . $newname;
            $relative_path = 'uploads/' . $newname;

            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                die("❌ Failed to move uploaded file.");
            }

            $storedFiles[] = [
                'file_path' => $relative_path,
                'name'      => $file['name'],
                'ext'       => $ext,
            ];

            if ($newPrimaryPhoto === null && is_image_ext($ext)) {
                $newPrimaryPhoto = $relative_path;
            }
        }
    }
}

/* Fire detail inputs */
$fire_rule_profile_id       = int_or_null($_POST['fire_rule_profile_id'] ?? null);
$fire_agent_type            = null_if_empty($_POST['fire_agent_type'] ?? null);
$fire_extinguisher_class    = null_if_empty($_POST['fire_extinguisher_class'] ?? null);
$fire_capacity_value        = decimal_or_null($_POST['fire_capacity_value'] ?? null);
$fire_capacity_unit         = null_if_empty($_POST['fire_capacity_unit'] ?? null);
$fire_ul_rating             = null_if_empty($_POST['fire_ul_rating'] ?? null);
$fire_cylinder_material     = null_if_empty($_POST['fire_cylinder_material'] ?? null);
$fire_manufacture_date      = null_if_empty($_POST['fire_manufacture_date'] ?? null);
$fire_last_service_vendor   = null_if_empty($_POST['fire_last_service_vendor'] ?? null);
$fire_service_tag_number    = null_if_empty($_POST['fire_service_tag_number'] ?? null);
$fire_remarks               = null_if_empty($_POST['fire_remarks'] ?? null);

$isFireExt = looks_like_fire_extinguisher($category_id, $equipmentName, $typeName, $subtypeName);
$tracking = vms_hour_tracking_payload_from_request($_POST, (int)$type_id, $subtype_id);

try {
    $pdo->beginTransaction();

    if ($newPrimaryPhoto) {
        $stmt = $pdo->prepare("
            UPDATE equipment
            SET
                category_id = ?,
                type_id = ?,
                subtype_id = ?,
                equipment_type_id = ?,
                equipment_subtype_id = ?,
                equipmentName = ?,
                equipmentLocation = ?,
                expDate = ?,
                quantity = ?,
                unit = ?,
                manufacturer = ?,
                modelNumber = ?,
                serialNumber = ?,
                installDate = ?,
                onBoardNotRequired = ?,
                notes = ?,
                photo_path = ?,
                is_active = ?
            WHERE eid = ?
        ");
        $stmt->execute([
            $category_id,
            $type_id,
            $subtype_id,
            $type_id,
            $subtype_id,
            $equipmentName,
            ($equipmentLocation !== '' ? $equipmentLocation : null),
            $expDate,
            $quantity,
            $unit,
            ($manufacturer !== '' ? $manufacturer : null),
            ($modelNumber !== '' ? $modelNumber : null),
            ($serialNumber !== '' ? $serialNumber : null),
            $installDate,
            $onBoardNotRequired,
            ($notes !== '' ? $notes : null),
            $newPrimaryPhoto,
            $equipmentIsActive,
            $eid
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE equipment
            SET
                category_id = ?,
                type_id = ?,
                subtype_id = ?,
                equipment_type_id = ?,
                equipment_subtype_id = ?,
                equipmentName = ?,
                equipmentLocation = ?,
                expDate = ?,
                quantity = ?,
                unit = ?,
                manufacturer = ?,
                modelNumber = ?,
                serialNumber = ?,
                installDate = ?,
                onBoardNotRequired = ?,
                notes = ?,
                is_active = ?
            WHERE eid = ?
        ");
        $stmt->execute([
            $category_id,
            $type_id,
            $subtype_id,
            $type_id,
            $subtype_id,
            $equipmentName,
            ($equipmentLocation !== '' ? $equipmentLocation : null),
            $expDate,
            $quantity,
            $unit,
            ($manufacturer !== '' ? $manufacturer : null),
            ($modelNumber !== '' ? $modelNumber : null),
            ($serialNumber !== '' ? $serialNumber : null),
            $installDate,
            $onBoardNotRequired,
            ($notes !== '' ? $notes : null),
            $equipmentIsActive,
            $eid
        ]);
    }

    vms_hour_sync_equipment_meter($pdo, $eid, $vessel_id, (string)$equipmentLocation, $tracking);

    foreach ($storedFiles as $f) {
        store_equipment_file($pdo, $eid, $f['file_path'], $f['name'], isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
    }

    if ($isFireExt) {
        $check = $pdo->prepare("SELECT extinguisher_detail_id FROM fire_extinguisher_details WHERE eid = ? LIMIT 1");
        $check->execute([$eid]);
        $existingId = $check->fetchColumn();

        if ($existingId) {
            $fireStmt = $pdo->prepare("
                UPDATE fire_extinguisher_details
                SET
                    rule_profile_id = :rule_profile_id,
                    agent_type = :agent_type,
                    extinguisher_class = :extinguisher_class,
                    ul_rating = :ul_rating,
                    capacity_value = :capacity_value,
                    capacity_unit = :capacity_unit,
                    cylinder_material = :cylinder_material,
                    manufacture_date = :manufacture_date,
                    last_service_vendor = :last_service_vendor,
                    service_tag_number = :service_tag_number,
                    remarks = :remarks,
                    updated_at = NOW()
                WHERE eid = :eid
            ");
        } else {
            $fireStmt = $pdo->prepare("
                INSERT INTO fire_extinguisher_details (
                    eid,
                    rule_profile_id,
                    agent_type,
                    extinguisher_class,
                    ul_rating,
                    capacity_value,
                    capacity_unit,
                    cylinder_material,
                    manufacture_date,
                    last_service_vendor,
                    service_tag_number,
                    remarks
                ) VALUES (
                    :eid,
                    :rule_profile_id,
                    :agent_type,
                    :extinguisher_class,
                    :ul_rating,
                    :capacity_value,
                    :capacity_unit,
                    :cylinder_material,
                    :manufacture_date,
                    :last_service_vendor,
                    :service_tag_number,
                    :remarks
                )
            ");
        }

        $fireStmt->execute([
            ':eid'                 => $eid,
            ':rule_profile_id'     => $fire_rule_profile_id,
            ':agent_type'          => $fire_agent_type,
            ':extinguisher_class'  => $fire_extinguisher_class,
            ':ul_rating'           => $fire_ul_rating,
            ':capacity_value'      => $fire_capacity_value,
            ':capacity_unit'       => $fire_capacity_unit,
            ':cylinder_material'   => $fire_cylinder_material,
            ':manufacture_date'    => $fire_manufacture_date,
            ':last_service_vendor' => $fire_last_service_vendor,
            ':service_tag_number'  => $fire_service_tag_number,
            ':remarks'             => $fire_remarks
        ]);
    }

    $pdo->commit();

    header("Location: equipment_detail.php?id=$eid&success=equipment_updated");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("❌ Failed to update equipment: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
?>
