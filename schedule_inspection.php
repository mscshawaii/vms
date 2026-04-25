<?php
/**
 * schedule_inspection.php — modernized UI shell
 * Keeps existing logic intact:
 * - Flatpickr date+time pickers (preferred_dates[])
 * - Local TCPDF include + cache
 * - OCMI resolver (uscg_contacts) + dynamic greeting
 * - TCPDF packet + mailto + PHPMailer “Send via VMS”
 * - CSRF + ACL
 * - Persist/reuse generated PDF across requests
 */

session_start();
require 'session_check.php';
require 'db_connect.php';
require_once __DIR__ . '/pdf_common.php';

date_default_timezone_set('Pacific/Honolulu');

/* =========================
   CONFIG
   ========================= */
$MSCS_OWNER_ID    = 1;
$ADMIN_ROLE_ID    = 1;
$defaultOCMI      = 'D14-SMB-SEC-HONOLULU-INSPECTIONS@uscg.mil';
$defaultSectorTag = 'Sector Honolulu';

$uploadsBaseFS    = __DIR__ . '/uploads/vessels';
$scriptBase       = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uploadsBaseWeb   = $scriptBase . '/uploads/vessels';

// TCPDF
$tcpdfPath  = __DIR__ . '/tcpdf/tcpdf.php';
$tcpdfCache = __DIR__ . '/tcpdf_cache';
if (!is_dir($tcpdfCache)) { @mkdir($tcpdfCache, 0775, true); }
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', rtrim($tcpdfCache, '/\\') . DIRECTORY_SEPARATOR);
}
if (!is_file($tcpdfPath)) {
    http_response_code(500);
    exit('PDF engine missing: ' . $tcpdfPath);
}
require_once $tcpdfPath;

// PHPMailer
$composerAutoload = __DIR__ . '/vendor/autoload.php';
$mailCfgPath      = __DIR__ . '/config_mail.php';

/* =========================
   HELPERS
   ========================= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function safeStr($v, $max=220){
    $v = trim((string)$v);
    $v = preg_replace('/\s+/', ' ', $v);
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}

function prettyDateTime($s) {
    if (!$s) return $s;
    $ts = strtotime($s);
    if (!$ts) return $s;
    return date('l, F j, Y \a\t g:ia', $ts);
}

function nl2br_html(string $s): string {
    return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
}

/* =========================
   INPUT + ACL
   ========================= */
$vessel_id = (int)($_GET['vessel_id'] ?? $_POST['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id    = (int)($_SESSION['role_id'] ?? 0);

// Load vessel + owner/company
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        o.owner_id                           AS owner_owner_id,
        o.company_name                       AS owner_name,
        o.email                              AS owner_email,
        o.phone                              AS owner_phone,
        o.contact_name                       AS owner_contact_name,
        o.logo_path                          AS company_logo_path,
        o.primary_contact_user_id,
        o.alt_contact_user_id,
        u1.fName                             AS primary_fname,
        u1.lName                             AS primary_lname,
        u1.phoneNumber                       AS primary_phone,
        u1.email                             AS primary_email,
        u2.fName                             AS alt_fname,
        u2.lName                             AS alt_lname,
        u2.phoneNumber                       AS alt_phone,
        u2.email                             AS alt_email
    FROM vessels v
    LEFT JOIN owners o ON o.owner_id = v.company_id
    LEFT JOIN users  u1 ON u1.id = o.primary_contact_user_id
    LEFT JOIN users  u2 ON u2.id = o.alt_contact_user_id
    WHERE v.vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    http_response_code(404);
    exit('Vessel not found');
}

$composeName = function($fn, $ln) {
    $fn = trim((string)$fn);
    $ln = trim((string)$ln);
    return trim($fn . ' ' . $ln);
};

$primaryPOC = [
    'name'  => ($n = $composeName($vessel['primary_fname'] ?? '', $vessel['primary_lname'] ?? '')) ?: ($vessel['owner_contact_name'] ?? ''),
    'phone' => ($vessel['primary_phone'] ?? '') ?: ($vessel['owner_phone'] ?? ''),
    'email' => ($vessel['primary_email'] ?? '') ?: ($vessel['owner_email'] ?? ''),
];

$secondaryPOC = [
    'name'  => $composeName($vessel['alt_fname'] ?? '', $vessel['alt_lname'] ?? ''),
    'phone' => $vessel['alt_phone'] ?? '',
    'email' => $vessel['alt_email'] ?? '',
];

// Tenant ACL
$allow = false;
if ($role_id === $ADMIN_ROLE_ID || $company_id === $MSCS_OWNER_ID) $allow = true;
if ($company_id === (int)$vessel['company_id']) $allow = true;
if (!$allow) {
    http_response_code(403);
    exit('Forbidden');
}

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
} else {
    $posted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted)) {
        http_response_code(400);
        exit('Invalid request token');
    }
}

