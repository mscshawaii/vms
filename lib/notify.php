<?php
declare(strict_types=1);

/**
 * lib/notify.php (SECURE)
 *
 * - ENV-only secrets (no API keys in code or config files)
 * - Kill switch: MAIL_ENABLED=0 disables sending
 * - Optional safety routing: MAIL_FORCE_TO and MAIL_ADMIN_BCC
 * - Removes Windows-only CA bundle hacks (uses system trust store)
 *
 * Required env:
 *   SENDGRID_API_KEY (or MAIL_SG_KEY)
 *
 * Optional env:
 *   MAIL_ENABLED=1|0
 *   MAIL_FROM_EMAIL, MAIL_FROM_NAME
 *   MAIL_REPLYTO_EMAIL, MAIL_REPLYTO_NAME
 *   MAIL_FORCE_TO (routes ALL mail to this address)
 *   MAIL_ADMIN_BCC
 *
 * Optional rate limits (APCu-based if available):
 *   MAIL_LIMIT_GLOBAL_PER_HOUR (default 50)
 *   MAIL_LIMIT_TO_PER_HOUR     (default 10)
 */

$__cfg = [];
$__cfgPath = __DIR__ . '/../config_notify.php';
if (file_exists($__cfgPath)) {
    $loaded = require $__cfgPath;
    if (is_array($loaded)) $__cfg = $loaded;
}

/** Basic APCu increment w/ TTL. Returns new value or null if APCu not available. */
function __apcu_incr_ttl(string $key, int $ttlSeconds): ?int {
    if (!function_exists('apcu_fetch') || !function_exists('apcu_store') || !function_exists('apcu_inc')) return null;

    $ok = false;
    $val = apcu_fetch($key, $ok);
    if (!$ok) {
        apcu_store($key, 0, $ttlSeconds);
    }
    // apcu_inc returns false on failure; cast carefully
    $newVal = apcu_inc($key, 1, $success);
    if (!$success) return null;
    return (int)$newVal;
}

/** Determine whether sending is allowed right now. */
function __mail_enabled(array $cfgEmail): bool {
    // env wins
    $env = getenv('MAIL_ENABLED');
    if ($env !== false) return $env === '1';
    // config fallback (safe; not secret)
    if (isset($cfgEmail['enabled'])) return (bool)$cfgEmail['enabled'];
    return true;
}

/**
 * Send with SendGrid Web API (v3).
 * ENV-ONLY API key. No config-file fallback.
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody=null): array {
    $cfgEmail = $GLOBALS['__cfg']['email'] ?? [];

    // Kill switch
    if (!__mail_enabled($cfgEmail)) {
        return ['ok' => false, 'error' => 'MAIL_ENABLED disabled'];
    }

    // ENV-only secret
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('MAIL_SG_KEY') ?: '';
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Missing SENDGRID_API_KEY (or MAIL_SG_KEY) env var'];
    }

    // Identity (safe in config/env)
    $fromEmail = getenv('MAIL_FROM_EMAIL') ?: ($cfgEmail['from_email'] ?? 'alerts@vms.mschawaii.org');
    $fromName  = getenv('MAIL_FROM_NAME')  ?: ($cfgEmail['from_name']  ?? 'MSCS Hawaii – VMS');
    $replyE    = getenv('MAIL_REPLYTO_EMAIL') ?: ($cfgEmail['reply_to_email'] ?? $fromEmail);
    $replyN    = getenv('MAIL_REPLYTO_NAME')  ?: ($cfgEmail['reply_to_name']  ?? $fromName);

    // Safety routing (highly recommended for staging/testing)
    $forceTo   = getenv('MAIL_FORCE_TO') ?: ($cfgEmail['force_to'] ?? '');
    $adminBcc  = getenv('MAIL_ADMIN_BCC') ?: ($cfgEmail['admin_bcc'] ?? '');

    $originalTo = $toEmail;
    if ($forceTo !== '') {
        $toEmail = $forceTo;
        $toName  = $toName ?: $toEmail;
        // Make it obvious when mail was redirected
        $subject = '[FORCED TO ' . $forceTo . '] ' . $subject . ' (orig: ' . $originalTo . ')';
    }

    // --- Rate limiting (APCu if present; otherwise no-op) ---
    $globalLimit = (int)(getenv('MAIL_LIMIT_GLOBAL_PER_HOUR') ?: '50');
    $toLimit     = (int)(getenv('MAIL_LIMIT_TO_PER_HOUR')     ?: '10');

    $bucketGlobal = 'vms_mail_global_' . date('YmdH'); // per-hour bucket
    $bucketTo     = 'vms_mail_to_' . md5(strtolower(trim($toEmail))) . '_' . date('YmdH');

    $ttl = 3700; // a bit over an hour
    $gCount = __apcu_incr_ttl($bucketGlobal, $ttl);
    if ($gCount !== null && $gCount > $globalLimit) {
        return ['ok' => false, 'error' => 'Rate limit exceeded (global/hour)', 'limit' => $globalLimit, 'count' => $gCount];
    }
    $tCount = __apcu_incr_ttl($bucketTo, $ttl);
    if ($tCount !== null && $tCount > $toLimit) {
        return ['ok' => false, 'error' => 'Rate limit exceeded (recipient/hour)', 'limit' => $toLimit, 'count' => $tCount];
    }

    // Content: always include text/plain for deliverability
    $plain = $textBody ?? trim(strip_tags($htmlBody));
    if ($plain === '') $plain = '(no content)';

    $personalization = [
        'to' => [[ 'email' => $toEmail, 'name' => ($toName ?: $toEmail) ]],
    ];

    if ($adminBcc !== '' && $forceTo === '') {
        // Only add BCC when not forced-to (keeps tests clean)
        $personalization['bcc'] = [[ 'email' => $adminBcc ]];
    }

    $payload = [
        'personalizations' => [ $personalization ],
        'from'      => [ 'email' => $fromEmail, 'name' => $fromName ],
        'reply_to'  => [ 'email' => $replyE,    'name' => $replyN ],
        'subject'   => $subject,
        'content'   => [
            [ 'type' => 'text/plain', 'value' => $plain ],
            [ 'type' => 'text/html',  'value' => $htmlBody ],
        ],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,

        // IMPORTANT:
        // Do NOT hardcode CURLOPT_CAINFO to a Windows path.
        // Let libcurl use the system trust store on Linux.
        // If your local Windows dev needs a CA bundle, set CURL_CA_BUNDLE env locally (not in code).
    ]);

    $resp   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => "cURL error: $err"];
    if ($status >= 200 && $status < 300) return ['ok' => true, 'status' => $status];

    // Avoid echoing huge responses; keep it for logging only
    return ['ok' => false, 'status' => $status, 'response' => $resp];
}
