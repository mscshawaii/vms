<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/digest_core.php';
require_once __DIR__ . '/lib/acl.php';

session_start();
if (empty($_SESSION['user_id'])) { http_response_code(403); echo "Please sign in."; exit; }

// ---- scope flags (must come BEFORE you build $optsBase) ----
$companyId = (int)($_SESSION['company_id'] ?? 0);
$isMSCS    = ($companyId === 1);

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
                // Ensure a vessel label is present for grouped view
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

// ---- optional notify config (safe default = empty array) ----
$config = [];
$configPath = __DIR__ . '/config_notify.php';
if (is_file($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) { $config = $loaded; }
}

/**
 * Render grouped-by-section as Bootstrap tables (like your screenshot).
 * Input is the output of merge_sections_grouped(...).
 */
function _pick(array $row, array $candidates, $fallback=null) {
    foreach ($candidates as $k) {
        if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return $fallback;
}

// NEW: compute status from a date and window
function _compute_status(?string $dateStr, int $daysWindow): ?string {
    if (!$dateStr) return null;
    try {
        $today = new DateTimeImmutable('today');
        $d     = new DateTimeImmutable($dateStr);
    } catch (Throwable $e) {
        return null;
    }
    $diffDays = (int)$today->diff($d)->format('%r%a'); // negative if past
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

    if ($days === null) {
        return '—';
    }

    if ($days < 0) {
        return 'Expired ' . abs($days) . 'd';
    }

    if ($days === 0) {
        return 'Today';
    }

    return $days . 'd';
}

// badge renderer (kept)
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
 * Render grouped-by-section as Bootstrap tables; auto-compute status when missing.
 */
function render_grouped_tables(array $sectionsGrouped, array $order, int $daysWindow): string {
    $labels = [
        'docs_vessel'          => 'Vessel Documents – Expired & Due Soon',
        'docs_equipment'       => 'Equipment – Expired & Due Soon',
        'crew_credentials'     => 'Crew Credentials – Expired & Due Soon',
        'icr_due'              => 'ICRs – Due Soon & Overdue / Never Performed',
        'car_due'              => 'Corrective Actions',
        'crew_drills'          => 'Drills',
        'upcoming_inspections' => 'Upcoming Inspections',
    ];

    $out = [];

    foreach ($order as $secId) {
        $rows = $sectionsGrouped[$secId] ?? [];
        if (!$rows) continue;

        $out[] = '<div class="mb-4">';
        $out[] = '<h3 class="h5">'.htmlspecialchars($labels[$secId] ?? $secId).'</h3>';

        switch ($secId) {
            case 'docs_equipment': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>Equipment</th><th>Due</th><th>Days</th><th>Status</th></tr></thead><tbody>';
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
                    . '<td>'._days_cell((string)$due).'</td>'
                    . '<td>'._status_badge($status).'</td>'
                    . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }

            case 'crew_credentials': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Crew</th><th>Vessel</th><th>Credential</th><th>Due</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $crew    = _pick($r, ['crew_name','crew','title'], '—');
                    $vessel  = _pick($r, ['vessel_label','vesselName'], '—');
                    $cred    = _pick($r, ['credential','docName','category'], '—');
                    $due     = _pick($r, ['expDate','due_date','issueDate'], '—');
                    $status  = _pick($r, ['status'], null) ?: _compute_status($due, $daysWindow);
                    $out[]   = '<tr>'
                            . '<td>'.htmlspecialchars((string)$crew).'</td>'
                            . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$cred).'</td>'
                            . '<td>'.htmlspecialchars((string)$due).'</td>'
                            . '<td>'._days_cell((string)$due).'</td>'
                            . '<td>'._status_badge($status).'</td>'
                            . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }

            case 'icr_due': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>ICR</th><th>Last</th><th>Next Due</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $icr    = _pick($r, ['icr_title','icr_name','title','category'], '—');
                    $step   = _pick($r, ['step_title','step','requirement']);
                    if ($step) $icr .= ' — '.$step;
                    $last   = _pick($r, ['last','last_done','last_date','issueDate'], '—');
                    $next   = _pick($r, ['due','due_date','next_due','expDate'], '—');
                    $status = _pick($r, ['status'], null) ?: _compute_status($next, $daysWindow);
                    $out[]  = '<tr>'
                            . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$icr).'</td>'
                            . '<td>'.htmlspecialchars((string)$last).'</td>'
                            . '<td>'.htmlspecialchars((string)$next).'</td>'
                            . '<td>'._days_cell((string)$next).'</td>'
                            . '<td>'._status_badge($status).'</td>'
                            . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }

            case 'docs_vessel': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>Document</th><th>Issue</th><th>Exp</th><th>Days</th><th>Status</th></tr></thead><tbody>';

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
                        . '<td>'._days_cell((string)$exp).'</td>'
                        . '<td>'._status_badge($status).'</td>'
                        . '</tr>';
                }

                $out[] = '</tbody></table></div>';
                break;
            }

            case 'upcoming_inspections': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>Inspection</th><th>Date / Window</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $kind   = _pick($r, ['inspection_type','title','category'], 'Inspection');
                    $date   = _pick($r, ['next_date','due','due_date','coiExp','coiIssue','nextScheduledInspection','nextDrydock','nextUnstep'], '—');
                    $win    = _pick($r, ['window','interval','notes'], null);
                    $status = _pick($r, ['status'], null) ?: _compute_status($date, $daysWindow);
                    $col3   = $date . ($win ? ' · '.$win : '');
                    $out[]  = '<tr>'
                            . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$kind).'</td>'
                            . '<td>'.htmlspecialchars((string)$col3).'</td>'
                            . '<td>'._days_cell((string)$date).'</td>'
                            . '<td>'._status_badge($status).'</td>'
                            . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }

            case 'car_due': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>CAR</th><th>Due</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $title  = _pick($r, ['title','car_title','finding','category'], '—');
                    $due    = _pick($r, ['due','due_date','created_at'], '—');
                    $status = _pick($r, ['status'], 'Open'); // CARs usually carry explicit status (Open/Closed)
                    $out[]  = '<tr>'
                            . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$title).'</td>'
                            . '<td>'.htmlspecialchars((string)$due).'</td>'
                            . '<td>'._days_cell((string)$due).'</td>'
                            . '<td>'._status_badge($status).'</td>'
                            . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }

            case 'crew_drills': {
                $out[] = '<div class="table-responsive"><table class="table table-sm align-middle">';
                $out[] = '<thead><tr><th>Vessel</th><th>Drill</th><th>Last</th><th>Next Due</th><th>Days</th><th>Status</th></tr></thead><tbody>';
                foreach ($rows as $r) {
                    $vessel = _pick($r, ['vessel_label','vesselName'], '—');
                    $drill  = _pick($r, ['drill','type','title','category'], '—');
                    $last   = _pick($r, ['last','last_done','drill_date'], '—');
                    $next   = _pick($r, ['due','due_date','next_due'], '—');
                    $status = _pick($r, ['status'], null) ?: _compute_status($next, $daysWindow);
                    $out[]  = '<tr>'
                            . '<td>'.htmlspecialchars((string)$vessel).'</td>'
                            . '<td>'.htmlspecialchars((string)$drill).'</td>'
                            . '<td>'.htmlspecialchars((string)$last).'</td>'
                            . '<td>'.htmlspecialchars((string)$next).'</td>'
                            . '<td>'._days_cell((string)$next).'</td>'
                            . '<td>'._status_badge($status).'</td>'
                            . '</tr>';
                }
                $out[] = '</tbody></table></div>';
                break;
            }
        }

        $out[] = '</div>';
    }

    if (!$out) {
        $out[] = '<div class="alert alert-info">No items matched your filters.</div>';
    }
    return implode("\n", $out);
}

