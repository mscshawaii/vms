<?php
require 'db.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);

if ($vessel_id <= 0) {
    echo '<option value="">-- No users found --</option>';
    exit;
}

$result = $mysqli->query("
    SELECT u.id, u.name
    FROM vessel_users vu
    JOIN users u ON vu.user_id = u.id
    WHERE vu.vessel_id = $vessel_id
    ORDER BY u.name
");

if (!$result || $result->num_rows === 0) {
    echo '<option value="">-- No users assigned to this vessel --</option>';
} else {
    echo '<option value="">-- Select User --</option>';
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['name']}</option>";
    }
}
?>
