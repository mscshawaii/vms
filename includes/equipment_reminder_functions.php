<?php

if (!defined('DOCUMENT_REMINDER_TRIGGER_DAYS')) {
    define('DOCUMENT_REMINDER_TRIGGER_DAYS', [60, 30, 15, 7]);
}

if (!defined('DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS')) {
    define('DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS', 7);
}

if (!defined('EQUIPMENT_REMINDER_DIGEST_WINDOW_DAYS')) {
    define('EQUIPMENT_REMINDER_DIGEST_WINDOW_DAYS', 60);
}

if (!function_exists('parseEquipmentReminderExpDate')) {
    function parseEquipmentReminderExpDate(?string $expDate): ?DateTimeImmutable
    {
        $expDate = trim((string)$expDate);

        if ($expDate === '' || $expDate === '0000-00-00') {
            return null;
        }

        $exp = DateTimeImmutable::createFromFormat('Y-m-d', $expDate);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$exp ||
            ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            return null;
        }

        return $exp;
    }
}

if (!function_exists('isEquipmentExpiredReminderWithinWindow')) {
    function isEquipmentExpiredReminderWithinWindow(int $daysRemaining): bool
    {
        return $daysRemaining < 0 && $daysRemaining >= -DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS;
    }
}

if (!function_exists('getEquipmentReminderTypeFromDays')) {
    function getEquipmentReminderTypeFromDays(int $daysRemaining): ?string
    {
        if ($daysRemaining === 60) return '60_day';
        if ($daysRemaining === 30) return '30_day';
        if ($daysRemaining === 15) return '15_day';
        if ($daysRemaining === 7)  return '7_day';
        if ($daysRemaining < 0)    return 'expired';
        return null;
    }
}

if (!function_exists('buildEquipmentDisplayName')) {
    function buildEquipmentDisplayName(array $row): string
    {
        $name = trim((string)($row['equipmentName'] ?? ''));
        $location = trim((string)($row['equipmentLocation'] ?? ''));

        if ($name !== '' && $location !== '') {
            return $name . ' - ' . $location;
        }

        if ($name !== '') {
            return $name;
        }

        if ($location !== '') {
            return 'Equipment - ' . $location;
        }

        return 'Equipment #' . (int)($row['eid'] ?? 0);
    }
}

if (!function_exists('getTriggerVesselEquipment')) {
    function getTriggerVesselEquipment(PDO $pdo): array
    {
        $sql = "
            SELECT
                e.eid,
                e.vessel_id,
                e.equipmentName,
                e.equipmentLocation,
                e.manufacturer,
                e.modelNumber,
                e.serialNumber,
                e.expDate,
                v.vesselName,
                fed.agent_type,
                fed.extinguisher_class,
                fed.ul_rating,
                fed.capacity_value,
                fed.capacity_unit,
                fed.last_annual_service_date,
                fed.next_annual_due,
                fed.next_internal_exam_due,
                fed.next_hydro_due
            FROM equipment e
            INNER JOIN vessels v
                ON v.vessel_id = e.vessel_id
            LEFT JOIN fire_extinguisher_details fed
                ON fed.eid = e.eid
            WHERE e.vessel_id IS NOT NULL
              AND e.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY v.vesselName ASC, e.equipmentName ASC, e.eid ASC
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $due = [];

        foreach ($rows as $row) {
            $exp = parseEquipmentReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            $reminderType = getPendingReminderTypeFromDays($daysRemaining);

            if ($reminderType !== null) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = $reminderType;
                $row['display_name'] = buildEquipmentDisplayName($row);
                $due[] = $row;
            }
        }

        usort($due, function ($a, $b) {
            $vesselCmp = strcmp((string)$a['vesselName'], (string)$b['vesselName']);
            if ($vesselCmp !== 0) return $vesselCmp;

            $dateCmp = strcmp((string)$a['expDate'], (string)$b['expDate']);
            if ($dateCmp !== 0) return $dateCmp;

            $nameCmp = strcmp((string)$a['display_name'], (string)$b['display_name']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['eid'] <=> (int)$b['eid']);
        });

        return $due;
    }
}

if (!function_exists('getIncludedVesselEquipment')) {
    function getIncludedVesselEquipment(PDO $pdo, int $vesselId): array
    {
        $sql = "
            SELECT
                e.eid,
                e.vessel_id,
                e.equipmentName,
                e.equipmentLocation,
                e.manufacturer,
                e.modelNumber,
                e.serialNumber,
                e.expDate,
                v.vesselName,
                fed.agent_type,
                fed.extinguisher_class,
                fed.ul_rating,
                fed.capacity_value,
                fed.capacity_unit,
                fed.last_annual_service_date,
                fed.next_annual_due,
                fed.next_internal_exam_due,
                fed.next_hydro_due
            FROM equipment e
            INNER JOIN vessels v
                ON v.vessel_id = e.vessel_id
            LEFT JOIN fire_extinguisher_details fed
                ON fed.eid = e.eid
            WHERE e.vessel_id = ?
              AND e.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY e.expDate ASC, e.equipmentName ASC, e.eid ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vesselId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $included = [];

        foreach ($rows as $row) {
            $exp = parseEquipmentReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            // Include anything due within 60 days OR already expired, regardless of how old.
            if ($daysRemaining <= 60) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = getEquipmentReminderTypeFromDays($daysRemaining) ?? ($daysRemaining < 0 ? 'expired' : 'included');
                $row['display_name'] = buildEquipmentDisplayName($row);
                $included[] = $row;
            }
        }

        usort($included, function ($a, $b) {
            $dateCmp = strcmp((string)$a['expDate'], (string)$b['expDate']);
            if ($dateCmp !== 0) return $dateCmp;

            $nameCmp = strcmp((string)$a['display_name'], (string)$b['display_name']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['eid'] <=> (int)$b['eid']);
        });

        return $included;
    }
}

