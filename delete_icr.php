<?php
require 'db.php';
$step_id = intval($_GET['id'] ?? 0);
$icr_id = intval($_GET['icr_id'] ?? 0);

$mysqli->query("DELETE FROM icr_steps WHERE step_id = $step_id");
header("Location: edit_icr.php?id=$icr_id");
exit;
?>
