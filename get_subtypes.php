<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

$type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;

$stmt = $pdo->prepare("SELECT id, name FROM equipment_subtype WHERE type_id = ?");
$stmt->execute([$type_id]);

echo '<option value="">-- Select Subtype --</option>';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
}
?>
