<?php
session_start();
require 'db_connect.php'; // defines $pdo

$user_id    = $_SESSION['user_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;
$is_mscs    = ($company_id == 1);

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$crew_id   = intval($_POST['crew_id'] ?? 0);

// role is optional, keep it short/sane
$role = trim($_POST['role'] ?? '');
if (mb_strlen($role) > 80) {
    $role = mb_substr($role, 0, 80);
}

if (!$user_id || !$vessel_id || !$crew_id) {
    http_response_code(400);
    exit('Missing required data.');
}

/**
 * Security: verify the vessel belongs to the session company (unless MSCS).
 * This prevents someone from posting a random vessel_id.
 */
if (!$is_mscs) {
    $vesselCheck = $pdo->prepare("SELECT vessel_id FROM vessels WHERE vessel_id = ? AND company_id = ?");
    $vesselCheck->execute([$vessel_id, $company_id]);
    if ($vesselCheck->rowCount() === 0) {
        http_response_code(403);
        exit('Access denied: You may only modify vessels in your company.');
    }
} else {
    // Even for MSCS, make sure vessel exists (avoid inserting orphaned assignments)
    $vesselExists = $pdo->prepare("SELECT vessel_id FROM vessels WHERE vessel_id = ?");
    $vesselExists->execute([$vessel_id]);
    if ($vesselExists->rowCount() === 0) {
        http_response_code(404);
        exit('Vessel not found.');
    }
}

/**
 * Restrict non-MSCS users to assigning crew from THEIR company.
 * (MSCS can assign per your existing logic.)
 */
if (!$is_mscs) {
    $crewCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
    $crewCheck->execute([$crew_id, $company_id]);
    if ($crewCheck->rowCount() === 0) {
        http_response_code(403);
        exit('Access denied: You may only assign crew from your company.');
    }
}

try {
    /**
     * Best practice: enforce uniqueness at DB-level too.
     * If you add UNIQUE(vessel_id, crew_id), this insert will just fail cleanly.
     *
     * Even without the UNIQUE index, this try/catch still helps.
     */
    $insertStmt = $pdo->prepare("
        INSERT INTO vessel_crew (vessel_id, crew_id, role, assigned_on)
        VALUES (?, ?, ?, CURRENT_DATE)
    ");
    $insertStmt->execute([$vessel_id, $crew_id, $role]);

} catch (PDOException $e) {
    // If you add a unique key, MySQL duplicate is typically SQLSTATE 23000
    // We treat duplicate as "already assigned" and just redirect.
    if ($e->getCode() !== '23000') {
        http_response_code(500);
        exit("Insert failed: " . $e->getMessage());
    }
    // else: duplicate assignment -> ignore and redirect
}

// Redirect back to vessel dashboard crew section
header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#crewModal");
exit;
