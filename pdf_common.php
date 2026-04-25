<?php
// pdf_common.php — shared TCPDF theme + helpers used across PDFs

// --- TCPDF include & cache ---
$tcpdfPath  = __DIR__ . '/tcpdf/tcpdf.php';
$tcpdfCache = __DIR__ . '/tcpdf_cache';
if (!is_dir($tcpdfCache)) @mkdir($tcpdfCache, 0775, true);
if (!defined('K_PATH_CACHE')) define('K_PATH_CACHE', rtrim($tcpdfCache, '/\\') . DIRECTORY_SEPARATOR);
if (!is_file($tcpdfPath)) { http_response_code(500); exit('PDF engine missing.'); }
require_once $tcpdfPath;

// --- Helpers (guarded to avoid redeclare) ---
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('safe')) {
  function safe($v){ return ($v!==null && $v!=='') ? h($v) : '—'; }
}
if (!function_exists('vms_val')) {
  function vms_val($arr, $keys, $fallback='—') {
    foreach ((array)$keys as $k) {
      if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== '0000-00-00') return $arr[$k];
    }
    return $fallback;
  }
}
if (!function_exists('vms_fmtdate')) {
  function vms_fmtdate($s) {
    if (!$s || $s==='0000-00-00') return '—';
    $ts = strtotime($s);
    return $ts ? date('M j, Y', $ts) : '—';
  }
}
if (!function_exists('fmtDate')) {
  // alias kept for legacy calls in your file
  function fmtDate($d){ return vms_fmtdate($d); }
}
if (!function_exists('prettyDateTime')) {
  function prettyDateTime($s) {
    if (!$s) return '—';
    $ts = strtotime($s);
    return $ts ? date('l, F j, Y \a\t g:ia', $ts) : '—';
  }
}
if (!function_exists('statusColor')) {
  function statusColor($s){
    return match($s){
      'EXPIRED' => '#b71c1c',
      'DUE SOON' => '#e65100',
      default => '#1b5e20'
    };
  }
}
if (!function_exists('dueStatus')) {
  function dueStatus($dateStr){
    if (!$dateStr || $dateStr==='0000-00-00') return '—';
    $today = new DateTime('today');
    $d = new DateTime($dateStr);
    if ($d < $today) return 'EXPIRED';
    $days = (int)$today->diff($d)->format('%r%a');
    return ($days <= 30) ? 'DUE SOON' : 'OK';
  }
}
if (!function_exists('vms_status_rank')) {
  function vms_status_rank($s){
    return match($s){
      'EXPIRED'  => 0,
      'DUE SOON' => 1,
      'OK'       => 2,
      default    => 3
    };
  }
}
if (!function_exists('vms_cell_table')) {
  function vms_cell_table(array $rows, $labelW='40%', $pt=8.5, $pad=3) {
    $html  = '<table cellpadding="'.(int)$pad.'" cellspacing="0" border="0" width="100%" style="font-size:'.$pt.'pt; line-height:1.2">';
    foreach ($rows as [$label,$value]) {
      $html .= '<tr>';
      $html .= '<td width="'.h($labelW).'" style="font-weight:bold; background:#f7f7f7;">'.h($label).'</td>';
      $html .= '<td>'.h((string)$value).'</td>';
      $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
  }
}
if (!function_exists('vms_resolve_photo')) {
  function vms_resolve_photo(string $photoPath, string $uploadsBaseFS, string $uploadsBaseWeb, int $vessel_id): array {
    $photoPath = trim($photoPath);
    $norm = preg_replace('#[\\\\]+#', '/', $photoPath);
    $uploadsRootFS  = rtrim($uploadsBaseFS, '/');
    $uploadsRootWeb = rtrim($uploadsBaseWeb, '/');
    $newDirFS  = $uploadsRootFS  . "/vessels/$vessel_id";
    $newDirWeb = $uploadsRootWeb . "/vessels/$vessel_id";
    $cFS=[]; $cURL=[];

    if ($norm !== '') {
      if ($norm[0] === '/' && strpos($norm, '/uploads/') !== false) $cFS[] = $norm;
      if (strpos($norm, '/uploads/') === 0) {
        $cFS[] = __DIR__ . $norm;
        $cURL[] = $uploadsRootWeb . substr($norm, strlen('/uploads'));
      }
      if (strpos($norm, 'uploads/') === 0) {
        $cFS[]  = __DIR__ . '/' . $norm;
        $cURL[] = $uploadsRootWeb . substr($norm, strlen('uploads'));
      }
    }

    $base = basename($norm);
    if ($base !== '') {
      $cFS[]="$newDirFS/$base"; $cURL[]="$newDirWeb/$base";
      $cFS[]="$uploadsRootFS/$base"; $cURL[]="$uploadsRootWeb/$base";
    }

    $cFS=array_values(array_unique($cFS));
    $cURL=array_values(array_unique($cURL));

    foreach ($cFS as $fs) {
      $fsReal = realpath($fs) ?: $fs;
      if (@is_file($fsReal) && @is_readable($fsReal)) {
        $type = @exif_imagetype($fsReal);
        if ($type===IMAGETYPE_JPEG || $type===IMAGETYPE_PNG) return ['fs'=>$fsReal,'url'=>null,'reason'=>null];
      }
    }
    foreach ($cURL as $url) return ['fs'=>null,'url'=>$url,'reason'=>'fs_not_found_or_unreadable'];
    return ['fs'=>null,'url'=>null,'reason'=>'no_candidate_matched'];
  }
}

// --- Header class (identical look, with optional watermark) ---
if (!class_exists('VMS_Header')) {
  class VMS_Header extends TCPDF {
    public string $leftLogo   = '';
    public string $rightLogo  = '';
    public string $titleLine  = '';
    public string $subtitle   = '';

    // NEW: watermark support
    public string $watermarkFS   = '';   // set to a filesystem path to enable
    public float  $watermarkAlpha = 0.06;

    public float $logoH_mm    = 40.0;
    public float $maxLogoW_mm = 40.0;
    public float $topY_mm     = 10.0;

    public function Header() {
      // --- OPTIONAL WATERMARK (drawn behind everything) ---
      if ($this->watermarkFS && @is_file($this->watermarkFS)) {
        $pw = $this->getPageWidth();
        $ph = $this->getPageHeight();

        $this->SetAlpha($this->watermarkAlpha);
        $this->StartTransform();
        $this->Rotate(45, $pw/2, $ph/2);

        // big, centered watermark
        $w = min($pw * 0.90, 220);        // width in mm
        $x = ($pw - $w) / 2.0;
        $y = ($ph - $w) / 2.0;
        $this->Image($this->watermarkFS, $x, $y, $w, 0, '', '', '', false, 300);

        $this->StopTransform();
        $this->SetAlpha(1);
      }

      // --- your existing header logos + title block ---
      $yTop  = $this->topY_mm;
      $M     = $this->getMargins();
      $pageW = $this->getPageWidth();

      // Treat both logos as fitting into the same bounding box.
      $boxH = $this->logoH_mm;      // max height
      $boxW = $this->maxLogoW_mm;   // max width

      // Helper: fit image into boxW × boxH while preserving aspect ratio
      $fitInBox = function(string $path) use ($boxW, $boxH): array {
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) return [0.0, 0.0];
        $ratio = $info[0] / $info[1]; // w/h
        if ($ratio * $boxH > $boxW) { // width-limited
          $w = $boxW; $h = $w / $ratio;
        } else {                      // height-limited
          $h = $boxH; $w = $h * $ratio;
        }
        return [$w, $h];
      };

      // Reserve symmetric boxes left and right so the title stays centered visually
      $leftBoxX  = $M['left'];
      $rightBoxX = $pageW - $M['right'] - $boxW;
      $boxY      = $yTop;

      // LEFT LOGO
      if ($this->leftLogo && file_exists($this->leftLogo)) {
        [$w, $h] = $fitInBox($this->leftLogo);
        if ($w > 0 && $h > 0) {
          $x = $leftBoxX + ($boxW - $w) / 2.0;
          $y = $boxY     + ($boxH - $h) / 2.0;
          $this->Image($this->leftLogo, $x, $y, $w, $h, '', '', '', false, 300);
        }
      }

      // RIGHT LOGO
      if ($this->rightLogo && file_exists($this->rightLogo)) {
        [$w, $h] = $fitInBox($this->rightLogo);
        if ($w > 0 && $h > 0) {
          $x = $rightBoxX + ($boxW - $w) / 2.0;
          $y = $boxY      + ($boxH - $h) / 2.0;
          $this->Image($this->rightLogo, $x, $y, $w, $h, '', '', '', false, 300);
        }
      }

      // Title block
      $textBlockH = 15.0;
      $textY = $yTop + ($boxH - $textBlockH) / 2.0;

      $this->SetY($textY);
      $this->SetFont('helvetica','B',20);
      $this->Cell(0, 9, $this->titleLine, 0, 1, 'C');
      $this->SetFont('helvetica','',8);
      $this->Cell(0, 6, $this->subtitle, 0, 1, 'C');
    }

    public function Footer() {
      $this->SetY(-15);
      $this->SetFont('helvetica','I',8);
      $this->Cell(0, 7, 'Generated by Vessel Management System', 0, 0, 'C');
    }
  }
}

