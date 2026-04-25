<?php
// vessel_profile_pdf.php — STREAM a dashboard-style PDF profile (read-only)
ob_start();
session_start();
require 'session_check.php';
require 'db_connect.php';

if (isset($pdo)) {
  try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/tcpdf_errors.log');

date_default_timezone_set('Pacific/Honolulu');

// --- CONFIG (paths/logos) ---
$MSCS_OWNER_ID  = 1;
$uploadsBaseFS  = __DIR__ . '/uploads';
$scriptBase     = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uploadsBaseWeb = $scriptBase . '/uploads';

// *** NEW: pull in shared theme + helpers ***
require_once __DIR__ . '/pdf_common.php';

$defaultSectorTag = 'Sector Honolulu';

// --- input & ACL ---
$vessel_id = (int)($_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) { http_response_code(400); exit('Bad request'); }

$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id    = (int)($_SESSION['role_id'] ?? 0);
$ADMIN_ROLE_ID = 1;
$preferredDates = [];

// --- Load vessel + owner/company + POCs ---
$stmt = $pdo->prepare("
  SELECT 
    v.*,
    o.owner_id AS owner_owner_id,
    o.company_name AS owner_name,
    o.email AS owner_email,
    o.phone AS owner_phone,
    o.contact_name AS owner_contact_name,
    o.logo_path AS company_logo_path,
    o.primary_contact_user_id,
    o.alt_contact_user_id,
    u1.fName AS primary_fname, u1.lName AS primary_lname, u1.phoneNumber AS primary_phone, u1.email AS primary_email,
    u2.fName AS alt_fname,     u2.lName AS alt_lname,     u2.phoneNumber AS alt_phone,     u2.email AS alt_email
  FROM vessels v
  LEFT JOIN owners o ON o.owner_id = v.company_id
  LEFT JOIN users  u1 ON u1.id = o.primary_contact_user_id
  LEFT JOIN users  u2 ON u2.id = o.alt_contact_user_id
  WHERE v.vessel_id = ?
  LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vessel) { http_response_code(404); exit('Vessel not found'); }

// ACL
$allow = false;
if ($role_id === $ADMIN_ROLE_ID || $company_id === $MSCS_OWNER_ID) $allow = true;
if ($company_id === (int)$vessel['company_id']) $allow = true;
if (!$allow) { http_response_code(403); exit('Forbidden'); }

// --- Resolve OCMI (if assigned) ---
$ocmiRegion = $ocmiEmail = null;
if (!empty($vessel['ocmi_contact_id'])) {
  $q = $pdo->prepare("SELECT region_name, email_to FROM uscg_contacts WHERE contact_id=? AND active=1");
  $q->execute([(int)$vessel['ocmi_contact_id']]);
  if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
    $ocmiRegion = trim((string)$r['region_name']);
    $ocmiEmail  = trim((string)$r['email_to']);
  }
}

