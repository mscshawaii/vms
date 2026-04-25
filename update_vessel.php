<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';
session_start();

function fail(string $message, int $status = 400): void {
    http_response_code($status);
    die($message);
}

function cleanString($val, bool $allowNull = true): ?string {
    if (!isset($val)) {
        return $allowNull ? null : '';
    }
    $val = trim((string)$val);
    if ($val === '') {
        return $allowNull ? null : '';
    }
    return $val;
}

function asIntOrNull($val): ?int {
    return (isset($val) && $val !== '' && is_numeric($val)) ? (int)$val : null;
}

function asIntOrDefault($val, int $default = 0): int {
    return (isset($val) && $val !== '' && is_numeric($val)) ? (int)$val : $default;
}

function asFloatOrNull($val): ?float {
    return (isset($val) && $val !== '' && is_numeric($val)) ? (float)$val : null;
}

function asFloatOrDefault($val, float $default = 0.0): float {
    return (isset($val) && $val !== '' && is_numeric($val)) ? (float)$val : $default;
}

function dateOrNull($val): ?string {
    $val = trim((string)($val ?? ''));
    return ($val !== '') ? $val : null;
}

$vessel_id = (int)($_POST['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    fail('Invalid vessel ID.');
}

if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    fail('Invalid CSRF token.', 403);
}

$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$session_role_id    = (int)($_SESSION['role_id'] ?? 0);
$is_mscs_admin      = ($session_company_id === 1 && $session_role_id === 1);

$stmt = $pdo->prepare('SELECT * FROM vessels WHERE vessel_id = ?');
$stmt->execute([$vessel_id]);
$current_vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_vessel) {
    fail('Vessel not found.', 404);
}

$current_company_id = (int)$current_vessel['company_id'];
if (!$is_mscs_admin && $current_company_id !== $session_company_id) {
    fail('Access denied.', 403);
}

$company_id = $is_mscs_admin ? (asIntOrNull($_POST['company_id'] ?? null) ?? $current_company_id) : $current_company_id;

$master           = asIntOrDefault($_POST['master'] ?? null, (int)$current_vessel['master']);
$deckhands        = asIntOrDefault($_POST['deckhands'] ?? null, (int)($current_vessel['deckhands'] ?? 0));
$othersInCrew     = asIntOrDefault($_POST['othersInCrew'] ?? null, (int)($current_vessel['othersInCrew'] ?? 0));
$personInAddition = asIntOrDefault($_POST['personInAddition'] ?? null, (int)($current_vessel['personInAddition'] ?? 0));
$passengers       = asIntOrDefault($_POST['passengers'] ?? null, (int)($current_vessel['passengers'] ?? 0));
$pob              = $master + $deckhands + $othersInCrew + $personInAddition + $passengers;

$photo_path = $current_vessel['photo_path'];
if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ((int)$_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        fail('Photo upload failed with error code: ' . (int)$_FILES['photo']['error']);
    }

    $tmpPath = $_FILES['photo']['tmp_name'] ?? '';
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        fail('Uploaded file is not a valid image.');
    }

    $mime = $imageInfo['mime'] ?? '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        fail('Unsupported image type. Please upload JPG, PNG, GIF, or WEBP.');
    }

    $uploadRoot = __DIR__ . '/uploads/vessels';
    $uploadDir = $uploadRoot . '/' . $vessel_id;
    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
        fail('Unable to create uploads/vessels directory.');
    }
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        fail('Unable to create vessel-specific photo upload directory.');
    }
    if (!is_writable($uploadDir)) {
        fail('Vessel photo upload directory is not writable.');
    }

    $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($_FILES['photo']['name'] ?? 'image.' . $allowed[$mime]));
    if ($filename === '' || $filename === '_' ) {
        $filename = 'vessel_' . $vessel_id . '_' . date('Ymd_His') . '.' . $allowed[$mime];
    }
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        fail('Failed to move uploaded vessel photo.');
    }

    $photo_path = 'uploads/vessels/' . $vessel_id . '/' . $filename;
}