// --- Factory: build a themed PDF identical to vessel_profile_pdf ---
if (!function_exists('vms_build_pdf')) {
  function vms_build_pdf(
    string $title,
    ?string $subtitle,
    ?string $leftLogoFS,
    ?string $rightLogoFS,
    ?string $watermarkFS = null,
    ?float  $watermarkAlpha = null
  ): VMS_Header {
    $pdf = new VMS_Header('P','mm','LETTER', true, 'UTF-8', false);
    $pdf->leftLogo  = ($leftLogoFS  && is_file($leftLogoFS))  ? realpath($leftLogoFS)  : '';
    $pdf->rightLogo = ($rightLogoFS && is_file($rightLogoFS)) ? realpath($rightLogoFS) : '';
    $pdf->titleLine = $title;
    $pdf->subtitle  = $subtitle ?? date('F j, Y');

    // NEW: watermark (must be set BEFORE AddPage)
    if ($watermarkFS && is_file($watermarkFS)) {
      $pdf->watermarkFS = realpath($watermarkFS);
      if ($watermarkAlpha !== null) {
        $pdf->watermarkAlpha = max(0.01, min(0.5, (float)$watermarkAlpha));
      }
    }

    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(12, 52, 12);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica','',9);
    $pdf->setCellHeightRatio(1.12);
    return $pdf;
  }
}


