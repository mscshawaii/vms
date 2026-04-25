<?php

if (!defined('DOCUMENT_REMINDER_TRIGGER_DAYS')) {
    define('DOCUMENT_REMINDER_TRIGGER_DAYS', [60, 30, 15, 7]);
}

if (!defined('DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS')) {
    define('DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS', 7);
}

if (!defined('DOCUMENT_REMINDER_DIGEST_WINDOW_DAYS')) {
    define('DOCUMENT_REMINDER_DIGEST_WINDOW_DAYS', 60);
}

if (!function_exists('getReminderTypeFromDays')) {
    function getReminderTypeFromDays(int $daysRemaining): ?string
    {
        if ($daysRemaining === 60) return '60_day';
        if ($daysRemaining === 30) return '30_day';
        if ($daysRemaining === 15) return '15_day';
        if ($daysRemaining === 7)  return '7_day';
        if ($daysRemaining < 0)    return 'expired';
        return null;
    }
}

if (!function_exists('parseReminderExpDate')) {
    function parseReminderExpDate(?string $expDate): ?DateTimeImmutable
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

if (!function_exists('isExpiredReminderWithinWindow')) {
    function isExpiredReminderWithinWindow(int $daysRemaining): bool
    {
        return $daysRemaining < 0 && $daysRemaining >= -DOCUMENT_REMINDER_EXPIRED_WINDOW_DAYS;
    }
}

if (!function_exists('getTriggerVesselDocuments')) {
    function getTriggerVesselDocuments(PDO $pdo): array
    {
        $sql = "
            SELECT
                d.id,
                d.vessel_id,
                d.docType,
                d.category,
                d.docName,
                d.expDate,
                d.reminder_enabled,
                d.reminder_notes,
                v.vesselName
            FROM documents d
            INNER JOIN vessels v
                ON v.vessel_id = d.vessel_id
            WHERE d.related_to = 'vessel'
              AND d.vessel_id IS NOT NULL
              AND d.archived_at IS NULL
              AND d.reminder_enabled = 1
              AND d.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY v.vesselName ASC, d.docName ASC, d.id ASC
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $due = [];

        foreach ($rows as $row) {
            $exp = parseReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            $reminderType = getPendingReminderTypeFromDays($daysRemaining);

            if ($reminderType !== null) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = $reminderType;
                $due[] = $row;
            }
        }

        usort($due, function ($a, $b) {
            $vesselCmp = strcmp((string)$a['vesselName'], (string)$b['vesselName']);
            if ($vesselCmp !== 0) return $vesselCmp;

            $dateCmp = strcmp((string)$a['expDate'], (string)$b['expDate']);
            if ($dateCmp !== 0) return $dateCmp;

            $nameCmp = strcmp((string)$a['docName'], (string)$b['docName']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['id'] <=> (int)$b['id']);
        });

        return $due;
    }
}

if (!function_exists('getIncludedVesselDocuments')) {
    function getIncludedVesselDocuments(PDO $pdo, int $vesselId): array
    {
        $sql = "
            SELECT
                d.id,
                d.vessel_id,
                d.docType,
                d.category,
                d.docName,
                d.expDate,
                d.reminder_enabled,
                d.reminder_notes,
                v.vesselName
            FROM documents d
            INNER JOIN vessels v
                ON v.vessel_id = d.vessel_id
            WHERE d.related_to = 'vessel'
              AND d.vessel_id = ?
              AND d.archived_at IS NULL
              AND d.reminder_enabled = 1
              AND d.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY d.expDate ASC, d.docName ASC, d.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vesselId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $included = [];

        foreach ($rows as $row) {
            $exp = parseReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            // Include anything due within 60 days OR already expired, regardless of how old.
            if ($daysRemaining <= 60) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = getReminderTypeFromDays($daysRemaining) ?? ($daysRemaining < 0 ? 'expired' : 'included');
                $included[] = $row;
            }
        }

        usort($included, function ($a, $b) {
            $dateCmp = strcmp((string)$a['expDate'], (string)$b['expDate']);
            if ($dateCmp !== 0) return $dateCmp;

            $nameCmp = strcmp((string)$a['docName'], (string)$b['docName']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['id'] <=> (int)$b['id']);
        });

        return $included;
    }
}

