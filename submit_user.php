<?php
require 'session_check.php';
require 'db_connect.php';

// Allow MSCS Admin and Company Admin
if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2], true)) {
    echo "Access denied.";
    exit;
}

// Upload handler
function handleUpload($field, $folder = 'uploads/') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $filename = $folder . uniqid($field . '_', true) . ($ext ? '.' . $ext : '');

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    return move_uploaded_file($_FILES[$field]['tmp_name'], $filename) ? $filename : null;
}

// Convert empty date inputs to null
function sanitizeDate($value) {
    return (!empty($value) && $value !== '') ? $value : null;
}

function normalizeAssignmentFlags(string $role, int $drillsPosted, int $logsPosted): array {
    if (in_array($role, ['Master', 'Deckhand'], true)) {
        return [
            'counts_for_drills' => 1,
            'counts_for_voyage_logs' => 1,
        ];
    }

    return [
        'counts_for_drills' => $drillsPosted ? 1 : 0,
        'counts_for_voyage_logs' => $logsPosted ? 1 : 0,
    ];
}

// Company logic
$is_mscs = ((int)($_SESSION['company_id'] ?? 0) === 1);
$company_id = $is_mscs
    ? (int)($_POST['company_id'] ?? 0)
    : (int)$_SESSION['company_id'];

// Force override POST value to prevent tampering
$_POST['company_id'] = $company_id;

// Sanitize form inputs
$fName    = trim($_POST['fName'] ?? '');
$lName    = trim($_POST['lName'] ?? '');
$phone    = trim($_POST['phoneNumber'] ?? '');
$email    = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$password_raw = (string)($_POST['password'] ?? '');
$role_id  = (int)($_POST['role_id'] ?? 0);

// New master notification toggle
$receive_notifications = isset($_POST['receive_notifications']) ? 1 : 0;

// Allowed vessel roles
$allowed_vessel_roles = ['Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'];

// Assignment arrays
$assignment_vessel_ids = $_POST['assignment_vessel_id'] ?? [];
$assignment_roles = $_POST['assignment_role'] ?? [];
$assignment_drills = $_POST['assignment_counts_for_drills'] ?? [];
$assignment_logs = $_POST['assignment_counts_for_voyage_logs'] ?? [];

// Credential Dates (null if empty)
$mmc_date         = sanitizeDate($_POST['mmc'] ?? null);
$fa_date          = sanitizeDate($_POST['fa'] ?? null);
$mrop_date        = sanitizeDate($_POST['mrop'] ?? null);
$mmc_medical_date = sanitizeDate($_POST['mmc_medical'] ?? null);

// File Uploads (null if not present)
$mmc_path         = handleUpload('mmc_path');
$fa_path          = handleUpload('fa_path');
$mrop_path        = handleUpload('mrop_path');
$mmc_medical_path = handleUpload('mmc_medical_path');

// Basic validation
if ($fName === '' || $lName === '' || $email === '' || $username === '' || $password_raw === '') {
    exit("❌ Required fields are missing.");
}

if ($company_id <= 0) {
    exit("❌ Invalid company selection.");
}

if ($role_id <= 0) {
    exit("❌ Invalid system role.");
}

// Prevent duplicate username
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute([$username]);
if ((int)$stmt->fetchColumn() > 0) {
    exit("❌ Username already exists.");
}

// Prevent duplicate email
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
$stmt->execute([$email]);
if ((int)$stmt->fetchColumn() > 0) {
    exit("❌ Email already exists.");
}

// Normalize and validate assignments
$normalizedAssignments = [];
$seenVessels = [];

$maxRows = max(
    count($assignment_vessel_ids),
    count($assignment_roles),
    count($assignment_drills),
    count($assignment_logs)
);

for ($i = 0; $i < $maxRows; $i++) {
    $vessel_id = isset($assignment_vessel_ids[$i]) ? (int)$assignment_vessel_ids[$i] : 0;
    $role = trim($assignment_roles[$i] ?? '');

    $drillsPosted = 0;
    $logsPosted = 0;

    // Because unchecked checkboxes do not submit array elements reliably in row-based forms,
    // detect based on presence of index.
    if (isset($assignment_drills[$i]) && (string)$assignment_drills[$i] === '1') {
        $drillsPosted = 1;
    }
    if (isset($assignment_logs[$i]) && (string)$assignment_logs[$i] === '1') {
        $logsPosted = 1;
    }

    // Skip fully empty rows
    if ($vessel_id <= 0 && $role === '') {
        continue;
    }

    if ($vessel_id <= 0 || $role === '') {
        exit("❌ Each vessel assignment must include both a vessel and a vessel role.");
    }

    if (!in_array($role, $allowed_vessel_roles, true)) {
        exit("❌ Invalid vessel role selected.");
    }

    if (isset($seenVessels[$vessel_id])) {
        exit("❌ The same vessel cannot be assigned more than once.");
    }
    $seenVessels[$vessel_id] = true;

    // Confirm vessel belongs to allowed scope
    if ($is_mscs) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vessels WHERE vessel_id = ?");
        $stmt->execute([$vessel_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vessels WHERE vessel_id = ? AND company_id = ?");
        $stmt->execute([$vessel_id, $company_id]);
    }

    if ((int)$stmt->fetchColumn() === 0) {
        exit("❌ Invalid vessel selection.");
    }

    $flags = normalizeAssignmentFlags($role, $drillsPosted, $logsPosted);

    $normalizedAssignments[] = [
        'vessel_id' => $vessel_id,
        'role' => $role,
        'counts_for_drills' => $flags['counts_for_drills'],
        'counts_for_voyage_logs' => $flags['counts_for_voyage_logs'],
    ];
}

$password = password_hash($password_raw, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // Insert user into database
    $stmt = $pdo->prepare("
        INSERT INTO users (
            fName,
            lName,
            phoneNumber,
            email,
            username,
            pword,
            role_id,
            company_id,
            receive_notifications,
            mmc,
            fa,
            mrop,
            mmc_medical,
            mmc_path,
            fa_path,
            mrop_path,
            mmc_medical_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $fName,
        $lName,
        $phone,
        $email,
        $username,
        $password,
        $role_id,
        $company_id,
        $receive_notifications,
        $mmc_date,
        $fa_date,
        $mrop_date,
        $mmc_medical_date,
        $mmc_path,
        $fa_path,
        $mrop_path,
        $mmc_medical_path
    ]);

    $user_id = (int)$pdo->lastInsertId();

    if (!empty($normalizedAssignments)) {
        $stmtCrew = $pdo->prepare("
            INSERT INTO vessel_crew (
                vessel_id,
                crew_id,
                role,
                assigned_on,
                is_active,
                counts_for_drills,
                counts_for_voyage_logs
            ) VALUES (?, ?, ?, CURDATE(), 1, ?, ?)
        ");

        foreach ($normalizedAssignments as $assignment) {
            $stmtCrew->execute([
                $assignment['vessel_id'],
                $user_id,
                $assignment['role'],
                $assignment['counts_for_drills'],
                $assignment['counts_for_voyage_logs'],
            ]);
        }
    }

    $pdo->commit();
    header("Location: manage_users.php?success=user_created");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "❌ Failed to create user: " . htmlspecialchars($e->getMessage());
}