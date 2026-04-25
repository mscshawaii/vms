<?php
declare(strict_types=1);

date_default_timezone_set('Pacific/Honolulu');

// -------------------------------
// Small utilities
// -------------------------------
function h(?string $s): string { return htmlspecialchars($s ?? '—', ENT_QUOTES, 'UTF-8'); }

function compute_dates(int $days): array {
    $tz     = new DateTimeZone('Pacific/Honolulu');
    $today  = (new DateTime('today', $tz))->format('Y-m-d');
    $cutoff = (new DateTime('today', $tz))->modify("+{$days} days")->format('Y-m-d');
    return [$today, $cutoff];
}

function days_until(?string $dateStr): ?int {
    if (!$dateStr || $dateStr === '—') return null;

    try {
        $today = new DateTimeImmutable('today', new DateTimeZone('Pacific/Honolulu'));
        $d     = new DateTimeImmutable(substr($dateStr, 0, 10), new DateTimeZone('Pacific/Honolulu'));
    } catch (Throwable $e) {
        return null;
    }

    return (int)$today->diff($d)->format('%r%a');
}

function days_label(?string $dateStr): string {
    $days = days_until($dateStr);

    if ($days === null) return '—';
    if ($days < 0)      return 'Expired ' . abs($days) . 'd';
    if ($days === 0)    return 'Today';
    return $days . 'd';
}