// collect form inputs (IMPORTANT: parse before using)
$days     = isset($_POST['days']) ? (int)$_POST['days'] : 45;
$vesselId = isset($_POST['vessel']) && $_POST['vessel'] !== '' ? (int)$_POST['vessel'] : null;
$sections = isset($_POST['sections'])
    ? array_values(array_unique(array_map('strval', (array)$_POST['sections'])))
    : [];

// default sections if none selected
$sections = $sections ?: [
    'docs_vessel','docs_equipment','crew_credentials','icr_due','car_due','crew_drills','upcoming_inspections'
];

// base opts used for every vessel
$optsBase = [
  'days'       => $days,
  'sections'   => $sections,
  'is_mscs'    => $isMSCS,
  'company_id' => $companyId,
];

// Compute the final, *authorized* target list (honors MSCS vs non-MSCS and is_active=1)
$targetVesselIds = clamp_to_allowed_vessels($pdo, $vesselId);
if (empty($targetVesselIds)) {
    http_response_code(403);
    exit('Not allowed to access that vessel.');
}

if (count($targetVesselIds) === 1) {
    // Normal single-vessel flow (exactly as before)
    $opts = $optsBase + ['vessel' => $targetVesselIds[0]];
    $secs = build_digest_sections($pdo, $opts, $config);
    $html = render_digest_html($opts, $secs);
} else {
    // “All” → loop allowed vessels, collect raw sections, then render grouped by category
    $stmtName = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
    $allPerVesselSecs = [];

    foreach ($targetVesselIds as $vid) {
        $opts = $optsBase + ['vessel' => $vid];
        $secs = build_digest_sections($pdo, $opts, $config);

        // fetch vesselName for labeling
        $stmtName->execute([$vid]);
        $vname = (string)($stmtName->fetchColumn() ?: ('Vessel #'.$vid));

        // store bundle with vessel id/name for later merge
        $secs['_vessel_id']   = $vid;
        $secs['_vessel_name'] = $vname;
        $allPerVesselSecs[] = $secs;
    }

    // Merge by section and render
    $grouped = merge_sections_grouped($allPerVesselSecs);
    $html = render_grouped_tables($grouped, $sections, $days);
}

