<?php
require 'db.php'; // Make sure this connects to your MySQL database

echo "<h2>📊 Vessel Management System: Table Structure Overview</h2>";

$result = $mysqli->query("SHOW TABLES");
if (!$result) {
    die("❌ Could not retrieve tables: " . $mysqli->error);
}

while ($row = $result->fetch_array()) {
    $table = $row[0];
    echo "<h3>🗂️ $table</h3>";
    
    $columns = $mysqli->query("SHOW COLUMNS FROM `$table`");
    if ($columns) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($col = $columns->fetch_assoc()) {
            echo "<tr>
                <td>{$col['Field']}</td>
                <td>{$col['Type']}</td>
                <td>{$col['Null']}</td>
                <td>{$col['Key']}</td>
                <td>{$col['Default']}</td>
                <td>{$col['Extra']}</td>
            </tr>";
        }
        echo "</table><br>";
    } else {
        echo "⚠️ Could not describe $table: " . $mysqli->error . "<br>";
    }
}
?>
