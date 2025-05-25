<?php
require 'db.php';

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));

$reminders = $mysqli->query("
    SELECT COUNT(*) AS expiring
    FROM documents
    WHERE expDate IS NOT NULL
      AND expDate <= '$soon'
");

$row = $reminders->fetch_assoc();
$expiring_count = $row['expiring'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>📄 All Documents</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📂 Document Library</h2>

    <?php if ($expiring_count > 0): ?>
        <div class="alert alert-warning">
            ⚠️ <?= $expiring_count ?> document<?= $expiring_count == 1 ? '' : 's' ?> expiring in the next 30 days.
        </div>
    <?php endif; ?>

    <a href="add_document.php" class="btn btn-success mb-3">➕ Add New Document</a>

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Category</th>
                <th>Related To</th>
                <th>Issue</th>
                <th>Expires</th>
                <th>File</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $docs = $mysqli->query("SELECT * FROM documents ORDER BY expDate ASC");
        while ($d = $docs->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$d['docName']}</td>";
            echo "<td>{$d['docType']}</td>";
            echo "<td>{$d['category']}</td>";
            echo "<td>{$d['related_to']}</td>";
            echo "<td>{$d['issueDate']}</td>";
            echo "<td>{$d['expDate']}</td>";
            echo "<td><a href='{$d['file_path']}' target='_blank'>📎 View</a></td>";
            echo "<td>
                <a href='edit_document.php?id={$d['id']}' class='btn btn-sm btn-primary'>Edit</a>
                <a href='delete_document.php?id={$d['id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this document?')\">Delete</a>
              </td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>
</body>
</html>
