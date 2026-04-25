<?php
declare(strict_types=1);

/**
 * Send one email via SendGrid Web API.
 * Supports both text/plain and text/html (email clients pick best).
 */
function send_via_sendgrid_api(
    string $to,
    string $subject,
    string $htmlBody,
    string $fromEmail,
    string $fromName = 'MSCS Hawaii VMS',
    ?string $textBody = null
): array {
    $apiKey = getenv('SENDGRID_API_KEY') ?: getenv('MAIL_SG_KEY') ?: '';
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'SendGrid API key not set (SENDGRID_API_KEY or MAIL_SG_KEY)'];
    }

    // Plaintext fallback if not provided
    if ($textBody === null || trim($textBody) === '') {
        $textBody = trim(strip_tags($htmlBody));
        if ($textBody === '') {
            $textBody = '(No message body)';
        }
    }

    $payload = [
        'personalizations' => [[
            'to' => [[ 'email' => $to ]]
        ]],
        'from' => [ 'email' => $fromEmail, 'name' => $fromName ],
        'subject' => $subject,
        'content' => [
            [ 'type' => 'text/plain', 'value' => $textBody ],
            [ 'type' => 'text/html',  'value' => $htmlBody ],
        ],
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'error' => "cURL error: $err"];
    }

    if ($code === 202) {
        return ['ok' => true, 'code' => $code];
    }

    return ['ok' => false, 'error' => "SendGrid HTTP $code: " . ($resp ?: '(no response)')];
}
