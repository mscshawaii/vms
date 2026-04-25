<?php
declare(strict_types=1);

require_once __DIR__ . '/../config_reminders.php';

$config = require __DIR__ . '/../config_reminders.php';
$token = (string)($config['runner_token'] ?? '');

if ($token === '') {
    fwrite(STDERR, "Missing runner token.\n");
    exit(1);
}

$url = 'https://vms.mschawaii.org/run_icr_monthly_reminders.php?token=' . urlencode($token);

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 120,
    ],
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    fwrite(STDERR, "Failed to call ICR reminder runner.\n");
    exit(1);
}

echo $response;