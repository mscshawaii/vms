<?php
if (!isset($vessel_id)) exit('Vessel ID missing');

// Only define safe() if not already defined
if (!function_exists('safe')) {
    function safe($val) {
        return isset($val) && $val !== '' ? htmlspecialchars($val) : '—';
    }
}

// Set date boundaries
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+60 days'));

// Expiring documents alert
$expStmt = $pdo->prepare("
    SELECT COUNT(*) AS expiring
    FROM documents
    WHERE related_to = 'vessel'
      AND vessel_id = ?
      AND expDate IS NOT NULL
      AND STR_TO_DATE(expDate, '%Y-%m-%d') IS NOT NULL
      AND STR_TO_DATE(expDate, '%Y-%m-%d') <= ?
");
$expStmt->execute([$vessel_id, $soon]);
$row = $expStmt->fetch(PDO::FETCH_ASSOC);
$expiring_count = $row['expiring'] ?? 0;

if ($expiring_count > 0) {
    echo "<div class='alert alert-warning'>
        ⚠️ $expiring_count document" . ($expiring_count == 1 ? '' : 's') . " expired or expiring within 60 days.
    </div>";
}
?>

<h4>Documents</h4>

<a href="add_document.php?vessel_id=<?= $vessel_id ?>" class="btn btn-success mb-3">➕ Add Document</a>

<table class="table table-bordered table-striped">
    <thead class="table-light">
        <tr>
            <th>Type</th>
            <th>Document Name</th>
            <th>Issue Date</th>
            <th>Expiration Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $docStmt = $pdo->prepare("
            SELECT * FROM documents
            WHERE related_to = 'vessel' AND vessel_id = ?
            ORDER BY expDate
        ");
        $docStmt->execute([$vessel_id]);

        while ($doc = $docStmt->fetch(PDO::FETCH_ASSOC)) {
            $row_class = '';
            if (!empty($doc['expDate']) && $doc['expDate'] !== '0000-00-00') {
                if ($doc['expDate'] < $today) {
                    $row_class = 'table-danger';
                } elseif ($doc['expDate'] <= $soon) {
                    $row_class = 'table-warning';
                }
            }

            echo "<tr class='$row_class'>
                <td>" . safe($doc['docType']) . "</td>
                <td>" . safe($doc['docName']) . "</td>
                <td>" . safe($doc['issueDate']) . "</td>
                <td>" . safe($doc['expDate']) . "</td>
                <td>
                    <a href='view_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'>View</a> |
                    <a href='edit_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'>Edit</a> |
                    <a href='delete_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link' onclick=\"return confirm('Delete this document?')\">Delete</a>
                </td>
            </tr>";
        }
        ?>
    </tbody>
</table>

<style>
.action-link {
    text-decoration: none;
}
.action-link:hover {
    text-decoration: underline;
}
</style>
