<?php
declare(strict_types=1);

/**
 * config_notify.php (SECURE DROP-IN)
 *
 * SECURITY RULES:
 *  - NO API keys in this file. Ever.
 *  - Read secrets only from environment variables (Apache/PHP-FPM env).
 *  - Provide legacy SMTP_* constants for backwards compatibility.
 */

// ---- Email identity (safe to keep in code) ----
$fromEmail  = getenv('MAIL_FROM_EMAIL')  ?: 'alerts@vms.mschawaii.org';
$fromName   = getenv('MAIL_FROM_NAME')   ?: 'MSCS Hawaii – VMS';
$replyEmail = getenv('MAIL_REPLYTO_EMAIL') ?: $fromEmail;
$replyName  = getenv('MAIL_REPLYTO_NAME')  ?: $fromName;

// ---- Kill switch: set MAIL_ENABLED=0 to disable sending instantly ----
$mailEnabled = (getenv('MAIL_ENABLED') ?: '1') === '1';

// ---- LEGACY SMTP CONSTANTS (kept so older code keeps working) ----
// NOTE: SMTP_PASS comes ONLY from env SENDGRID_API_KEY (or blank if not set).
if (!defined('SMTP_HOST'))          define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.sendgrid.net');
if (!defined('SMTP_PORT'))          define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: '587'));
if (!defined('SMTP_USER'))          define('SMTP_USER', getenv('SMTP_USER') ?: 'apikey');
if (!defined('SMTP_PASS'))          define('SMTP_PASS', getenv('SENDGRID_API_KEY') ?: getenv('MAIL_SG_KEY') ?: '');
if (!defined('SMTP_FROM_EMAIL'))    define('SMTP_FROM_EMAIL', $fromEmail);
if (!defined('SMTP_FROM_NAME'))     define('SMTP_FROM_NAME',  $fromName);
if (!defined('SMTP_REPLYTO_EMAIL')) define('SMTP_REPLYTO_EMAIL', $replyEmail);
if (!defined('SMTP_REPLYTO_NAME'))  define('SMTP_REPLYTO_NAME',  $replyName);

// ---- Structured config for newer code (single return) ----
return [
    'email' => [
        // Kill switch used by wrappers (recommended)
        'enabled'        => $mailEnabled,

        // Identity
        'from_email'     => $fromEmail,
        'from_name'      => $fromName,
        'reply_to_email' => $replyEmail,
        'reply_to_name'  => $replyName,

        // Optional safety: route all outbound to admin only (e.g., staging)
        // Set MAIL_FORCE_TO=info@mschawaii.org to enable.
        'force_to'       => getenv('MAIL_FORCE_TO') ?: '',

        // Optional default BCC for audits/tests
        'admin_bcc'      => getenv('MAIL_ADMIN_BCC') ?: 'info@mschawaii.org',

        // IMPORTANT: ENV ONLY. No key is stored here.
        // Your sending code should read SENDGRID_API_KEY directly from getenv().
        'provider'       => getenv('MAIL_PROVIDER') ?: 'sendgrid',
    ],

    'thresholds' => [
        'doc_expiring_days' => (int)(getenv('DOC_EXPIRING_DAYS') ?: '45'),
        'icr_default_days'  => (int)(getenv('ICR_DEFAULT_DAYS')  ?: '90'),
    ],

    // Optional per-ICR cadence overrides by number OR title
    'icr_intervals' => [
        // 'ICR-001' => 180,
        // 'Fuel System Inspection' => 120,
    ],

    // OPTIONAL: rate-limit policy (you can wire this into notify.php later)
    'mail_limits' => [
        'global_per_hour'   => (int)(getenv('MAIL_LIMIT_GLOBAL_PER_HOUR') ?: '50'),
        'per_user_per_hour' => (int)(getenv('MAIL_LIMIT_USER_PER_HOUR')   ?: '20'),
        'per_to_per_hour'   => (int)(getenv('MAIL_LIMIT_TO_PER_HOUR')     ?: '10'),
    ],
];