// pdf_common.php
function vms_render_vessel_profile_pdf(PDO $pdo, int $vessel_id, array $opts = []): ?string {
  // Options
  $preferredDates = $opts['preferredDates'] ?? [];
  $subtitle       = $opts['subtitle']       ?? date('F j, Y');
  $outfile        = $opts['outfile']        ?? null;
  $stream         = $opts['stream']         ?? 'I';
  $rightLogoFS    = $opts['rightLogoFS']    ?? null;

  // Load vessel (as you already do)
  $stmt = $pdo->prepare("
    SELECT v.*, o.logo_path AS company_logo_path
    FROM vessels v
    LEFT JOIN owners o ON o.owner_id = v.company_id
    WHERE v.vessel_id = ?
    LIMIT 1
  ");
  $stmt->execute([$vessel_id]);
  $vessel = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$vessel) { throw new RuntimeException('Vessel not found'); }

   // 👇 Build the themed PDF now (ONE AddPage happens in the factory)
  $leftLogoFS = __DIR__ . '/assets/mscs_logo.png';
  $pdf = vms_build_pdf('Vessel Profile', $subtitle, $leftLogoFS, $rightLogoFS);

  // Enable watermark when requested
if (!empty($opts['watermark'])) {
  $wm = __DIR__ . '/assets/vms-logo.png';
  if (is_file($wm)) $pdf->watermarkFS = realpath($wm);
}

// --------------- BEGIN: content copied from vessel_profile_pdf.php ---------------

// Inputs from caller (safe defaults)
$uploadsBaseFS   = $opts['uploadsBaseFS']   ?? (__DIR__ . '/uploads');
$scriptBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$uploadsBaseWeb  = $opts['uploadsBaseWeb']  ?? ($scriptBase . '/uploads');
$defaultSectorTag= $opts['defaultSectorTag']?? 'Sector Honolulu';

 // Initialize OCMI vars
  $ocmiRegion = null; $ocmiEmail = null;
  if (!empty($vessel['ocmi_contact_id'])) {
    $q = $pdo->prepare("SELECT region_name, email_to FROM uscg_contacts WHERE contact_id=? AND active=1");
    $q->execute([(int)$vessel['ocmi_contact_id']]);   // correct param
    if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
      $ocmiRegion = trim((string)$r['region_name']);
      $ocmiEmail  = trim((string)$r['email_to']);
    }
  }

