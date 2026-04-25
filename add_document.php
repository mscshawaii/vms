<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

$vessel_id = isset($_GET['vessel_id']) ? intval($_GET['vessel_id']) : null;
$vessel_name = null;

$prefill_docType = isset($_GET['docType']) ? trim((string)$_GET['docType']) : '';

$docTypeAliases = [
    'FCC Safety Radio Certificate' => 'FCC Safety Radiotelephony Certificate',
    'FCC Bridge-to-Bridge Certificate' => 'FCC Bridge-to-Bridge Certificate',
    'Certificate of Documentation / State Registration' => 'Certificate of Documentation / State Registration',
    'EPIRB Registration' => 'EPIRB Registration',
    'EPRIB Registration' => 'EPIRB Registration',
];

if ($prefill_docType !== '' && isset($docTypeAliases[$prefill_docType])) {
    $prefill_docType = $docTypeAliases[$prefill_docType];
}

if ($vessel_id) {
    $stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
    $stmt->execute([$vessel_id]);
    $vessel_name = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Document - VMS</title>

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
$title = 'Add Document';
$back_link = $vessel_id ? ('vessel_documents.php?vessel_id=' . (int)$vessel_id) : 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="docs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="docs-header">
                    <div>
                        <h1 class="docs-title">Upload New Document</h1>
                        <p class="docs-subtitle">
                            <?= $vessel_name ? 'For vessel: ' . htmlspecialchars($vessel_name, ENT_QUOTES, 'UTF-8') : 'Upload a new document record' ?>
                        </p>
                    </div>

                    <div class="docs-actions">
                        <?php if ($vessel_id): ?>
                            <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Documents</a>
                        <?php else: ?>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">Back</a>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="submit_document.php" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                    <input type="hidden" name="archive_existing_same_type" value="<?= $prefill_docType !== '' ? '1' : '0' ?>">

                    <div class="col-md-6">
                        <label class="form-label">Document Type</label>
                        <select name="docType" id="docTypeSelect" class="form-select" required>
                            <?php
                            $types = [
                                'Certificate of Inspection',
                                'Certificate of Documentation / State Registration',
                                'Stability Letter',
                                'Commercial Permit',
                                'Insurance',
                                'Liquor License',
                                'Food Establishment Permit',
                                'FCC Station License',
                                'FCC Bridge-to-Bridge Certificate',
                                'FCC Safety Radiotelephony Certificate',
                                'Marine Radio Operator Permit',
                                'EPIRB Registration',
                                'Fire Equipment Servicing',
                                'Lifesaving Equipment Servicing',
                                'Emergency Instructions',
                                'Emergency Broadcast Instructions',
                                'Oil Discharge Placard',
                                'MARPOL Placard',
                                'Waste Management Plan',
                                'Broadcast Notice to Mariners',
                                'Charts',
                                'Tides Tables',
                                'Currents Tables',
                                'Light Lists',
                                'Coast Pilot',
                                'Navigation Rules',
                                'Other'
                            ];
                            foreach ($types as $type) {
                                $selected = ($prefill_docType !== '' && strcasecmp($prefill_docType, $type) === 0) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . "\" {$selected}>" . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Document Name</label>
                        <input type="text" name="docName" class="form-control" id="docNameInput" disabled
                               value="<?= htmlspecialchars($prefill_docType, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Related To</label>
                        <select name="related_to" class="form-select">
                            <option value="company">Company</option>
                            <option value="vessel" <?= $vessel_id ? 'selected' : '' ?>>Vessel</option>
                            <option value="crew">Crew</option>
                            <option value="equipment">Equipment</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Upload File(s)</label>
                        <input type="file" name="docFiles[]" class="form-control" accept=".pdf,.jpg,.jpeg,.png" multiple required>
                        <div class="form-text">
                            Upload one PDF, or multiple JPG/PNG images to combine into a single PDF.
                            Mixed file types and multiple PDFs are not supported in this first version.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Issue Date</label>
                        <input type="date" name="issueDate" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" name="expDate" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Upload Document</button>
                        <?php if ($vessel_id): ?>
                            <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const docType = document.getElementById('docTypeSelect');
    const docName = document.getElementById('docNameInput');

    function updateDocNameBehavior() {
        if (!docType || !docName) return;
        const selected = docType.value.trim();

        if (selected.toLowerCase() === 'other') {
            docName.disabled = false;
            docName.required = true;
            if (!docName.value) docName.value = '';
            docName.placeholder = 'Please specify document name';
        } else {
            docName.disabled = true;
            docName.required = false;
            docName.value = selected;
            docName.placeholder = 'Auto-filled from document type';
        }
    }

    docType.addEventListener('change', updateDocNameBehavior);
    updateDocNameBehavior();
});
</script>
</body>
</html>
