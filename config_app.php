<?php
declare(strict_types=1);

return [
    'qr_base_url' => rtrim(getenv('VMS_QR_BASE_URL') ?: 'https://vms.mschawaii.org', '/'),
];
