<?php
require 'db.php';

$crew_id = intval($_GET['id'] ?? 0);

// Optional: prevent delete if assigned to a vessel
$check = $mysqli->query("SELECT * FROM vessel_crew WHERE crew_id = $crew_id LIMIT 1");
if ($check && $check->num_rows > 0) {
    echo "❌ Cannot delete. Crew member is assigned to a vessel. <a href='crew_members.php'>Back</a>";
    exit;
}

// Delete from crew_members table
$mysqli->query("DELETE FROM crew_members WHERE crew_id = $crew_id");
header("Location: crew_members.php");
exit;