if (!function_exists('getWeeklyDigestReminderTypeFromDays')) {
    function getWeeklyDigestReminderTypeFromDays(int $daysRemaining): string
    {
        return $daysRemaining < 0 ? 'expired' : '60_day';
    }
}

if (!function_exists('getWeeklyDigestVesselDocuments')) {
    function getWeeklyDigestVesselDocuments(PDO $pdo): array
    {
        $sql = "
            SELECT
                d.id,
                d.vessel_id,
                d.docType,
                d.category,
                d.docName,
                d.expDate,
                d.reminder_enabled,
                d.reminder_notes,
                v.vesselName
            FROM documents d
            INNER JOIN vessels v
                ON v.vessel_id = d.vessel_id
            WHERE d.related_to = 'vessel'
              AND d.vessel_id IS NOT NULL
              AND d.archived_at IS NULL
              AND d.reminder_enabled = 1
              AND d.expDate IS NOT NULL
              AND v.is_active = 1
              AND v.archived_at IS NULL
              AND v.is_deleted = 0
            ORDER BY v.vesselName ASC, d.expDate ASC, d.docName ASC, d.id ASC
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $today = new DateTimeImmutable('today');
        $included = [];

        foreach ($rows as $row) {
            $exp = parseReminderExpDate((string)($row['expDate'] ?? ''));
            if (!$exp) {
                continue;
            }

            $daysRemaining = (int)$today->diff($exp)->format('%r%a');

            if ($daysRemaining <= DOCUMENT_REMINDER_DIGEST_WINDOW_DAYS) {
                $row['days_remaining'] = $daysRemaining;
                $row['reminder_type'] = getWeeklyDigestReminderTypeFromDays($daysRemaining);
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

            $nameCmp = strcmp((string)$a['docName'], (string)$b['docName']);
            if ($nameCmp !== 0) return $nameCmp;

            return ((int)$a['id'] <=> (int)$b['id']);
        });

        return $included;
    }
}

