
<?php
require_once 'session_check.php';
require_once 'db_connect.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Please sign in.');
}

$vessel_id = (int)($_POST['vessel_id'] ?? 0);
$user_id   = (int)($_POST['user_id'] ?? 0);

if ($vessel_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    exit('Missing vessel_id or user_id.');
}

$is_mscs   = ((int)($_SESSION['company_id'] ?? 0) === 1);
$companyId = (int)($_SESSION['company_id'] ?? 0);

try {
    if (!$is_mscs) {
        $vesselCheck = $pdo->prepare("
            SELECT vessel_id
            FROM vessels
            WHERE vessel_id = ?
              AND company_id = ?
            LIMIT 1
        ");
        $vesselCheck->execute([$vessel_id, $companyId]);

        if (!$vesselCheck->fetchColumn()) {
            http_response_code(403);
            exit('Access denied: vessel is outside your company.');
        }

        $userCheck = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND company_id = ?
            LIMIT 1
        ");
        $userCheck->execute([$user_id, $companyId]);

        if (!$userCheck->fetchColumn()) {
            http_response_code(403);
            exit('Access denied: user is outside your company.');
        }
    } else {
        $vesselExists = $pdo->prepare("
            SELECT vessel_id
            FROM vessels
            WHERE vessel_id = ?
            LIMIT 1
        ");
        $vesselExists->execute([$vessel_id]);

        if (!$vesselExists->fetchColumn()) {
            http_response_code(404);
            exit('Vessel not found.');
        }

        $userExists = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $userExists->execute([$user_id]);

        if (!$userExists->fetchColumn()) {
            http_response_code(404);
            exit('User not found.');
        }
    }

    $check = $pdo->prepare("
        SELECT 1
        FROM vessel_users
        WHERE vessel_id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $check->execute([$vessel_id, $user_id]);

    if (!$check->fetchColumn()) {
        $stmt = $pdo->prepare("
            INSERT INTO vessel_users (vessel_id, user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$vessel_id, $user_id]);
    }

    header("Location: assign_user_to_vessel.php?success=1");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    exit('Assignment failed: ' . $e->getMessage());
}