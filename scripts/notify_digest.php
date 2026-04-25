<?php
declare(strict_types=1);

// Run from either CLI or web; behave nicely for both.
$IS_CLI = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/notify.php';

date_default_timezone_set('Pacific/Honolulu');

// --- Load optional config array ---
$config = [];
$configPath = __DIR__ . '/../config_notify.php';
if (file_exists($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) $config = $loaded;
}

// Defaults
$defaultDays = (int)($config['thresholds']['doc_expiring_days'] ?? 45);
$defaultTo   = (string)($config['email']['admin_bcc'] ?? 'info@mschawaii.org');
$defaultName = (string)($config['email']['from_name'] ?? 'MSCS Hawaii – VMS');

// CLI flags
$argvOpts = [
    'days'     => $defaultDays,
    'company'  => null,
    'vessel'   => null,
    'dryRun'   => false,
    'saveHtml' => false,
    'to'       => $defaultTo,
    'name'     => $defaultName,
];

if ($IS_CLI && isset($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run')                        $argvOpts['dryRun'] = true;
        elseif ($arg === '--save-html')                  $argvOpts['saveHtml'] = true;
        elseif (preg_match('/^--days=(\d+)$/', $arg, $m))    $argvOpts['days'] = (int)$m[1];
        elseif (preg_match('/^--company=(\d+)$/', $arg, $m)) $argvOpts['company'] = (int)$m[1];
        elseif (preg_match('/^--vessel=(\d+)$/',  $arg, $m)) $argvOpts['vessel']  = (int)$m[1];
        elseif (preg_match('/^--to=(.+)$/', $arg, $m))       $argvOpts['to']   = trim($m[1]);
        elseif (preg_match('/^--name=(.+)$/', $arg, $m))     $argvOpts['name'] = trim($m[1]);
    }
}

$daysAhead  = max(0, (int)$argvOpts['days']);
$companyId  = $argvOpts['company'];
$vesselId   = $argvOpts['vessel'];
$dryRun     = (bool)$argvOpts['dryRun'];
$saveHtml   = (bool)$argvOpts['saveHtml'];
$toEmail    = $argvOpts['to'];
$toName     = $argvOpts['name'];

// Window
$tz     = new DateTimeZone('Pacific/Honolulu');
$today  = (new DateTime('today', $tz))->format('Y-m-d');
$cutoff = (new DateTime('today', $tz))->modify("+{$daysAhead} days")->format('Y-m-d');

