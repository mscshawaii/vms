<?php
require 'db.php';

$vid = intval($_GET['id'] ?? 0);

if ($vid > 0) {
    $stmt = $mysqli->prepare("DELETE FROM vessels WHERE vid = ?");
    $stmt->bind_param("i", $vid);

    if ($stmt->execute()) {
        header("Location: view_vessels.php");
        exit;
    } else {
        echo "❌ Failed to delete vessel: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "❌ Invalid vessel ID.";
}
?>