/* =========================
   RESOLVE OCMI EMAIL + GREETING
   ========================= */
$ocmiEmail    = null;
$ocmiExtraCC  = null;
$ocmiRegion   = null;

if (!empty($vessel['ocmi_contact_id'])) {
    $q = $pdo->prepare("
        SELECT region_name, port_name, email_to, email_cc
        FROM uscg_contacts
        WHERE contact_id = ? AND active = 1
    ");
    $q->execute([(int)$vessel['ocmi_contact_id']]);
    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $ocmiRegion  = trim((string)$row['region_name']);
        $ocmiEmail   = trim((string)$row['email_to']);
        $ocmiExtraCC = trim((string)($row['email_cc'] ?? ''));
    }
}

if (!$ocmiEmail && !empty($vessel['ocmi_email'])) {
    $ocmiEmail = trim((string)$vessel['ocmi_email']);
}
if (!$ocmiEmail) $ocmiEmail = $defaultOCMI;

$greetingTag = $ocmiRegion ? ("USCG " . $ocmiRegion) : $defaultSectorTag;

/* =========================
   GET: SHOW FORM
   ========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (isset($_POST['action']) && $_POST['action'] === 'back_to_form')) {
    $vName = $vessel['vesselName'] ?? ('Vessel '.$vessel_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Schedule Inspection: <?= h($vName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .insp-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .insp-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .insp-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .insp-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .insp-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .insp-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .insp-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .insp-radio-wrap {
            border: 1px solid #dbe4ee;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
        }

        .insp-badge-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        @media (min-width: 768px) {
            .insp-form-grid.dates {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Schedule Inspection';
$back_link = "vessel_dashboard.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="insp-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="insp-header">
                    <div>
                        <h1 class="insp-title">Schedule Inspection</h1>
                        <p class="insp-subtitle">
                            <?= h($vName) ?> · Prepare an inspection request packet and email workflow
                        </p>
                    </div>

                    <div class="insp-actions">
                        <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>">Back to Vessel</a>
                    </div>
                </div>

                <div class="insp-badge-row">
                    <span class="badge bg-info text-dark">OCMI target: <?= h($ocmiEmail) ?></span>
                    <?php if ($ocmiRegion): ?>
                        <span class="badge bg-secondary">Greeting: <?= h($greetingTag) ?> Inspections Team</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($vessel['ocmi_contact_id'])): ?>
                    <div class="alert alert-warning">
                        No island or port scheduler is assigned to this vessel. Using fallback address.
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>">Return to vessel dashboard</a> to assign one if needed.
                    </div>
                <?php endif; ?>

                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                    <input type="hidden" name="action" value="generate">

                    <div class="mb-3">
                        <label class="form-label">Preferred Inspection Dates/Times</label>
                        <div class="insp-form-grid dates">
                            <div>
                                <input type="text" name="preferred_dates[]" class="form-control datetimepicker" placeholder="Select date/time" required>
                            </div>
                            <div>
                                <input type="text" name="preferred_dates[]" class="form-control datetimepicker" placeholder="Select date/time" required>
                            </div>
                            <div>
                                <input type="text" name="preferred_dates[]" class="form-control datetimepicker" placeholder="Select date/time" required>
                            </div>
                        </div>
                        <div class="form-text">Pick up to three preferred date/time options.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Inspection Location</label>
                        <input type="text" name="location" class="form-control" placeholder="Pier/Harbor, island" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Inspection Type</label>

                        <div class="insp-radio-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="inspection_type" id="insp_renewal" value="COI Renewal" required>
                                <label class="form-check-label" for="insp_renewal">
                                    COI Renewal
                                </label>
                            </div>

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="radio" name="inspection_type" id="insp_annual" value="Annual Inspection" required>
                                <label class="form-check-label" for="insp_annual">
                                    Annual Inspection
                                </label>
                            </div>

                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="inspection_drydock" id="insp_drydock" value="1">
                                <label class="form-check-label" for="insp_drydock">
                                    Dry Dock Inspection (if applicable)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-4">
                        <button class="btn btn-primary" type="submit">Generate Packet &amp; Email</button>
                        <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr(".datetimepicker", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minDate: "today",
    time_24hr: false
});

const forms = document.querySelectorAll('.needs-validation');
Array.from(forms).forEach(form => {
    form.addEventListener('submit', e => {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
});
</script>

</body>
</html>
<?php
    exit;
}

/* =========================
   POST: inputs
   ========================= */
