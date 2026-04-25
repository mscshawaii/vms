<?php
session_start();
require 'session_check.php';
require 'db_connect.php';

function safe($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$vessel_id = isset($_GET['vessel_id']) ? (int)$_GET['vessel_id'] : 0;
if ($vessel_id === 0) {
    die("Invalid vessel ID.");
}

$role_id = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($role_id === 1) {
    $vesselStmt = $pdo->prepare("SELECT vessel_id, vesselName FROM vessels WHERE vessel_id = ?");
    $vesselStmt->execute([$vessel_id]);
} else {
    $vesselStmt = $pdo->prepare("SELECT vessel_id, vesselName FROM vessels WHERE vessel_id = ? AND company_id = ?");
    $vesselStmt->execute([$vessel_id, $company_id]);
}

$vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);
if (!$vessel) {
    die("Access denied or vessel not found.");
}

// Filter values
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$crew_name  = trim($_GET['crew_name'] ?? '');

// Build query
$sql = "
    SELECT cd.*, 
           u.fName, u.lName, 
           v.vesselName
    FROM crew_drills cd
    JOIN users u ON cd.crew_user_id = u.id
    JOIN vessels v ON cd.vessel_id = v.vessel_id
    WHERE cd.vessel_id = :vessel_id
";
$params = [':vessel_id' => $vessel_id];

if (!empty($start_date)) {
    $sql .= " AND cd.drill_date >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $sql .= " AND cd.drill_date <= :end_date";
    $params[':end_date'] = $end_date;
}
if (!empty($crew_name)) {
    $sql .= " AND (u.fName LIKE :crew_name OR u.lName LIKE :crew_name)";
    $params[':crew_name'] = '%' . $crew_name . '%';
}

$sql .= " ORDER BY cd.drill_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$drills = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drill History - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .history-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .history-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .history-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .history-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .history-actions .btn {
            border-radius: 12px;
            min-height: 42px;
        }

        .history-filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .history-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .history-table-wrap table {
            min-width: 760px;
            margin-bottom: 0;
        }

        @media (min-width: 768px) {
            .history-filter-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                align-items: end;
            }
        }
    </style>
</head>
<body>
<?php
$title = ($vessel['vesselName'] ?? 'Vessel') . ' Drill History';
$back_link = "vessel_drills.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="history-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="history-header">
                    <div>
                        <h1 class="history-title">Drill History</h1>
                        <p class="history-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Filter and review recorded drill entries
                        </p>
                    </div>

                    <div class="history-actions">
                        <a href="vessel_drills.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Drills</a>
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-primary">Vessel Dashboard</a>
                    </div>
                </div>

                <form method="GET" class="mb-3">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="history-filter-grid">
                        <div>
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" value="<?= safe($start_date) ?>" class="form-control">
                        </div>

                        <div>
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" value="<?= safe($end_date) ?>" class="form-control">
                        </div>

                        <div>
                            <label class="form-label">Crew Member</label>
                            <input type="text" name="crew_name" placeholder="e.g. John" value="<?= safe($crew_name) ?>" class="form-control">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                        </div>
                    </div>
                </form>

                <?php if (count($drills) > 0): ?>
                    <div class="history-table-wrap">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Crew Member</th>
                                    <th>Drill Type</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($drills as $row): ?>
                                    <tr>
                                        <td><?= safe($row['drill_date']) ?></td>
                                        <td><?= safe(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')) ?></td>
                                        <td><?= safe($row['drill_type']) ?></td>
                                        <td><?= safe($row['notes'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">No drills found for this vessel.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

</body>
</html>