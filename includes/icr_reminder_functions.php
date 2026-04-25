<?php
declare(strict_types=1);

if (!function_exists('parseIcrReminderDate')) {
    function parseIcrReminderDate(?string $date): ?DateTimeImmutable
    {
        $date = trim((string)$date);

        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$dt ||
            ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        ) {
            return null;
        }

        return $dt;
    }
}

if (!function_exists('calculateIcrNextDueDate')) {
    function calculateIcrNextDueDate(string $anchorDate, string $frequency): ?string
    {
        $dt = parseIcrReminderDate($anchorDate);
        if (!$dt) {
            return null;
        }

        return match ($frequency) {
            'Weekly'    => $dt->modify('+7 days')->format('Y-m-d'),
            'Monthly'   => $dt->modify('+1 month')->format('Y-m-d'),
            'Quarterly' => $dt->modify('+3 months')->format('Y-m-d'),
            'Annually'  => $dt->modify('+1 year')->format('Y-m-d'),
            default     => null,
        };
    }
}

if (!function_exists('formatIcrDateForEmail')) {
    function formatIcrDateForEmail(?string $date): string
    {
        $dt = parseIcrReminderDate($date);
        return $dt ? $dt->format('F j, Y') : '—';
    }
}

if (!function_exists('getDueVesselICRs')) {
    function getDueVesselICRs(PDO $pdo, ?DateTimeImmutable $asOf = null): array
    {
        $asOf = $asOf ?: new DateTimeImmutable('today');
        $monthStart = $asOf->modify('first day of this month');
        $monthEnd = $asOf->modify('last day of this month');
        $upcomingEnd = $monthEnd->modify('+30 days');

        $sql = "
            SELECT
                vi.vessel_icr_id,
                vi.vessel_id,
                vi.icr_id,
                vi.icr_number,
                vi.title,
                vi.frequency,
                vi.drill_type,
                DATE(vi.created_at) AS assigned_date,
                v.vesselName,
                MAX(CASE WHEN r.save_state = 'final' THEN r.run_date END) AS last_final_run_date,
                MAX(CASE WHEN r.save_state = 'draft' THEN r.run_id END) AS latest_draft_run_id
            FROM vessel_icrs vi
            INNER JOIN vessels v
                ON v.vessel_id = vi.vessel_id
            LEFT JOIN vessel_icr_runs r
                ON r.vessel_icr_id = vi.vessel_icr_id
            WHERE vi.is_removed = 0
                AND vi.frequency IN ('Monthly', 'Quarterly', 'Annually')
                AND (vi.drill_type IS NULL OR vi.drill_type = '')
                AND (
                        vi.icr_number IS NULL
                        OR UPPER(TRIM(vi.icr_number)) NOT LIKE 'K%'
                    )
                AND v.is_active = 1
                AND v.archived_at IS NULL
                AND v.is_deleted = 0
            GROUP BY
                vi.vessel_icr_id,
                vi.vessel_id,
                vi.icr_id,
                vi.icr_number,
                vi.title,
                vi.frequency,
                vi.drill_type,
                DATE(vi.created_at),
                v.vesselName
            ORDER BY
                v.vesselName,
                vi.icr_number,
                vi.vessel_icr_id
        ";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];

        foreach ($rows as $row) {
            $lastFinalRun = trim((string)($row['last_final_run_date'] ?? ''));
            $assignedDate = trim((string)($row['assigned_date'] ?? ''));
            $frequency = trim((string)($row['frequency'] ?? ''));

            $anchorDate = $lastFinalRun !== '' ? $lastFinalRun : $assignedDate;
            if ($anchorDate === '') {
                continue;
            }

            $nextDueDate = calculateIcrNextDueDate($anchorDate, $frequency);
            if (!$nextDueDate) {
                continue;
            }

            $nextDue = parseIcrReminderDate($nextDueDate);
            if (!$nextDue) {
                continue;
            }

            $bucket = null;

            if ($nextDue < $monthStart) {
                $bucket = 'overdue';
            } elseif ($nextDue >= $monthStart && $nextDue <= $monthEnd) {
                $bucket = 'due_this_month';
            } elseif ($nextDue > $monthEnd && $nextDue <= $upcomingEnd) {
                $bucket = 'upcoming';
            } else {
                continue;
            }

            $vesselId = (int)$row['vessel_id'];

            if (!isset($grouped[$vesselId])) {
                $grouped[$vesselId] = [
                    'vessel_id' => $vesselId,
                    'vessel_name' => (string)$row['vesselName'],
                    'overdue' => [],
                    'due_this_month' => [],
                    'upcoming' => [],
                ];
            }

            $daysOverdue = 0;

            if ($bucket === 'overdue') {
                $daysOverdue = (int)$nextDue->diff($asOf)->format('%a');
            }

            $grouped[$vesselId][$bucket][] = [
                'vessel_icr_id'       => (int)$row['vessel_icr_id'],
                'icr_id'              => (int)$row['icr_id'],
                'icr_number'          => (string)($row['icr_number'] ?? ''),
                'title'               => (string)($row['title'] ?? ''),
                'frequency'           => $frequency,
                'assigned_date'       => $assignedDate,
                'last_final_run_date' => $lastFinalRun !== '' ? $lastFinalRun : null,
                'anchor_date'         => $anchorDate,
                'next_due_date'       => $nextDueDate,
                'days_overdue'        => $daysOverdue,
                'has_draft'           => !empty($row['latest_draft_run_id']),
            ];
        }

        foreach ($grouped as &$vessel) {
            foreach (['overdue', 'due_this_month', 'upcoming'] as $bucket) {
                usort($vessel[$bucket], static function ($a, $b) {
                    $dateCmp = strcmp((string)$a['next_due_date'], (string)$b['next_due_date']);
                    if ($dateCmp !== 0) return $dateCmp;

                    $numCmp = strcmp((string)$a['icr_number'], (string)$b['icr_number']);
                    if ($numCmp !== 0) return $numCmp;

                    return ((int)$a['vessel_icr_id'] <=> (int)$b['vessel_icr_id']);
                });
            }
        }
        unset($vessel);

        return $grouped;
    }
}

