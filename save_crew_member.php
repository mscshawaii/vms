<?php
require 'db.php';

$first = trim($_POST['first_name']);
$last = trim($_POST['last_name']);
$title = trim($_POST['title']);
$license = trim($_POST['license_number']);
$notes = trim($_POST['notes']);

if (!$first || !$last) {
    die("❌ First and last name are required.");
}

$stmt = $mysqli->prepare("INSERT INTO crew_members (first_name, last_name, title, license_number, notes) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $first, $last, $title, $license, $notes);
$stmt->execute();
$stmt->close();

header("Location: crew_members.php");
exit;