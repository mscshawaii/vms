<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/digest_core.php';
require_once __DIR__ . '/lib/acl.php';
require_once __DIR__ . '/lib/sendgrid_api_mail.php';

// ---- auth guard ----
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(403); echo "Please sign in."; exit; }

// ---- scope ----
$userId    = (int)($_SESSION['user_id'] ?? 0);
$companyId = (int)($_SESSION['company_id'] ?? 0);
$isMSCS    = ($companyId === 1);

// ---- optional notify config ----
$config = [];
$configPath = __DIR__ . '/config_notify.php';
if (is_file($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) { $config = $loaded; }
}

// ---- collect inputs ----
$days     = isset($_POST['days']) ? (int)$_POST['days'] : 45;
$vesselId = isset($_POST['vessel']) && $_POST['vessel'] !== '' ? (int)$_POST['vessel'] : null;
$sections = isset($_POST['sections'])
    ? array_values(array_unique(array_map('strval', (array)$_POST['sections'])))
    : [];

$sections = $sections ?: [
    'docs_vessel','docs_equipment','crew_credentials','icr_due','car_due','crew_drills','upcoming_inspections'
];

$recipients = trim((string)($_POST['to'] ?? ''));
$subject    = trim((string)($_POST['subject'] ?? 'VMS Digest'));
if ($recipients === '') { http_response_code(400); echo "No recipients."; exit; }

// ---- helpers (same logic as preview) ----
function _pick(array $row, array $candidates, $fallback=null) {
    foreach ($candidates as $k) {
        if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return $fallback;
}

function _compute_status(?string $dateStr, int $daysWindow): ?string {
    if (!$dateStr) return null;
    try {
        $today = new DateTimeImmutable('today');
        $d     = new DateTimeImmutable($dateStr);
    } catch (Throwable $e) {
        return null;
    }
    $diffDays = (int)$today->diff($d)->format('%r%a');
    if ($diffDays < 0)  return 'Expired';
    if ($diffDays <= $daysWindow) return 'Due soon';
    return 'OK';
}

function _days_until(?string $dateStr): ?int {
    if (!$dateStr || $dateStr === '—') return null;

    try {
        $today = new DateTimeImmutable('today');
        $d     = new DateTimeImmutable($dateStr);
    } catch (Throwable $e) {
        return null;
    }

    return (int)$today->diff($d)->format('%r%a');
}

function _days_cell(?string $dateStr): string {
    $days = _days_until($dateStr);

    if ($days === null) return '—';
    if ($days < 0)      return 'Expired ' . abs($days) . 'd';
    if ($days === 0)    return 'Today';
    return $days . 'd';
}

function _inspection_days_cell(?string $dateStr): string {
    $dateStr = trim((string)$dateStr);
    if ($dateStr === '') return '—';

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/', $dateStr, $m)) {
        [, $start, $end] = $m;

        if ($end < $today) {
            $days = _days_until($end);
            return $days === null ? '—' : 'Expired ' . abs($days) . 'd';
        }

        if ($start <= $today && $today <= $end) {
            return 'Window open';
        }

        return _days_cell($start);
    }

    return _days_cell($dateStr);
}

function _status_badge(?string $s): string {
    $s = strtolower(trim((string)$s));
    $map = [
        'expired'   => 'danger',
        'due soon'  => 'warning',
        'overdue'   => 'danger',
        'ok'        => 'success',
        'complete'  => 'success',
        'scheduled' => 'info',
        'pending'   => 'secondary',
        'open'      => 'warning',
        'closed'    => 'success',
    ];
    $color = $map[$s] ?? 'secondary';
    return '<span class="badge bg-'.$color.'">'.htmlspecialchars($s ?: '—').'</span>';
}

/**
 * Input: list of per-vessel $secs arrays from build_digest_sections()
 *        plus _vessel_id/_vessel_name marker.
 * Output: ['sec_id' => [rows...], ...]
 */
function merge_sections_grouped(array $allPerVesselSecs): array {
    $merged = [];
    foreach ($allPerVesselSecs as $bundle) {
        $vid   = $bundle['_vessel_id']   ?? null;
        $vname = $bundle['_vessel_name'] ?? null;
        foreach ($bundle as $entry) {
            if (!is_array($entry) || !isset($entry['id'], $entry['rows'])) continue;
            $secId = (string)$entry['id'];
            if (!isset($merged[$secId])) $merged[$secId] = [];
            foreach ($entry['rows'] as $r) {
                if (!is_array($r)) continue;
                if (empty($r['vessel_label'])) {
                    $r['vessel_label'] = $r['vesselName'] ?? $vname ?? '—';
                }
                if (empty($r['vessel_id']) && $vid !== null) {
                    $r['vessel_id'] = $vid;
                }
                $merged[$secId][] = $r;
            }
        }
    }
    return $merged;
}

