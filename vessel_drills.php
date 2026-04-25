<?php
require_once 'session_check.php';
require_once 'db_connect.php';

function safe($value) {
    return !empty($value) ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '—';
}

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($role_id === 1) {
    $stmt = $pdo->prepare("SELECT * FROM vessels WHERE vessel_id = ?");
    $stmt->execute([$vessel_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM vessels WHERE vessel_id = ? AND company_id = ?");
    $stmt->execute([$vessel_id, $company_id]);
}

$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("❌ Access denied or vessel not found.");
}

// Load linked vessel ids once
$linked_ids = [$vessel_id];

$group_stmt = $pdo->prepare("SELECT group_id FROM linked_vessels WHERE vessel_id = ?");
$group_stmt->execute([$vessel_id]);
$group_id = $group_stmt->fetchColumn();

if ($group_id) {
    $vessels_stmt = $pdo->prepare("SELECT vessel_id FROM linked_vessels WHERE group_id = ?");
    $vessels_stmt->execute([$group_id]);
    $linked_ids = $vessels_stmt->fetchAll(PDO::FETCH_COLUMN);
    $linked_ids[] = $vessel_id;
    $linked_ids = array_values(array_unique(array_map('intval', $linked_ids)));
}

// Fetch only operational drill crew
$crew_stmt = $pdo->prepare("
    SELECT DISTINCT
        u.id,
        u.fName,
        u.lName,
        vc.role
    FROM vessel_crew vc
    INNER JOIN users u ON u.id = vc.crew_id
    WHERE vc.vessel_id = ?
      AND vc.is_active = 1
      AND u.is_active = 1
      AND vc.counts_for_drills = 1
      AND vc.role IN ('Master', 'Deckhand')
    ORDER BY
        FIELD(vc.role, 'Master', 'Deckhand'),
        u.lName,
        u.fName
");
$crew_stmt->execute([$vessel_id]);
$crew = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);

$drill_types = ['Fire', 'Man Overboard', 'Abandon Ship'];
$placeholders = implode(',', array_fill(0, count($linked_ids), '?'));

$drill_stmt = $pdo->prepare("
    SELECT MAX(drill_date)
    FROM crew_drills
    WHERE crew_user_id = ?
      AND drill_type = ?
      AND vessel_id IN ($placeholders)
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drills - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .drills-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .drills-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .drills-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .drills-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .drills-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .drills-actions .btn {
            border-radius: 12px;
            min-height: 42px;
        }

        .drills-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .drills-table-wrap table {
            min-width: 720px;
            margin-bottom: 0;
        }

        .drills-empty {
            padding: 20px;
            text-align: center;
            color: #6b7280;
        }
    </style>
</head>
<body>
<?php
$title = ($vessel['vesselName'] ?? 'Vessel') . ' Drills';
$back_link = "vessel_dashboard.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="drills-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="drills-header">
                    <div>
                        <h1 class="drills-title">Drills</h1>
                        <p class="drills-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Crew drill currency overview
                        </p>
                    </div>

                    <div class="drills-actions">
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                        <a href="drill_history.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">Drill History</a>
                    </div>
                </div>

                <?php if (empty($crew)): ?>
                    <div class="drills-empty">
                        No active Master or Deckhand assignments found for this vessel.
                    </div>
                <?php else: ?>
                    <div class="drills-table-wrap">
                        <table class="table table-bordered table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Crew Member</th>
                                    <?php foreach ($drill_types as $type): ?>
                                        <th><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?> Drill</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($crew as $member): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars(($member['fName'] ?? '') . ' ' . ($member['lName'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (!empty($member['role'])): ?>
                                                <span class="text-muted">(<?= htmlspecialchars($member['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                            <?php endif; ?>
                                        </td>

                                        <?php foreach ($drill_types as $type): ?>
                                            <?php
                                            $params = array_merge([(int)$member['id'], $type], $linked_ids);
                                            $drill_stmt->execute($params);
                                            $last_drill = $drill_stmt->fetchColumn();

                                            if ($last_drill) {
                                                $days_ago = (new DateTime())->diff(new DateTime($last_drill))->days;

                                                if ($days_ago <= 60) {
                                                    $badge = 'success';
                                                } elseif ($days_ago <= 90) {
                                                    $badge = 'warning';
                                                } else {
                                                    $badge = 'danger';
                                                }

                                                echo "<td><span class='badge bg-$badge'>" . htmlspecialchars($last_drill, ENT_QUOTES, 'UTF-8') . "</span></td>";
                                            } else {
                                                echo "<td><span class='badge bg-secondary'>None</span></td>";
                                            }
                                            ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

</body>
</html>