function scopeSql(array &$params, ?int $companyId, ?int $vesselId): array {
    $companyFilter = '';
    $vesselFilter  = '';
    if (!is_null($companyId)) { $companyFilter = ' AND v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $vesselFilter  = ' AND v.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
    return [$companyFilter, $vesselFilter];
}

/**
 * Apply tenant scope to queries that read from vessels (alias must be "v").
 * Always excludes archived vessels.
 */
function apply_scope_sql(array $opts, string $baseWhere = '1=1'): array {
    $where  = "($baseWhere) AND v.is_active = 1";
    $params = [];

    $vesselId  = $opts['vessel']     ?? null;
    $isMSCS    = $opts['is_mscs']    ?? false;
    $companyId = $opts['company_id'] ?? 0;

    if ($vesselId) {
        $where .= " AND v.vessel_id = :vessel_id";
        $params[':vessel_id'] = (int)$vesselId;
    } else {
        if (!$isMSCS) {
            $where .= " AND v.company_id = :company_id";
            $params[':company_id'] = (int)$companyId;
        }
    }
    return [$where, $params];
}

// -------------------------------
// FETCHERS (same logic you have now)
// -------------------------------

// 1) Vessel Docs (expired + due soon)
function fetch_docs_vessel(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $sql = <<<SQL
    SELECT 
        COALESCE(v.vesselName, CONCAT('Vessel #', d.vessel_id)) AS vessel_label,
        d.docName, 
        d.expDate
    FROM documents d
    LEFT JOIN vessels v ON v.vessel_id = d.vessel_id
    WHERE d.archived_at IS NULL
      AND d.related_to = 'vessel'
      AND d.vessel_id IS NOT NULL
      AND d.expDate IS NOT NULL
      AND d.expDate < :cutoff
      AND v.is_active = 1
SQL;

    $params = [':cutoff' => $cutoff];

    // ✅ Scope enforcement
    if ($vesselId) {
        // If a specific vessel is selected
        $sql .= " AND v.vessel_id = :vessel_id";
        $params[':vessel_id'] = $vesselId;
    } elseif (!is_null($companyId)) {
        // Non-MSCS company (limited to their own active vessels)
        $sql .= " AND v.company_id = :company_id";
        $params[':company_id'] = $companyId;
    }

    $sql .= " ORDER BY d.expDate ASC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


// 2) Equipment (from equipment.expDate)
function fetch_docs_equipment(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $sql = <<<SQL
    SELECT
      COALESCE(v.vesselName, CONCAT('Vessel #', e.vessel_id)) AS vessel_label,
      e.equipmentName                                         AS equipment_label,
      e.equipmentLocation                                     AS eq_location,
      e.expDate                                               AS expDate
    FROM equipment e
    LEFT JOIN vessels v ON v.vessel_id = e.vessel_id
    WHERE e.expDate IS NOT NULL
      AND e.expDate < :cutoff
      AND v.is_active = 1
SQL;

    $params = [':cutoff' => $cutoff];

    // Scope: if a vessel is chosen, use it; else if non-MSCS, limit to their company; MSCS sees all.
    if (!is_null($vesselId)) {
        $sql .= " AND v.vessel_id = :vid";
        $params[':vid'] = $vesselId;
    } elseif (!is_null($companyId)) {
        $sql .= " AND v.company_id = :cid";
        $params[':cid'] = $companyId;
    }

    $sql .= " ORDER BY e.expDate ASC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// 3) Crew Credentials (MMC, FA/CPR, MROP, Medical)
function fetch_crew_credentials(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $params = [':cutoff' => $cutoff];
    $scope  = '';

    if (!is_null($vesselId)) {
        $scope = " AND u.vessel_id = :vid";
        $params[':vid'] = $vesselId;
    } elseif (!is_null($companyId)) {
        $scope = " AND u.company_id = :cid";
        $params[':cid'] = $companyId;
    }

    // Only CAST when value strictly matches YYYY-MM-DD; everything else becomes NULL
    $sql = <<<SQL
    SELECT crew_name, vessel_label, credential, due
    FROM (
        SELECT
            CONCAT(u.fName,' ',u.lName) AS crew_name,
            v.vesselName                AS vessel_label,
            'MMC'                       AS credential,
            CASE
              WHEN u.mmc IS NULL THEN NULL
              WHEN CONCAT(u.mmc) REGEXP '^[12][0-9]{3}-[01][0-9]-[0-3][0-9]$'
                   THEN CAST(u.mmc AS DATE)
              ELSE NULL
            END AS due
        FROM users u
        LEFT JOIN vessels v
               ON v.vessel_id = u.vessel_id
              AND v.is_active = 1
        WHERE 1=1 {$scope}

        UNION ALL
        SELECT
            CONCAT(u.fName,' ',u.lName),
            v.vesselName,
            'First Aid/CPR',
            CASE
              WHEN u.fa IS NULL THEN NULL
              WHEN CONCAT(u.fa) REGEXP '^[12][0-9]{3}-[01][0-9]-[0-3][0-9]$'
                   THEN CAST(u.fa AS DATE)
              ELSE NULL
            END
        FROM users u
        LEFT JOIN vessels v
               ON v.vessel_id = u.vessel_id
              AND v.is_active = 1
        WHERE 1=1 {$scope}

        UNION ALL
        SELECT
            CONCAT(u.fName,' ',u.lName),
            v.vesselName,
            'MROP',
            CASE
              WHEN u.mrop IS NULL THEN NULL
              WHEN CONCAT(u.mrop) REGEXP '^[12][0-9]{3}-[01][0-9]-[0-3][0-9]$'
                   THEN CAST(u.mrop AS DATE)
              ELSE NULL
            END
        FROM users u
        LEFT JOIN vessels v
               ON v.vessel_id = u.vessel_id
              AND v.is_active = 1
        WHERE 1=1 {$scope}

        UNION ALL
        SELECT
            CONCAT(u.fName,' ',u.lName),
            v.vesselName,
            'MMC Medical',
            CASE
              WHEN u.mmc_medical IS NULL THEN NULL
              WHEN CONCAT(u.mmc_medical) REGEXP '^[12][0-9]{3}-[01][0-9]-[0-3][0-9]$'
                   THEN CAST(u.mmc_medical AS DATE)
              ELSE NULL
            END
        FROM users u
        LEFT JOIN vessels v
               ON v.vessel_id = u.vessel_id
              AND v.is_active = 1
        WHERE 1=1 {$scope}
    ) AS x
    WHERE x.due IS NOT NULL
      AND x.due < :cutoff
    ORDER BY x.due ASC
    SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}


// 4) ICRs incl. never-performed (vessel_icrs baseline)
function fetch_icrs(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId, array $config = []): array {
    $icrDefaultDays = (int)($config['thresholds']['icr_default_days'] ?? 365);
    $icrIntervals   = (array)($config['icr_intervals'] ?? []);

    $params  = [];
    $filters = [];

    // ✅ visibility scope
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'vi.vessel_id  = :vid'; $params[':vid'] = $vesselId; }

    // ✅ exclude archived vessels
    $filters[] = 'v.is_active = 1';

    // (optional) exclude archived/disabled ICR assignments if you track them
    // $filters[] = 'vi.archived_at IS NULL';

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

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
    JOIN vessels v   ON v.vessel_id  = vi.vessel_id
    JOIN icrs i      ON i.icr_id     = vi.icr_id
    LEFT JOIN last_runs lr ON lr.vessel_id = vi.vessel_id AND lr.icr_id = vi.icr_id
    {$where}
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $tz  = new DateTimeZone('Pacific/Honolulu');
    $out = [];

    foreach ($rows as $r) {
        $label = $r['icr_number']
            ? ($r['icr_number'].' – '.$r['icr_title'])
            : ($r['icr_title'] ?: ('ICR #'.(int)$r['icr_id']));

        $last = $r['last_run'] ?? null;

        if ($last && $last !== '0000-00-00') {
            // pick interval: per-number > per-title > default
            $interval = $icrDefaultDays;
            if (!empty($r['icr_number']) && isset($icrIntervals[$r['icr_number']])) {
                $interval = (int)$icrIntervals[$r['icr_number']];
            } elseif (!empty($r['icr_title']) && isset($icrIntervals[$r['icr_title']])) {
                $interval = (int)$icrIntervals[$r['icr_title']];
            }

            $nextDue = (new DateTimeImmutable($last, $tz))
                        ->modify("+{$interval} days")
                        ->format('Y-m-d');

            // only include if due before cutoff
            if ($nextDue < $cutoff) {
                $out[] = [
                    'vessel_label' => $r['vessel_label'] ?: ('Vessel #'.(int)$r['vessel_id']),
                    'icr_title'    => $label,
                    'last_run'     => $last,
                    'due_date'     => $nextDue,
                    'never'        => 0,
                ];
            }
        } else {
            // never-performed → always include (as requested)
            $out[] = [
                'vessel_label' => $r['vessel_label'] ?: ('Vessel #'.(int)$r['vessel_id']),
                'icr_title'    => $label,
                'last_run'     => null,
                'due_date'     => null,
                'never'        => 1,
            ];
        }
    }

    // sort: never-performed last, then due date
    usort($out, function ($a, $b) {
        $an = (int)($a['never'] ?? 0);
        $bn = (int)($b['never'] ?? 0);
        if ($an !== $bn) return $an <=> $bn;
        return strcmp((string)$a['due_date'], (string)$b['due_date']);
    });

    return $out;
}

// 5) CARs (open + due soon/overdue + new)
function fetch_cars(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $params  = [':cutoff' => $cutoff];
    $filters = [];

    // Scope to company/vessel
    if (!is_null($vesselId))  { $filters[] = 't.vessel_id = :vid'; $params[':vid'] = $vesselId; }
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }

    // Exclude archived vessels
    $filters[] = 'v.is_active = 1';

    // (Optional) only CAR tasks if your schema has a marker
    // e.g., t.type = 'car'   OR   t.category = 'CAR'   OR   t.is_car = 1
    // $filters[] = "t.type = 'car'";

    // (Optional) ignore archived/soft-deleted tasks if present
    // $filters[] = 't.archived_at IS NULL';

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

// 6) Drills (per crew, per type)
function fetch_drills(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $INTERVAL_BY_TYPE = [
        'Fire'          => 30,
        'Man Overboard' => 30,
        'Abandon Ship'  => 30
    ];

    $params  = [];
    $filters = [];

    // ✅ Scope for company/vessel
    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'cd.vessel_id  = :vid'; $params[':vid'] = $vesselId; }

    // ✅ Only active vessels
    $filters[] = 'v.is_active = 1';

    // (Optional) exclude archived/disabled drills
    // $filters[] = 'cd.archived_at IS NULL';

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    $sql = <<<SQL
    SELECT
      cd.vessel_id,
      v.vesselName AS vessel_label,
      cd.crew_user_id,
      CONCAT(u.fName, ' ', u.lName) AS crew_name,
      cd.drill_type,
      MAX(cd.drill_date) AS last_date
    FROM crew_drills cd
    LEFT JOIN vessels v ON v.vessel_id = cd.vessel_id
    LEFT JOIN users   u ON u.id        = cd.crew_user_id
    {$where}
    GROUP BY cd.vessel_id, v.vesselName, cd.crew_user_id, u.fName, u.lName, cd.drill_type
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

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

    usort($out, fn($a, $b) => strcmp($a['due'], $b['due']));
    return $out;
}

// 7) Upcoming inspections (incl. computed USCG windows)
function fetch_inspections(PDO $pdo, string $cutoff, ?int $companyId, ?int $vesselId): array {
    $params  = [];
    $filters = ['v.is_active = 1']; // ✅ only active vessels

    if (!is_null($companyId)) { $filters[] = 'v.company_id = :cid'; $params[':cid'] = $companyId; }
    if (!is_null($vesselId))  { $filters[] = 'v.vessel_id  = :vid'; $params[':vid'] = $vesselId; }

    $where = 'WHERE ' . implode(' AND ', $filters);

    // Helper macro: safely normalize any DATE/DATETIME/zero/empty -> DATE or NULL
    // We do this by converting to CHAR first, checking for zero/empty, then DATE(col).
    $safeDate = function($colSql) {
        return "CASE
            WHEN $colSql IS NULL THEN NULL
            WHEN CONVERT($colSql, CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00') THEN NULL
            ELSE DATE($colSql)
        END";
    };

    $sql = <<<SQL
    SELECT
      v.vessel_id,
      v.vesselName,

      /* Normalize vessel date/datetime fields safely */
      {$safeDate('v.lastInspection')}          AS lastInspection,
      {$safeDate('v.nextScheduledInspection')} AS nextScheduledInspection,
      {$safeDate('v.nextDrydock')}             AS nextDrydock,
      {$safeDate('v.nextUnstep')}              AS nextUnstep,

      /* COI dates pulled from documents subquery (already filtered) */
      {$safeDate('coi.issueDate')} AS coiIssue,
      {$safeDate('coi.expDate')}   AS coiExp

    FROM vessels v

    /* Latest active COI per vessel; ignore archived/zero dates using CHAR checks */
    LEFT JOIN (
      SELECT d1.vessel_id, d1.issueDate, d1.expDate
      FROM documents d1
      INNER JOIN (
        SELECT vessel_id, MAX(expDate) AS maxExp
        FROM documents
        WHERE docType = 'Certificate of Inspection'
          AND (
            archived_at IS NULL OR CONVERT(archived_at, CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00')
          )
          AND CONVERT(expDate, CHAR) NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')
        GROUP BY vessel_id
      ) dmax
        ON dmax.vessel_id = d1.vessel_id AND dmax.maxExp = d1.expDate
      WHERE d1.docType = 'Certificate of Inspection'
        AND (
          d1.archived_at IS NULL OR CONVERT(d1.archived_at, CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00')
        )
        AND CONVERT(d1.expDate, CHAR) NOT IN ('', '0000-00-00', '0000-00-00 00:00:00')
    ) coi ON coi.vessel_id = v.vessel_id

    {$where}
SQL;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    $tz  = new DateTimeZone('Pacific/Honolulu');

    // Helper: safe Y-m-d string or null (works on whatever MySQL returned)
    $normStrDate = function ($s) {
        if (!$s) return null;
        $s = substr($s, 0, 10);
        if ($s === '0000-00-00') return null;
        return $s;
    };

    foreach ($rows as $r) {
        $vessel = $r['vesselName'] ?? ('Vessel #' . (int)$r['vessel_id']);

        // Explicit dates that fall before cutoff
        $nextSched   = $normStrDate($r['nextScheduledInspection'] ?? null);
        $nextDrydock = $normStrDate($r['nextDrydock'] ?? null);
        $nextUnstep  = $normStrDate($r['nextUnstep'] ?? null);

        if ($nextSched   && $nextSched   < $cutoff) { $out[] = ['vessel_label'=>$vessel,'item'=>'Next Scheduled Inspection','due'=>$nextSched]; }
        if ($nextDrydock && $nextDrydock < $cutoff) { $out[] = ['vessel_label'=>$vessel,'item'=>'Drydock','due'=>$nextDrydock]; }
        if ($nextUnstep  && $nextUnstep  < $cutoff) { $out[] = ['vessel_label'=>$vessel,'item'=>'Unstep Mast','due'=>$nextUnstep]; }

        // Computed USCG window from COI dates
        $coiExpStr = $normStrDate($r['coiExp'] ?? null);
        if ($coiExpStr) {
            $exp = DateTime::createFromFormat('Y-m-d', $coiExpStr, $tz) ?: null;

            $lastInspectionStr = $normStrDate($r['lastInspection'] ?? null);
            $lastInspection = $lastInspectionStr
                ? (DateTime::createFromFormat('Y-m-d', $lastInspectionStr, $tz) ?: null)
                : null;

            if ($exp) {
                $inspectionType = '—';
                $winStart = null;
                $winEnd   = null;

                // Annuals 1..4 preceding the COI expiration
                for ($i = 1; $i <= 4; $i++) {
                    $annualDate  = (clone $exp)->modify('-' . (5 - $i) . ' years');
                    $startWindow = (clone $annualDate)->modify('-90 days');
                    $endWindow   = (clone $annualDate)->modify('+90 days');

                    if (!$lastInspection || $lastInspection < $startWindow) {
                        $inspectionType = "Annual (#{$i})";
                        $winStart = $startWindow;
                        $winEnd   = $endWindow;
                        break;
                    }
                }

                // Renewal window if all annuals satisfied
                if ($inspectionType === '—') {
                    $renewalStart = (clone $exp)->modify('-90 days');
                    if (!$lastInspection || $lastInspection < $renewalStart) {
                        $inspectionType = 'Renewal';
                        $winStart = $renewalStart;
                        $winEnd   = $exp;
                    } elseif ($lastInspection > $exp) {
                        $inspectionType = 'Inspection Complete';
                    }
                }

                if ($inspectionType !== 'Inspection Complete' && $winStart && $winEnd) {
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
    }

    // Sort by earliest date in the 'due' string
    usort($out, function ($a, $b) {
        $getKey = function ($d) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string)$d, $m)) return $m[1];
            return (string)$d;
        };
        return strcmp($getKey($a['due'] ?? ''), $getKey($b['due'] ?? ''));
    });

    return $out;
}

// -------------------------------
// Coordinator + Renderer
// -------------------------------
function build_digest_sections(PDO $pdo, array $opts, array $config = []): array {
    $days      = max(0, (int)($opts['days'] ?? 45));
    $companyId = $opts['company'] ?? null;
    $vesselId  = $opts['vessel'] ?? null;

    [, $cutoff] = compute_dates($days);

    $want = $opts['sections'] ?? ['docs_vessel','docs_equipment','crew_credentials','icr_due','car_due','crew_drills','upcoming_inspections'];
    $want = array_fill_keys($want, true);

    $sections = [];

    if (!empty($want['docs_vessel'])) {
        $rows = fetch_docs_vessel($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'docs_vessel','title'=>'Vessel Documents – Expired & Due Soon','rows'=>$rows];
    }
    if (!empty($want['docs_equipment'])) {
        $rows = fetch_docs_equipment($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'docs_equipment','title'=>'Equipment – Expired & Due Soon','rows'=>$rows];
    }
    if (!empty($want['crew_credentials'])) {
        $rows = fetch_crew_credentials($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'crew_credentials','title'=>'Crew Credentials – Expired & Due Soon','rows'=>$rows];
    }
    if (!empty($want['icr_due'])) {
        $rows = fetch_icrs($pdo, $cutoff, $companyId, $vesselId, $config);
        if ($rows) $sections[] = ['id'=>'icr_due','title'=>'ICRs – Due Soon & Overdue / Never Performed','rows'=>$rows];
    }
    if (!empty($want['car_due'])) {
        $rows = fetch_cars($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'car_due','title'=>'CARs – New, Due Soon & Overdue','rows'=>$rows];
    }
    if (!empty($want['crew_drills'])) {
        $rows = fetch_drills($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'crew_drills','title'=>'Drills – Due Soon & Overdue','rows'=>$rows];
    }
    if (!empty($want['upcoming_inspections'])) {
        $rows = fetch_inspections($pdo, $cutoff, $companyId, $vesselId);
        if ($rows) $sections[] = ['id'=>'upcoming_inspections','title'=>'Upcoming Inspections','rows'=>$rows];
    }

    return $sections;
}

function render_digest_html(array $opts, array $sections): string {
    $days      = max(0, (int)($opts['days'] ?? 45));
    [$today, $cutoff] = compute_dates($days);

    $scopeBits = [];
    if (!empty($opts['company'])) $scopeBits[] = "Company #{$opts['company']}";
    if (!empty($opts['vessel']))  $scopeBits[] = "Vessel #{$opts['vessel']}";
    $scopeText = $scopeBits ? (' – ' . implode(' / ', $scopeBits)) : '';

    ob_start();  
    ?>

<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.45">
  <h2 style="margin:0 0 12px">VMS Digest (next <?=h((string)$days)?> days)<?=h($scopeText)?></h2>
  <p style="margin:0 0 16px;color:#555">Window: all items with due date &lt; <?=$cutoff?>. Today is <?=$today?>.</p>

  <?php
  $inspectionDaysLabel = function (string $dueStr) use ($today): string {
      if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/', $dueStr, $m)) {
          [, $start, $end] = $m;

          if ($end < $today) {
              return 'Expired ' . abs((int)((new DateTimeImmutable('today'))->diff(new DateTimeImmutable($end))->format('%r%a'))) . 'd';
          }

          if ($start <= $today && $today <= $end) {
              return 'Window open';
          }

          return days_label($start);
      }

      return days_label($dueStr);
  };

  foreach ($sections as $s) {
      echo '<h3 style="margin:18px 0 6px">'.h($s['title']).'</h3>';
      echo '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #ddd;min-width:740px"><tr style="background:#f7f7f7">';
      switch ($s['id']) {
          case 'docs_vessel':
              echo '<th align="left">Vessel</th><th align="left">Document</th><th align="left">Due</th><th align="left">Days</th><th align="left">Status</th></tr>';
              foreach ($s['rows'] as $r) {
                  $due = $r['expDate'] ?? null; $dts = $due ? strtotime($due) : null;
                  $expired = ($dts && date('Y-m-d',$dts) < $today);
                  $badge = $expired
                      ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>'
                      : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                  echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['docName']).'</td><td>'.h($due).'</td><td>'.h(days_label($due)).'</td><td>'.$badge.'</td></tr>';
              }
              break;

          case 'docs_equipment':
              echo '<th align="left">Vessel</th><th align="left">Equipment</th><th align="left">Location</th><th align="left">Due</th><th align="left">Days</th><th align="left">Status</th></tr>';
              foreach ($s['rows'] as $r) {
                  $due = $r['expDate'] ?? null; $dts = $due ? strtotime($due) : null;
                  $expired = ($dts && date('Y-m-d',$dts) < $today);
                  $badge = $expired
                      ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>'
                      : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                  echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['equipment_label']).'</td><td>'.h($r['eq_location']).'</td><td>'.h($due).'</td><td>'.h(days_label($due)).'</td><td>'.$badge.'</td></tr>';
              }
              break;

            case 'crew_credentials':
                echo '<th align="left">Crew</th><th align="left">Vessel</th><th align="left">Credential</th><th align="left">Due</th><th align="left">Days</th><th align="left">Status</th></tr>';
                $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));
                foreach ($s['rows'] as $r) {
                    $due   = $r['due'] ?? null;
                    $badge = '';
                    if (!empty($due) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
                        $dueObj = new DateTime($due, new DateTimeZone('Pacific/Honolulu'));
                        $diff   = (int)$todayObj->diff($dueObj)->format('%r%a');
                        if ($diff < 0) {
                            $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>';
                        } elseif ($diff <= 30) {
                            $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Expiring soon</span>';
                        } else {
                            $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#e9fbe9;color:#0b5e0b;font-weight:600">Valid</span>';
                        }
                    }
                    echo '<tr style="border-top:1px solid #eee"><td>'.h($r['crew_name']).'</td><td>'.h($r['vessel_label']).'</td><td>'.h($r['credential']).'</td><td>'.h($due).'</td><td>'.h(days_label($due)).'</td><td>'.$badge.'</td></tr>';
                }
                break;

          case 'icr_due':
              echo '<th align="left">Vessel</th><th align="left">ICR</th><th align="left">Last</th><th align="left">Next Due</th><th align="left">Days</th><th align="left">Status</th></tr>';
              $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));
              foreach ($s['rows'] as $r) {
                  $dueStr = $r['due_date'] ?? null;
                  $never  = !empty($r['never']);
                  if ($never) {
                      $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#eef1f5;color:#334155;font-weight:600">Never performed</span>';
                  } else {
                      $badge = '';
                      if ($dueStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
                          $dueObj = new DateTime($dueStr, new DateTimeZone('Pacific/Honolulu'));
                          $diff   = (int)$todayObj->diff($dueObj)->format('%r%a');
                          $badge  = ($diff < 0)
                              ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>'
                              : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                      }
                  }
                  echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['icr_title']).'</td><td>'.h($r['last_run'] ?? '—').'</td><td>'.h($dueStr ?: '—').'</td><td>'.h($r['never'] ? '—' : days_label($dueStr)).'</td><td>'.$badge.'</td></tr>';
              }
              break;

          case 'car_due':
              echo '<th align="left">Vessel</th><th align="left">CAR</th><th align="left">Status</th><th align="left">Due</th><th align="left">Days</th></tr>';
              foreach ($s['rows'] as $r) {
                  $due    = $r['due_date'] ?? null;
                  $status = trim(strtolower((string)($r['status'] ?? 'open')));
                  $isOver = ($due && $due < $today);
                  $isDeferred = ($status === 'deferred');
                  if ($isDeferred) {
                      $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#f0f5ff;color:#003e8a;font-weight:600">Deferred</span>';
                  } elseif ($isOver) {
                      $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
                  } else {
                      $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                  }
                  $statusHtml = $statusBadge . ' <span style="opacity:0.75">'.h($r['status'] ?? 'open').'</span>';
                 $dueDisplay = $due ?: $r['created_at'];
                 echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['title']).'</td><td>'.$statusHtml.'</td><td>'.h($dueDisplay).'</td><td>'.h(days_label($dueDisplay)).'</td></tr>';
              }
              break;

            case 'crew_drills':
                echo '<th align="left">Vessel</th><th align="left">Crew</th><th align="left">Drill</th><th align="left">Last</th><th align="left">Next Due</th><th align="left">Days</th><th align="left">Status</th></tr>';
                $todayObj = new DateTime('today', new DateTimeZone('Pacific/Honolulu'));
                foreach ($s['rows'] as $r) {
                    $dueStr = $r['due'] ?? null;
                    $badge = '';
                    if ($dueStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
                        $dueObj = new DateTime($dueStr, new DateTimeZone('Pacific/Honolulu'));
                        $diff   = (int)$todayObj->diff($dueObj)->format('%r%a');
                        $badge  = ($diff < 0)
                            ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>'
                            : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';
                    }
                    echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label']).'</td><td>'.h($r['crew_name']).'</td><td>'.h($r['drill']).'</td><td>'.h($r['last']).'</td><td>'.h($dueStr ?: '—').'</td><td>'.h(days_label($dueStr)).'</td><td>'.$badge.'</td></tr>';
                }
                break;

            case 'upcoming_inspections':
                echo '<th align="left">Vessel</th><th align="left">Inspection</th><th align="left">Due / Window</th><th align="left">Days</th><th align="left">Status</th></tr>';
                $statusForDue = function (string $dueStr) use ($today): string {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/', $dueStr, $m)) {
                        [, $start, $end] = $m;
                        if ($end < $today) return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
                        if ($start <= $today && $today <= $end) return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff1cc;color:#6a3d00;font-weight:600">Window open</span>';
                        return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Upcoming</span>';
                    }
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr)) {
                        if ($dueStr < $today) return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Overdue</span>';
                        return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Upcoming</span>';
                    }
                    return '';
                };

                foreach ($s['rows'] as $r) {
                    $dueStr = (string)($r['due'] ?? '—');
                    echo '<tr style="border-top:1px solid #eee"><td>'.h($r['vessel_label'] ?? '—').'</td><td>'.h($r['item'] ?? '—').'</td><td>'.h($dueStr).'</td><td>'.h($inspectionDaysLabel($dueStr)).'</td><td>'.$statusForDue($dueStr).'</td></tr>';
                }
                break;
      }
      echo '</table>';
  }
  ?>
  <p style="margin-top:18px;color:#999;font-size:12px">You're receiving this because you enabled VMS notifications.</p>
</div>
    <?php
    return (string)ob_get_clean();
}
