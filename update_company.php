<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

$stmt = $pdo->prepare("UPDATE owners SET company_name = ?, contact_name = ?, email = ?, phone = ?, address = ? WHERE owner_id = ?");
$stmt->execute([
    $_POST['company_name'],
    $_POST['contact_name'],
    $_POST['email'],
    $_POST['phone'],
    $_POST['address'],
    $_POST['owner_id']
]);

header("Location: view_companies.php?updated=1");
exit;
