<?php
require 'session_check.php';
require 'db_connect.php';

$tcpdfPath  = __DIR__ . '/tcpdf/tcpdf.php';
$tcpdfCache = __DIR__ . '/tcpdf_cache';

if (!is_dir($tcpdfCache)) {
    @mkdir($tcpdfCache, 0775, true);
}
if (!defined('K_PATH_CACHE')) {
    define('K_PATH_CACHE', rtrim($tcpdfCache, '/\\') . DIRECTORY_SEPARATOR);
}
if (!is_file($tcpdfPath)) {
    die("❌ TCPDF not found.");
}
require_once $tcpdfPath;

function clean($val) {
    return isset($val) && trim((string)$val) !== '' ? trim((string)$val) : null;
}

function ensure_dir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function normalize_uploads_array(array $files): array {
    $normalized = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $normalized;
    }

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $normalized[] = [
            'name'     => $files['name'][$i] ?? '',
            'type'     => $files['type'][$i] ?? '',
            'tmp_name' => $files['tmp_name'][$i] ?? '',
            'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$i] ?? 0,
        ];
    }

    return $normalized;
}

function ext_of(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function is_pdf_file(array $file): bool {
    $ext = ext_of($file['name'] ?? '');
    return $ext === 'pdf';
}

function is_supported_image_file(array $file): bool {
    $ext = ext_of($file['name'] ?? '');
    return in_array($ext, ['jpg', 'jpeg', 'png'], true);
}

function build_pdf_from_images(array $files, string $outputPath): void {
    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator('VMS');
    $pdf->SetAuthor('VMS');
    $pdf->SetTitle('Merged Document Upload');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    foreach ($files as $file) {
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid uploaded image.');
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        [$imgWpx, $imgHpx] = $imgInfo;

        $pdf->AddPage();

        $pageW = $pdf->getPageWidth() - 20;
        $pageH = $pdf->getPageHeight() - 20;

        $ratio = min($pageW / $imgWpx, $pageH / $imgHpx);
        $drawW = $imgWpx * $ratio;
        $drawH = $imgHpx * $ratio;

        $x = ($pdf->getPageWidth() - $drawW) / 2;
        $y = ($pdf->getPageHeight() - $drawH) / 2;

        $pdf->Image($file['tmp_name'], $x, $y, $drawW, $drawH, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }

    $pdf->Output($outputPath, 'F');
}

/* ---------- form data ---------- */
$docType    = clean($_POST['docType'] ?? '');
$docName    = clean($_POST['docName'] ?? '');
$related_to = clean($_POST['related_to'] ?? 'company');
$category   = clean($_POST['category'] ?? null);
$issueDate  = !empty($_POST['issueDate']) ? $_POST['issueDate'] : null;
$expDate    = !empty($_POST['expDate']) ? $_POST['expDate'] : null;
$notes      = clean($_POST['notes'] ?? null);
$vessel_id  = !empty($_POST['vessel_id']) ? (int)$_POST['vessel_id'] : null;
$archiveExistingSameType = !empty($_POST['archive_existing_same_type']) && $_POST['archive_existing_same_type'] === '1';

if ($docType && strtolower($docType) !== 'other') {
    $docName = $docType;
}

if ($related_to !== 'vessel') {
    $vessel_id = null;
}

/* ---------- uploads ---------- */
if (!isset($_FILES['docFiles'])) {
    die("❌ No files uploaded.");
}

$uploads = normalize_uploads_array($_FILES['docFiles']);
if (!$uploads) {
    die("❌ File upload failed.");
}

foreach ($uploads as $file) {
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        die("❌ One or more uploaded files failed.");
    }
}

$pdfFiles = array_values(array_filter($uploads, 'is_pdf_file'));
$imageFiles = array_values(array_filter($uploads, 'is_supported_image_file'));

if ((count($pdfFiles) > 0 && count($imageFiles) > 0) || (count($pdfFiles) > 1)) {
    die("❌ Upload either one PDF or multiple JPG/PNG images. Mixed uploads and multiple PDFs are not supported.");
}

if (count($pdfFiles) === 0 && count($imageFiles) === 0) {
    die("❌ Unsupported file type. Please upload one PDF or JPG/PNG image files.");
}

$upload_dir = __DIR__ . '/uploads/';
ensure_dir($upload_dir);

$storedRelativePath = null;

/* ---------- save final file ---------- */
if (count($pdfFiles) === 1) {
    $file = $pdfFiles[0];
    $ext = 'pdf';
    $newname = uniqid('doc_', true) . '.' . $ext;
    $targetFsPath = $upload_dir . $newname;
    $storedRelativePath = 'uploads/' . $newname;

    if (!move_uploaded_file($file['tmp_name'], $targetFsPath)) {
        die("❌ Failed to store uploaded PDF.");
    }

} else {
    $newname = uniqid('doc_', true) . '.pdf';
    $targetFsPath = $upload_dir . $newname;
    $storedRelativePath = 'uploads/' . $newname;

    try {
        build_pdf_from_images($imageFiles, $targetFsPath);
    } catch (Throwable $e) {
        die("❌ Failed to combine images into PDF: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

/* ---------- save db ---------- */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO documents (
            docName,
            docType,
            category,
            related_to,
            issueDate,
            expDate,
            file_path,
            notes,
            vessel_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $docName,
        $docType,
        $category,
        $related_to,
        $issueDate,
        $expDate,
        $storedRelativePath,
        $notes,
        $vessel_id
    ]);

    $newDocumentId = (int)$pdo->lastInsertId();

    if (
        $archiveExistingSameType &&
        $related_to === 'vessel' &&
        $vessel_id > 0 &&
        !empty($docType)
    ) {
        $archiveStmt = $pdo->prepare("
            UPDATE documents
            SET archived_at = NOW()
            WHERE related_to = 'vessel'
              AND vessel_id = ?
              AND docType = ?
              AND archived_at IS NULL
              AND id <> ?
        ");

        $archiveStmt->execute([
            $vessel_id,
            $docType,
            $newDocumentId
        ]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("❌ Error saving document: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if ($vessel_id) {
    header("Location: vessel_documents.php?vessel_id={$vessel_id}&document_saved=1");
} else {
    header("Location: documents.php");
}
exit;
?>