// show preview
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>VMS Report Preview</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0d6efd">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
  <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="/assets/css/vms-mobile.css" rel="stylesheet">

  <style>
    body { background-color: #f8f9fa; }

    .reports-shell {
      background: var(--vms-bg, #f4f7fb);
      min-height: 100vh;
    }

    .page-card {
      border-radius: 1rem;
      border: 0;
    }

    .sticky-actions {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: #f8f9fa;
      padding-bottom: 0.5rem;
    }

    .page-meta {
      color: #6b7280;
      margin: 0;
    }

    @media (max-width: 768px) {
      .btn-stack-mobile .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
<?php
$title = 'Report Preview';
$back_link = 'reports.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="reports-shell">
  <div class="app-page">
    <div class="app-container pb-5">

      <div class="sticky-actions mb-3">
        <div class="card shadow-sm page-card p-3">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
              <h1 class="h4 mb-1">Report Preview</h1>
              <p class="page-meta">Review the digest before sending.</p>
            </div>

            <div class="d-flex flex-column flex-md-row gap-2 w-100 w-md-auto btn-stack-mobile">
              <a href="reports.php" class="btn btn-outline-secondary">Back</a>

              <form method="post" action="reports_send.php" class="d-flex flex-column flex-md-row gap-2 w-100">
                <input type="hidden" name="days" value="<?= htmlspecialchars((string)$days) ?>">
                <input type="hidden" name="vessel" value="<?= htmlspecialchars((string)($vesselId ?? '')) ?>">
                <?php foreach ($sections as $s): ?>
                  <input type="hidden" name="sections[]" value="<?= htmlspecialchars($s) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="to" value="<?= htmlspecialchars($_POST['to'] ?? 'info@mschawaii.org') ?>">
                <input type="hidden" name="subject" value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                <button class="btn btn-success" type="submit">Send Report</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm page-card p-3 p-md-4">
        <?= $html ?>
      </div>

    </div>
  </div>
</div>

</body>
</html>