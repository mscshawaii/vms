<?php
require 'session_check.php';
require 'db_connect.php';
require 'includes/onesignal_service.php';

header('Content-Type: text/plain');

$result = vms_send_push_external_ids(
    ['20'],
    'Anna direct test',
    'This is a direct test to Anna only.',
    '/auth_redirect.php?to=' . urlencode('/company_messages.php'),
    ['type' => 'anna_direct_test']
);

print_r($result);