// Layout numbers
$M       = $pdf->getMargins();
$pageW   = $pdf->getPageWidth();
$usableW = $pageW - $M['left'] - $M['right'];
$gutter    = 6;
$leftColW  = round($usableW * 0.48, 1);
$rightColW = $usableW - $leftColW - $gutter;

$yStart = $pdf->GetY();
$xLeft  = $M['left'];
$xRight = $M['left'] + $leftColW + $gutter;

// Identity rows
$identityRows = [
  ['Vessel Name',            vms_val($vessel,['vesselName'])],
  ['Official Number / Reg.', vms_val($vessel,['vesselON'])],
  ['Call Sign',              vms_val($vessel,['callSign'])],
  ['MMSI',                   vms_val($vessel,['mmsi'])],
  ['Hailing Port',           vms_val($vessel,['hailingPort'])],
  ['EPIRB Hex ID',           vms_val($vessel,['epirbHexId'])],
  ['Hull ID (HIN)',          vms_val($vessel,['hin'])],
  ['Cognizant OCMI / Scheduler', trim(($ocmiRegion ?: $defaultSectorTag).' ('.($ocmiEmail ?: '—').')')],
];
$identityTbl = vms_cell_table($identityRows, '42%', 8.5, 3);

// Photo (robust resolver)
$imgPlaced  = false;
$imgBottomY = $yStart;
try {
  $resolved = null;
  if (!empty($vessel['photo_path'])) {
    $resolved = vms_resolve_photo((string)$vessel['photo_path'], $uploadsBaseFS, $uploadsBaseWeb, (int)$vessel_id);
  }
  $imgSource = null;
  if (!empty($resolved['fs'])) {
    $imgSource = str_replace('\\','/',$resolved['fs']);
  } elseif (!empty($resolved['url'])) {
    $imgSource = $resolved['url'];
  }
  if ($imgSource) {
    $pdf->Image($imgSource, $xLeft, $yStart, $leftColW);
    $imgBottomY = method_exists($pdf,'getImageRBY') ? $pdf->getImageRBY() : ($yStart + 50);
    $imgPlaced = true;
  }
} catch (Throwable $e) { /* ignore photo errors */ }

if ($imgPlaced) {
  $drawW   = $leftColW;
  $borderH = max($imgBottomY - $yStart, 1);
  $pdf->SetDrawColor(210,210,210);
  $pdf->Rect($xLeft, $yStart, $drawW, $borderH);
  $pdf->SetDrawColor(0,0,0);
}