$action = $_POST['action'] ?? '';

$preferredDates = $_POST['preferred_dates'] ?? [];
$preferredDates = array_values(array_filter(array_map(function($d){
    return safeStr($d, 120);
}, $preferredDates)));

$location = safeStr($_POST['location'] ?? '', 160);

$inspectionType = safeStr($_POST['inspection_type'] ?? '', 40);
$isDryDock      = !empty($_POST['inspection_drydock']);

if ($action === 'send_vms' && empty($inspectionType) && !empty($_SESSION['last_inspection_inspection'])) {
    $inspectionType = $_SESSION['last_inspection_inspection']['type'] ?? '';
    $isDryDock      = !empty($_SESSION['last_inspection_inspection']['dry_dock']);
}

$inspectionLabel = $inspectionType;
if ($inspectionLabel && $isDryDock) {
    $inspectionLabel .= ' with Dry Dock';
}

if ($action === 'generate') {
    if (count($preferredDates) === 0 || !$location || !$inspectionType) {
        http_response_code(400);
        exit('Missing required fields');
    }
}

$vName       = $vessel['vesselName'] ?? ('Vessel '.$vessel_id);
$officialNo  = $vessel['vesselON']   ?? '—';
$ownerName   = $vessel['owner_name'] ?? '—';
$prettyToday = date('F j, Y');

/* =========================
   PDF paths (default)
   ========================= */
$fnameSafe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $vName);
$dirFS     = $uploadsBaseFS . "/{$vessel_id}/outgoing";
$dirWeb    = $uploadsBaseWeb . "/{$vessel_id}/outgoing";
if (!is_dir($dirFS)) { @mkdir($dirFS, 0775, true); }

$pdfFS  = null;
$pdfWeb = null;

if (!empty($_POST['pdf_fs']))  $pdfFS  = (string)$_POST['pdf_fs'];
if (!empty($_POST['pdf_web'])) $pdfWeb = (string)$_POST['pdf_web'];

if (!$pdfFS && !empty($_SESSION['last_inspection_pdf']['fs'])) {
    $pdfFS  = $_SESSION['last_inspection_pdf']['fs'];
    $pdfWeb = $_SESSION['last_inspection_pdf']['web'] ?? null;
}

/* =========================
   Generate PDF when action=generate
   ========================= */
if ($action === 'generate') {
    $today     = date('Y-m-d_His');
    $baseName  = "Inspection_Packet_{$fnameSafe}_{$today}.pdf";
    $pdfFS     = $dirFS . '/' . $baseName;
    $pdfWeb    = $dirWeb . '/' . $baseName;

    $rightLogoFS = (!empty($vessel['company_logo_path']) && is_file(__DIR__ . '/' . ltrim($vessel['company_logo_path'], '/')))
        ? realpath(__DIR__ . '/' . ltrim($vessel['company_logo_path'],'/'))
        : null;

    $opts = [
        'preferredDates' => $preferredDates,
        'subtitle'       => date('F j, Y'),
        'stream'         => 'F',
        'outfile'        => $pdfFS,
        'rightLogoFS'    => $rightLogoFS,
        'watermark'      => true,
    ];

    $built = vms_render_vessel_profile_pdf($pdo, $vessel_id, $opts);

    if (!$built || !is_file($built)) {
        http_response_code(500);
        exit('Failed to create PDF file');
    }

    $_SESSION['last_inspection_pdf'] = [
        'fs'  => $pdfFS,
        'web' => $pdfWeb
    ];

    $_SESSION['last_inspection_inspection'] = [
        'type'     => $inspectionType,
        'dry_dock' => $isDryDock,
        'label'    => $inspectionLabel
    ];
}