if (!function_exists('getVesselDrillSummary')) {
    function getVesselDrillSummary(PDO $pdo, int $vesselId): array
    {
        // Step 1 — get linked vessels (same logic as drills_tab)
        $linkedIds = [$vesselId];

        $groupStmt = $pdo->prepare("SELECT group_id FROM linked_vessels WHERE vessel_id = ?");
        $groupStmt->execute([$vesselId]);
        $groupId = $groupStmt->fetchColumn();

        if ($groupId) {
            $vesselsStmt = $pdo->prepare("SELECT vessel_id FROM linked_vessels WHERE group_id = ?");
            $vesselsStmt->execute([$groupId]);
            $linkedIds = $vesselsStmt->fetchAll(PDO::FETCH_COLUMN);
            $linkedIds[] = $vesselId;
            $linkedIds = array_values(array_unique(array_map('intval', $linkedIds)));
        }

        // Step 2 — get crew
        $crewStmt = $pdo->prepare("
            SELECT DISTINCT
                u.id,
                u.fName,
                u.lName,
                vc.role
            FROM vessel_crew vc
            INNER JOIN users u ON u.id = vc.crew_id
            WHERE vc.vessel_id = ?
              AND vc.is_active = 1
              AND u.is_active = 1
              AND vc.counts_for_drills = 1
              AND vc.role IN ('Master', 'Deckhand')
            ORDER BY
                FIELD(vc.role, 'Master', 'Deckhand'),
                u.lName,
                u.fName
        ");
        $crewStmt->execute([$vesselId]);
        $crew = $crewStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($crew)) {
            return [];
        }

        $drillTypes = ['Fire', 'Man Overboard', 'Abandon Ship'];

        $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));

        $drillStmt = $pdo->prepare("
            SELECT MAX(drill_date)
            FROM crew_drills
            WHERE crew_user_id = ?
              AND drill_type = ?
              AND vessel_id IN ($placeholders)
        ");

        $results = [];

        foreach ($crew as $member) {
            $row = [
                'name' => trim(($member['fName'] ?? '') . ' ' . ($member['lName'] ?? '')),
                'role' => $member['role'] ?? '',
                'drills' => []
            ];

            foreach ($drillTypes as $type) {
                $params = array_merge([(int)$member['id'], $type], $linkedIds);
                $drillStmt->execute($params);
                $last = $drillStmt->fetchColumn();

                $status = 'none';

                if ($last) {
                    $daysAgo = (new DateTime())->diff(new DateTime($last))->days;

                    if ($daysAgo <= 60) {
                        $status = 'current';
                    } elseif ($daysAgo <= 90) {
                        $status = 'due_soon';
                    } else {
                        $status = 'overdue';
                    }
                }

                $row['drills'][$type] = [
                    'date' => $last ?: null,
                    'status' => $status
                ];
            }

            $results[] = $row;
        }

        return $results;
    }
}

