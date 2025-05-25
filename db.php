<?php
$host = 'localhost';
$db   = 'vessel_management_system';  // ✅ spelling corrected
$user = 'root';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
?>
