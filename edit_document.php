<?php
require 'session_check.php';
require 'db_connect.php';

function safe($value, $default = '') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Document - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .docs-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .docs-header {
            display:flex; justify-content:space-between; align-items:flex-start;
            gap:12px; flex-wrap:wrap; margin-bottom:16px;
        }
        .docs-title { font-size:1.65rem; font-weight:700; margin:0 0 4px; }
        .docs-subtitle { color:#6b7280; margin:0; }
        .docs-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .docs-actions .btn { min-height:42px; border-radius:12px; }
    </style>
</head>
<body>
<?php
$title = 'Edit Document';
$back_link = 'vessel_documents.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="docs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="docs-header">
                    <div>
                        <h1 class="docs-title">Edit Document</h1>
                        <p class="docs-subtitle">Update document details or replace the stored file.</p>
                    </div>

                    <div class="docs-actions">
                        <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Documents</a>
                    </div>
                </div>

                <form method="post" action="update_document.php" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="col-md-6">
                        <label class="form-label">Document Type</label>
                        <input type="text" name="docType" value="<?= safe($doc['docType']) ?>" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Document Name</label>
                        <input type="text" name="docName" value="<?= safe($doc['docName']) ?>" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issueDate" value="<?= safe($doc['issueDate']) ?>" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" name="expDate" value="<?= safe($doc['expDate']) ?>" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"><?= htmlspecialchars((string)($doc['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Replace Uploaded File(s) (optional)</label>
                        <input type="file" name="docFiles[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        <div class="form-text">
                            Upload one PDF, or multiple JPG/PNG images to combine into a single replacement PDF.
                        </div>
                        <?php if (!empty($doc['file_path'])): ?>
                            <small class="text-muted">Current File: <a href="<?= safe($doc['file_path']) ?>" target="_blank">View / Download</a></small>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
</body>
</html>