// Helper: optional filters
function scopeSql(array &$params, ?int $companyId, ?int $vesselId): array {
    $companyFilter = '';
    $vesselFilter  = '';
    if (!is_null($companyId)) { $companyFilter = ' AND v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $vesselFilter  = ' AND v.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
    return [$companyFilter, $vesselFilter];
}

// SECTION 1 — Vessel Documents (expired + due soon)
function fetch_docs_vessel(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $sql = <<<SQL
    SELECT COALESCE(v.vesselName, CONCAT('Vessel #', d.vessel_id)) AS vessel_label,
           d.docName, d.expDate
    FROM documents d
    LEFT JOIN vessels v ON v.vessel_id = d.vessel_id
    WHERE d.archived_at IS NULL
      AND d.related_to = 'vessel'
      AND d.vessel_id IS NOT NULL
      AND d.expDate IS NOT NULL
      AND d.expDate < :cutoff
      -- {companyFilter}
      -- {vesselFilter}
    ORDER BY d.expDate ASC
SQL;
    $params = [':cutoff'=>$cutoff];
    [$c,$v] = scopeSql($params, $companyId, $vesselId);
    $sql = str_replace(['-- {companyFilter}','-- {vesselFilter}'], [$c,$v], $sql);
    $st = $pdo->prepare($sql); $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// SECTION 2 — Equipment (expired + due soon)
function fetch_docs_equipment(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // Pull expiring/expired equipment from equipment.expDate (not documents)
    $sql = <<<SQL
    SELECT
      COALESCE(v.vesselName, CONCAT('Vessel #', e.vessel_id))  AS vessel_label,
      e.equipmentName                                         AS equipment_label,
      e.equipmentLocation                                     AS eq_location,
      e.expDate                                               AS expDate
    FROM equipment e
    LEFT JOIN vessels v ON v.vessel_id = e.vessel_id
    WHERE e.expDate IS NOT NULL
      AND e.expDate < :cutoff
      -- {companyFilter}
      -- {vesselFilter}
    ORDER BY e.expDate ASC
SQL;

    $params = [':cutoff' => $cutoff];
    $companyFilter = '';
    $vesselFilter  = '';
    if (!is_null($companyId)) { $companyFilter = ' AND v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $vesselFilter  = ' AND e.vessel_id  = :vid'; $params[':vid'] = $vesselId; }

    $sql = str_replace(['-- {companyFilter}','-- {vesselFilter}'], [$companyFilter,$vesselFilter], $sql);

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


// SECTION 3 — Crew Certifications (documents on crew)
function fetch_crew_credentials(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // Scope filters against users.company_cid and users.vessel_id
    $filters = [];
    $params  = [':cutoff' => $cutoff];

    if (!is_null($companyId)) { $filters[] = 'u.company_cid = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'u.vessel_id   = :vid'; $params[':vid'] = $vesselId; }

    $whereScope = $filters ? (' AND ' . implode(' AND ', $filters)) : '';

    $sql = <<<SQL
    SELECT CONCAT(u.fName,' ',u.lName) AS crew_name, v.vesselName AS vessel_label, 'MMC' AS credential, u.mmc AS due
    FROM users u
    LEFT JOIN vessels v ON v.vessel_id = u.vessel_id
    WHERE u.mmc IS NOT NULL AND u.mmc < :cutoff {$whereScope}

    UNION ALL
    SELECT CONCAT(u.fName,' ',u.lName), v.vesselName, 'First Aid/CPR' AS credential, u.fa AS due
    FROM users u
    LEFT JOIN vessels v ON v.vessel_id = u.vessel_id
    WHERE u.fa IS NOT NULL AND u.fa < :cutoff {$whereScope}

    UNION ALL
    SELECT CONCAT(u.fName,' ',u.lName), v.vesselName, 'MROP' AS credential, u.mrop AS due
    FROM users u
    LEFT JOIN vessels v ON v.vessel_id = u.vessel_id
    WHERE u.mrop IS NOT NULL AND u.mrop < :cutoff {$whereScope}

    UNION ALL
    SELECT CONCAT(u.fName,' ',u.lName), v.vesselName, 'MMC Medical' AS credential, u.mmc_medical AS due
    FROM users u
    LEFT JOIN vessels v ON v.vessel_id = u.vessel_id
    WHERE u.mmc_medical IS NOT NULL AND u.mmc_medical < :cutoff {$whereScope}

    ORDER BY due ASC
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// SECTION 4 — ICRs due soon / overdue
function fetch_icrs(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // read optional config
    $icrDefaultDays = 365;
    $icrIntervals = [];
    if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
        $icrDefaultDays = (int)($GLOBALS['config']['thresholds']['icr_default_days'] ?? 365);
        $icrIntervals   = (array)($GLOBALS['config']['icr_intervals'] ?? []);
    }

    $params  = [];
    $filters = [];
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'vi.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    // Latest run per (vessel, icr)
    $sql = <<<SQL
    WITH last_runs AS (
      SELECT r.vessel_id, r.icr_id, MAX(r.run_date) AS last_run
      FROM vessel_icr_runs r
      GROUP BY r.vessel_id, r.icr_id
    )
    SELECT
      vi.vessel_id,
      v.vesselName AS vessel_label,
      vi.icr_id,
      i.icr_number,
      i.title AS icr_title,
      lr.last_run
    FROM vessel_icrs vi
    JOIN vessels v  ON v.vessel_id  = vi.vessel_id
    JOIN icrs i     ON i.icr_id     = vi.icr_id
    LEFT JOIN last_runs lr ON lr.vessel_id = vi.vessel_id AND lr.icr_id = vi.icr_id
    {$where}
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $tz = new DateTimeZone('Pacific/Honolulu');
    $out = [];

    foreach ($rows as $r) {
        $label = $r['icr_number']
            ? ($r['icr_number'].' – '.$r['icr_title'])
            : ($r['icr_title'] ?: ('ICR #'.(int)$r['icr_id']));

        $last = $r['last_run'] ?? null;
        if ($last && $last !== '0000-00-00') {
            // cadence: override by number or title; else default
            $interval = $icrDefaultDays;
            if (!empty($r['icr_number']) && isset($icrIntervals[$r['icr_number']])) {
                $interval = (int)$icrIntervals[$r['icr_number']];
            } elseif (!empty($r['icr_title']) && isset($icrIntervals[$r['icr_title']])) {
                $interval = (int)$icrIntervals[$r['icr_title']];
            }

            $nextDue = (new DateTime($last, $tz))->modify("+{$interval} days")->format('Y-m-d');
            if ($nextDue < $cutoff) {
                $out[] = [
                    'vessel_label' => $r['vessel_label'] ?? ('Vessel #'.(int)$r['vessel_id']),
                    'icr_title'    => $label,
                    'last_run'     => $last,
                    'due_date'     => $nextDue,
                    'never'        => 0,
                ];
            }
        } else {
            // Never performed: include (no due date)
            $out[] = [
                'vessel_label' => $r['vessel_label'] ?? ('Vessel #'.(int)$r['vessel_id']),
                'icr_title'    => $label,
                'last_run'     => null,
                'due_date'     => null,
                'never'        => 1,
            ];
        }
    }

    // Order: never-performed last, otherwise by due_date ascending
    usort($out, function ($a, $b) {
        if (($a['never'] ?? 0) !== ($b['never'] ?? 0)) {
            return ($a['never'] ?? 0) <=> ($b['never'] ?? 0); // 0 before 1 → due items first
        }
        return strcmp((string)$a['due_date'], (string)$b['due_date']);
    });

    return $out;
}


// SECTION 5 — CARs (Corrective Actions) from tasks table
function fetch_cars(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // Open CARs only:
    // - NOT completed/closed (status not in ('complete','closed') and completed_date IS NULL)
    // - due_date < :cutoff  (due soon / overdue)
    // - OR (no due_date AND created in last 7 days) → show brand-new items
    $params = [':cutoff' => $cutoff];

    $filters = [];
    if (!is_null($vesselId))  { $filters[] = 't.vessel_id = :vid'; $params[':vid'] = $vesselId; }
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    $whereScope = $filters ? (' AND ' . implode(' AND ', $filters)) : '';

    $sql = <<<SQL
    SELECT
      COALESCE(v.vesselName, CONCAT('Vessel #', t.vessel_id)) AS vessel_label,
      t.title,
      t.status,
      t.due_date,
      t.created_at
    FROM tasks t
    LEFT JOIN vessels v ON v.vessel_id = t.vessel_id
    WHERE
      (t.status IS NULL OR t.status NOT IN ('complete','closed'))
      AND t.completed_date IS NULL
      AND (
            (t.due_date IS NOT NULL AND t.due_date < :cutoff)
         OR (t.due_date IS NULL AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))
      )
      {$whereScope}
    ORDER BY COALESCE(t.due_date, t.created_at) ASC
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// SECTION 6 — Drills due soon / overdue
function fetch_drills(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // Defaults: how often each drill is due (days)
    // Tweak these if your policy differs, e.g. 'Abandon Ship' => 90
    $INTERVAL_BY_TYPE = [
        'Fire'           => 30,
        'Man Overboard'  => 30,
        'Abandon Ship'   => 30,
    ];

    $params  = [];
    $filters = [];
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'cd.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    // Get last completed date per (vessel, crew, drill_type)
    $sql = <<<SQL
    SELECT
      cd.vessel_id,
      v.vesselName                                        AS vessel_label,
      cd.crew_user_id,
      CONCAT(u.fName, ' ', u.lName)                       AS crew_name,
      cd.drill_type,
      MAX(cd.drill_date)                                  AS last_date
    FROM crew_drills cd
    LEFT JOIN vessels v ON v.vessel_id = cd.vessel_id
    LEFT JOIN users   u ON u.id        = cd.crew_user_id
    {$where}
    GROUP BY cd.vessel_id, v.vesselName, cd.crew_user_id, u.fName, u.lName, cd.drill_type
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Compute next due, keep only items with next_due < cutoff
    $out = [];
    foreach ($rows as $r) {
        $last = $r['last_date'] ?? null;
        if (!$last) continue;

        $type = $r['drill_type'];
        $days = $INTERVAL_BY_TYPE[$type] ?? 30;

        $nextDue = (new DateTime($last, new DateTimeZone('Pacific/Honolulu')))
            ->modify("+{$days} days")
            ->format('Y-m-d');

        if ($nextDue < $cutoff) {
            $out[] = [
                'vessel_label' => $r['vessel_label'] ?? ('Vessel #' . (int)$r['vessel_id']),
                'crew_name'    => $r['crew_name'] ?? ('User #' . (int)$r['crew_user_id']),
                'drill'        => $type,
                'last'         => $last,
                'due'          => $nextDue,
            ];
        }
    }

    usort($out, fn($a,$b) => strcmp($a['due'], $b['due']));
    return $out;
}

// SECTION 7 — Upcoming inspections from vessels
function fetch_inspections(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    // Pull vessels + latest COI (issue/exp) in one query
    $params = [];
    $filters = [];
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'v.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    $sql = <<<SQL
    SELECT
      v.vessel_id,
      v.vesselName,
      v.lastInspection,
      v.nextScheduledInspection,
      v.nextDrydock,
      v.nextUnstep,
      coi.issueDate AS coiIssue,
      coi.expDate   AS coiExp
    FROM vessels v
    LEFT JOIN (
      SELECT d1.vessel_id, d1.issueDate, d1.expDate
      FROM documents d1
      INNER JOIN (
        SELECT vessel_id, MAX(expDate) AS maxExp
        FROM documents
        WHERE docType = 'Certificate of Inspection'
        GROUP BY vessel_id
      ) dmax
      ON dmax.vessel_id = d1.vessel_id AND dmax.maxExp = d1.expDate
      WHERE d1.docType = 'Certificate of Inspection'
    ) coi ON coi.vessel_id = v.vessel_id
    {$where}
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    $today = (new DateTime('today', new DateTimeZone('Pacific/Honolulu')))->format('Y-m-d');

    foreach ($rows as $r) {
        $vessel = $r['vesselName'] ?? ('Vessel #' . (int)$r['vessel_id']);

        // 1) Next Scheduled Inspection (manual date users set)
        $nextSched = $r['nextScheduledInspection'] ?? null;
        if (!empty($nextSched) && $nextSched !== '0000-00-00') {
            if ($nextSched < $cutoff) {
                $out[] = [
                    'vessel_label' => $vessel,
                    'item'         => 'Next Scheduled Inspection',
                    'due'          => $nextSched,
                ];
            }
        }

        // 2) Drydock / Unstep Mast (already working for you; keep them)
        $nextDrydock = $r['nextDrydock'] ?? null;
        if (!empty($nextDrydock) && $nextDrydock !== '0000-00-00' && $nextDrydock < $cutoff) {
            $out[] = ['vessel_label' => $vessel, 'item' => 'Drydock', 'due' => $nextDrydock];
        }

        $nextUnstep = $r['nextUnstep'] ?? null;
        if (!empty($nextUnstep) && $nextUnstep !== '0000-00-00' && $nextUnstep < $cutoff) {
            $out[] = ['vessel_label' => $vessel, 'item' => 'Unstep Mast', 'due' => $nextUnstep];
        }

        // 3) USCG Inspection window (computed from COI + lastInspection)
        $coiExp = $r['coiExp'] ?? null;
        if (!empty($coiExp) && $coiExp !== '0000-00-00') {
            $exp = DateTime::createFromFormat('Y-m-d', $coiExp, new DateTimeZone('Pacific/Honolulu'));
            $lastInspectionRaw = $r['lastInspection'] ?? null;
            $lastInspection = (!empty($lastInspectionRaw) && $lastInspectionRaw !== '0000-00-00')
                ? DateTime::createFromFormat('Y-m-d', $lastInspectionRaw, new DateTimeZone('Pacific/Honolulu'))
                : null;

            $inspectionType = '—';
            $winStart = null;
            $winEnd   = null;

            // Annuals #1..#4 (expDate - 4y .. expDate - 1y) each with ±90 days
            for ($i = 1; $i <= 4; $i++) {
                $annualDate = (clone $exp)->modify('-' . (5 - $i) . ' years');
                $startWindow = (clone $annualDate)->modify('-90 days');
                $endWindow   = (clone $annualDate)->modify('+90 days');

                if (!$lastInspection || $lastInspection < $startWindow) {
                    $inspectionType = "Annual (#{$i})";
                    $winStart = $startWindow;
                    $winEnd   = $endWindow;
                    break;
                }
            }

            // If no annual outstanding, check Renewal (last 90 days before exp through exp)
            if ($inspectionType === '—') {
                $renewalStart = (clone $exp)->modify('-90 days');
                if (!$lastInspection || $lastInspection < $renewalStart) {
                    $inspectionType = 'Renewal';
                    $winStart = $renewalStart;
                    $winEnd   = $exp;
                } elseif ($lastInspection > $exp) {
                    // already inspected beyond exp; skip adding a USCG row
                    $inspectionType = 'Inspection Complete';
                }
            }

            if ($inspectionType !== 'Inspection Complete' && $winStart && $winEnd) {
                // Include if the window is approaching — show if either edge is within cutoff
                if ($winStart->format('Y-m-d') < $cutoff || $winEnd->format('Y-m-d') < $cutoff) {
                    $out[] = [
                        'vessel_label' => $vessel,
                        'item'         => "USCG Inspection – {$inspectionType}",
                        'due'          => $winStart->format('Y-m-d') . ' to ' . $winEnd->format('Y-m-d'),
                    ];
                }
            }
        }
    }

    // Order: by date-like value where possible (try to sort by first date in 'due')
    usort($out, function ($a, $b) {
        $ad = $a['due'] ?? ''; $bd = $b['due'] ?? '';
        $adKey = preg_match('/^\d{4}-\d{2}-\d{2}/', $ad) ? substr($ad,0,10) : $ad;
        $bdKey = preg_match('/^\d{4}-\d{2}-\d{2}/', $bd) ? substr($bd,0,10) : $bd;
        return strcmp($adKey, $bdKey);
    });

    return $out;
}

// Collect sections
$sections = [];

$docsVessel = fetch_docs_vessel($pdo, $cutoff, $companyId, $vesselId);
if ($docsVessel) $sections[] = ['id'=>'docs_vessel','title'=>'Vessel Documents – Expired & Due Soon','rows'=>$docsVessel];

$docsEquip  = fetch_docs_equipment($pdo, $cutoff, $companyId, $vesselId);
if ($docsEquip)  $sections[] = ['id'=>'docs_equipment','title'=>'Equipment – Expired & Due Soon','rows'=>$docsEquip];

$crewCreds  = fetch_crew_credentials($pdo, $cutoff, $companyId, $vesselId);
if ($crewCreds)  $sections[] = ['id'=>'crew_credentials','title'=>'Crew Credentials – Expired & Due Soon','rows'=>$crewCreds];

$icrs = fetch_icrs($pdo, $cutoff, $companyId, $vesselId);
if ($icrs) $sections[] = ['id'=>'icr_due','title'=>'ICRs – Due Soon & Overdue','rows'=>$icrs];

$cars = fetch_cars($pdo, $cutoff, $companyId, $vesselId);
if ($cars) $sections[] = ['id'=>'car_due','title'=>'CARs – New, Due Soon & Overdue','rows'=>$cars];

$drills = fetch_drills($pdo, $cutoff, $companyId, $vesselId);
if ($drills) $sections[] = ['id'=>'crew_drills','title'=>'Drills – Due Soon & Overdue','rows'=>$drills];

$insp  = fetch_inspections($pdo, $cutoff, $companyId, $vesselId);
if ($insp)  $sections[] = ['id'=>'upcoming_inspections','title'=>'Upcoming Inspections','rows'=>$insp];

// Nothing to send?
if (!$sections) {
    $scopeBits = [];
    if (!is_null($companyId)) $scopeBits[] = "company={$companyId}";
    if (!is_null($vesselId))  $scopeBits[] = "vessel={$vesselId}";
    $scopeStr = $scopeBits ? (' [' . implode(', ', $scopeBits) . ']') : '';
    if (!$IS_CLI) header('Content-Type: text/plain; charset=UTF-8');
    echo "No items found before {$cutoff} (next {$daysAhead} days){$scopeStr}.\n";
    exit(0);
}

// --- Render HTML digest (simple, readable, branded) ---
function h(?string $s): string { return htmlspecialchars($s ?? '—', ENT_QUOTES, 'UTF-8'); }

$rowsHtml = '';
foreach ($sections as $s) {
    $rowsHtml .= '<h3 style="margin:18px 0 6px">'.h($s['title']).'</h3>';
    $rowsHtml .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #ddd;min-width:740px"><tr style="background:#f7f7f7">';
    // headers by section
    switch ($s['id']) {
        case 'docs_vessel':
            $rowsHtml .= '<th align="left">Vessel</th><th align="left">Document</th><th align="left">Due</th><th align="left">Status</th></tr>';
            foreach ($s['rows'] as $r) {
                $due = $r['expDate'] ?? null; $dts = $due ? strtotime($due) : null;
                $status = ($dts && date('Y-m-d',$dts) < $today) ? 'Expired' : 'Due soon';
                $badge = $status==='Expired'
                    ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>'
                    : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                $rowsHtml .= '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['docName']).'</td><td>'.h($due).'</td><td>'.$badge.'</td></tr>';
            }
            break;

        case 'docs_equipment':
            $rowsHtml .= '<th align="left">Vessel</th><th align="left">Equipment</th><th align="left">Location</th><th align="left">Due</th><th align="left">Status</th></tr>';
            foreach ($s['rows'] as $r) {
                $due = $r['expDate'] ?? null;
                $dts = $due ? strtotime($due) : null;
                $status = ($dts && date('Y-m-d',$dts) < $today) ? 'Expired' : 'Due soon';
                $badge = $status==='Expired'
                    ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>'
                    : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                        . '<td>'.h($r['vessel_label']).'</td>'
                        . '<td>'.h($r['equipment_label']).'</td>'
                        . '<td>'.h($r['eq_location']).'</td>'
                        . '<td>'.h($due).'</td>'
                        . '<td>'.$badge.'</td>'
                        . '</tr>';
            }
            break;

case 'crew_credentials':
    $rowsHtml .= '<th align="left">Crew</th><th align="left">Vessel</th><th align="left">Credential</th><th align="left">Due</th><th align="left">Status</th></tr>';

    $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));
    foreach ($s['rows'] as $r) {
        $due   = $r['due'] ?? null;
        $badge = '';
        if (!empty($due) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $dueObj = new DateTime($due, new DateTimeZone('Pacific/Honolulu'));
            $diff   = (int)$todayObj->diff($dueObj)->format('%r%a'); // neg if past
            if ($diff < 0) {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>';
            } elseif ($diff <= 30) {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Expiring soon</span>';
            } else {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#e9fbe9;color:#0b5e0b;font-weight:600">Valid</span>';
            }
        }
        $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                   . '<td>'.h($r['crew_name']).'</td>'
                   . '<td>'.h($r['vessel_label']).'</td>'
                   . '<td>'.h($r['credential']).'</td>'
                   . '<td>'.h($due ?: '—').'</td>'
                   . '<td>'.$badge.'</td>'
                   . '</tr>';
    }
    break;


case 'icr_due':
    $rowsHtml .= '<th align="left">Vessel</th><th align="left">ICR</th><th align="left">Last</th><th align="left">Next Due</th><th align="left">Status</th></tr>';
    $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));

    foreach ($s['rows'] as $r) {
        $dueStr = $r['due_date'] ?? null;
        $never  = !empty($r['never']); // 1 for never performed

        if ($never) {
            $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#eef1f5;color:#334155;font-weight:600">Never performed</span>';
        } else {
            $badge  = '';
            if ($dueStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
                $dueObj = new DateTime($dueStr, new DateTimeZone('Pacific/Honolulu'));
                $diff   = (int)$todayObj->diff($dueObj)->format('%r%a'); // negative if past
                if ($diff < 0) {
                    $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
                } else {
                    $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                }
            }
        }

        $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                   . '<td>'.h($r['vessel_label']).'</td>'
                   . '<td>'.h($r['icr_title']).'</td>'
                   . '<td>'.h($r['last_run'] ?? '—').'</td>'
                   . '<td>'.h($dueStr ?: '—').'</td>'
                   . '<td>'.$badge.'</td>'
                   . '</tr>';
    }
    break;



case 'car_due':
    $rowsHtml .= '<th align="left">Vessel</th><th align="left">CAR</th><th align="left">Status</th><th align="left">Due</th></tr>';
    foreach ($s['rows'] as $r) {
        $due    = $r['due_date'] ?? null;
        $status = trim(strtolower((string)($r['status'] ?? 'open')));
        $isOver = ($due && $due < $today);
        $isDeferred = ($status === 'deferred');

        // status pill only (no row shading)
        if ($isDeferred) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f5ff;color:#003e8a;font-weight:600">Deferred</span>';
        } elseif ($isOver) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
        } else {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
        }

        // append original status (small, muted) for context
        $statusHtml = $statusBadge . ' <span style="opacity:0.75">'.h($r['status'] ?? 'open').'</span>';

        $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                   . '<td>'.h($r['vessel_label']).'</td>'
                   . '<td>'.h($r['title']).'</td>'
                   . '<td>'.$statusHtml.'</td>'
                   . '<td>'.h($due ?: $r['created_at']).'</td>'
                   . '</tr>';
    }
    break;


case 'crew_drills':
    $rowsHtml .= '<th align="left">Vessel</th><th align="left">Crew</th><th align="left">Drill</th><th align="left">Last</th><th align="left">Next Due</th><th align="left">Status</th></tr>';

    $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));
    foreach ($s['rows'] as $r) {
        $dueStr = $r['due'] ?? null;
        $badge  = '';
        if ($dueStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
            $dueObj = new DateTime($dueStr, new DateTimeZone('Pacific/Honolulu'));
            $diff   = (int)$todayObj->diff($dueObj)->format('%r%a'); // negative if past
            if ($diff < 0) {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
            } else {
                $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
            }
        }

        $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                   . '<td>'.h($r['vessel_label']).'</td>'
                   . '<td>'.h($r['crew_name']).'</td>'
                   . '<td>'.h($r['drill']).'</td>'
                   . '<td>'.h($r['last']).'</td>'
                   . '<td>'.h($dueStr ?: '—').'</td>'
                   . '<td>'.$badge.'</td>'
                   . '</tr>';
    }
    break;