$params = [
    ':vessel_id'              => $vessel_id,
    ':company_id'             => $company_id,
    ':vesselName'             => cleanString($_POST['vesselName'] ?? null, false) ?: (string)$current_vessel['vesselName'],
    ':vesselON'               => cleanString($_POST['vesselON'] ?? null, false) ?: (string)$current_vessel['vesselON'],
    ':hailingPort'            => cleanString($_POST['hailingPort'] ?? null, false) ?: (string)$current_vessel['hailingPort'],
    ':callSign'               => cleanString($_POST['callSign'] ?? null),
    ':mmsi'                   => asIntOrNull($_POST['mmsi'] ?? null),
    ':epirbHexId'             => cleanString($_POST['epirbHexId'] ?? null),
    ':hin'                    => cleanString($_POST['hin'] ?? null),
    ':photo_path'             => $photo_path,
    ':vesselClass'            => cleanString($_POST['vesselClass'] ?? null),
    ':classType'              => cleanString($_POST['classType'] ?? null),
    ':vesselService'          => cleanString($_POST['vesselService'] ?? null),
    ':grossTons'              => asFloatOrDefault($_POST['grossTons'] ?? null, (float)$current_vessel['grossTons']),
    ':netTons'                => asFloatOrNull($_POST['netTons'] ?? null),
    ':lightshipTons'          => asFloatOrNull($_POST['lightshipTons'] ?? null),
    ':length'                 => asFloatOrDefault($_POST['length'] ?? null, (float)$current_vessel['length']),
    ':lbp'                    => asFloatOrNull($_POST['lbp'] ?? null),
    ':propulsionType'         => cleanString($_POST['propulsionType'] ?? null),
    ':auxSail'                => asIntOrNull($_POST['auxSail'] ?? null),
    ':horsepower'             => asIntOrDefault($_POST['horsepower'] ?? null, (int)$current_vessel['horsepower']),
    ':inspSubChapter'         => cleanString($_POST['inspSubChapter'] ?? null),
    ':sip'                    => asIntOrNull($_POST['sip'] ?? null),
    ':keelLaidDate'           => dateOrNull($_POST['keelLaidDate'] ?? null) ?: $current_vessel['keelLaidDate'],
    ':deliveryDate'           => dateOrNull($_POST['deliveryDate'] ?? null) ?: $current_vessel['deliveryDate'],
    ':master'                 => $master,
    ':deckhands'              => $deckhands,
    ':othersInCrew'           => $othersInCrew,
    ':personInAddition'       => $personInAddition,
    ':passengers'             => $passengers,
    ':pob'                    => $pob,
    ':route'                  => cleanString($_POST['route'] ?? null),
    ':waters'                 => cleanString($_POST['waters'] ?? null),
    ':hullMaterial'           => cleanString($_POST['hullMaterial'] ?? null),
    ':lastInspection'         => dateOrNull($_POST['lastInspection'] ?? null),
    ':lastDrydock'            => dateOrNull($_POST['lastDrydock'] ?? null),
    ':nextDrydock'            => dateOrNull($_POST['nextDrydock'] ?? null),
    ':nextUnstep'             => dateOrNull($_POST['nextUnstep'] ?? null),
    ':nextScheduledInspection'=> dateOrNull($_POST['nextScheduledInspection'] ?? null),
    ':ocmi_contact_id'        => asIntOrNull($_POST['ocmi_contact_id'] ?? null),
    ':is_active'              => ((int)($_POST['is_active'] ?? $current_vessel['is_active']) === 1) ? 1 : 0,
];

$sql = "
    UPDATE vessels SET
        company_id = :company_id,
        vesselName = :vesselName,
        vesselON = :vesselON,
        hailingPort = :hailingPort,
        callSign = :callSign,
        mmsi = :mmsi,
        epirbHexId = :epirbHexId,
        hin = :hin,
        photo_path = :photo_path,
        vesselClass = :vesselClass,
        classType = :classType,
        vesselService = :vesselService,
        grossTons = :grossTons,
        netTons = :netTons,
        lightshipTons = :lightshipTons,
        length = :length,
        lbp = :lbp,
        propulsionType = :propulsionType,
        auxSail = :auxSail,
        horsepower = :horsepower,
        inspSubChapter = :inspSubChapter,
        sip = :sip,
        keelLaidDate = :keelLaidDate,
        deliveryDate = :deliveryDate,
        master = :master,
        deckhands = :deckhands,
        othersInCrew = :othersInCrew,
        personInAddition = :personInAddition,
        passengers = :passengers,
        pob = :pob,
        route = :route,
        waters = :waters,
        hullMaterial = :hullMaterial,
        lastInspection = :lastInspection,
        lastDrydock = :lastDrydock,
        nextDrydock = :nextDrydock,
        nextUnstep = :nextUnstep,
        nextScheduledInspection = :nextScheduledInspection,
        ocmi_contact_id = :ocmi_contact_id,
        is_active = :is_active
    WHERE vessel_id = :vessel_id
";

$stmt = $pdo->prepare($sql);
if (!$stmt->execute($params)) {
    $error = $stmt->errorInfo();
    fail('Failed to update vessel: ' . implode(' | ', array_map('strval', $error)), 500);
}

header('Location: vessel_dashboard.php?vessel_id=' . $vessel_id);
exit;