if (!function_exists('formatDrillStatusText')) {
    function formatDrillStatusText(?string $date, string $status): string
    {
        return match ($status) {
            'current'  => $date ? 'Current (' . formatIcrDateForEmail($date) . ')' : 'Current',
            'due_soon' => $date ? 'Due Soon (' . formatIcrDateForEmail($date) . ')' : 'Due Soon',
            'overdue'  => $date ? 'Overdue (' . formatIcrDateForEmail($date) . ')' : 'Overdue',
            default    => 'None recorded',
        };
    }
}

if (!function_exists('buildDrillSummaryPlainSection')) {
    function buildDrillSummaryPlainSection(array $drillRows): array
    {
        $lines = [];
        $lines[] = 'CREW DRILL STATUS (QUARTERLY REQUIREMENT)';
        $lines[] = '-----------------------------------------';
        $lines[] = 'Drill participation is tracked by crew member assignment, not strictly by vessel alone.';
        $lines[] = '';

        if (empty($drillRows)) {
            $lines[] = 'No active Master or Deckhand assignments found for drill tracking.';
            $lines[] = '';
            return $lines;
        }

        foreach ($drillRows as $row) {
            $crewLabel = trim(($row['name'] ?? '') . (!empty($row['role']) ? ' (' . $row['role'] . ')' : ''));
            $lines[] = $crewLabel;

            foreach (['Fire', 'Man Overboard', 'Abandon Ship'] as $type) {
                $cell = $row['drills'][$type] ?? ['date' => null, 'status' => 'none'];
                $lines[] = '• ' . $type . ': ' . formatDrillStatusText($cell['date'] ?? null, (string)($cell['status'] ?? 'none'));
            }

            $lines[] = '';
        }

        return $lines;
    }
}

if (!function_exists('buildIcrReminderSubject')) {
    function buildIcrReminderSubject(string $vesselName, ?DateTimeImmutable $asOf = null): string
    {
        $asOf = $asOf ?: new DateTimeImmutable('today');
        return sprintf(
            '%s ICRs Due / Overdue - %s',
            $vesselName,
            $asOf->format('F Y')
        );
    }
}