if (!$pdfFS || !$pdfWeb) {
    // allow render to show error
}

/* =========================
   BUILD EMAIL TEXT
   ========================= */
$lines = [];
$lines[] = "Aloha {$greetingTag} Inspections Team!";
$lines[] = "";
$lines[] = "Inspection Type: {$inspectionLabel}";
$lines[] = "";
$lines[] = "On behalf of {$ownerName} I would like to schedule an inspection for the vessel {$vName} (Official No. {$officialNo}). We have identified the following dates and times that best accommodate our schedule and are listed in our order of preference. Please select the date your office can accommodate, or reply with your availability.";
$lines[] = "";
foreach ($preferredDates as $idx => $d) {
    $lines[] = ($idx + 1) . ") " . prettyDateTime($d);
}
$lines[] = "";
$lines[] = "Inspection Location: {$location}";
$lines[] = "";
$lines[] = "Primary POC:";
$lines[] = "  Name : {$primaryPOC['name']}";
$lines[] = "  Phone: {$primaryPOC['phone']}";
$lines[] = "  Email: {$primaryPOC['email']}";
$lines[] = "";
$lines[] = "Secondary POC:";
$lines[] = "  Name : {$secondaryPOC['name']}";
$lines[] = "  Phone: {$secondaryPOC['phone']}";
$lines[] = "  Email: {$secondaryPOC['email']}";
$lines[] = "";
$lines[] = "MSCS POC:";
$lines[] = "  Name : Sean Keeman";
$lines[] = "  Phone: 907-957-3161";
$lines[] = "  Email: info@mschawaii.org";
$lines[] = "";
$lines[] = "MSCS Secondary POC:";
$lines[] = "  Name : Anna Keeman";
$lines[] = "  Phone: 810-824-8398";
$lines[] = "  Email: anna@mschawaii.org";
$lines[] = "";
$lines[] = "Additionally, attached you will find a PDF summary of documents and equipment expirations generated by VMS.";
$lines[] = "";
$lines[] = "When responding, please reply-all to ensure all involved parties are kept informed of the scheduled inspection.";
$lines[] = "";
$lines[] = "Mahalo,";
$lines[] = "";
$lines[] = "Sean Keeman";
$lines[] = "MSCS Hawaii";
$lines[] = "907-957-3161";
$lines[] = "www.mschawaii.org";

$bodyStr = rawurlencode(implode("\r\n", $lines));
$subject = rawurlencode("Request to Schedule Inspection ({$inspectionLabel}) – {$vName}");
$mailto  = "mailto:{$ocmiEmail}?subject={$subject}&body={$bodyStr}";

/* =========================
   SEND VIA VMS (PHPMailer)
   ========================= */
$flash = null;

