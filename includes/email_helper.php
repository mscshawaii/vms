<?php

use PHPMailer\PHPMailer\PHPMailer;

if (!function_exists('sendVmsSystemEmail')) {
    function sendVmsSystemEmail(
        string|array $to,
        string $subject,
        string $plainBody,
        bool $isHtml = false,
        ?string $htmlBody = null,
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        array $attachments = []
    ): array {
        $composerAutoload = __DIR__ . '/../vendor/autoload.php';
        $mailCfgPath      = __DIR__ . '/../config_mail.php';

        if (!file_exists($composerAutoload)) {
            return ['success' => false, 'error' => 'Composer autoload missing'];
        }

        if (!file_exists($mailCfgPath)) {
            return ['success' => false, 'error' => 'config_mail.php missing'];
        }

        $toList = is_array($to) ? $to : [$to];
        $toList = array_values(array_unique(array_filter(array_map(
            static fn($email) => strtolower(trim((string)$email)),
            $toList
        ))));

        if (empty($toList)) {
            return ['success' => false, 'error' => 'No recipient email provided'];
        }

        foreach ($toList as $toEmail) {
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => "Invalid recipient email: {$toEmail}"];
            }
        }

        require_once $composerAutoload;
        $mailCfg = require $mailCfgPath;

        if (!is_array($mailCfg)) {
            return ['success' => false, 'error' => 'Invalid mail configuration'];
        }

        try {
            $mailer = new PHPMailer(true);

            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host     = $mailCfg['smtp_host'];
            $mailer->Port     = (int)$mailCfg['smtp_port'];
            $mailer->CharSet  = 'UTF-8';

            if (($mailCfg['smtp_secure'] ?? '') === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (($mailCfg['smtp_secure'] ?? '') === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->Username = $mailCfg['smtp_user'];
            $mailer->Password = $mailCfg['smtp_pass'];

            if (!empty($mailCfg['smtp_debug'])) {
                $mailer->SMTPDebug   = (int)$mailCfg['smtp_debug'];
                $mailer->Debugoutput = 'error_log';
            }

            $mailer->setFrom($mailCfg['from_email'], $mailCfg['from_name']);
            foreach ($toList as $toEmail) {
            $mailer->addAddress($toEmail);
            }

            foreach (array_unique(array_filter($cc)) as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mailer->addCC($ccEmail);
                }
            }

            foreach (array_unique(array_filter($bcc)) as $bccEmail) {
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mailer->addBCC($bccEmail);
                }
            }

            if (!empty($replyTo['email']) && filter_var($replyTo['email'], FILTER_VALIDATE_EMAIL)) {
                $mailer->addReplyTo($replyTo['email'], $replyTo['name'] ?? '');
            }

            if (!empty($mailCfg['bcc_log']) && filter_var($mailCfg['bcc_log'], FILTER_VALIDATE_EMAIL)) {
                $mailer->addBCC($mailCfg['bcc_log']);
            }

            foreach ($attachments as $attachment) {
                if (!empty($attachment['path']) && is_file($attachment['path'])) {
                    $mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            $mailer->Subject = $subject;

            if ($isHtml && $htmlBody) {
                $mailer->isHTML(true);
                $mailer->Body    = $htmlBody;
                $mailer->AltBody = $plainBody;
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $plainBody;
            }

            $mailer->send();

            return ['success' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}