if (!function_exists('buildIcrReminderPlainSection')) {
    function buildIcrReminderPlainSection(string $heading, array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $lines = [];
        $lines[] = strtoupper($heading);
        $lines[] = str_repeat('-', strlen($heading));

        foreach ($items as $item) {
            $lines[] = trim(($item['icr_number'] ?? '') . ' - ' . ($item['title'] ?? ''));
            $lines[] = '• Frequency: ' . ($item['frequency'] ?? '—');
            $lines[] = '• Last Finalized: ' . (($item['last_final_run_date'] ?? null) ? formatIcrDateForEmail($item['last_final_run_date']) : 'Never finalized');
            $lines[] = '• Next Due: ' . formatIcrDateForEmail($item['next_due_date'] ?? null);

            if (!empty($item['days_overdue'])) {
                $lines[] = '• Status: ' . (int)$item['days_overdue'] . ' day(s) overdue';
            }

            if (!empty($item['has_draft'])) {
                $lines[] = '• Note: Draft exists';
            }

            $lines[] = '';
        }

        return $lines;
    }
}

if (!function_exists('buildIcrReminderBody')) {
    function buildIcrReminderBody(
        string $vesselName,
        int $vesselId,
        array $overdue,
        array $dueThisMonth,
        array $upcoming = [],
        array $drillSummary = []
    ): string {
        $lines = [];
        $lines[] = 'Aloha,';
        $lines[] = '';
        $lines[] = 'This is an automated reminder from the MSCS Hawaii Vessel Management System (VMS).';
        $lines[] = '';
        $lines[] = "Our records indicate that the following assigned Inspection Criteria Records (ICRs) for {$vesselName} are overdue, due this month, or coming due soon.";
        $lines[] = 'Only finalized ICR runs are treated as completed. Draft ICRs remain pending until finalized.';
        $lines[] = '';
        $lines[] = 'Summary';
        $lines[] = '-------';
        $lines[] = '• Overdue: ' . count($overdue);
        $lines[] = '• Due This Month: ' . count($dueThisMonth);
        $lines[] = '• Upcoming: ' . count($upcoming);
        $lines[] = '';

        $lines = array_merge($lines, buildIcrReminderPlainSection('Overdue', $overdue));
        $lines = array_merge($lines, buildIcrReminderPlainSection('Due This Month', $dueThisMonth));
        $lines = array_merge($lines, buildIcrReminderPlainSection('Upcoming', $upcoming));
        $lines = array_merge($lines, buildDrillSummaryPlainSection($drillSummary));

        $lines[] = 'View vessel in VMS:';
        $lines[] = 'https://vms.mschawaii.org/vessel_dashboard.php?vessel_id=' . $vesselId;
        $lines[] = '';
        $lines[] = 'Mahalo,';
        $lines[] = '';
        $lines[] = 'Sean Keeman';
        $lines[] = 'MSCS Hawaii';
        $lines[] = 'Marine Safety Consulting & Surveying';
        $lines[] = '907-957-3161';
        $lines[] = 'info@mschawaii.org';
        $lines[] = 'www.mschawaii.org';

        return implode("\n", $lines);
    }
}

