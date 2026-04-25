<?php
declare(strict_types=1);

// Run from either CLI or web; behave nicely for both.
$IS_CLI = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/notify.php';

date_default_timezone_set('Pacific/Honolulu');

// --- Load config (optional) ---
$config = [];
$configPath = __DIR__ . '/../config_notify.php';
if (file_exists($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

// Defaults (safe fallbacks) — now 45 days
$defaultDays = (int)($config['thresholds']['doc_expiring_days'] ?? 45);
$defaultTo   = (string)($config['email']['admin_bcc'] ?? 'info@mschawaii.org');
$defaultName = (string)($config['email']['from_name'] ?? 'MSCS Hawaii – VMS');

// --- CLI flags ---
$argvOpts = [
    'dryRun'   => false,
    'days'     => $defaultDays,
    'to'       => $defaultTo,
    'name'     => $defaultName,
    'saveHtml' => false,
    'company'  => null,  // int|null
    'vessel'   => null,  // int|null
];

if ($IS_CLI && isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run')                        $argvOpts['dryRun'] = true;
        elseif ($arg === '--save-html')                  $argvOpts['saveHtml'] = true;
        elseif (preg_match('/^--days=(\d+)$/', $arg, $m))    $argvOpts['days'] = (int)$m[1];
        elseif (preg_match('/^--to=(.+)$/', $arg, $m))       $argvOpts['to']   = trim($m[1]);
        elseif (preg_match('/^--name=(.+)$/', $arg, $m))     $argvOpts['name'] = trim($m[1]);
        elseif (preg_match('/^--company=(\d+)$/', $arg, $m)) $argvOpts['company'] = (int)$m[1];
        elseif (preg_match('/^--vessel=(\d+)$/',  $arg, $m)) $argvOpts['vessel']  = (int)$m[1];
    }
}

$daysAhead  = max(0, (int)$argvOpts['days']);
$alertEmail = $argvOpts['to'];
$alertName  = $argvOpts['name'];
$dryRun     = (bool)$argvOpts['dryRun'];
$saveHtml   = (bool)$argvOpts['saveHtml'];
$companyId  = $argvOpts['company'];
$vesselId   = $argvOpts['vessel'];

// --- Compute date range (half-open [today, cutoff)) ---
$tz     = new DateTimeZone('Pacific/Honolulu');
$today  = (new DateTime('today', $tz))->format('Y-m-d');
$cutoff = (new DateTime('today', $tz))->modify("+{$daysAhead} days")->format('Y-m-d');

// --- Build SQL: include anything with expDate < cutoff (expired OR due soon) ---
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
  -- {companyFilter}
  -- {vesselFilter}
ORDER BY d.expDate ASC
SQL;

$params = [':cutoff' => $cutoff];
$companyFilter = '';
$vesselFilter  = '';
if (!is_null($companyId)) { $companyFilter = ' AND v.company_id = :cid'; $params[':cid'] = $companyId; }
if (!is_null($vesselId))  { $vesselFilter  = ' AND v.vessel_id  = :vid'; $params[':vid'] = $vesselId; }
$sql = str_replace(['-- {companyFilter}', '-- {vesselFilter}'], [$companyFilter, $vesselFilter], $sql);

// --- Execute ---
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output type for web vs CLI
if (!$IS_CLI) {
    header('Content-Type: text/plain; charset=UTF-8');
}

if (!$rows) {
    $scopeBits = [];
    if (!is_null($companyId)) $scopeBits[] = "company={$companyId}";
    if (!is_null($vesselId))  $scopeBits[] = "vessel={$vesselId}";
    $scopeStr = $scopeBits ? (' [' . implode(', ', $scopeBits) . ']') : '';
    echo "No expired or due-soon docs before {$cutoff} (next {$daysAhead} days){$scopeStr}.\n";
    exit(0);
}

// --- Build HTML (add Status column) ---
$bodyRows = '';
foreach ($rows as $r) {
    $v = htmlspecialchars($r['vessel_label'] ?? '—', ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($r['docName'] ?? '—', ENT_QUOTES, 'UTF-8');
    $dts = !empty($r['expDate']) ? strtotime($r['expDate']) : null;
    $d = $dts ? htmlspecialchars(date('M j, Y', $dts), ENT_QUOTES, 'UTF-8') : '—';

    $status = 'Due soon';
    if ($dts && date('Y-m-d', $dts) < $today) {
        $status = 'Expired';
    }

    $statusBadge = $status === 'Expired'
        ? '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#ffe6e6;color:#a40000;font-weight:600">Expired</span>'
        : '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fff6e0;color:#8a4b00;font-weight:600">Due soon</span>';

    $bodyRows .= "<tr style=\"border-top:1px solid #eee\">
        <td>{$v}</td><td>{$t}</td><td>{$d}</td><td>{$statusBadge}</td></tr>";
}

$scopeLabel = [];
if (!is_null($companyId)) $scopeLabel[] = "Company #{$companyId}";
if (!is_null($vesselId))  $scopeLabel[] = "Vessel #{$vesselId}";
$scopeText = $scopeLabel ? (' – ' . implode(' / ', $scopeLabel)) : '';

$html = <<<HTML
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;line-height:1.45">
  <h3 style="margin:0 0 8px">VMS: Expired & Expiring Documents (next {$daysAhead} days){$scopeText}</h3>
  <p style="margin:0 0 12px;color:#555">Window: all items with due date < {$cutoff}. Today is {$today}.</p>
  <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;border:1px solid #ddd;min-width:640px">
    <tr style="background:#f7f7f7">
      <th align="left">Vessel</th>
      <th align="left">Document</th>
      <th align="left">Due</th>
      <th align="left">Status</th>
    </tr>
    {$bodyRows}
  </table>
</div>
HTML;

// --- Save preview to disk (no send) ---
if ($saveHtml) {
    $ts = date('Ymd_His');
    $outDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'previews';
    if (!is_dir($outDir)) @mkdir($outDir, 0777, true);
    $scopeSlug = ($companyId ? "c{$companyId}_" : '') . ($vesselId ? "v{$vesselId}_" : '');
    $outPath = $outDir . DIRECTORY_SEPARATOR . "{$scopeSlug}docs_expired_and_expiring_{$daysAhead}d_{$ts}.html";
    file_put_contents($outPath, $html);
    echo "Preview written to: {$outPath}\n";
    exit(0);
}

$subject = "VMS: Expired & Expiring Docs ({$daysAhead}d)";

// --- Dry run (console only) ---
if ($dryRun) {
    echo "DRY RUN ✔  Would email to: {$alertEmail} ({$alertName})\n";
    if (!is_null($companyId)) echo "Scope: company={$companyId}\n";
    if (!is_null($vesselId))  echo "Scope: vessel={$vesselId}\n";
    echo "Subject: {$subject}\n";
    echo "Rows: " . count($rows) . "\n";
    foreach ($rows as $r) {
        $v = $r['vessel_label'] ?? '—';
        $t = $r['docName'] ?? '—';
        $d = $r['expDate'] ?? '—';
        $status = ($d && $d < $today) ? 'Expired' : 'Due soon';
        echo "- {$v} | {$t} | {$d} | {$status}\n";
    }
    exit(0);
}

// --- Send for real ---
$res = sendEmail($alertEmail, $alertName, $subject, $html);

// Console-friendly summary
if (is_array($res)) {
    echo "Email result: " . json_encode($res) . "\n";
} else {
    echo "Email result: {$res}\n";
}
