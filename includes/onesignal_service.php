<?php
declare(strict_types=1);

if (!function_exists('vms_onesignal_config')) {
    function vms_onesignal_config(): array
    {
        $configPath = __DIR__ . '/../private/config_onesignal.php';
        if (!file_exists($configPath)) {
            $configPath = '/var/www/private/config_onesignal.php';
        }

        if (file_exists($configPath)) {
            require_once $configPath;
        }

        if (!defined('ONESIGNAL_APP_ID') || !defined('ONESIGNAL_REST_API_KEY') || ONESIGNAL_REST_API_KEY === '') {
            return [
                'enabled' => false,
                'error' => 'OneSignal config missing or incomplete.',
            ];
        }

        return [
            'enabled' => true,
            'app_id'   => ONESIGNAL_APP_ID,
            'api_key'  => ONESIGNAL_REST_API_KEY,
            'api_url'  => 'https://api.onesignal.com/notifications?c=push',
        ];
    }
}

if (!function_exists('vms_log_push_result')) {
    function vms_log_push_result(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }

        $logFile = $logDir . '/vms_push.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

if (!function_exists('vms_send_push_external_ids')) {
    /**
     * @param string[] $externalIds
     */
    function vms_send_push_external_ids(
        array $externalIds,
        string $title,
        string $body,
        ?string $url = null,
        array $data = []
    ): array {
        $externalIds = array_values(array_unique(array_filter(array_map('strval', $externalIds))));

        if (empty($externalIds)) {
            return [
                'ok' => false,
                'error' => 'No external IDs supplied.'
            ];
        }

        $cfg = vms_onesignal_config();

        if (empty($cfg['enabled'])) {
            vms_log_push_result('SKIPPED ' . ($cfg['error'] ?? 'OneSignal disabled or not configured.'));
            return [
                'ok' => false,
                'skipped' => true,
                'error' => $cfg['error'] ?? 'OneSignal disabled or not configured.',
            ];
        }

        $payload = [
            'app_id' => $cfg['app_id'],
            'include_aliases' => [
                'external_id' => $externalIds
            ],
            'target_channel' => 'push',
            'headings' => ['en' => $title],
            'contents' => ['en' => $body],
            'data' => $data,
        ];

        if (!empty($url)) {
            $payload['url'] = $url;
        }

        $ch = curl_init($cfg['api_url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Key ' . $cfg['api_key'],
                'Content-Type: application/json; charset=utf-8',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);

        if ($curlError) {
            vms_log_push_result("ERROR curl={$curlError}");
            return [
                'ok' => false,
                'error' => $curlError,
                'http_code' => $httpCode,
                'response' => $decoded ?: $response,
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            vms_log_push_result("ERROR http={$httpCode} response=" . substr((string)$response, 0, 1000));
            return [
                'ok' => false,
                'error' => 'OneSignal API returned non-2xx response.',
                'http_code' => $httpCode,
                'response' => $decoded ?: $response,
            ];
        }

        vms_log_push_result("SENT http={$httpCode} recipients=" . implode(',', $externalIds) . " response=" . substr((string)$response, 0, 1000));

        return [
            'ok' => true,
            'http_code' => $httpCode,
            'response' => $decoded ?: $response,
        ];
    }
}
