<?php
declare(strict_types=1);

if (!function_exists('reminder_normalize_email')) {
    function reminder_normalize_email($email): ?string
    {
        $normalized = strtolower(trim((string)$email));
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return $normalized;
    }
}

if (!function_exists('reminder_dedupe_email_list')) {
    function reminder_dedupe_email_list(array $emails): array
    {
        $seen = [];
        $deduped = [];

        foreach ($emails as $email) {
            $displayEmail = trim((string)$email);
            $normalized = reminder_normalize_email($displayEmail);
            if ($normalized === null || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $deduped[] = $displayEmail;
        }

        return $deduped;
    }
}

if (!function_exists('reminder_dedupe_to_cc')) {
    function reminder_dedupe_to_cc(array $toEmails, array $ccEmails): array
    {
        $to = reminder_dedupe_email_list($toEmails);
        $toSeen = [];

        foreach ($to as $email) {
            $normalized = reminder_normalize_email($email);
            if ($normalized !== null) {
                $toSeen[$normalized] = true;
            }
        }

        $cc = [];
        $ccSeen = [];
        foreach ($ccEmails as $email) {
            $displayEmail = trim((string)$email);
            $normalized = reminder_normalize_email($displayEmail);
            if ($normalized === null || isset($toSeen[$normalized]) || isset($ccSeen[$normalized])) {
                continue;
            }

            $ccSeen[$normalized] = true;
            $cc[] = $displayEmail;
        }

        return [$to, $cc];
    }
}
