<?php
declare(strict_types=1);

require 'session_check.php';
require 'db_connect.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo "Access denied.";
    exit;
}

function clean(?string $value): string {
    return trim((string)$value);
}

function nullIfBlank(?string $value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function uploadUserCredentialFile(string $fieldName, int $userId, ?string $existingPath = null): ?string {
    if (
        !isset($_FILES[$fieldName]) ||
        !is_array($_FILES[$fieldName]) ||
        ($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return $existingPath;
    }

    $file = $_FILES[$fieldName];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload failed for {$fieldName}.");
    }

    $originalName = $file['name'] ?? 'file';
    $tmpName = $file['tmp_name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException("Invalid file type for {$fieldName}.");
    }

    $uploadDir = __DIR__ . '/uploads/user_credentials/' . $userId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException("Failed to create upload directory.");
    }

    $filename = $fieldName . '_' . time() . '.' . $ext;
    $targetAbs = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpName, $targetAbs)) {
        throw new RuntimeException("Failed to save uploaded file for {$fieldName}.");
    }

    return 'uploads/user_credentials/' . $userId . '/' . $filename;
}

$fName = clean($_POST['fName'] ?? '');
$lName = clean($_POST['lName'] ?? '');
$email = clean($_POST['email'] ?? '');
$phone = clean($_POST['phoneNumber'] ?? '');
$username = clean($_POST['username'] ?? '');
$newPassword = clean($_POST['new_password'] ?? '');
$receiveNotifications = isset($_POST['receive_notifications']) ? 1 : 0;

$mmc = nullIfBlank($_POST['mmc'] ?? '');
$mmcMedical = nullIfBlank($_POST['mmc_medical'] ?? '');
$fa = nullIfBlank($_POST['fa'] ?? '');
$mrop = nullIfBlank($_POST['mrop'] ?? '');

if ($fName === '' || $lName === '' || $email === '' || $username === '') {
    exit('❌ Required fields are missing.');
}

// Username uniqueness except self
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
$stmt->execute([$username, $userId]);
if ((int)$stmt->fetchColumn() > 0) {
    exit('❌ Username already exists.');
}

// Email uniqueness except self
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
$stmt->execute([$email, $userId]);
if ((int)$stmt->fetchColumn() > 0) {
    exit('❌ Email already exists.');
}

// Get existing file paths
$stmt = $pdo->prepare("
    SELECT mmc_path, mmc_medical_path, fa_path, mrop_path
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    exit('❌ User not found.');
}

try {
    $mmcPath = uploadUserCredentialFile('mmc_path', $userId, $current['mmc_path'] ?? null);
    $mmcMedicalPath = uploadUserCredentialFile('mmc_medical_path', $userId, $current['mmc_medical_path'] ?? null);
    $faPath = uploadUserCredentialFile('fa_path', $userId, $current['fa_path'] ?? null);
    $mropPath = uploadUserCredentialFile('mrop_path', $userId, $current['mrop_path'] ?? null);

    $stmt = $pdo->prepare("
        UPDATE users
        SET
            fName = ?,
            lName = ?,
            email = ?,
            phoneNumber = ?,
            username = ?,
            receive_notifications = ?,
            mmc = ?,
            mmc_medical = ?,
            fa = ?,
            mrop = ?,
            mmc_path = ?,
            mmc_medical_path = ?,
            fa_path = ?,
            mrop_path = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $fName,
        $lName,
        $email,
        $phone,
        $username,
        $receiveNotifications,
        $mmc,
        $mmcMedical,
        $fa,
        $mrop,
        $mmcPath,
        $mmcMedicalPath,
        $faPath,
        $mropPath,
        $userId
    ]);

    if ($newPassword !== '') {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtPwd = $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?");
        $stmtPwd->execute([$hashed, $userId]);
    }

    $_SESSION['fName'] = $fName;
    $_SESSION['username'] = $username;

    header('Location: user_settings.php?status=saved');
    exit;

} catch (Throwable $e) {
    echo '❌ Failed to update settings: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}