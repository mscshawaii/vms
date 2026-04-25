<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php'; // ✅ fixed path

$vessel_icr_id = isset($_REQUEST['vessel_icr_id']) ? (int)$_REQUEST['vessel_icr_id'] : 0;
$vessel_id     = isset($_REQUEST['vessel_id']) ? (int)$_REQUEST['vessel_id'] : 0;

if ($vessel_icr_id <= 0) {
    http_response_code(400);
    exit('Missing vessel_icr_id.');
}

$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        UPDATE vessel_icrs
        SET is_removed = 1,
            removed_at = NOW(),
            removed_by = :removed_by
        WHERE vessel_icr_id = :vessel_icr_id
        LIMIT 1
    ");
    $stmt->execute([
        ':removed_by' => $user_id,
        ':vessel_icr_id' => $vessel_icr_id
    ]);

    // Redirect back to vessel ICRs page
    if ($vessel_id > 0) {
        header("Location: vessel_icrs.php?vessel_id=" . $vessel_id . "&removed=1");
    } else {
        header("Location: index.php");
    }
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Error removing vessel ICR.";
    exit;
}