case 'upcoming_inspections':
    $rowsHtml .= '<th align="left">Vessel</th><th align="left">Inspection</th><th align="left">Due / Window</th><th align="left">Status</th></tr>';

    $statusForDue = function (string $dueStr) use ($today): string {
        // window: "YYYY-MM-DD to YYYY-MM-DD"
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/', $dueStr, $m)) {
            [, $start, $end] = $m;
            if ($end < $today) {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
            } elseif ($start <= $today && $today <= $end) {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff1cc;color:#6a3d00;font-weight:600">Window open</span>';
            }
            return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Upcoming</span>';
        }
        // single date
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
            if ($dueStr < $today) {
                return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
            }
            return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Upcoming</span>';
        }
        return '';
    };

    foreach ($s['rows'] as $r) {
        $dueStr = (string)($r['due'] ?? '—');
        $rowsHtml .= '<tr style="border-top:1px solid #eee">'
                   . '<td>'.h($r['vessel_label'] ?? '—').'</td>'
                   . '<td>'.h($r['item'] ?? '—').'</td>'
                   . '<td>'.h($dueStr).'</td>'
                   . '<td>'.$statusForDue($dueStr).'</td>'
                   . '</tr>';
    }
    break;
    }
    $rowsHtml .= '</table>';
}

