<?php
require 'db_connect.php';

$company_id = intval($_POST['company_id']);
$primary = !empty($_POST['primary_contact_user_id']) ? intval($_POST['primary_contact_user_id']) : null;
$alt = !empty($_POST['alt_contact_user_id']) ? intval($_POST['alt_contact_user_id']) : null;

$stmt = $pdo->prepare("UPDATE owners SET primary_contact_user_id = ?, alt_contact_user_id = ? WHERE owner_id = ?");
$stmt->execute([$primary, $alt, $company_id]);

header("Location: dashboard.php");
exit;