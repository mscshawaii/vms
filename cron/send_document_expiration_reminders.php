<?php
declare(strict_types=1);

/**
 * Cron launcher for document expiration reminders.
 *
 * Current design:
 * - Reads secure runner token from config_reminders.php
 * - Calls the internal localhost runner endpoint
 * - Returns non-zero exit code on failure for cron visibility
 */

$config = require __DIR__ . '/../config_reminders.php';

$token = trim((string)($config['runner_token'] ?? ''));
if ($token === '') {
    fwrite(STDERR, "Missing runner token.\n");
    exit(1);
}

// Allow override from config later, but keep current tested behavior as default.
$runnerUrl = (string)($config['runner_url'] ?? 'http://127.0.0.1/run_document_expiration_reminders.php');
$separator = (strpos($runnerUrl, '?') === false) ? '?' : '&';
$url = $runnerUrl . $separator . 'token=' . urlencode($token);

$ch = curl_init($url);
if ($ch === false) {
    fwrite(STDERR, "Failed to initialize cURL.\n");
    exit(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 300,
    CURLOPT_USERAGENT      => 'VMS-Document-Reminder-Runner/1.0',
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

if ($response !== false && trim((string)$response) !== '') {
    echo rtrim((string)$response) . "\n";
}

if ($error !== '') {
    fwrite(STDERR, "CURL ERROR: {$error}\n");
    exit(1);
}

if ($httpCode !== 200) {
    fwrite(STDERR, "HTTP ERROR: {$httpCode}\n");
    exit(1);
}

exit(0);