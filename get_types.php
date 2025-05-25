<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';

$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

$stmt = $pdo->prepare("SELECT id, name FROM equipment_type WHERE category_id = ?");
$stmt->execute([$category_id]);

echo '<option value="">-- Select Type --</option>';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
}
?>
