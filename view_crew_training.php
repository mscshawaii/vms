<?php
require 'db_connect.php';
require 'session_check.php';

$crew_id = intval($_GET['id'] ?? 0);
if (!$crew_id) {
    die("❌ Invalid crew ID.");
}

// Fetch crew + user data
$stmt = $pdo->prepare("
    SELECT cm.crew_id, u.fName AS fName, u.lName AS lName, u.title, u.mmc, u.mmc_medical, u.fa, u.mrop
    FROM crew_members cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.crew_id = ?
");
$stmt->execute([$crew_id]);
$crew = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crew) {
    die("❌ Crew member not found.");
}

$expired = fn($d) => ($d && strtotime($d) < time()) ? 'text-danger fw-bold' : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Crew Training & Credentials</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<div class="container">
    <h3>🎓 Training & Credentials: <?= htmlspecialchars($crew['fName'] . ' ' . $crew['lName']) ?></h3>
    <p><strong>Title:</strong> <?= htmlspecialchars($crew['title']) ?></p>

    <table class="table table-bordered w-75">
        <thead class="table-light">
            <tr><th>Credential</th><th>Date</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>MMC Expiration</td>
                <td class="<?= $expired($crew['mmc']) ?>"><?= htmlspecialchars($crew['mmc'] ?? '—') ?></td>
            </tr>
            <tr>
                <td>MMC Medical Certificate</td>
                <td class="<?= $expired($crew['mmc_medical']) ?>"><?= htmlspecialchars($crew['mmc_medical'] ?? '—') ?></td>
            </tr>
            <tr>
                <td>First Aid Expiration</td>
                <td class="<?= $expired($crew['fa']) ?>"><?= htmlspecialchars($crew['fa'] ?? '—') ?></td>
            </tr>
            <tr>
                <td>Marine Radio Operator Permit</td>
                <td><?= htmlspecialchars($crew['mrop'] ?? '—') ?></td>
            </tr>
        </tbody>
    </table>

    <hr>
    <h4>📝 Drill Participation Log</h4>
    <p class="text-muted">Coming soon: drill records per vessel and drill type.</p>

    <a href="log_drill.php?crew_id=<?= $crew_id ?>" class="btn btn-sm btn-success">➕ Log Drill</a>
    <a href="crew_members.php" class="btn btn-secondary">← Back to Crew List</a>
</div>

</body>
</html>
