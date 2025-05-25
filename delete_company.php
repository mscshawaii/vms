<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("DELETE FROM owners WHERE owner_id = ?");
$stmt->execute([$id]);

header("Location: view_companies.php?deleted=1");
exit;
