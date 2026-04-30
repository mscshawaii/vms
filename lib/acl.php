<?php
// lib/acl.php

// MSCS users are those in company_id = 1
function user_is_mscs(): bool {
    return (int)($_SESSION['company_id'] ?? 0) === 1;
}

function user_is_mscs_admin(): bool {
    return isset($_SESSION['company_id'], $_SESSION['role_id'])
        && (int)$_SESSION['company_id'] === 1
        && (int)$_SESSION['role_id'] === 1;
}

/**
 * Return the list of vessel_ids this user can access when "All" is selected.
 * - MSCS: all ACTIVE vessels (no company filter)
 * - Others: ACTIVE vessels in their company
 */
function allowed_vessel_ids(PDO $pdo): array {
    $companyId = (int)($_SESSION['company_id'] ?? 0);

    if (user_is_mscs()) {
        $sql = "SELECT vessel_id FROM vessels WHERE is_active = 1 ORDER BY vesselName";
        $stmt = $pdo->query($sql);
    } else {
        $sql = "SELECT vessel_id FROM vessels WHERE is_active = 1 AND company_id = ? ORDER BY vesselName";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$companyId]);
    }

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Given a requested vessel id (or null/empty for “All”),
 * return the authoritative list of target vessel_ids.
 * - If a specific id is provided and allowed → [id]
 * - If specific id not allowed → []
 * - If null/empty → allowed_vessel_ids()
 */
function clamp_to_allowed_vessels(PDO $pdo, ?int $requestedVesselId): array {
    $allowed = allowed_vessel_ids($pdo);

    if ($requestedVesselId) {
        return in_array((int)$requestedVesselId, $allowed, true) ? [(int)$requestedVesselId] : [];
    }

    // “All”
    return $allowed;
}
