<?php
require 'db_connect.php';
require 'session_check.php';

// Sanitize inputs
$vessel_id = intval($_POST['vessel_id'] ?? 0);
$drill_type = trim($_POST['drill_type'] ?? '');
$drill_date = $_POST['drill_date'] ?? '';
$notes = trim($_POST['notes'] ?? '');
$crew_ids = $_POST['crew_ids'] ?? [];

if (!$vessel_id || !$drill_type || !$drill_date) {
    die("❌ Missing required drill information.");
}

try {
    $pdo->beginTransaction();

    // Insert into drills
    $stmt = $pdo->prepare("
        INSERT INTO drills (vessel_id, drill_type, drill_date, notes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$vessel_id, $drill_type, $drill_date, $notes]);

    $drill_id = $pdo->lastInsertId();

    // Insert each participating crew member
    if (!empty($crew_ids)) {
        $crew_stmt = $pdo->prepare("
            INSERT INTO drill_crew (drill_id, crew_id)
            VALUES (?, ?)
        ");

        foreach ($crew_ids as $crew_id) {
            $crew_stmt->execute([$drill_id, intval($crew_id)]);
        }
    }

    $pdo->commit();

    header("Location: crew_members.php?success=drill_logged");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Failed to log drill: " . $e->getMessage();
}
?>
