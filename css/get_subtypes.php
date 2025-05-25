<?php
require 'db.php';

if (isset($_POST['type_id'])) {
    $type_id = (int) $_POST['type_id'];

    $stmt = $mysqli->prepare("SELECT id, name FROM equipment_subtype WHERE type_id = ?");
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">-- Select Subtype --</option>';
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
    $stmt->close();
}
?>