if (!function_exists('buildIcrReminderHtmlTableRows')) {
    function buildIcrReminderHtmlTableRows(array $items, bool $showOverdue = false): string
    {
        $rows = '';

        foreach ($items as $item) {
            $status = '&mdash;';
            if ($showOverdue && !empty($item['days_overdue'])) {
                $status = (int)$item['days_overdue'] . ' day(s) overdue';
            } elseif (!empty($item['has_draft'])) {
                $status = 'Draft exists';
            }

            $note = !empty($item['has_draft']) ? '<div style="font-size:12px;color:#856404;">Draft exists</div>' : '';

            $rows .= '
                <tr>
                    <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars((string)$item['icr_number'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td style="padding:10px;border:1px solid #ddd;">
                        ' . htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') . '
                        ' . $note . '
                    </td>
                    <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars((string)$item['frequency'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars(formatIcrDateForEmail($item['last_final_run_date'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>
                    <td style="padding:10px;border:1px solid #ddd;">' . htmlspecialchars(formatIcrDateForEmail($item['next_due_date'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>
                    <td style="padding:10px;border:1px solid #ddd;">' . $status . '</td>
                </tr>';
        }

        return $rows;
    }
}

if (!function_exists('buildIcrReminderHtmlSection')) {
    function buildIcrReminderHtmlSection(string $heading, array $items, bool $showOverdue = false): string
    {
        if (empty($items)) {
            return '';
        }

        return '
            <h3 style="margin:24px 0 10px 0;font-size:16px;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h3>
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:14px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th align="left" style="padding:10px;border:1px solid #ddd;">ICR</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Title</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Frequency</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Last Finalized</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Next Due</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ' . buildIcrReminderHtmlTableRows($items, $showOverdue) . '
                </tbody>
            </table>';
    }
}

if (!function_exists('getDrillStatusLabel')) {
    function getDrillStatusLabel(string $status, ?string $date): string
    {
        return match ($status) {
            'current'  => $date ? 'Current - ' . formatIcrDateForEmail($date) : 'Current',
            'due_soon' => $date ? 'Due Soon - ' . formatIcrDateForEmail($date) : 'Due Soon',
            'overdue'  => $date ? 'Overdue - ' . formatIcrDateForEmail($date) : 'Overdue',
            default    => 'None recorded',
        };
    }
}

if (!function_exists('getDrillStatusColor')) {
    function getDrillStatusColor(string $status): string
    {
        return match ($status) {
            'current'  => '#198754',
            'due_soon' => '#b8860b',
            'overdue'  => '#dc3545',
            default    => '#6c757d',
        };
    }
}

if (!function_exists('buildDrillSummaryHtmlSection')) {
    function buildDrillSummaryHtmlSection(array $drillRows): string
    {
        $intro = '
            <h3 style="margin:24px 0 10px 0;font-size:16px;">Crew Drill Status (Quarterly Requirement)</h3>
            <p style="margin:0 0 12px 0;">
                Drill participation is tracked by crew member assignment, not strictly by vessel alone.
                The table below reflects the latest recorded Fire, Man Overboard, and Abandon Ship drills
                for active operational crew associated with this vessel.
            </p>';

        if (empty($drillRows)) {
            return $intro . '
                <div style="padding:10px;border:1px solid #ddd;background:#f8f9fa;border-radius:6px;">
                    No active Master or Deckhand assignments found for drill tracking.
                </div>';
        }

        $rows = '';

        foreach ($drillRows as $row) {
            $crewLabel = htmlspecialchars(
                trim(($row['name'] ?? '') . (!empty($row['role']) ? ' (' . $row['role'] . ')' : '')),
                ENT_QUOTES,
                'UTF-8'
            );

            $cells = '';
            foreach (['Fire', 'Man Overboard', 'Abandon Ship'] as $type) {
                $cell = $row['drills'][$type] ?? ['date' => null, 'status' => 'none'];
                $status = (string)($cell['status'] ?? 'none');
                $date = $cell['date'] ?? null;

                $label = htmlspecialchars(getDrillStatusLabel($status, $date), ENT_QUOTES, 'UTF-8');
                $color = getDrillStatusColor($status);

                $cells .= '<td style="padding:10px;border:1px solid #ddd;color:' . $color . ';font-weight:bold;">' . $label . '</td>';
            }

            $rows .= '<tr>
                <td style="padding:10px;border:1px solid #ddd;">' . $crewLabel . '</td>
                ' . $cells . '
            </tr>';
        }

        return $intro . '
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:14px;">
                <thead>
                    <tr style="background:#f3f4f6;">
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Crew Member</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Fire</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Man Overboard</th>
                        <th align="left" style="padding:10px;border:1px solid #ddd;">Abandon Ship</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $rows . '
                </tbody>
            </table>';
    }
}

if (!function_exists('buildIcrReminderHtmlBody')) {
    function buildIcrReminderHtmlBody(
        string $vesselName,
        int $vesselId,
        array $overdue,
        array $dueThisMonth,
        array $upcoming = [],
        array $drillSummary = []
    ): string {
        $safeVesselName = htmlspecialchars($vesselName, ENT_QUOTES, 'UTF-8');
        $vmsLink = 'https://vms.mschawaii.org/vessel_dashboard.php?vessel_id=' . $vesselId;

        return '
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ICR Reminder</title>
</head>
<body style="margin:0;padding:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#222;">
<div style="max-width:760px;margin:0 auto;padding:20px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <div style="background:#0b3d5c;color:#ffffff;padding:16px 20px;font-size:18px;font-weight:bold;">
            MSCS Hawaii — Monthly ICR Reminder
        </div>

        <div style="padding:24px;">
            <p style="margin-top:0;">Aloha,</p>

            <p>This is an automated reminder from the MSCS Hawaii Vessel Management System (VMS).</p>

            <p>
                Our records indicate that the following assigned Inspection Criteria Records (ICRs) for
                <strong>' . $safeVesselName . '</strong>
                are overdue, due this month, or coming due soon.
            </p>

            <p>Only finalized ICR runs are treated as completed. Draft ICRs remain pending until finalized.</p>

            <div style="margin:20px 0;padding:12px 14px;background:#f8f9fa;border:1px solid #ddd;border-radius:8px;">
                <strong>Summary</strong><br>
                Overdue: ' . count($overdue) . '<br>
                Due This Month: ' . count($dueThisMonth) . '<br>
                Upcoming: ' . count($upcoming) . '
            </div>

            ' . buildIcrReminderHtmlSection('Overdue', $overdue, true) . '
            ' . buildIcrReminderHtmlSection('Due This Month', $dueThisMonth, false) . '
            ' . buildIcrReminderHtmlSection('Upcoming', $upcoming, false) . '
            ' . buildDrillSummaryHtmlSection($drillSummary) . '

            <h3 style="margin:26px 0 10px 0;font-size:16px;">VMS Portal</h3>

            <p style="margin:0 0 12px 0;">
                <a href="' . $vmsLink . '"
                   style="display:inline-block;background:#0d6efd;color:#ffffff;padding:12px 18px;border-radius:6px;text-decoration:none;font-weight:bold;">
                    View this vessel in VMS
                </a>
            </p>

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
            </div>
        </div>
    </div>
</div>
</body>
</html>';
    }
}

if (!function_exists('icrMonthlyReminderAlreadySent')) {
    function icrMonthlyReminderAlreadySent(
        PDO $pdo,
        int $vesselId,
        string $summaryMonth,
        string $recipientType,
        string $recipientEmail
    ): bool {
        $sql = "
            SELECT 1
            FROM icr_reminder_log
            WHERE vessel_id = ?
              AND summary_month = ?
              AND recipient_type = ?
              AND recipient_email = ?
              AND email_status = 'sent'
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $vesselId,
            $summaryMonth,
            $recipientType,
            $recipientEmail,
        ]);

        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('logIcrReminder')) {
    function logIcrReminder(PDO $pdo, array $data): void
    {
        $sql = "
            INSERT INTO icr_reminder_log
            (
                vessel_id,
                summary_month,
                recipient_type,
                recipient_email,
                email_subject,
                overdue_count,
                due_count,
                upcoming_count,
                email_status,
                error_message,
                sent_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                email_subject = VALUES(email_subject),
                overdue_count = VALUES(overdue_count),
                due_count = VALUES(due_count),
                upcoming_count = VALUES(upcoming_count),
                email_status = VALUES(email_status),
                error_message = VALUES(error_message),
                sent_at = NOW()
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['vessel_id'],
            $data['summary_month'],
            $data['recipient_type'],
            $data['recipient_email'],
            $data['email_subject'] ?? null,
            $data['overdue_count'] ?? 0,
            $data['due_count'] ?? 0,
            $data['upcoming_count'] ?? 0,
            $data['email_status'] ?? 'sent',
            $data['error_message'] ?? null,
        ]);
    }
}