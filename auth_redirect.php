<?php
declare(strict_types=1);

session_start();

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
$defaultRedirect = $basePath . '/dashboard.php';

$to = $_GET['to'] ?? $defaultRedirect;

if (!is_string($to) || $to === '') {
    $to = $defaultRedirect;
}

// Only allow internal relative paths
if ($to[0] !== '/') {
    $to = $defaultRedirect;
}

// Block full external URLs
if (preg_match('/^https?:\/\//i', $to)) {
    $to = $defaultRedirect;
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $to);
    exit;
}

header('Location: ' . $basePath . '/login.php?redirect=' . urlencode($to));
exit;
