<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    die("❌ Access Denied: ICR templates can only be managed by MSCS Hawaii.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>📘 ICR Templates</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>📘 Inspection Criteria Reports (ICRs)</h2>
    <a href="add_icr.php" class="btn btn-success mb-3">➕ New ICR Template</a>

    <a href="dashboard.php" class="btn btn-secondary mb-4">← Return to MSCS Dashboard</a>



    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>ICR #</th>
                <th>Title</th>
                <th>Reference / Authorization</th>
                <th>Frequency</th>
                <th># Steps</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $pdo->query("
            SELECT i.icr_id, i.icr_number, i.title, i.reference_text, i.frequency, COUNT(s.step_id) AS steps
            FROM icrs i
            LEFT JOIN icr_steps s ON i.icr_id = s.icr_id
            GROUP BY i.icr_id
            ORDER BY i.icr_number
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['icr_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['title']) . "</td>";
            echo "<td>" . nl2br(htmlspecialchars($row['reference_text'])) . "</td>";
            echo "<td>" . htmlspecialchars($row['frequency']) . "</td>";
            echo "<td>" . (int)$row['steps'] . "</td>";
            echo "<td>
                    <a href='edit_icr.php?id={$row['icr_id']}' class='btn btn-sm btn-primary'>Edit</a>
                    <a href='delete_icr.php?id={$row['icr_id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this ICR?')\">Delete</a>
                  </td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>
</body>
</html>
