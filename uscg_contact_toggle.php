<?php
// uscg_contact_toggle.php — quick status switch (MSCS-only)
session_start();
require 'session_check.php';
require 'db_connect.php';

$MSCS_OWNER_ID = 1;
if ((int)($_SESSION['company_id'] ?? 0) !== $MSCS_OWNER_ID) {
  http_response_code(403); exit('Forbidden');
}

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$do  = $_GET['do'] ?? '';
$tok = $_GET['csrf'] ?? '';

if (!$id || !in_array($do, ['activate','deactivate'], true) ||
    empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tok)) {
  http_response_code(400); exit('Bad request');
}

$active = ($do === 'activate') ? 1 : 0;
$stmt = $pdo->prepare("UPDATE uscg_contacts SET active=? WHERE contact_id=?");
$stmt->execute([$active, $id]);

header('Location: uscg_contacts.php');
exit;