if ($action === 'send_vms') {
    if (!empty($_POST['pdf_fs']))  $pdfFS  = (string)$_POST['pdf_fs'];
    if (!empty($_POST['pdf_web'])) $pdfWeb = (string)$_POST['pdf_web'];

    if (!$pdfFS && !empty($_SESSION['last_inspection_pdf']['fs'])) {
        $pdfFS  = $_SESSION['last_inspection_pdf']['fs'];
        $pdfWeb = $_SESSION['last_inspection_pdf']['web'] ?? $pdfWeb;
    }

    if (!$pdfFS || !is_file($pdfFS)) {
        $flash = ['type'=>'danger','msg'=>'PDF file not found. Please regenerate the inspection packet.'];
    } elseif (!file_exists($composerAutoload)) {
        $flash = ['type'=>'danger','msg'=>'Email system error: Composer autoload missing.'];
    } elseif (!file_exists($mailCfgPath)) {
        $flash = ['type'=>'danger','msg'=>'Email system error: config_mail.php missing.'];
    } else {
        require $composerAutoload;
        $mailCfg = require $mailCfgPath;

        if (!is_array($mailCfg)) {
            throw new RuntimeException('Invalid mail configuration: config_mail.php must return an array');
        }

        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

            $mailer->isSMTP();
            $mailer->SMTPAuth = true;
            $mailer->Host     = $mailCfg['smtp_host'];
            $mailer->Port     = (int)$mailCfg['smtp_port'];
            $mailer->CharSet  = 'UTF-8';

            if (($mailCfg['smtp_secure'] ?? '') === 'tls') {
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (($mailCfg['smtp_secure'] ?? '') === 'ssl') {
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->Username = $mailCfg['smtp_user'];
            $mailer->Password = $mailCfg['smtp_pass'];

            if (!empty($mailCfg['smtp_debug'])) {
                $mailer->SMTPDebug   = (int)$mailCfg['smtp_debug'];
                $mailer->Debugoutput = 'error_log';
            }

            $mailer->setFrom($mailCfg['from_email'], $mailCfg['from_name']);
            $mailer->addAddress($ocmiEmail);

            $ccList = [];
            if (!empty($primaryPOC['email']))   $ccList[] = $primaryPOC['email'];
            if (!empty($secondaryPOC['email'])) $ccList[] = $secondaryPOC['email'];
            $ccList[] = 'info@mschawaii.org';
            $ccList[] = 'anna@mschawaii.org';

            if (!empty($ocmiExtraCC)) {
                foreach (explode(',', $ocmiExtraCC) as $cc) {
                    $cc = trim($cc);
                    if ($cc) $ccList[] = $cc;
                }
            }

            foreach (array_unique(array_filter($ccList)) as $cc) {
                $mailer->addCC($cc);
            }

            if (!empty($_SESSION['email'])) {
                $mailer->addReplyTo(
                    $_SESSION['email'],
                    trim(($_SESSION['fName'] ?? '') . ' ' . ($_SESSION['lName'] ?? ''))
                );
            }

            if (!empty($mailCfg['bcc_log'])) {
                $mailer->addBCC($mailCfg['bcc_log']);
            }

            $mailer->Subject = "Request to Schedule Inspection ({$inspectionLabel}) – {$vName}";

            $htmlBody = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; color:#222; }
  .section { margin-bottom: 16px; }
  .label { font-weight: bold; }
  ul { margin: 6px 0 0 20px; }
</style>
</head>
<body>

<p><strong>Aloha ' . h($greetingTag) . ' Inspections Team!</strong></p>

<div class="section">
  <p>
    On behalf of <strong>' . h($ownerName) . '</strong>, I would like to schedule an inspection
    for the vessel <strong>' . h($vName) . '</strong>
    (Official No. ' . h($officialNo) . ').
  </p>
  <p>
    We have identified the following preferred dates and times. Please select the date your
    office can accommodate, or reply with your availability.
  </p>
</div>

<div class="section">
  <span class="label">Inspection Type:</span><br>
  ' . h($inspectionLabel) . '
</div>

<div class="section">
  <span class="label">Preferred Dates:</span>
  <ul style="margin-top:6px; margin-bottom:6px;">
';

            foreach ($preferredDates as $d) {
                $htmlBody .= '<li>' . h(prettyDateTime($d)) . '</li>';
            }

            $htmlBody .= '
  </ul>
</div>

<div class="section">
  <span class="label">Inspection Location:</span><br>
  ' . h($location) . '
</div>

<div class="section">
  <span class="label">Primary POC</span><br>
  ' . h($primaryPOC['name']) . '<br>
  ' . h($primaryPOC['phone']) . '<br>
  ' . h($primaryPOC['email']) . '
</div>

<div class="section">
  <span class="label">Secondary POC</span><br>
  ' . h($secondaryPOC['name']) . '<br>
  ' . h($secondaryPOC['phone']) . '<br>
  ' . h($secondaryPOC['email']) . '
</div>

<div class="section">
  <span class="label">MSCS POC</span><br>
  Sean Keeman<br>
  907-957-3161<br>
  info@mschawaii.org
</div>

<p>
  Additionally, attached you will find a PDF summary of documents and equipment expirations
  generated by VMS.
</p>

<p>
  When responding, please reply-all to ensure all involved parties are kept informed.
</p>

<p>
  Mahalo,<br>
  <strong>Sean Keeman</strong><br>
  MSCS Hawaii<br>
  www.mschawaii.org
</p>

</body>
</html>';

            $mailer->isHTML(true);
            $mailer->Body    = $htmlBody;
            $mailer->AltBody = implode("\r\n", $lines);

            $mailer->addAttachment($pdfFS, basename($pdfFS));
            $mailer->send();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_sends
                        (user_id, company_id, vessel_id, subject, recipients, result_json)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'] ?? null,
                    $company_id,
                    $vessel_id,
                    $mailer->Subject,
                    implode(',', array_merge([$ocmiEmail], $ccList)),
                    json_encode(['status'=>'sent'])
                ]);
            } catch (Throwable $ignored) {}

            $flash = ['type'=>'success','msg'=>'Email sent successfully via VMS.'];

        } catch (Throwable $e) {
            $flash = ['type'=>'danger','msg'=>'Failed to send email: ' . h($e->getMessage())];
        }
    }
}