if (!function_exists('reminderAlreadySent')) {
    function reminderAlreadySent(
        PDO $pdo,
        int $documentId,
        string $reminderType,
        string $recipientType,
        string $recipientEmail,
        string $expirationSnapshot
    ): bool {
        $sql = "
            SELECT 1
            FROM document_reminder_log
            WHERE document_id = ?
              AND reminder_type = ?
              AND recipient_type = ?
              AND recipient_email = ?
              AND expiration_snapshot = ?
              AND email_status = 'sent'
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $documentId,
            $reminderType,
            $recipientType,
            $recipientEmail,
            $expirationSnapshot
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('vesselReminderSentRecently')) {
    function vesselReminderSentRecently(
        PDO $pdo,
        int $vesselId,
        string $recipientType,
        string $recipientEmail,
        int $cooldownDays = 7
    ): bool {
        $sql = "
            SELECT 1
            FROM document_reminder_log
            WHERE vessel_id = ?
              AND recipient_type = ?
              AND recipient_email = ?
              AND email_status = 'sent'
              AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $vesselId,
            $recipientType,
            $recipientEmail,
            $cooldownDays
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('weeklyDigestAlreadySent')) {
    function weeklyDigestAlreadySent(
        PDO $pdo,
        int $vesselId,
        string $emailSubject,
        string $cycleStart,
        string $cycleEnd
    ): bool {
        $sql = "
            SELECT 1
            FROM (
                SELECT sent_at
                FROM document_reminder_log
                WHERE vessel_id = ?
                  AND recipient_type = 'grouped'
                  AND email_subject = ?
                  AND email_status = 'sent'

                UNION ALL

                SELECT sent_at
                FROM equipment_reminder_log
                WHERE vessel_id = ?
                  AND recipient_type = 'grouped'
                  AND email_subject = ?
                  AND email_status = 'sent'
            ) digest_log
            WHERE sent_at >= ?
              AND sent_at < ?
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $vesselId,
            $emailSubject,
            $vesselId,
            $emailSubject,
            $cycleStart,
            $cycleEnd
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('logDocumentReminder')) {
    function logDocumentReminder(PDO $pdo, array $data): void
    {
        $sql = "
            INSERT INTO document_reminder_log
            (
                document_id,
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
            $data['document_id'],
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

if (!function_exists('formatReminderStatusText')) {
    function formatReminderStatusText(int $daysRemaining): string
    {
        if ($daysRemaining < 0) {
            return 'Expired';
        }

        if ($daysRemaining === 0) {
            return 'Expires today';
        }

        return "Due in {$daysRemaining} day(s)";
    }
}

if (!function_exists('buildEquipmentPlainDetailLines')) {
    function buildEquipmentPlainDetailLines(array $item): array
    {
        $details = [];

        $serial = trim((string)($item['serialNumber'] ?? ''));
        if ($serial !== '') {
            $details[] = "• Serial: {$serial}";
        }

        $manufacturer = trim((string)($item['manufacturer'] ?? ''));
        $model = trim((string)($item['modelNumber'] ?? ''));
        if ($manufacturer !== '' || $model !== '') {
            $details[] = '• Make/Model: ' . trim($manufacturer . ' ' . $model);
        }

        $agentType = trim((string)($item['agent_type'] ?? ''));
        $extClass  = trim((string)($item['extinguisher_class'] ?? ''));
        $ulRating  = trim((string)($item['ul_rating'] ?? ''));

        $agentParts = array_values(array_filter([$agentType, $extClass, $ulRating], fn($v) => $v !== ''));
        if ($agentParts) {
            $details[] = '• Extinguisher Details: ' . implode(' | ', $agentParts);
        }

        $capacityValue = $item['capacity_value'] ?? null;
        $capacityUnit  = trim((string)($item['capacity_unit'] ?? ''));
        if ($capacityValue !== null && $capacityValue !== '') {
            $capacityText = rtrim(rtrim((string)$capacityValue, '0'), '.');
            if ($capacityText === '') {
                $capacityText = (string)$capacityValue;
            }
            $details[] = '• Capacity: ' . trim($capacityText . ' ' . $capacityUnit);
        }

        return $details;
    }
}

if (!function_exists('buildReminderSubject')) {
    function buildReminderSubject(string $recipientType, string $vesselName, array $documents, array $equipment = []): string
    {
        if ($recipientType === 'weekly_digest') {
            return "MSCS Weekly Vessel Compliance Digest - {$vesselName}";
        }

        $hasExpired = false;

        foreach ($documents as $doc) {
            if ((int)($doc['days_remaining'] ?? 9999) < 0) {
                $hasExpired = true;
                break;
            }
        }

        if (!$hasExpired) {
            foreach ($equipment as $item) {
                if ((int)($item['days_remaining'] ?? 9999) < 0) {
                    $hasExpired = true;
                    break;
                }
            }
        }

        if ($recipientType === 'owner') {
            return $hasExpired
                ? "MSCS Notice: Expired / Expiring Vessel Compliance Items - {$vesselName}"
                : "MSCS Notice: Vessel Compliance Reminder - {$vesselName}";
        }

        return "VMS Internal Alert - {$vesselName} Compliance Reminders";
    }
}

if (!function_exists('buildReminderBody')) {
    function buildReminderBody(string $recipientType, string $vesselName, int $vesselId, array $documents, array $equipment = []): string
    {
        $lines = [];

        $needsCOD   = false;
        $needsEPIRB = false;
        $needsFCC   = false;
        $isWeeklyDigest = ($recipientType === 'weekly_digest');

        $lines[] = "Aloha,";
        $lines[] = "";
        if ($isWeeklyDigest) {
            $lines[] = "This is your weekly vessel compliance digest for {$vesselName}. The items below are either expired or due within the next 60 days.";
        } else {
            $lines[] = "We are conducting a routine audit of vessel compliance items and noticed that the following documents and/or equipment for {$vesselName} are approaching expiration or are already expired.";
        }
        $lines[] = "";
        $lines[] = "It would be best to complete these renewals or servicing actions at your earliest convenience.";
        $lines[] = "";

        if (!empty($documents)) {
            $lines[] = "VESSEL DOCUMENTS";
            $lines[] = "----------------";

            foreach ($documents as $doc) {
                $docName = trim((string)($doc['docName'] ?? 'Document'));
                $docType = strtolower(trim((string)($doc['docType'] ?? '')));
                $expDate = trim((string)($doc['expDate'] ?? ''));
                $daysRemaining = (int)($doc['days_remaining'] ?? 0);

                $expFormatted = $expDate ? date("F j, Y", strtotime($expDate)) : "Unknown";

                $lines[] = $docName;
                $lines[] = "• Expires: {$expFormatted}";
                $lines[] = "• Status: " . formatReminderStatusText($daysRemaining);
                $lines[] = "";

                $combined = strtolower($docName . ' ' . $docType);

                if (strpos($combined, 'documentation') !== false || strpos($combined, 'cod') !== false) {
                    $needsCOD = true;
                }

                if (
                    strpos($combined, 'epirb') !== false ||
                    strpos($combined, 'eprib') !== false ||
                    strpos($combined, 'beacon registration') !== false ||
                    strpos($combined, 'beacon') !== false ||
                    strpos($combined, '406') !== false
                ) {
                    $needsEPIRB = true;
                }

                if (strpos($combined, 'fcc') !== false || strpos($combined, 'station license') !== false) {
                    $needsFCC = true;
                }
            }
        }

        if (!empty($equipment)) {
            $lines[] = "VESSEL EQUIPMENT";
            $lines[] = "----------------";

            foreach ($equipment as $item) {
                $name = trim((string)($item['display_name'] ?? $item['equipmentName'] ?? 'Equipment'));
                $expDate = trim((string)($item['expDate'] ?? ''));
                $daysRemaining = (int)($item['days_remaining'] ?? 0);

                $expFormatted = $expDate ? date("F j, Y", strtotime($expDate)) : "Unknown";

                $lines[] = $name;
                $lines[] = "• Expires: {$expFormatted}";
                $lines[] = "• Status: " . formatReminderStatusText($daysRemaining);

                foreach (buildEquipmentPlainDetailLines($item) as $detailLine) {
                    $lines[] = $detailLine;
                }

                $lines[] = "";
            }
        }

        if ($needsCOD || $needsEPIRB || $needsFCC) {
            $lines[] = "Helpful renewal links:";
            $lines[] = "";

            if ($needsCOD) {
                $lines[] = "Certificate of Documentation (USCG NVDC)";
                $lines[] = "https://www.nvdc-estorefront.uscg.mil/vds-estorefront/terms";
                $lines[] = "";
            }

            if ($needsEPIRB) {
                $lines[] = "EPIRB Registration (NOAA)";
                $lines[] = "https://beaconregistration.noaa.gov/RGDB/index?outcome=%2FRGDB%2FBeaconOwner%2Fmanagebeacons";
                $lines[] = "";
            }

            if ($needsFCC) {
                $lines[] = "FCC Ship Station License";
                $lines[] = "https://wireless2.fcc.gov/UlsEntry/licManager/login.jsp";
                $lines[] = "";
            }
        }

        $lines[] = "View vessel in VMS:";
        $lines[] = "https://vms.mschawaii.org/vessel_dashboard.php?vessel_id={$vesselId}";
        $lines[] = "";

        $lines[] = "If you would prefer, MSCS Hawaii would be happy to assist with processing these renewals or servicing items on your behalf for a nominal service fee.";
        $lines[] = "";
        $lines[] = "Please let me know if you would like any assistance.";
        $lines[] = "";

        $lines[] = "Best regards,";
        $lines[] = "";
        $lines[] = "Sean Keeman";
        $lines[] = "MSCS Hawaii";
        $lines[] = "Marine Safety Consulting & Surveying";
        $lines[] = "907-957-3161";
        $lines[] = "info@mschawaii.org";
        $lines[] = "www.mschawaii.org";

        return implode("\n", $lines);
    }
}

if (!function_exists('buildEquipmentHtmlDetailLines')) {
    function buildEquipmentHtmlDetailLines(array $item): string
    {
        $lines = [];

        $serial = trim((string)($item['serialNumber'] ?? ''));
        if ($serial !== '') {
            $lines[] = '<div style="font-size:12px;color:#555;">Serial: ' . htmlspecialchars($serial, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $manufacturer = trim((string)($item['manufacturer'] ?? ''));
        $model = trim((string)($item['modelNumber'] ?? ''));
        $makeModel = trim($manufacturer . ' ' . $model);
        if ($makeModel !== '') {
            $lines[] = '<div style="font-size:12px;color:#555;">Make / Model: ' . htmlspecialchars($makeModel, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $agentType = trim((string)($item['agent_type'] ?? ''));
        $extClass  = trim((string)($item['extinguisher_class'] ?? ''));
        $ulRating  = trim((string)($item['ul_rating'] ?? ''));
        $agentParts = array_values(array_filter([$agentType, $extClass, $ulRating], fn($v) => $v !== ''));
        if ($agentParts) {
            $lines[] = '<div style="font-size:12px;color:#555;">Extinguisher Details: ' . htmlspecialchars(implode(' | ', $agentParts), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $capacityValue = $item['capacity_value'] ?? null;
        $capacityUnit  = trim((string)($item['capacity_unit'] ?? ''));
        if ($capacityValue !== null && $capacityValue !== '') {
            $capacityText = rtrim(rtrim((string)$capacityValue, '0'), '.');
            if ($capacityText === '') {
                $capacityText = (string)$capacityValue;
            }
            $lines[] = '<div style="font-size:12px;color:#555;">Capacity: ' . htmlspecialchars(trim($capacityText . ' ' . $capacityUnit), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        return implode('', $lines);
    }
}

if (!function_exists('buildReminderHtmlBody')) {
    function buildReminderHtmlBody(string $recipientType, string $vesselName, int $vesselId, array $documents, array $equipment = []): string
    {
        $safeVesselName = htmlspecialchars($vesselName, ENT_QUOTES, 'UTF-8');
        $isWeeklyDigest = ($recipientType === 'weekly_digest');

        $needsCOD   = false;
        $needsEPIRB = false;
        $needsFCC   = false;

        $documentRowsHtml = '';
        foreach ($documents as $doc) {
            $docNameRaw = trim((string)($doc['docName'] ?? 'Document'));
            $docName = htmlspecialchars($docNameRaw, ENT_QUOTES, 'UTF-8');
            $docType = strtolower(trim((string)($doc['docType'] ?? '')));
            $expDate = trim((string)($doc['expDate'] ?? ''));
            $daysRemaining = (int)($doc['days_remaining'] ?? 0);

            $expFormatted = $expDate ? date("F j, Y", strtotime($expDate)) : "Unknown";
            $expFormattedSafe = htmlspecialchars($expFormatted, ENT_QUOTES, 'UTF-8');
            $status = formatReminderStatusText($daysRemaining);
            $statusSafe = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

            if ($daysRemaining < 0) {
                $statusColor = '#dc3545';
            } elseif ($daysRemaining === 0) {
                $statusColor = '#fd7e14';
            } else {
                $statusColor = '#b8860b';
            }

            $uploadLink = 'https://vms.mschawaii.org/add_document.php?vessel_id=' . (int)$vesselId
                . '&docType=' . rawurlencode(trim((string)($doc['docType'] ?? $doc['docName'] ?? '')));

            $documentRowsHtml .= '
            <tr>
                <td style="padding:10px;border:1px solid #ddd;">
                    ' . $docName . '<br>
                    <a href="' . $uploadLink . '" style="font-size:12px;">Upload renewal for this document</a>
                </td>
                <td style="padding:10px;border:1px solid #ddd;">' . $expFormattedSafe . '</td>
                <td style="padding:10px;border:1px solid #ddd;color:' . $statusColor . ';font-weight:bold;">' . $statusSafe . '</td>
            </tr>';

            $combined = strtolower($docNameRaw . ' ' . $docType);

            if (strpos($combined, 'documentation') !== false || strpos($combined, 'cod') !== false) {
                $needsCOD = true;
            }

            if (
                strpos($combined, 'epirb') !== false ||
                strpos($combined, 'eprib') !== false ||
                strpos($combined, 'beacon registration') !== false ||
                strpos($combined, 'beacon') !== false ||
                strpos($combined, '406') !== false
            ) {
                $needsEPIRB = true;
            }

            if (strpos($combined, 'fcc') !== false || strpos($combined, 'station license') !== false) {
                $needsFCC = true;
            }
        }

        $equipmentRowsHtml = '';
        foreach ($equipment as $item) {
            $name = trim((string)($item['display_name'] ?? $item['equipmentName'] ?? 'Equipment'));
            $nameSafe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $expDate = trim((string)($item['expDate'] ?? ''));
            $daysRemaining = (int)($item['days_remaining'] ?? 0);

            $expFormatted = $expDate ? date("F j, Y", strtotime($expDate)) : "Unknown";
            $expFormattedSafe = htmlspecialchars($expFormatted, ENT_QUOTES, 'UTF-8');
            $status = formatReminderStatusText($daysRemaining);
            $statusSafe = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');

            if ($daysRemaining < 0) {
                $statusColor = '#dc3545';
            } elseif ($daysRemaining === 0) {
                $statusColor = '#fd7e14';
            } else {
                $statusColor = '#b8860b';
            }

            $equipmentRowsHtml .= '
            <tr>
                <td style="padding:10px;border:1px solid #ddd;">
                    ' . $nameSafe . '
                    ' . buildEquipmentHtmlDetailLines($item) . '
                </td>
                <td style="padding:10px;border:1px solid #ddd;">' . $expFormattedSafe . '</td>
                <td style="padding:10px;border:1px solid #ddd;color:' . $statusColor . ';font-weight:bold;">' . $statusSafe . '</td>
            </tr>';
        }

        $linksHtml = '';
        if ($needsCOD || $needsEPIRB || $needsFCC) {
            $linksHtml .= '<h3 style="margin:24px 0 8px 0;font-size:16px;">Helpful renewal links</h3><ul style="padding-left:20px;">';

            if ($needsCOD) {
                $linksHtml .= '<li><a href="https://www.nvdc-estorefront.uscg.mil/vds-estorefront/terms">Certificate of Documentation (USCG NVDC)</a></li>';
            }

            if ($needsEPIRB) {
                $linksHtml .= '<li><a href="https://beaconregistration.noaa.gov/RGDB/index?outcome=%2FRGDB%2FBeaconOwner%2Fmanagebeacons">EPIRB Registration (NOAA)</a></li>';
            }

            if ($needsFCC) {
                $linksHtml .= '<li><a href="https://wireless2.fcc.gov/UlsEntry/licManager/login.jsp">FCC Ship Station License</a></li>';
            }

            $linksHtml .= '</ul>';
        }

        $vmsLink = 'https://vms.mschawaii.org/vessel_dashboard.php?vessel_id=' . (int)$vesselId;
        $addDocumentLink = 'https://vms.mschawaii.org/add_document.php?vessel_id=' . (int)$vesselId;

        $documentsSectionHtml = '';
        if ($documentRowsHtml !== '') {
            $documentsSectionHtml = '
            <h3 style="margin:24px 0 10px 0;font-size:16px;">Vessel Documents</h3>
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:14px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Document</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Expiration</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $documentRowsHtml . '
                </tbody>
            </table>';
        }

        $equipmentSectionHtml = '';
        if ($equipmentRowsHtml !== '') {
            $equipmentSectionHtml = '
            <h3 style="margin:24px 0 10px 0;font-size:16px;">Vessel Equipment</h3>
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:14px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Equipment</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Expiration</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $equipmentRowsHtml . '
                </tbody>
            </table>';
        }

        $pageTitle = $isWeeklyDigest ? 'Weekly Vessel Compliance Digest' : 'Vessel Compliance Reminder';
        $headerTitle = $isWeeklyDigest
            ? 'MSCS Hawaii — Weekly Vessel Compliance Digest'
            : 'MSCS Hawaii — Vessel Compliance Reminder';
        $introHtml = $isWeeklyDigest
            ? '<p>This is an automated weekly compliance digest from the MSCS Hawaii Vessel Management System (VMS).</p>

            <p>The items below for <strong>' . $safeVesselName . '</strong> are either expired or due within the next 60 days.</p>'
            : '<p>This is an automated reminder from the MSCS Hawaii Vessel Management System (VMS).</p>

            <p>
                Our records indicate that the following documents and/or equipment for
                <strong>' . $safeVesselName . '</strong>
                are approaching expiration or may already be expired.
            </p>';

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $pageTitle . '</title>
</head>

<body style="margin:0;padding:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#222;">
<div style="max-width:760px;margin:0 auto;padding:20px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">

        <div style="background:#0b3d5c;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:bold;">
            ' . $headerTitle . '
        </div>

        <div style="padding:24px;">
            <p style="margin-top:0;">Aloha,</p>

            ' . $introHtml . '

            <p>Please review the items below and complete any necessary renewals or servicing actions at your earliest convenience.</p>

            ' . $documentsSectionHtml . '
            ' . $equipmentSectionHtml . '
            ' . $linksHtml . '

            <h3 style="margin:26px 0 10px 0;font-size:16px;">VMS Portal</h3>

            <p style="margin:0 0 12px 0;">
                <a href="' . $vmsLink . '"
                   style="display:inline-block;background:#0d6efd;color:#ffffff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:bold;margin-right:10px;">
                    View this vessel in VMS
                </a>

                <a href="' . $addDocumentLink . '"
                   style="display:inline-block;background:#198754;color:#ffffff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:bold;">
                    Upload renewed document
                </a>
            </p>

            <p style="font-size:13px;color:#555;margin-top:8px;">
                Use the VMS portal to review this vessel and upload renewed documents as needed.
            </p>

            <p style="margin-top:24px;">
                If you have already renewed or serviced any of the items listed above, please upload updated documentation to VMS
                or send us a copy so we can update our records.
            </p>

            <p>
                If you would prefer, MSCS Hawaii can also assist with processing these renewals or servicing items on your
                behalf for a nominal service fee.
            </p>

            <p>Please let me know if you would like assistance.</p>

            <div style="margin-top:28px;font-size:14px;line-height:1.5;">
                <strong>Sean Keeman</strong><br>
                MSCS Hawaii<br>
                Marine Safety Consulting & Surveying<br>
                907-957-3161<br>
                <a href="mailto:info@mschawaii.org">info@mschawaii.org</a><br>
                <a href="https://www.mschawaii.org">www.mschawaii.org</a>
            </div>

            <div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.5;">
                This message was automatically generated by the MSCS Hawaii Vessel Management System (VMS).
                You are receiving this notification because you are listed as a vessel owner or contact
                associated with <strong>' . $safeVesselName . '</strong>.
                If you believe you received this message in error, please contact
                <a href="mailto:info@mschawaii.org">info@mschawaii.org</a>.
            </div>

        </div>
    </div>
</div>
</body>
</html>';
    }
}