// === COI + Next Inspection Window/Type (use schema you confirmed) ===
$coiExpRaw = null;
try {
  $coi = $pdo->prepare("
    SELECT issueDate, expDate
    FROM documents
    WHERE vessel_id = ? AND docType = 'Certificate of Inspection'
    ORDER BY expDate DESC LIMIT 1
  ");
  $coi->execute([$vessel_id]);
  if ($row = $coi->fetch(PDO::FETCH_ASSOC)) {
    $coiExpRaw = $row['expDate'] ?? null;
  }
} catch (Throwable $e) {}

$lastInspectionRaw = vms_val($vessel, ['lastInspection','last_inspection'], null);
$inspectionType   = '—';
$inspectionWindow = '—';

if ($coiExpRaw && $coiExpRaw !== '0000-00-00') {
  try {
    $exp = new DateTime($coiExpRaw);
    $lastInspection = ($lastInspectionRaw && $lastInspectionRaw !== '0000-00-00') ? new DateTime($lastInspectionRaw) : null;

    $found = false;
    for ($i=1; $i<=4; $i++) {
      $annualDate = (clone $exp)->modify('-'.(5-$i).' years');
      $start = (clone $annualDate)->modify('-90 days');
      $end   = (clone $annualDate)->modify('+90 days');
      if (!$lastInspection || $lastInspection < $start) {
        $inspectionType   = "Annual (#{$i})";
        $inspectionWindow = vms_fmtdate($start->format('Y-m-d')) . ' – ' . vms_fmtdate($end->format('Y-m-d'));
        $found = true; break;
      }
    }
    if (!$found) {
      $renewalStart = (clone $exp)->modify('-90 days');
      if (!$lastInspection || $lastInspection < $renewalStart) {
        $inspectionType   = "Renewal";
        $inspectionWindow = vms_fmtdate($renewalStart->format('Y-m-d')) . ' – ' . vms_fmtdate($exp->format('Y-m-d'));
      } else {
        $inspectionType   = "Inspection Complete";
        $inspectionWindow = '—';
      }
    }
  } catch (Throwable $e) {}
}

// Preferred dates → “Inspection Dates” row
$prefLines = [];
foreach ($preferredDates as $i=>$d) { $prefLines[] = ($i+1).') '.prettyDateTime($d); }

// Drydock / mast un-step
$lastDryDock = vms_fmtdate(vms_val($vessel, [
  'lastDrydock','lastDryDock','last_drydock','lastDryDockDate','dry_dock_last','drydock_last'
], ''));
$nextDryDock = vms_fmtdate(vms_val($vessel, [
  'nextDrydock','nextDryDock','next_drydock','nextDryDockDate','dry_dock_next','drydock_next'
], ''));
$nextMastUn  = vms_fmtdate(vms_val($vessel, [
  'nextUnstep','nextMastUnstep','mast_unstep_due','mastUnstepDueOn','mast_unstep_next'
], ''));

// Left/Right blocks (exactly like vessel_profile_pdf.php)
$leftBlock = [
  ['Inspection Window',          $inspectionWindow],
  ['Next Inspection Type',       $inspectionType],
  ['Last Dry Dock',              $lastDryDock],
  ['Next Dry Dock',              $nextDryDock],
  ['Next Mast Un-step',          $nextMastUn],
  ['Last Inspection Date',       vms_fmtdate($vessel['lastInspection'] ?? null)],
  ['Next Scheduled Inspection',  vms_fmtdate($vessel['nextScheduledInspection'] ?? null)],
  ['Class',                      vms_val($vessel,['vesselClass','class','class_name'])],
  ['Class Type',                 vms_val($vessel,['classType','class_type'])],
  ['Service',                    vms_val($vessel,['vesselService','service'])],
  ['Subchapter',                 vms_val($vessel,['inspSubChapter','subchapter'])],
  ['Inspection Dates',           $prefLines ? implode('   ', $prefLines) : '—'],
];

$yesNo = function($v){ return ($v && $v!=='0' && strtolower((string)$v)!=='no') ? 'Yes' : 'No'; };
$lengthOverall = vms_val($vessel, ['length','lengthOverall','length_overall'],'');
$lengthLBP     = vms_val($vessel, ['lbp','lengthLBP','length_lbp'],'');
if ($lengthOverall !== '—' && $lengthOverall !== '') $lengthOverall .= ' ft';
if ($lengthLBP     !== '—' && $lengthLBP     !== '') $lengthLBP     .= ' ft';

$rightBlock = [
  ['SIP',                      $yesNo(vms_val($vessel,['sip','is_sip'],'0'))],
  ['Gross Tons',               vms_val($vessel,['grossTons','gross_tons'])],
  ['Net Tons',                 vms_val($vessel,['netTons','net_tons'])],
  ['Lightship Tons',           vms_val($vessel,['lightshipTons','lightship_tons'])],
  ['Length Overall',           $lengthOverall ?: '—'],
  ['Length Between Perp.',     $lengthLBP ?: '—'],
  ['Hull Material',            vms_val($vessel,['hullMaterial','hull_material'])],
  ['Auxiliary Sail',           $yesNo(vms_val($vessel,['auxSail','auxiliarySail','aux_sail'],'0'))],
  ['Horsepower',               vms_val($vessel,['horsepower','hp','engine_hp'])],
  ['Propulsion Type',          vms_val($vessel,['propulsionType','propulsion'])],
  ['Route',                    vms_val($vessel,['route','operating_route'])],
  ['Waters',                   vms_val($vessel,['waters','operating_waters'])],
  ['Keel Laid Date',           vms_fmtdate(vms_val($vessel,['keelLaidDate','keel_laid_date'],''))],
  ['Delivery Date',            vms_fmtdate(vms_val($vessel,['deliveryDate','delivery_date'],''))],
];

// Render blocks around photo (A/B branches)
if ($imgPlaced) {
  $pdf->writeHTMLCell($rightColW, 0, $xRight, $yStart, vms_cell_table($identityRows,'42%',8.5,3), 0, 0, 0, true, '', true);
  $textBottomY = $pdf->GetY();
  $blockBottom = max($imgBottomY, $textBottomY);
  $pdf->SetY($blockBottom + 6);

  $htmlLeft  = vms_cell_table($leftBlock,  '44%', 8.5, 3);
  $htmlRight = vms_cell_table($rightBlock, '44%', 8.5, 3);

  $grid  = '<table cellpadding="6" cellspacing="0" border="0" width="100%"><tr>';
  $grid .= '<td width="50%" valign="top">'.$htmlLeft.'</td>';
  $grid .= '<td width="50%" valign="top">'.$htmlRight.'</td>';
  $grid .= '</tr></table><br>';
  $pdf->writeHTML($grid, true, false, true, false, '');
} else {
  $leftMerged = array_merge($identityRows, [['','']], $leftBlock);
  $htmlLeft  = vms_cell_table($leftMerged, '44%', 8.5, 3);
  $htmlRight = vms_cell_table($rightBlock, '44%', 8.5, 3);

  $grid  = '<table cellpadding="6" cellspacing="0" border="0" width="100%"><tr>';
  $grid .= '<td width="50%" valign="top">'.$htmlLeft.'</td>';
  $grid .= '<td width="50%" valign="top">'.$htmlRight.'</td>';
  $grid .= '</tr></table><br>';
  $pdf->writeHTMLCell($usableW, 0, $M['left'], $yStart, $grid, 0, 1, 0, true, '', true);
  $pdf->Ln(2);
}

// ===== Documents =====
$pdf->SetFont('helvetica','B',12);
$pdf->Cell(0, 8, 'Documents & Expirations', 0, 1, 'L');
$pdf->SetFont('helvetica','',9);
$pdf->setCellHeightRatio(1.12);

try {
  $q = $pdo->prepare("
    SELECT
      docName                                   AS doc_name,
      COALESCE(NULLIF(docType,''), category, '') AS doc_type,
      expDate                                    AS exp_date
    FROM documents
    WHERE related_to = 'vessel'
      AND vessel_id  = ?
      AND archived_at IS NULL
  ");
  $q->execute([$vessel_id]);
  $docs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $docs = []; }

$docs = array_values(array_filter($docs, fn($d) =>
  trim((string)($d['doc_name'] ?? '')) !== '' ||
  trim((string)($d['doc_type'] ?? '')) !== '' ||
  trim((string)($d['exp_date'] ?? '')) !== ''
));

usort($docs, function($a,$b){
  $sa = dueStatus($a['exp_date'] ?? null);
  $sb = dueStatus($b['exp_date'] ?? null);
  $ra = vms_status_rank($sa);
  $rb = vms_status_rank($sb);
  if ($ra !== $rb) return $ra <=> $rb;
  $da = ($a['exp_date'] ?? '') ?: '9999-12-31';
  $db = ($b['exp_date'] ?? '') ?: '9999-12-31';
  return strcmp($da, $db);
});

if (!$docs) {
  $pdf->writeHTML('<i>No vessel documents found.</i><br><br>', true, false, true, false, '');
} else {
  $html  = '<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:9pt">';
  $html .= '<tr style="font-weight:bold; background:#f2f2f2;">
              <td width="50%">Document</td>
              <td width="22%">Type</td>
              <td width="15%">Expires</td>
              <td width="13%">Status</td>
           </tr>';
  $i=0;
  foreach ($docs as $d) {
    $exp = $d['exp_date'] ?? null;
    $st  = dueStatus($exp);
    $rowBg = ($i++ % 2 === 1) ? ' style="background:#fafafa;"' : '';
    $html .= "<tr{$rowBg}>";
    $html .= '<td>'.h($d['doc_name']).'</td>';
    $html .= '<td>'.h($d['doc_type']).'</td>';
    $html .= '<td>'.h(vms_fmtdate($exp)).'</td>';
    $html .= '<td><span style="color:'.statusColor($st).'">'.h($st).'</span></td>';
    $html .= '</tr>';
  }
  $html .= '</table><br>';
  $pdf->writeHTML($html, true, false, true, false, '');
}

// ===== Equipment (all items) =====
$pdf->SetFont('helvetica','B',12);
$pdf->Cell(0, 8, 'Equipment', 0, 1, 'L');
$pdf->SetFont('helvetica','',9);
$pdf->setCellHeightRatio(1.12);

$equipment = [];
try {
  $q = $pdo->prepare("
    SELECT 
      e.equipmentName,
      CONCAT_WS(' / ', et.name, est.name) AS type_name,
      e.modelNumber,
      e.serialNumber,
      e.expDate AS due_date
    FROM equipment e
    LEFT JOIN equipment_types     et  ON et.id  = e.equipment_type_id
    LEFT JOIN equipment_subtypes  est ON est.id = e.equipment_subtype_id
    WHERE e.vessel_id = ?
      AND (e.onBoardNotRequired IS NULL OR e.onBoardNotRequired = 0)
    ORDER BY COALESCE(et.name,''), COALESCE(est.name,''), e.equipmentName
  ");
  $q->execute([$vessel_id]);
  $equipment = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  // Fallback without joins
  try {
    $q = $pdo->prepare("
      SELECT 
        equipmentName,
        '' AS type_name,
        modelNumber,
        serialNumber,
        expDate AS due_date
      FROM equipment
      WHERE vessel_id = ?
        AND (onBoardNotRequired IS NULL OR onBoardNotRequired = 0)
      ORDER BY equipmentName
    ");
    $q->execute([$vessel_id]);
    $equipment = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e2) { $equipment = []; }
}

if (!$equipment) {
  $pdf->writeHTML('<i>No equipment recorded.</i>', true, false, true, false, '');
} else {
  $html  = '<table cellpadding="3" cellspacing="0" border="1" width="100%" style="font-size:9pt">';
  $html .= '<tr style="font-weight:bold; background:#f2f2f2;">
              <td width="38%">Equipment</td>
              <td width="24%">Type</td>
              <td width="22%">Model / Serial</td>
              <td width="8%">Due</td>
              <td width="8%">Status</td>
           </tr>';
  $i=0;
  foreach ($equipment as $e) {
    $due = $e['due_date'] ?? null;
    $st  = dueStatus($due);
    $ms  = trim(($e['modelNumber'] ?? '') . ' ' . ($e['serialNumber'] ?? ''));
    $rowBg = ($i++ % 2 === 1) ? ' style="background:#fafafa;"' : '';
    $html .= "<tr{$rowBg}>";
    $html .= '<td>'.h($e['equipmentName'] ?? '—').'</td>';
    $html .= '<td>'.h($e['type_name'] ?? '').'</td>';
    $html .= '<td>'.h($ms ?: '—').'</td>';
    $html .= '<td>'.h(vms_fmtdate($due)).'</td>';
    $html .= '<td><span style="color:'.statusColor($st).'">'.h($st).'</span></td>';
    $html .= '</tr>';
  }
  $html .= '</table>';
  $pdf->writeHTML($html, true, false, true, false, '');
}

// --------------- END: content copied from vessel_profile_pdf.php ---------------
  $fnameSafe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', ($vessel['vesselName'] ?: ('Vessel_'.$vessel_id)));
  if ($stream === 'F') {
    if (!$outfile) throw new InvalidArgumentException('outfile required when stream = F');
    $pdf->Output($outfile, 'F');
    return $outfile;
  }
  $pdf->Output("Vessel_Profile_{$fnameSafe}.pdf", $stream);
  return null;
}
