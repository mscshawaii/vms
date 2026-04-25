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
    return ext_of($file['name'] ?? '') === 'pdf';
}

function is_supported_image_file(array $file): bool {
    return in_array(ext_of($file['name'] ?? ''), ['jpg', 'jpeg', 'png'], true);
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

$id = intval($_POST['id'] ?? 0);
$vessel_id = intval($_POST['vessel_id'] ?? 0);

$docType = trim((string)($_POST['docType'] ?? ''));
$docName = trim((string)($_POST['docName'] ?? ''));
$issueDate = !empty($_POST['issueDate']) ? $_POST['issueDate'] : null;
$expDate   = !empty($_POST['expDate']) ? $_POST['expDate'] : null;
$notes = trim((string)($_POST['notes'] ?? ''));

$file_path = null;

/* ---------- replacement upload ---------- */
$uploads = [];
if (isset($_FILES['docFiles']) && is_array($_FILES['docFiles']['name'] ?? null)) {
    $uploads = normalize_uploads_array($_FILES['docFiles']);
}

if ($uploads) {
    foreach ($uploads as $file) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            header("Location: vessel_documents.php?vessel_id=$vessel_id&error=One or more files failed to upload.");
            exit;
        }
    }

    $pdfFiles = array_values(array_filter($uploads, 'is_pdf_file'));
    $imageFiles = array_values(array_filter($uploads, 'is_supported_image_file'));

    if ((count($pdfFiles) > 0 && count($imageFiles) > 0) || (count($pdfFiles) > 1)) {
        header("Location: vessel_documents.php?vessel_id=$vessel_id&error=Upload one PDF or multiple JPG/PNG images only.");
        exit;
    }

    if (count($pdfFiles) === 0 && count($imageFiles) === 0) {
        header("Location: vessel_documents.php?vessel_id=$vessel_id&error=Unsupported file type.");
        exit;
    }

    $uploads_dir = __DIR__ . '/uploads/';
    ensure_dir($uploads_dir);

    if (count($pdfFiles) === 1) {
        $file = $pdfFiles[0];
        $newname = uniqid('doc_', true) . '.pdf';
        $targetFsPath = $uploads_dir . $newname;
        $file_path = 'uploads/' . $newname;

        if (!move_uploaded_file($file['tmp_name'], $targetFsPath)) {
            header("Location: vessel_documents.php?vessel_id=$vessel_id&error=Failed to upload PDF.");
            exit;
        }
    } else {
        $newname = uniqid('doc_', true) . '.pdf';
        $targetFsPath = $uploads_dir . $newname;
        $file_path = 'uploads/' . $newname;

        try {
            build_pdf_from_images($imageFiles, $targetFsPath);
        } catch (Throwable $e) {
            header("Location: vessel_documents.php?vessel_id=$vessel_id&error=Failed to merge images into PDF.");
            exit;
        }
    }
}

/* ---------- update ---------- */
$sql = "
    UPDATE documents
    SET docType = ?, docName = ?, issueDate = ?, expDate = ?, notes = ?
    " . ($file_path ? ", file_path = ?" : "") . "
    WHERE id = ?
";

$params = [$docType, $docName, $issueDate, $expDate, $notes];
if ($file_path) {
    $params[] = $file_path;
}
$params[] = $id;

$stmt = $pdo->prepare($sql);

if ($stmt->execute($params)) {
    header("Location: vessel_documents.php?vessel_id=$vessel_id&success=document_updated");
    exit;
} else {
    header("Location: vessel_documents.php?vessel_id=$vessel_id&error=Failed to update document.");
    exit;
}
?>