// --- Documents (profile list; separate from COI query below) ---
$docs = [];
try {
  $q=$pdo->prepare("
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
  $docs=$q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch(Throwable $e){ error_log('DOCS_LOAD_ERROR: '.$e->getMessage()); }

// --- Equipment (all items) ---
$equipment=[];
try{
  $q=$pdo->prepare("
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
  $equipment=$q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}catch(Throwable $e){
  error_log('EQUIP_JOIN_FAIL: '.$e->getMessage());
  try {
    $q=$pdo->prepare("
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
    $equipment=$q->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e2) {
    error_log('EQUIP_Q_ERR: '.$e2->getMessage());
    $equipment=[];
  }
}

// ===== Build Themed PDF (identical look) =====
$leftLogoFS  = __DIR__.'/assets/mscs_logo.png';
$rightLogoFS = !empty($vessel['company_logo_path']) ? (__DIR__ . '/' . ltrim($vessel['company_logo_path'],'/')) : null;
$pdf = vms_build_pdf('Vessel Profile', date('F j, Y'), $leftLogoFS, $rightLogoFS);

// ===== Vessel Photo (left) + Identity (right) =====
$M       = $pdf->getMargins();
$pageW   = $pdf->getPageWidth();
$usableW = $pageW - $M['left'] - $M['right'];

$gutter    = 6;
$leftColW  = round($usableW * 0.48, 1);
$rightColW = $usableW - $leftColW - $gutter;

$yStart = $pdf->GetY();
$xLeft  = $M['left'];
$xRight = $M['left'] + $leftColW + $gutter;

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

// Image try
$imgPlaced=false; $imgBottomY=$yStart;
try {
  if (!empty($vessel['photo_path'])) {
    $resolved = vms_resolve_photo((string)$vessel['photo_path'], $uploadsBaseFS, $uploadsBaseWeb, (int)$vessel_id);
    if (!empty($resolved)) {
      error_log('PHOTO_RESOLVE: path='.$vessel['photo_path'].' | fs='.($resolved['fs'] ?? 'null').' | url='.($resolved['url'] ?? 'null').' | reason='.($resolved['reason'] ?? 'ok'));
    }
    $imgSource = !empty($resolved['fs']) ? str_replace('\\','/',$resolved['fs']) : ($resolved['url'] ?? null);
    if ($imgSource) {
      $pdf->Image($imgSource, $xLeft, $yStart, $leftColW);
      $imgBottomY = method_exists($pdf,'getImageRBY') ? $pdf->getImageRBY() : ($yStart + 50);
      $imgPlaced = true;
    }
  }
} catch (Throwable $e) {
  error_log('PHOTO_ERROR: '.$e->getMessage());
  $imgPlaced=false;
}
if ($imgPlaced) {
  $drawW   = $leftColW;
  $borderH = max($imgBottomY - $yStart, 1);
  $pdf->SetDrawColor(210,210,210);
  $pdf->Rect($xLeft, $yStart, $drawW, $borderH);
  $pdf->SetDrawColor(0,0,0);
}

// --- Schedule/inspection (COI-driven) ---
$prefLines = [];
foreach ($preferredDates as $i=>$d) { $prefLines[] = ($i+1) . ') ' . prettyDateTime($d); }

$coiIssue   = '—';
$coiExp     = '—';
$coiExpRaw  = null;

try {
  $qCOI = $pdo->prepare("
    SELECT 
      expDate   AS exp_raw,
      issueDate AS issue_raw
    FROM documents
    WHERE vessel_id   = ?
      AND related_to  = 'vessel'
      AND archived_at IS NULL
      AND (
        UPPER(TRIM(docType))  = 'CERTIFICATE OF INSPECTION'
        OR UPPER(TRIM(category)) = 'CERTIFICATE OF INSPECTION'
        OR UPPER(TRIM(docName))  = 'CERTIFICATE OF INSPECTION'
      )
    ORDER BY expDate DESC
    LIMIT 1
  ");
  $qCOI->execute([$vessel_id]);
  if ($row = $qCOI->fetch(PDO::FETCH_ASSOC)) {
    $coiExpRaw = $row['exp_raw'] ?? null;
    $coiIssue  = vms_fmtdate($row['issue_raw'] ?? null);
    $coiExp    = vms_fmtdate($coiExpRaw);
  }
} catch (Throwable $e) {
  error_log('COI_LOOKUP_ERROR: '.$e->getMessage());
}

if (!$coiExpRaw) {
  try {
    $qCOI2 = $pdo->prepare("
      SELECT expDate AS exp_raw
      FROM documents
      WHERE vessel_id   = ?
        AND related_to  = 'vessel'
        AND archived_at IS NULL
        AND (
          UPPER(TRIM(docType))   LIKE '%COI%'
          OR UPPER(TRIM(category)) LIKE '%COI%'
          OR UPPER(TRIM(docName))  LIKE '%COI%'
          OR UPPER(TRIM(docType))   LIKE '%CERTIFICATE%INSPECTION%'
          OR UPPER(TRIM(docName))   LIKE '%CERTIFICATE%INSPECTION%'
        )
      ORDER BY expDate DESC
      LIMIT 1
    ");
    $qCOI2->execute([$vessel_id]);
    $coiExpRaw = $qCOI2->fetchColumn() ?: null;
    $coiExp    = vms_fmtdate($coiExpRaw);
  } catch (Throwable $e) {
    error_log('COI_LOOKUP_FUZZY_ERROR: '.$e->getMessage());
  }
}

// Next inspection calc
$inspectionType   = '—';
$inspectionWindow = '—';
$lastInspectionRaw = vms_val($vessel, ['lastInspection','last_inspection'], null);
error_log("DEBUG COI: vessel_id={$vessel_id} expRaw={$coiExpRaw} lastInspection={$lastInspectionRaw}");

if ($coiExpRaw && $coiExpRaw !== '0000-00-00') {
  try {
    $exp = new DateTime($coiExpRaw);
    $lastInspection = ($lastInspectionRaw && $lastInspectionRaw !== '0000-00-00') ? new DateTime($lastInspectionRaw) : null;

    $found=false;
    for ($i=1; $i<=4; $i++) {
      $annualDate = (clone $exp)->modify('-'.(5-$i).' years');
      $start      = (clone $annualDate)->modify('-90 days');
      $end        = (clone $annualDate)->modify('+90 days');
      if (!$lastInspection || $lastInspection < $start) {
        $inspectionType   = "Annual (#{$i})";
        $inspectionWindow = vms_fmtdate($start->format('Y-m-d')) . ' – ' . vms_fmtdate($end->format('Y-m-d'));
        $found=true; break;
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
  } catch (Throwable $e) { error_log('COI_COMPUTE_ERROR: '.$e->getMessage()); }
} else {
  error_log("DEBUG COI: no COI expiration found for vessel_id={$vessel_id}");
}

// Left/Right blocks
$leftBlock = [
  ['Inspection Window',          $inspectionWindow],
  ['Next Inspection Type',       $inspectionType],
  ['Last Dry Dock',              vms_fmtdate(vms_val($vessel, ['lastDrydock','lastDryDock','last_drydock','lastDryDockDate','dry_dock_last','drydock_last'], ''))],
  ['Next Dry Dock',              vms_fmtdate(vms_val($vessel, ['nextDrydock','nextDryDock','next_drydock','nextDryDockDate','dry_dock_next','drydock_next'], ''))],
  ['Next Mast Un-step',          vms_fmtdate(vms_val($vessel, ['nextUnstep','nextMastUnstep','mast_unstep_due','mastUnstepDueOn','mast_unstep_next'], ''))],
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

// Layout: photo + identity top, then 2-col grid
if ($imgPlaced) {
  $pdf->writeHTMLCell($rightColW, 0, $xRight, $yStart, $identityTbl, 0, 0, 0, true, '', true);
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
  $leftMerged = array_merge($identityRows, [['', '']], $leftBlock);
  $htmlLeft  = vms_cell_table($leftMerged,  '44%', 8.5, 3);
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

// clean & sort
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

// --- Load equipment for this vessel (includes items with or without exp dates) ---
$equipment = [];

// Detect if the optional type tables exist (avoid noisy join errors)
$hasTypeTables = false;
try {
  $chk = $pdo->query("
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
      AND table_name IN ('equipment_types','equipment_subtypes')
  ")->fetchColumn();
  $hasTypeTables = ((int)$chk === 2);
} catch (Throwable $e) {
  // ignore; we'll fall back to simple query
}

try {
  if ($hasTypeTables) {
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
  } else {
    // Fallback without joins if those tables don't exist
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
  }

  $q->execute([$vessel_id]);
  $equipment = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  error_log('EQUIP_Q_ERR: '.$e->getMessage());
  $equipment = [];
}

// ===== Equipment =====
$pdf->SetFont('helvetica','B',12);
$pdf->Cell(0, 8, 'Equipment', 0, 1, 'L');
$pdf->SetFont('helvetica','',9);
$pdf->setCellHeightRatio(1.12);

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

// --- Clean output buffers & stream ---
while (ob_get_level() > 0) { ob_end_clean(); }
if (ini_get('zlib.output_compression')) { ini_set('zlib.output_compression', 'Off'); }
header_remove();

$fnameSafe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', ($vessel['vesselName'] ?: ('Vessel_'.$vessel_id)));
$pdf->Output("Vessel_Profile_{$fnameSafe}.pdf", 'I');
exit;
