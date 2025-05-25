<?php
require 'session_check.php';
require 'db_connect.php';

function safe($value, $default = '�') {
    return htmlspecialchars($value !== null ? $value : $default, ENT_QUOTES, 'UTF-8');
}

$doc_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("❌ Document not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Document</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<a href="vessel_dashboard.php?vessel_id=<?= $doc['vessel_id'] ?>#documents" class="btn btn-outline-secondary mb-3">← Back to Vessel Dashboard</a>

<div class="card">
    <div class="card-header bg-primary text-white">
        📄 Document Details
    </div>
    <div class="card-body">
        <table class="table table-bordered">
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
                <td><?= safe($doc['notes']) ?></td>
            </tr>
            <?php if (!empty($doc['file_path'])): ?>
            <tr>
                <th>Uploaded File</th>
                <td>
                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                        📥 Download Document
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

</body>
</html>
