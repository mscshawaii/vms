<?php
require 'session_check.php';
require 'db_connect.php';

function safe($value, $default = '') {
    return htmlspecialchars($value ?? $default);
}

$doc_id = intval($_GET['id'] ?? 0);

// Fetch document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("❌ Document not found.");
}

// Fetch vessel id for back button
$vessel_id = $doc['vessel_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#documents" class="btn btn-outline-secondary mb-3">← Back to Vessel Dashboard</a>

<div class="card">
    <div class="card-header bg-primary text-white">
        📄 Edit Document
    </div>
    <div class="card-body">
        <form method="post" action="update_document.php" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="id" value="<?= $doc['id'] ?>">
            <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

            <div class="col-md-6">
                <label class="form-label">Document Type:</label>
                <input type="text" name="docType" value="<?= safe($doc['docType']) ?>" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Document Name:</label>
                <input type="text" name="docName" value="<?= safe($doc['docName']) ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Issue Date:</label>
                <input type="date" name="issueDate" value="<?= safe($doc['issueDate']) ?>" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Expiration Date:</label>
                <input type="date" name="expDate" value="<?= safe($doc['expDate']) ?>" class="form-control">
            </div>

            <div class="col-12">
                <label class="form-label">Notes:</label>
                <textarea name="notes" class="form-control"><?= htmlspecialchars($doc['notes'] ?? '') ?></textarea>

            </div>

            <div class="col-12">
                <label class="form-label">Replace Uploaded File (optional):</label>
                <input type="file" name="file" class="form-control">
                <?php if (!empty($doc['file_path'])): ?>
                    <small class="text-muted">Current File: <a href="<?= safe($doc['file_path']) ?>" target="_blank">View/Download</a></small>
                <?php endif; ?>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>#documents" class="btn btn-secondary">← Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