// Scope display
$scopeBits = [];
if (!is_null($companyId)) $scopeBits[] = "Company #{$companyId}";
if (!is_null($vesselId))  $scopeBits[] = "Vessel #{$vesselId}";
$scopeText = $scopeBits ? (' – ' . implode(' / ', $scopeBits)) : '';

$html = <<<HTML
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.45">
  <h2 style="margin:0 0 12px">VMS Digest (next {$daysAhead} days){$scopeText}</h2>
  <p style="margin:0 0 16px;color:#555">Window: all items with due date &lt; {$cutoff}. Today is {$today}.</p>
  {$rowsHtml}
  <p style="margin-top:18px;color:#999;font-size:12px">You're receiving this because you enabled VMS notifications.</p>
</div>
HTML;

// Save preview?
if ($saveHtml) {
    $ts = date('Ymd_His');
    $outDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'previews';
    if (!is_dir($outDir)) @mkdir($outDir, 0777, true);
    $scopeSlug = ($companyId ? "c{$companyId}_" : '') . ($vesselId ? "v{$vesselId}_" : '');
    $outPath = $outDir . DIRECTORY_SEPARATOR . "{$scopeSlug}digest_{$daysAhead}d_{$ts}.html";
    file_put_contents($outPath, $html);
    if (!$IS_CLI) header('Content-Type: text/plain; charset=UTF-8');
    echo "Preview written to: {$outPath}\n";
    exit(0);
}

$subject = "VMS Digest ({$daysAhead}d)";

// Dry run?
if ($dryRun) {
    if (!$IS_CLI) header('Content-Type: text/plain; charset=UTF-8');
    echo "DRY RUN ✔ Would email to: {$toEmail} ({$toName})\n";
    echo "Sections: " . implode(', ', array_map(fn($s) => $s['id'], $sections)) . "\n";
    echo "Subject: {$subject}\n";
    exit(0);
}

// Send for real
$res = sendEmail($toEmail, $toName, $subject, $html);
if (is_array($res)) {
    echo "Email result: " . json_encode($res) . "\n";
} else {
    echo "Email result: {$res}\n";
}
