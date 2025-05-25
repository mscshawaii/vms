<?php
require 'db_connect.php';

$vessel_id = isset($_GET['vessel_id']) ? intval($_GET['vessel_id']) : null;
$vessel_name = null;

if ($vessel_id) {
    $stmt = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ?");
    $stmt->execute([$vessel_id]);
    $vessel_name = $stmt->fetchColumn();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📄 Upload New Document</h2>

    <?php if ($vessel_name): ?>
        <div class="alert alert-info">
            Uploading document for vessel: <strong><?= htmlspecialchars($vessel_name) ?></strong>
        </div>
    <?php endif; ?>

    <form method="post" action="submit_document.php" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

        <div class="col-md-6">
            <label class="form-label">Document Type:</label>
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
                    'EPRIB Registration',
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
                    echo "<option value=\"$type\">$type</option>";
                }
                ?>
            </select>
        </div>

        <div class="col-md-6">
    <label class="form-label">Document Name:</label>
    <input type="text" name="docName" class="form-control" id="docNameInput" disabled>
</div>


        <div class="col-md-4">
            <label class="form-label">Related To:</label>
            <select name="related_to" class="form-select">
                <option value="company">Company</option>
                <option value="vessel" <?= $vessel_id ? 'selected' : '' ?>>Vessel</option>
                <option value="crew">Crew</option>
                <option value="equipment">Equipment</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">File Upload:</label>
            <input type="file" name="docFile" class="form-control" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">Issue Date:</label>
            <input type="date" name="issueDate" class="form-control">
        </div>

        <div class="col-md-4">
            <label class="form-label">Expiration Date:</label>
            <input type="date" name="expDate" class="form-control">
        </div>

        <div class="col-12">
            <label class="form-label">Notes:</label>
            <textarea name="notes" class="form-control"></textarea>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">📤 Upload Document</button>
            <?php if ($vessel_id): ?>
    		<a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#documents" class="btn btn-secondary">? Cancel</a>
	    <?php else: ?>
    		<a href="javascript:history.back()" class="btn btn-secondary">? Cancel</a>
	    <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const docType = document.getElementById('docTypeSelect');
    const docName = document.getElementById('docNameInput');

    function updateDocNameBehavior() {
        if (!docType || !docName) return;

        const selected = docType.value;

        if (selected.toLowerCase() === 'other') {
            docName.disabled = false;
            docName.required = true;
            docName.value = '';
            docName.placeholder = 'Please specify document name';
        } else {
            docName.disabled = true;
            docName.required = false;
            docName.value = selected;
            docName.placeholder = 'Auto-filled from document type';
        }
    }

    docType.addEventListener('change', updateDocNameBehavior);
    updateDocNameBehavior(); // run once on load
});
</script>


</body>
</html>
