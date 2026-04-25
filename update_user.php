<?php
require 'session_check.php';
require 'db_connect.php';

if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2], true)) {
    echo "Access denied.";
    exit;
}

$user_id = (int)($_POST['id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    exit('Invalid user ID.');
}

$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs = ($session_company_id === 1);

if (!$is_mscs) {
    $targetCheck = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
    $targetCheck->execute([$user_id]);
    $targetCompanyId = (int)($targetCheck->fetchColumn() ?: 0);

    if ($targetCompanyId !== $session_company_id) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// File upload handler
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

function sanitizeDate($val) {
    return (!empty($val) && $val !== '') ? $val : null;
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

// Inputs
$fName = trim($_POST['fName'] ?? '');
$lName = trim($_POST['lName'] ?? '');
$phone = trim($_POST['phoneNumber'] ?? '');
$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');
$role_id = (int)($_POST['role_id'] ?? 0);
$new_password = trim($_POST['new_password'] ?? '');

// Company restriction
$company_id = $is_mscs
    ? (int)($_POST['company_id'] ?? 0)
    : $session_company_id;

// Master notification toggle
$receive_notifications = isset($_POST['receive_notifications']) ? 1 : 0;

// Dates
$mmc = sanitizeDate($_POST['mmc'] ?? null);
$mmc_medical = sanitizeDate($_POST['mmc_medical'] ?? null);
$fa = sanitizeDate($_POST['fa'] ?? null);
$mrop = sanitizeDate($_POST['mrop'] ?? null);

// File uploads
$mmc_path = handleUpload('mmc_path');
$mmc_medical_path = handleUpload('mmc_medical_path');
$fa_path = handleUpload('fa_path');
$mrop_path = handleUpload('mrop_path');

// Current file paths
$current = $pdo->prepare("
    SELECT mmc_path, mmc_medical_path, fa_path, mrop_path
    FROM users
    WHERE id = ?
");
$current->execute([$user_id]);
$existing = $current->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    exit('User not found.');
}

// Keep existing file if not replaced
$mmc_path = $mmc_path ?: $existing['mmc_path'];
$mmc_medical_path = $mmc_medical_path ?: $existing['mmc_medical_path'];
$fa_path = $fa_path ?: $existing['fa_path'];
$mrop_path = $mrop_path ?: $existing['mrop_path'];

// Basic validation
if ($fName === '' || $lName === '' || $email === '' || $username === '') {
    exit("❌ Required fields are missing.");
}

if ($company_id <= 0) {
    exit("❌ Invalid company.");
}

if ($role_id <= 0) {
    exit("❌ Invalid system role.");
}

// Username uniqueness except self
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?");
$stmt->execute([$username, $user_id]);
if ((int)$stmt->fetchColumn() > 0) {
    exit("❌ Username already exists.");
}

// Email uniqueness except self
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
$stmt->execute([$email, $user_id]);
if ((int)$stmt->fetchColumn() > 0) {
    exit("❌ Email already exists.");
}

$allowed_vessel_roles = ['Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'];

$assignment_existing_ids = $_POST['assignment_existing_id'] ?? [];
$assignment_vessel_ids = $_POST['assignment_vessel_id'] ?? [];
$assignment_roles = $_POST['assignment_role'] ?? [];
$assignment_drills = $_POST['assignment_counts_for_drills'] ?? [];
$assignment_logs = $_POST['assignment_counts_for_voyage_logs'] ?? [];

$normalizedAssignments = [];
$seenVessels = [];

$maxRows = max(
    count($assignment_existing_ids),
    count($assignment_vessel_ids),
    count($assignment_roles),
    count($assignment_drills),
    count($assignment_logs)
);

for ($i = 0; $i < $maxRows; $i++) {
    $existing_id = isset($assignment_existing_ids[$i]) && $assignment_existing_ids[$i] !== ''
        ? (int)$assignment_existing_ids[$i]
        : 0;

    $vessel_id = isset($assignment_vessel_ids[$i]) ? (int)$assignment_vessel_ids[$i] : 0;
    $role = trim($assignment_roles[$i] ?? '');

    $drillsPosted = (isset($assignment_drills[$i]) && (string)$assignment_drills[$i] === '1') ? 1 : 0;
    $logsPosted = (isset($assignment_logs[$i]) && (string)$assignment_logs[$i] === '1') ? 1 : 0;

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
        'existing_id' => $existing_id,
        'vessel_id' => $vessel_id,
        'role' => $role,
        'counts_for_drills' => $flags['counts_for_drills'],
        'counts_for_voyage_logs' => $flags['counts_for_voyage_logs'],
    ];
}

try {
    $pdo->beginTransaction();

    // Update user
    $stmt = $pdo->prepare("
        UPDATE users SET
            fName = ?,
            lName = ?,
            phoneNumber = ?,
            email = ?,
            username = ?,
            company_id = ?,
            role_id = ?,
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
        $phone,
        $email,
        $username,
        $company_id,
        $role_id,
        $receive_notifications,
        $mmc,
        $mmc_medical,
        $fa,
        $mrop,
        $mmc_path,
        $mmc_medical_path,
        $fa_path,
        $mrop_path,
        $user_id
    ]);

    // Update password if provided
    if ($new_password !== '') {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pwd_stmt = $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?");
        $pwd_stmt->execute([$hashed, $user_id]);
    }

    // Load current active assignments
    $currentAssignmentsStmt = $pdo->prepare("
        SELECT id, vessel_id
        FROM vessel_crew
        WHERE crew_id = ?
          AND is_active = 1
    ");
    $currentAssignmentsStmt->execute([$user_id]);
    $currentAssignments = $currentAssignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    $currentById = [];
    foreach ($currentAssignments as $row) {
        $currentById[(int)$row['id']] = $row;
    }

    $submittedActiveIds = [];

    $updateAssignmentStmt = $pdo->prepare("
        UPDATE vessel_crew
        SET
            vessel_id = ?,
            role = ?,
            counts_for_drills = ?,
            counts_for_voyage_logs = ?,
            is_active = 1,
            removed_at = NULL,
            removed_by = NULL
        WHERE id = ?
          AND crew_id = ?
    ");

    $insertAssignmentStmt = $pdo->prepare("
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
        if ($assignment['existing_id'] > 0) {
            $updateAssignmentStmt->execute([
                $assignment['vessel_id'],
                $assignment['role'],
                $assignment['counts_for_drills'],
                $assignment['counts_for_voyage_logs'],
                $assignment['existing_id'],
                $user_id
            ]);

            $submittedActiveIds[] = $assignment['existing_id'];
        } else {
            $insertAssignmentStmt->execute([
                $assignment['vessel_id'],
                $user_id,
                $assignment['role'],
                $assignment['counts_for_drills'],
                $assignment['counts_for_voyage_logs']
            ]);

            $submittedActiveIds[] = (int)$pdo->lastInsertId();
        }
    }

    // Deactivate assignments removed from form
    $deactivateStmt = $pdo->prepare("
        UPDATE vessel_crew
        SET
            is_active = 0,
            removed_at = NOW(),
            removed_by = ?
        WHERE id = ?
          AND crew_id = ?
          AND is_active = 1
    ");

    foreach ($currentAssignments as $currentRow) {
        $currentId = (int)$currentRow['id'];

        if (!in_array($currentId, $submittedActiveIds, true)) {
            $deactivateStmt->execute([
                (int)($_SESSION['user_id'] ?? 0),
                $currentId,
                $user_id
            ]);
        }
    }

    $pdo->commit();
    header("Location: manage_users.php?success=user_updated");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "❌ Failed to update user: " . htmlspecialchars($e->getMessage());
}