if (!function_exists('getWeeklyDigestEquipmentReminderTypeFromDays')) {
    function getWeeklyDigestEquipmentReminderTypeFromDays(int $daysRemaining): string
    {
        return $daysRemaining < 0 ? 'expired' : '60_day';
    }
}

if (!function_exists('getWeeklyDigestVesselEquipment')) {
    function getWeeklyDigestVesselEquipment(PDO $pdo): array
    {
        $sql = "
            SELECT
                e.eid,
                e.vessel_id,
                e.equipmentName,
                e.equipmentLocation,
                e.manufacturer,
                e.modelNumber,
                e.serialNumber,
                e.expDate,
                v.vesselName,
                fed.agent_type,
                fed.extinguisher_class,
                fed.ul_rating,
                fed.capacity_value,
                fed.capacity_unit,
                fed.last_annual_service_date,
                fed.next_annual_due,
                fed.next_internal_exam_due,
                fed.next_hydro_due
            FROM equipment e
            INNER JOIN vessels v
                ON v.vessel_id = e.vessel_id
            LEFT JOIN fire_extinguisher_details fed
                ON fed.eid = e.eid
            WHERE e.vessel_id IS NOT NULL
              AND e.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY v.vesselName ASC, e.expDate ASC, e.equipmentName ASC, e.eid ASC
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $included = [];

        foreach ($rows as $row) {
            $exp = parseEquipmentReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            if ($daysRemaining <= EQUIPMENT_REMINDER_DIGEST_WINDOW_DAYS) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = getWeeklyDigestEquipmentReminderTypeFromDays($daysRemaining);
                $row['display_name'] = buildEquipmentDisplayName($row);
                $included[] = $row;
            }
        }

        usort($included, function ($a, $b) {
            $vesselCmp = strcmp((string)$a['vesselName'], (string)$b['vesselName']);
            if ($vesselCmp !== 0) return $vesselCmp;

            $aExpired = (int)($a['days_remaining'] ?? 0) < 0;
            $bExpired = (int)($b['days_remaining'] ?? 0) < 0;
            if ($aExpired !== $bExpired) {
                return $aExpired ? -1 : 1;
            }

            $dateCmp = strcmp((string)$a['expDate'], (string)$b['expDate']);
            if ($dateCmp !== 0) return $dateCmp;

            $nameCmp = strcmp((string)$a['display_name'], (string)$b['display_name']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['eid'] <=> (int)$b['eid']);
        });

        return $included;
    }
}

if (!function_exists('equipmentReminderAlreadySent')) {
    function equipmentReminderAlreadySent(
        PDO $pdo,
        int $eid,
        string $reminderType,
        string $recipientType,
        string $recipientEmail,
        string $expirationSnapshot
    ): bool {
        $sql = "
            SELECT 1
            FROM equipment_reminder_log
            WHERE eid = ?
              AND reminder_type = ?
              AND recipient_type = ?
              AND recipient_email = ?
              AND expiration_snapshot = ?
              AND email_status = 'sent'
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eid,
            $reminderType,
            $recipientType,
            $recipientEmail,
            $expirationSnapshot
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('logEquipmentReminder')) {
    function logEquipmentReminder(PDO $pdo, array $data): void
    {
        $sql = "
            INSERT INTO equipment_reminder_log
            (
                eid,
                vessel_id,
                reminder_type,
                expiration_snapshot,
                recipient_type,
                recipient_email,
                email_subject,
                email_status,
                error_message,
                sent_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                email_subject = VALUES(email_subject),
                email_status = VALUES(email_status),
                error_message = VALUES(error_message),
                sent_at = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['eid'],
            $data['vessel_id'],
            $data['reminder_type'],
            $data['expiration_snapshot'],
            $data['recipient_type'],
            $data['recipient_email'],
            $data['email_subject'] ?? null,
            $data['email_status'] ?? 'sent',
            $data['error_message'] ?? null
        ]);
    }
}

if (!function_exists('getPendingReminderTypeFromDays')) {
    function getPendingReminderTypeFromDays(int $daysRemaining): ?string
    {
        if ($daysRemaining <= 60 && $daysRemaining >= 31) return '60_day';
        if ($daysRemaining <= 30 && $daysRemaining >= 16) return '30_day';
        if ($daysRemaining <= 15 && $daysRemaining >= 8)  return '15_day';
        if ($daysRemaining <= 7  && $daysRemaining >= 0)  return '7_day';
        if ($daysRemaining < 0   && $daysRemaining >= -7) return 'expired';
        return null;
    }
}
