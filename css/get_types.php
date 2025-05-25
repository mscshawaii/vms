<?php
require 'db.php';

if (isset($_POST['category_id'])) {
    $category_id = (int) $_POST['category_id'];

    $stmt = $mysqli->prepare("SELECT id, name FROM equipment_type WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo '<option value="">-- Select Type --</option>';
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
    $stmt->close();
}
?>