<?php
require 'db_connect.php';
require 'session_check.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/
$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id    = (int)($_SESSION['role_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id'] ?? 0);

if ($company_id !== 1 && $role_id !== 1) {
    die('Access denied.');
}

/*
|--------------------------------------------------------------------------
| TCPDF
|--------------------------------------------------------------------------
*/
$tcpdfPath = __DIR__ . '/tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) {
    $tcpdfPath = __DIR__ . '/lib/tcpdf/tcpdf.php';
}
if (!file_exists($tcpdfPath)) {
    die('TCPDF not found.');
}
require_once $tcpdfPath;

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmtDate($value, string $fallback = ''): string
{
    if (!$value || $value === '0000-00-00') {
        return $fallback;
    }
    $ts = strtotime($value);
    if (!$ts) {
        return $fallback;
    }
    return date('F j, Y', $ts);
}

function fmtDateInput($value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00') {
        return null;
    }
    $ts = strtotime($value);
    if (!$ts) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function sanitizeFileName(string $name): string
{
    $name = preg_replace('/[^\w\s\-\.\(\)]+/u', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    $name = str_replace(' ', '_', $name);
    return $name !== '' ? $name : 'fire_service_report';
}

function decodeSnapshot(array $item): array
{
    $raw = $item['equipment_snapshot_json'] ?? '';
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function buildCapacityString(array $snap): string
{
    $value = $snap['capacity_value'] ?? '';
    $unit  = $snap['capacity_unit'] ?? '';

    if ($value === null || $value === '') {
        return '';
    }

    $value = rtrim(rtrim((string)$value, '0'), '.');
    return trim($value . ($unit ? ' ' . $unit : ''));
}

function buildSizeDisplay(array $item): string
{
    $snap = decodeSnapshot($item);

    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    $ulRating = trim((string)($snap['ul_rating'] ?? ''));
    $capacityValue = $snap['capacity_value'] ?? '';
    $capacityUnit  = trim((string)($snap['capacity_unit'] ?? ''));
    $subtype = trim((string)($item['subtype'] ?? ''));
    if ($subtype === '') {
        $subtype = trim((string)($snap['equipment_subtype_name'] ?? $snap['agent_type'] ?? ''));
    }
    $sizeRating = trim((string)($item['size_rating'] ?? ''));

    if ($itemType === 'fixed') {
        if ($capacityValue !== null && $capacityValue !== '') {
            $capacity = rtrim(rtrim((string)$capacityValue, '0'), '.');
            return trim($capacity . ($capacityUnit ? ' ' . $capacityUnit : ''));
        }

        if ($subtype !== '') {
            return $subtype;
        }

        if ($sizeRating !== '') {
            return $sizeRating;
        }

        return '';
    }

    // Portable
    if ($ulRating !== '') {
        return $ulRating;
    }

    if ($sizeRating !== '') {
        return $sizeRating;
    }

    return $subtype;
}

function computeNextDueForItem(array $item, string $serviceDate): ?string
{
    $conditionCode = trim((string)($item['condition_code'] ?? ''));

    // Non-serviceable / removed from service = no next due
    if ($conditionCode === '2') {
        return null;
    }

    $existing = fmtDateInput($item['next_due'] ?? '');
    if ($existing) {
        return $existing;
    }

    $serviceTs = strtotime($serviceDate);
    if (!$serviceTs) {
        return null;
    }

    // Annual service default for now
    return date('Y-m-d', strtotime('+1 year', $serviceTs));
}

function computeDocumentExpDate(array $items, string $serviceDate): ?string
{
    $candidateDates = [];

    foreach ($items as $item) {
        $nextDue = computeNextDueForItem($item, $serviceDate);
        if ($nextDue) {
            $candidateDates[] = $nextDue;
        }
    }

    if (!empty($candidateDates)) {
        sort($candidateDates);
        return $candidateDates[0];
    }

    $serviceTs = strtotime($serviceDate);
    if ($serviceTs) {
        return date('Y-m-d', strtotime('+1 year', $serviceTs));
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| LOAD REPORT ID
|--------------------------------------------------------------------------
*/
$report_id = (int)($_POST['report_id'] ?? $_GET['report_id'] ?? 0);
if ($report_id <= 0) {
    die('Missing report_id.');
}

/*
|--------------------------------------------------------------------------
| SAVE ANY POSTED FORM DATA FIRST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name        = trim($_POST['customer_name'] ?? '');
    $facility_vessel_name = trim($_POST['facility_vessel_name'] ?? '');
    $address              = trim($_POST['address'] ?? '');
    $contact_person       = trim($_POST['contact_person'] ?? '');
    $phone                = trim($_POST['phone'] ?? '');
    $email                = trim($_POST['email'] ?? '');
    $serviced_by          = trim($_POST['serviced_by'] ?? '');
    $technician_name      = trim($_POST['technician_name'] ?? '');
    $technician_license   = trim($_POST['technician_license'] ?? '');
    $service_date         = trim($_POST['service_date'] ?? '');
    $source_notes         = trim($_POST['source_notes'] ?? '');

    $item_ids          = $_POST['item_id'] ?? [];
    $condition_codes   = $_POST['condition_code'] ?? [];
    $next_due_map      = $_POST['next_due'] ?? [];
    $notes_map         = $_POST['notes'] ?? [];
    $service_codes_map = $_POST['service_codes'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE fire_service_reports
            SET
                service_date = ?,
                customer_name = ?,
                facility_vessel_name = ?,
                address = ?,
                contact_person = ?,
                phone = ?,
                email = ?,
                serviced_by = ?,
                technician_name = ?,
                technician_license = ?,
                source_notes = ?,
                updated_at = NOW()
            WHERE fire_service_report_id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $service_date ?: null,
            $customer_name ?: null,
            $facility_vessel_name ?: null,
            $address ?: null,
            $contact_person ?: null,
            $phone ?: null,
            $email ?: null,
            $serviced_by ?: null,
            $technician_name ?: null,
            $technician_license ?: null,
            $source_notes ?: null,
            $report_id
        ]);

        $updateItem = $pdo->prepare("
            UPDATE fire_service_report_items
            SET
                condition_code = ?,
                service_codes = ?,
                next_due = ?,
                notes = ?,
                updated_at = NOW()
            WHERE fire_service_report_item_id = ?
              AND fire_service_report_id = ?
            LIMIT 1
        ");

        foreach ($item_ids as $rawItemId) {
            $itemId = (int)$rawItemId;
            $condition = trim((string)($condition_codes[$itemId] ?? ''));
            $nextDue = trim((string)($next_due_map[$itemId] ?? ''));
            $notes = trim((string)($notes_map[$itemId] ?? ''));

            $serviceCodes = $service_codes_map[$itemId] ?? [];
            if (!is_array($serviceCodes)) {
                $serviceCodes = [];
            }
            $serviceCodes = array_values(array_filter(array_map('trim', $serviceCodes)));
            $serviceCodesValue = $serviceCodes ? implode(', ', $serviceCodes) : null;

            $updateItem->execute([
                $condition !== '' ? $condition : null,
                $serviceCodesValue,
                $nextDue !== '' ? $nextDue : null,
                $notes !== '' ? $notes : null,
                $itemId,
                $report_id
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('Failed to save report before finalizing: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| LOAD FINAL REPORT DATA
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT fsr.*, v.vesselName, v.vesselON
    FROM fire_service_reports fsr
    INNER JOIN vessels v
        ON v.vessel_id = fsr.vessel_id
    WHERE fsr.fire_service_report_id = ?
    LIMIT 1
");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die('Report not found.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM fire_service_report_items
    WHERE fire_service_report_id = ?
    ORDER BY item_order ASC, fire_service_report_item_id ASC
");
$stmt->execute([$report_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    die('No service items found for this report.');
}

$serviceDate = fmtDateInput($report['service_date'] ?? '') ?: date('Y-m-d');
$documentExpDate = computeDocumentExpDate($items, $serviceDate);

/*
|--------------------------------------------------------------------------
| GENERATE PDF
|--------------------------------------------------------------------------
*/
$docName = trim(($report['report_number'] ?? 'AFSR') . ' - Fire Equipment Servicing - ' . ($report['vesselName'] ?? 'Vessel'));
$fileName = sanitizeFileName($docName) . '.pdf';

$relativeDir = '/uploads/documents/vessels/' . (int)$report['vessel_id'];
$absoluteDir = __DIR__ . $relativeDir;

if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
    die('Failed to create PDF directory.');
}

$relativePath = $relativeDir . '/' . $fileName;
$absolutePath = $absoluteDir . '/' . $fileName;

$pdf = new TCPDF('L', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('VMS');
$pdf->SetAuthor((string)($report['technician_name'] ?? 'MSCS Hawaii'));
$pdf->SetTitle($docName);
$pdf->SetSubject('Annual Fire Equipment Service Report');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 10);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

/*
|--------------------------------------------------------------------------
| WATERMARK LOGO
|--------------------------------------------------------------------------
*/
$logoPath = __DIR__ . '/uploads/logos/68093a02c24b4_MSCS_Logo_Color.png';
if (file_exists($logoPath)) {
    $pdf->SetAlpha(0.08);
    $pdf->Image($logoPath, 75, 35, 130, 0, '', '', '', false, 300, '', false, false, 0);
    $pdf->SetAlpha(1);
}

$pdf->SetFont('helvetica', '', 10);

$headerHtml = '
<h2 style="text-align:center; margin-bottom:8px;">ANNUAL FIRE EQUIPMENT SERVICE REPORT</h2>

<table border="0" cellpadding="4">
    <tr>
        <td width="16%"><b>Customer Name:</b></td>
        <td width="34%">' . h($report['customer_name'] ?? '') . '</td>
        <td width="16%"><b>Facility/Vessel:</b></td>
        <td width="34%">' . h($report['facility_vessel_name'] ?? '') . '</td>
    </tr>
    <tr>
        <td><b>Address:</b></td>
        <td>' . h($report['address'] ?? '') . '</td>
        <td><b>Contact Person:</b></td>
        <td>' . h($report['contact_person'] ?? '') . '</td>
    </tr>
    <tr>
        <td><b>Phone:</b></td>
        <td>' . h($report['phone'] ?? '') . '</td>
        <td><b>Email:</b></td>
        <td>' . h($report['email'] ?? '') . '</td>
    </tr>
</table>
';

$pdf->writeHTML($headerHtml, true, false, true, false, '');

$tableHtml = '
<table border="1" cellpadding="3">
    <thead>
        <tr style="background-color:#f2f2f2; font-weight:bold;">
            <th width="4%">#</th>
            <th width="7%">Item Type</th>
            <th width="10%">Manufacturer</th>
            <th width="10%">Model</th>
            <th width="11%">Serial #</th>
            <th width="13%">Location</th>
            <th width="11%">Size / Rating</th>
            <th width="8%">Condition</th>
            <th width="9%">Service Performed</th>
            <th width="8%">Next Due</th>
            <th width="10%">Notes</th>
        </tr>
    </thead>
    <tbody>
';

foreach ($items as $item) {
    $conditionDisplay = trim((string)($item['condition_code'] ?? ''));
    $nextDueDisplay = computeNextDueForItem($item, $serviceDate);

    $pdfNotes = trim((string)($item['notes'] ?? ''));
    if ($conditionDisplay === '2') {
        $autoNote = 'Removed from service (non-serviceable)';
        $pdfNotes = $pdfNotes !== '' ? $autoNote . ' | ' . $pdfNotes : $autoNote;
    }

    $tableHtml .= '
        <tr>
            <td width="4%">' . h($item['item_order']) . '</td>
            <td width="7%">' . h($item['item_type']) . '</td>
            <td width="10%">' . h($item['manufacturer']) . '</td>
            <td width="10%">' . h($item['model_number']) . '</td>
            <td width="11%">' . h($item['serial_number']) . '</td>
            <td width="13%">' . h($item['location']) . '</td>
            <td width="11%">' . h(buildSizeDisplay($item)) . '</td>
            <td width="8%">' . h($conditionDisplay) . '</td>
            <td width="9%">' . h($item['service_codes']) . '</td>
            <td width="8%">' . h(fmtDate($nextDueDisplay, '')) . '</td>
            <td width="10%" style="font-size:8px;">' . h($pdfNotes) . '</td>
        </tr>
    ';
}

$tableHtml .= '
    </tbody>
</table>

<br>
<div style="font-size:9px;">
    Condition Codes: 1 - Serviceable, 2 - Non-Serviceable, 3 - New.
    Service Codes: V - Visual, R - Replaced, RV - Replace Valve/Hose, C - Pressure/Weight Check, CL - Clean Exterior.
</div>
';

$pdf->writeHTML($tableHtml, true, false, true, false, '');

$footerHtml = '
<br><br>
<table border="0" cellpadding="4">
    <tr>
        <td width="16%"><b>Serviced By:</b></td>
        <td width="34%">' . h($report['serviced_by'] ?? '') . '</td>
        <td width="16%"><b>Technician Name:</b></td>
        <td width="34%">' . h($report['technician_name'] ?? '') . '</td>
    </tr>
    <tr>
        <td><b>Signature:</b></td>
        <td>__________________________</td>
        <td><b>Date:</b></td>
        <td>' . h(fmtDate($serviceDate, '')) . '</td>
    </tr>
    <tr>
        <td><b>License #:</b></td>
        <td>' . h($report['technician_license'] ?? '') . '</td>
        <td></td>
        <td></td>
    </tr>
</table>

<br><br>
<div style="text-align:center; font-size:9px;">
Marine Safety Consulting &amp; Surveying (MSCS Hawaii) | 5352 Olopua Street, Kapaa, HI 96746 | 810-824-8398 | info@mschawaii.org
</div>
';

$pdf->writeHTML($footerHtml, true, false, true, false, '');
$pdf->Output($absolutePath, 'F');

/*
|--------------------------------------------------------------------------
| ARCHIVE PRIOR ACTIVE DOCS + INSERT NEW DOC + WRITE BACK HISTORY
|--------------------------------------------------------------------------
*/
try {
    $pdo->beginTransaction();

    $archiveStmt = $pdo->prepare("
        UPDATE documents
        SET archived_at = NOW()
        WHERE vessel_id = ?
          AND archived_at IS NULL
          AND (
                category = 'Fire Equipment Servicing'
             OR docType = 'Fire Equipment Servicing'
             OR docName = 'Fire Equipment Servicing'
          )
    ");
    $archiveStmt->execute([(int)$report['vessel_id']]);

    $insertDoc = $pdo->prepare("
        INSERT INTO documents (
            docType,
            category,
            docName,
            related_to,
            vessel_id,
            issueDate,
            expDate,
            reminder_enabled,
            file_path,
            notes,
            uploaded_by
        ) VALUES (?, ?, ?, 'vessel', ?, ?, ?, 1, ?, ?, ?)
    ");
    $insertDoc->execute([
        'Fire Equipment Servicing',
        'Fire Equipment Servicing',
        $docName,
        (int)$report['vessel_id'],
        $serviceDate,
        $documentExpDate,
        $relativePath,
        'Generated from fire service report #' . ($report['report_number'] ?? ''),
        $user_id
    ]);

    $document_id = (int)$pdo->lastInsertId();

    $updateDetailStmt = $pdo->prepare("
        UPDATE fire_extinguisher_details
        SET
            last_annual_service_date = ?,
            next_annual_due = ?,
            last_service_vendor = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE eid = ?
        LIMIT 1
    ");

    $insertHistoryStmt = $pdo->prepare("
        INSERT INTO fire_extinguisher_service_history (
            eid,
            service_type,
            service_date,
            result,
            vendor_name,
            technician_name,
            notes,
            next_due_date
        ) VALUES (?, 'annual_service', ?, ?, ?, ?, ?, ?)
    ");

    $updateReportItemHistoryStmt = $pdo->prepare("
        UPDATE fire_service_report_items
        SET service_history_id = ?
        WHERE fire_service_report_item_id = ?
          AND fire_service_report_id = ?
        LIMIT 1
    ");

    foreach ($items as $item) {
        $eid = (int)($item['equipment_id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }

        $nextDue = computeNextDueForItem($item, $serviceDate);
        $conditionCode = trim((string)($item['condition_code'] ?? ''));
        $result = ($conditionCode === '2') ? 'fail' : 'completed';

        $historyNotesParts = [];
        if (!empty($report['report_number'])) {
            $historyNotesParts[] = 'Report: ' . $report['report_number'];
        }
        if (!empty($item['condition_code'])) {
            $historyNotesParts[] = 'Condition: ' . $item['condition_code'];
        }
        if (!empty($item['service_codes'])) {
            $historyNotesParts[] = 'Service: ' . $item['service_codes'];
        }
        if (!empty($item['notes'])) {
            $historyNotesParts[] = 'Notes: ' . $item['notes'];
        }

        $historyNotes = implode(' | ', $historyNotesParts);

        $updateDetailStmt->execute([
            $serviceDate,
            $nextDue,
            $report['serviced_by'] ?? null,
            $eid
        ]);

        $insertHistoryStmt->execute([
            $eid,
            $serviceDate,
            $result,
            $report['serviced_by'] ?? null,
            $report['technician_name'] ?? null,
            $historyNotes ?: null,
            $nextDue
        ]);

        $service_history_id = (int)$pdo->lastInsertId();

        $updateReportItemHistoryStmt->execute([
            $service_history_id,
            (int)$item['fire_service_report_item_id'],
            $report_id
        ]);
    }

    $updateReport = $pdo->prepare("
        UPDATE fire_service_reports
        SET
            status = 'final',
            pdf_path = ?,
            document_id = ?,
            updated_at = NOW()
        WHERE fire_service_report_id = ?
        LIMIT 1
    ");
    $updateReport->execute([
        $relativePath,
        $document_id,
        $report_id
    ]);

    $pdo->commit();

    header('Location: view_document.php?id=' . $document_id);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Failed to save final PDF/document record: ' . $e->getMessage());
}