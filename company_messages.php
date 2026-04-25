<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT owner_id, company_name
    FROM owners
    WHERE owner_id = ?
    LIMIT 1
");
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("❌ Company not found.");
}

$companyThreadId = ensureCompanyGeneralThread($pdo, $companyId, $currentUserId);
syncCompanyThreadMembers($pdo, $companyId, $currentUserId);
$companyThreadTitle = ($company['company_name'] ?? 'Company') . ' - General';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Messages - VMS</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php
$title = $companyThreadTitle;
$back_link = "dashboard.php";
include __DIR__ . '/partials/top_nav.php';
?>

<?php
$thread_id = (int)$companyThreadId;
$placeholder = "Post company-wide update, operations note, scheduling item, or admin message...";
include __DIR__ . '/partials/thread_panel.php';
?>

</body>
</html>
