<?php
declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Master controls
    |--------------------------------------------------------------------------
    */
    'reminders_enabled' => true,
    'dry_run'           => false,
    'test_mode'         => true,
    'ignore_cooldown'   => false,

    /*
    |--------------------------------------------------------------------------
    | Recipient controls
    |--------------------------------------------------------------------------
    | owners_enabled:
    |   false = do not send to owner/company contacts
    |   true  = include owner/company contacts when test_mode is false
    */
    'owners_enabled'    => true,

    /*
    |--------------------------------------------------------------------------
    | Safety controls
    |--------------------------------------------------------------------------
    */
    'test_email_override' => 'info@mschawaii.org',
    'max_emails_per_run'  => 50,
    'allowed_recipients'  => [],

    /*
    |--------------------------------------------------------------------------
    | Internal web runner protection
    |--------------------------------------------------------------------------
    */
    'runner_token' => 'b4a8d0c4f8f34b0f8d4b8c8a0a42d8f6f3f5b5f9d8c7a6e1',

    /*
    |--------------------------------------------------------------------------
    | Sender identity
    |--------------------------------------------------------------------------
    */
    'from_email' => 'info@mschawaii.org',
    'from_name'  => 'MSCS Hawaii VMS',
];