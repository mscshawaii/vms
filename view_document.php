<?php
require 'session_check.php';
require 'db_connect.php';

function safe($value, $default = '—') {
    return htmlspecialchars((string)($value ?? $default), ENT_QUOTES, 'UTF-8');
}

$doc_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("Document not found.");
}

$vessel_id = (int)($doc['vessel_id'] ?? 0);

/**
 * Build a project-aware base path.
 * Examples:
 * - /vessel_management_system
 * - '' if app is at web root
 */
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
}

/**
 * Convert stored file_path to a browser-usable URL.
 * Handles:
 * - relative paths like uploads/abc.pdf
 * - absolute filesystem paths inside this project
 * - already absolute URLs
 */
$fileUrl = null;

if (!empty($doc['file_path'])) {
    $rawPath = (string)$doc['file_path'];
    $normalized = str_replace('\\', '/', $rawPath);
    $projectRoot = str_replace('\\', '/', __DIR__);

    if (preg_match('#^https?://#i', $normalized)) {
        $fileUrl = $normalized;
    } else {
        if (strpos($normalized, $projectRoot) === 0) {
            $normalized = ltrim(substr($normalized, strlen($projectRoot)), '/');
        } else {
            $normalized = ltrim($normalized, '/');
        }

        $fileUrl = ($basePath !== '' ? $basePath . '/' : '/') . ltrim($normalized, '/');
    }
}

$fileName = $fileUrl ? basename(parse_url($fileUrl, PHP_URL_PATH) ?? $fileUrl) : null;
$isPdf = $fileUrl && preg_match('/\.pdf(\?.*)?$/i', $fileUrl);
$isImage = $fileUrl && preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)(\?.*)?$/i', $fileUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Document - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .docs-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .docs-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .docs-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .docs-subtitle {
            color: #6b7280;
            margin: 0;
        }

        .docs-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .docs-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .docs-detail-table th {
            width: 220px;
            white-space: nowrap;
        }

        .docs-preview-frame {
            width: 100%;
            min-height: 70vh;
            border: 1px solid #dbe4ee;
            border-radius: 12px;
            background: #fff;
        }

        .docs-image-preview {
            max-width: 100%;
            max-height: 75vh;
            display: block;
            margin: 0 auto;
            border: 1px solid #dbe4ee;
            border-radius: 12px;
            background: #fff;
        }

        .docs-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #dbe4ee;
            border-radius: 12px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<?php
$title = 'View Document';
$back_link = $vessel_id ? ('vessel_documents.php?vessel_id=' . $vessel_id) : 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="docs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="docs-header">
                    <div>
                        <h1 class="docs-title">Document Details</h1>
                        <p class="docs-subtitle">
                            <?= safe($doc['docName']) ?> · <?= safe($doc['docType']) ?>
                        </p>
                    </div>

                    <div class="docs-actions">
                        <?php if ($vessel_id): ?>
                            <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Documents</a>
                            <a href="edit_document.php?id=<?= (int)$doc['id'] ?>&vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">Edit</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered docs-detail-table mb-0">
                        <tr>
                            <th>Document Type</th>
                            <td><?= safe($doc['docType']) ?></td>
                        </tr>
                        <tr>
                            <th>Document Name</th>
                            <td><?= safe($doc['docName']) ?></td>
                        </tr>
                        <tr>
                            <th>Issue Date</th>
                            <td><?= safe($doc['issueDate']) ?></td>
                        </tr>
                        <tr>
                            <th>Expiration Date</th>
                            <td><?= safe($doc['expDate']) ?></td>
                        </tr>
                        <tr>
                            <th>Notes</th>
                            <td><?= nl2br(safe($doc['notes'])) ?></td>
                        </tr>
                        <tr>
                            <th>Archived</th>
                            <td><?= !empty($doc['archived_at']) ? 'Yes' : 'No' ?></td>
                        </tr>

                        <?php if ($fileUrl): ?>
                            <tr>
                                <th>Attached File</th>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <div class="docs-file-chip">
                                            <span>📎</span>
                                            <span><?= safe($fileName) ?></span>
                                        </div>

                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="<?= safe($fileUrl) ?>" target="_blank" class="btn btn-primary btn-sm">
                                                Open
                                            </a>

                                            <a href="<?= safe($fileUrl) ?>" download class="btn btn-outline-secondary btn-sm">
                                                Save / Download
                                            </a>

                                            <?php if ($isPdf || $isImage): ?>
                                                <button type="button" class="btn btn-outline-dark btn-sm" onclick="printDocumentPreview()">
                                                    Print
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-text">
                                            Stored file path: <?= safe((string)$doc['file_path']) ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>Attached File</th>
                                <td class="text-muted">No file attached.</td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <?php if ($fileUrl && $isPdf): ?>
                <div class="vms-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0">PDF Preview</h2>
                        <a href="<?= safe($fileUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Open Full PDF</a>
                    </div>

                    <iframe
                        id="docPreviewFrame"
                        src="<?= safe($fileUrl) ?>"
                        class="docs-preview-frame"
                        title="Document PDF Preview">
                    </iframe>
                </div>
            <?php elseif ($fileUrl && $isImage): ?>
                <div class="vms-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0">Image Preview</h2>
                        <a href="<?= safe($fileUrl) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Open Full Image</a>
                    </div>

                    <img
                        id="docPreviewImage"
                        src="<?= safe($fileUrl) ?>"
                        alt="<?= safe($fileName ?? 'Document Image') ?>"
                        class="docs-image-preview">
                </div>
            <?php elseif ($fileUrl): ?>
                <div class="vms-card">
                    <h2 class="h5 mb-3">File Preview</h2>
                    <div class="alert alert-info mb-0">
                        Preview is not available for this file type. Use the Open or Save / Download buttons above.
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function printDocumentPreview() {
    const frame = document.getElementById('docPreviewFrame');
    const image = document.getElementById('docPreviewImage');

    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        return;
    }

    if (image) {
        const w = window.open('', '_blank');
        if (!w) return;

        w.document.write(`
            <html>
            <head>
                <title>Print Document</title>
                <style>
                    body { margin: 0; text-align: center; }
                    img { max-width: 100%; height: auto; }
                </style>
            </head>
            <body>
                <img src="${image.src}" onload="window.print(); window.close();">
            </body>
            </html>
        `);
        w.document.close();
    }
}
</script>
</body>
</html>