/**
 * Render grouped-by-section as simple tables with minimal inline CSS for email.
 */
function render_grouped_tables_email(array $sectionsGrouped, array $order, int $daysWindow, int $days): string {
    $labels = [
        'docs_vessel'          => 'Vessel Documents – Expired & Due Soon',
        'docs_equipment'       => 'Equipment – Expired & Due Soon',
        'crew_credentials'     => 'Crew Credentials – Expired & Due Soon',
        'icr_due'              => 'ICRs – Due Soon & Overdue / Never Performed',
        'car_due'              => 'Corrective Actions',
        'crew_drills'          => 'Drills',
        'upcoming_inspections' => 'Upcoming Inspections',
    ];

    // basic email-safe styles (no external CSS)
    $css = '<style>
      .tbl{width:100%;border-collapse:collapse;margin:8px 0}
      .tbl th,.tbl td{border:1px solid #ddd;padding:6px;font-size:13px}
      .tbl th{background:#f5f5f5;text-align:left}
      .badge{display:inline-block;padding:2px 6px;border-radius:8px;font-size:11px;color:#fff}
      .bg-danger{background:#dc3545}.bg-warning{background:#ffc107;color:#000}
      .bg-success{background:#28a745}.bg-info{background:#17a2b8}
      .bg-secondary{background:#6c757d}
      h3{margin:12px 0 6px 0;font-size:16px}
      small{color:#666}
    </style>';

    $out = [];
    $out[] = $css;
    $out[] = '<div><small>Window: items due within '.$days.' days.</small></div>';

    foreach ($order as $secId) {
        $rows = $sectionsGrouped[$secId] ?? [];
        if (!$rows) continue;

        $out[] = '<h3>'.htmlspecialchars($labels[$secId] ?? $secId).'</h3>';

        switch ($secId) {
            case 'docs_equipment': {
                $out[] = '<table class="tbl"><thead><tr>
                    <th>Vessel</th><th>Equipment</th><th>Due</th><th>Days</th><th>Status</th>
                </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel  = _pick($r, ['vessel_label','vesselName'], '—');
                    $name = _pick($r, ['equipment_label','equipmentName','title','type','category'], '—');
                    $model   = _pick($r, ['model']);
                    $serial  = _pick($r, ['serial_number','serial']);
                    $equip   = trim($name . ($model ? ' · '.$model : '') . ($serial ? ' · #'.$serial : ''));
                    $due     = _pick($r, ['expDate','due_date','service_due','next_due','date'], '—');
                    $status  = _pick($r, ['status'], null) ?: _compute_status($due, $daysWindow);
                    $out[] = '<tr>'
                    . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                    . '<td>'.htmlspecialchars($equip ?: '—').'</td>'
                    . '<td>'.htmlspecialchars((string)$due).'</td>'
                    . '<td>'.htmlspecialchars(_days_cell((string)$due)).'</td>'
                    . '<td>'._status_badge($status).'</td>'
                    . '</tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }

            case 'crew_credentials': {
                    $out[] = '<table class="tbl"><thead><tr>
                        <th>Crew</th><th>Vessel</th><th>Credential</th><th>Due</th><th>Days</th><th>Status</th>
                    </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $crew    = _pick($r, ['crew_name','crew','title'], '—');
                    $vessel  = _pick($r, ['vessel_label','vesselName'], '—');
                    $cred    = _pick($r, ['credential','docName','category'], '—');
                    $due     = _pick($r, ['expDate','due_date','issueDate'], '—');
                    $status  = _pick($r, ['status'], null) ?: _compute_status($due, $daysWindow);
                    $out[]   = '<tr><td>'.htmlspecialchars((string)$crew).'</td>'
                             . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                             . '<td>'.htmlspecialchars((string)$cred).'</td>'
                             . '<td>'.htmlspecialchars((string)$due).'</td>'
                             . '<td>'.htmlspecialchars(_days_cell((string)$due)).'</td>'
                             . '<td>'._status_badge($status).'</td></tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }

            case 'icr_due': {
                $out[] = '<table class="tbl"><thead><tr>
                    <th>Vessel</th><th>ICR</th><th>Last</th><th>Next Due</th><th>Days</th><th>Status</th>
                </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $icr    = _pick($r, ['icr_title','icr_name','title','category'], '—');
                    $step   = _pick($r, ['step_title','step','requirement']);
                    if ($step) $icr .= ' — '.$step;
                    $last   = _pick($r, ['last','last_done','last_date','issueDate'], '—');
                    $next   = _pick($r, ['due','due_date','next_due','expDate'], '—');
                    $status = _pick($r, ['status'], null) ?: _compute_status($next, $daysWindow);
                    $out[]  = '<tr><td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$icr).'</td>'
                            . '<td>'.htmlspecialchars((string)$last).'</td>'
                            . '<td>'.htmlspecialchars((string)$next).'</td>'
                            . '<td>'.htmlspecialchars((string)$next).'</td>'
                            . '<td>'.htmlspecialchars(_days_cell((string)$next)).'</td>'
                            . '<td>'._status_badge($status).'</td></tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }

            case 'docs_vessel': {
                $out[] = '<table class="tbl"><thead><tr>
                                    </tr></thead><tbody>';
                $out[] = '<thead>
                            <tr style="background-color:#f2f2f2;">
                            <th align="left">Vessel</th>
                            <th align="left">Document</th>
                            <th align="left">Issue</th>
                            <th align="left">Exp</th>
                            <th align="left">Days</th>
                            <th align="left">Status</th>
                            </tr>
                        </thead><tbody>';

                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $doc    = _pick($r, ['docName','title'], '—');
                    $issue  = _pick($r, ['issueDate','issue_date'], '—');
                    $exp    = _pick($r, ['expDate','exp_date','due_date'], '—');
                    $status = _pick($r, ['status'], null) ?: _compute_status($exp, $daysWindow);

                    $out[] = '<tr>'
                        . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                        . '<td>'.htmlspecialchars((string)$doc).'</td>'
                        . '<td>'.htmlspecialchars((string)$issue).'</td>'
                        . '<td>'.htmlspecialchars((string)$exp).'</td>'
                        . '<td>'.htmlspecialchars(_days_cell((string)$exp)).'</td>'
                        . '<td>'.strip_tags(_status_badge($status)).'</td>'
                        . '</tr>';
                }

                $out[] = '</tbody></table></div>';
                break;
            }

            case 'upcoming_inspections': {
                $out[] = '<table class="tbl"><thead><tr>
                    <th>Vessel</th><th>Inspection</th><th>Date / Window</th><th>Days</th><th>Status</th>
                </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $kind   = _pick($r, ['inspection_type','title','category'], 'Inspection');
                    $date   = _pick($r, ['next_date','due','due_date','coiExp','coiIssue','nextScheduledInspection','nextDrydock','nextUnstep'], '—');
                    $win    = _pick($r, ['window','interval','notes'], null);
                    $status = _pick($r, ['status'], null) ?: _compute_status($date, $daysWindow);
                    $col3   = $date . ($win ? ' · '.$win : '');
                    $out[]  = '<tr><td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$kind).'</td>'
                            . '<td>'.htmlspecialchars((string)$col3).'</td>'
                            . '<td>'.htmlspecialchars((string)$col3).'</td>'
                            . '<td>'.htmlspecialchars(_inspection_days_cell((string)$date)).'</td>'
                            . '<td>'._status_badge($status).'</td></tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }

            case 'car_due': {
                $out[] = '<table class="tbl"><thead><tr>
                    <th>Vessel</th><th>CAR</th><th>Due</th><th>Days</th><th>Status</th>
                </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $title  = _pick($r, ['title','car_title','finding','category'], '—');
                    $due    = _pick($r, ['due','due_date','created_at'], '—');
                    $status = _pick($r, ['status'], 'Open');
                    $out[]  = '<tr><td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$title).'</td>'
                            . '<td>'.htmlspecialchars((string)$due).'</td>'
                            . '<td>'.htmlspecialchars(_days_cell((string)$due)).'</td>'
                            . '<td>'._status_badge($status).'</td></tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }

            case 'crew_drills': {
                $out[] = '<table class="tbl"><thead><tr>
                    <th>Vessel</th><th>Drill</th><th>Last</th><th>Next Due</th><th>Days</th><th>Status</th>
                </tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $drill  = _pick($r, ['drill','type','title','category'], '—');
                    $last   = _pick($r, ['last','last_done','drill_date'], '—');
                    $next   = _pick($r, ['due','due_date','next_due'], '—');
                    $status = _pick($r, ['status'], null) ?: _compute_status($next, $daysWindow);
                    $out[]  = '<tr><td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$drill).'</td>'
                            . '<td>'.htmlspecialchars((string)$last).'</td>'
                            . '<td>'.htmlspecialchars((string)$next).'</td>'
                            . '<td>'.htmlspecialchars(_days_cell((string)$next)).'</td>'
                            . '<td>'._status_badge($status).'</td></tr>';
                }
                $out[] = '</tbody></table>';
                break;
            }
        }
    }

    if (count($out) === 2) { // only CSS + header added
        $out[] = '<div>No items matched your filters.</div>';
    }
    return implode("\n", $out);
}

// ---- build body (respect ACL) ----
$optsBase = [
  'days'       => $days,
  'sections'   => $sections,
  'is_mscs'    => $isMSCS,
  'company_id' => $companyId,
];

$targetVesselIds = clamp_to_allowed_vessels($pdo, $vesselId);
if (empty($targetVesselIds)) {
    http_response_code(403);
    exit('Not allowed to access that vessel.');
}

// Collect per-vessel sections
$stmtName = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
$allPerVesselSecs = [];
foreach ($targetVesselIds as $vid) {
    $opts = $optsBase + ['vessel' => $vid];
    $secs = build_digest_sections($pdo, $opts, $config);

    $stmtName->execute([$vid]);
    $vname = (string)($stmtName->fetchColumn() ?: ('Vessel #'.$vid));

    $secs['_vessel_id']   = $vid;
    $secs['_vessel_name'] = $vname;
    $allPerVesselSecs[] = $secs;
}

$grouped = merge_sections_grouped($allPerVesselSecs);
$bodyHtml = render_grouped_tables_email($grouped, $sections, $days, $days);

$subj = ($subject !== '' ? $subject : 'VMS Digest');

$styles = '';
if (preg_match('/<style\b[^>]*>.*?<\/style>/is', $bodyHtml, $m)) {
    $styles = $m[0];
    $bodyHtml = str_replace($m[0], '', $bodyHtml);
}

$bodyHtmlWrapped = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$subj}</title>
  {$styles}
</head>
<body style="margin:0;padding:0;background:#f6f7f9;">
  <div style="max-width:900px;margin:0 auto;padding:18px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
      {$bodyHtml}
    </div>
    <div style="color:#6b7280;font-size:12px;margin-top:10px;">
      You're receiving this because you enabled VMS notifications.
    </div>
  </div>
</body>
</html>
HTML;


// Plaintext fallback
$bodyText = "VMS Digest\nWindow: items due within {$days} days.\n\n(HTML tables not shown in plaintext.)";


// ---- send email via SendGrid Web API (replaces PHPMailer SMTP) ----
$sendResult = ['ok' => false, 'error' => null];

try {
    $subj = ($subject !== '' ? $subject : 'VMS Digest');

    // Make sure we actually have a body
    if (trim($bodyHtml) === '') {
        throw new RuntimeException('Report body was empty (no $bodyHtml generated).');
    }

    $errors = [];
    foreach (preg_split('/\s*,\s*/', $recipients, -1, PREG_SPLIT_NO_EMPTY) as $addr) {
        $r = send_via_sendgrid_api($addr, $subj, $bodyHtmlWrapped, 'info@mschawaii.org', 'MSCS Hawaii VMS');
       
        if (!$r['ok']) {
            $errors[] = $addr . ': ' . $r['error'];
        }
    }

    if (empty($errors)) {
        $sendResult['ok'] = true;
    } else {
        $sendResult['ok']    = false;
        $sendResult['error'] = implode(' | ', $errors);
    }

} catch (Throwable $e) {
    $sendResult['ok']    = false;
    $sendResult['error'] = $e->getMessage();
}

// after $sendResult is computed...

$_SESSION['flash'] = [
  'type' => $sendResult['ok'] ? 'success' : 'danger',
  'msg'  => $sendResult['ok']
            ? 'Digest sent successfully.'
            : ('Send failed: ' . ($sendResult['error'] ?? 'Unknown error')),
];

// redirect back to the page they came from (preferred), else fallback:
$back = $_SERVER['HTTP_REFERER'] ?? 'reports.php';
header("Location: $back");
exit;