/* =========================
   RENDER RESULT PAGE
   ========================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Inspection Packet Ready</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .insp-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .result-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .result-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
<?php
$title = 'Inspection Packet Ready';
$back_link = "vessel_dashboard.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="insp-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <h1 class="page-title mb-2">Inspection Packet Ready</h1>
                <p class="page-subtitle mb-3">
                    <?= h($vName) ?> · Generated <?= h($prettyToday) ?> (HST)
                </p>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-info text-dark">To: <?= h($ocmiEmail) ?></span>
                    <span class="badge bg-secondary">Greeting: <?= h($greetingTag) ?> Inspections Team</span>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= h($flash['type']) ?>"><?= $flash['msg'] ?></div>
                <?php endif; ?>

                <?php if (!$pdfWeb): ?>
                    <div class="alert alert-warning">
                        PDF was not generated or could not be resolved. Please go back and regenerate.
                    </div>
                <?php else: ?>
                    <?php $cacheBust = time(); ?>
                    <div class="mb-3">
                        <strong>PDF link:</strong>
                        <a href="<?= h($pdfWeb) ?>?t=<?= $cacheBust ?>" target="_blank">
                            <?= h($pdfWeb) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="result-actions mb-3">
                    <?php if ($pdfWeb): ?>
                        <a class="btn btn-success" href="<?= h($pdfWeb) ?>" target="_blank">Download PDF</a>
                    <?php endif; ?>

                    <a class="btn btn-primary" href="<?= h($mailto) ?>">Compose in Email Client</a>

                    <form method="post" class="d-inline" onsubmit="return confirm('Send this inspection request via VMS email now?');">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                        <input type="hidden" name="action" value="send_vms">
                        <input type="hidden" name="pdf_fs" value="<?= h($pdfFS ?? '') ?>">
                        <input type="hidden" name="pdf_web" value="<?= h($pdfWeb ?? '') ?>">

                        <?php foreach ($preferredDates as $pd): ?>
                            <input type="hidden" name="preferred_dates[]" value="<?= h($pd) ?>">
                        <?php endforeach; ?>
                        <input type="hidden" name="location" value="<?= h($location) ?>">

                        <button class="btn btn-warning" type="submit" <?= (!$pdfFS || !is_file($pdfFS)) ? 'disabled' : '' ?>>
                            Send via VMS (attach PDF)
                        </button>
                    </form>

                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                        <input type="hidden" name="action" value="back_to_form">
                        <button class="btn btn-outline-dark" type="submit">Edit Dates/Location</button>
                    </form>

                    <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>">Back to Vessel</a>
                </div>

                <label class="form-label">Email Preview</label>
                <textarea id="emailPreview" class="form-control" rows="14"><?= h(implode("\r\n", $lines)) ?></textarea>
            </div>

        </div>
    </div>
</div>

</body>
</html>