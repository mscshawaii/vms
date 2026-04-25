<?php
declare(strict_types=1);

/**
 * config_mail.php
 * MUST return an array — no PHPMailer objects here.
 */

return [
    // Kill switch (checked by caller if desired)
    'mail_enabled' => (getenv('MAIL_ENABLED') ?: '1') === '1',

    // SMTP (SendGrid)
    'smtp_host'   => getenv('SMTP_HOST') ?: 'smtp.sendgrid.net',
    'smtp_port'   => (int)(getenv('SMTP_PORT') ?: '2525'),
    'smtp_user'   => getenv('SMTP_USER') ?: 'apikey',
    'smtp_pass'   => getenv('SENDGRID_API_KEY') ?: (getenv('MAIL_SG_KEY') ?: ''),
    'smtp_secure' => 'tls',

    // Identity
    'from_email'  => getenv('MAIL_FROM_EMAIL') ?: 'alerts@vms.mschawaii.org',
    'from_name'   => getenv('MAIL_FROM_NAME')  ?: 'MSCS Hawaii – VMS',

    // Optional
    'bcc_log'     => getenv('MAIL_BCC_LOG') ?: null,
    'smtp_debug'  => (int)(getenv('SMTP_DEBUG') ?: '0'),
];
