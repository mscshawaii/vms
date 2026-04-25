<?php
require 'db_connect.php';

function safe($value) {
    return !empty($value) ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '—';
}

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    http_response_code(400);
    exit('Missing QR code.');
}

/*
|--------------------------------------------------------------------------
| Resolve QR / vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id
    FROM qr_links
    WHERE code = ?
      AND asset_type = 'drills'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$vessel_id = (int)$stmt->fetchColumn();

if ($vessel_id <= 0) {
    http_response_code(404);
    exit('QR code not found.');
}

/*
|--------------------------------------------------------------------------
| Vessel
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    http_response_code(404);
    exit('Vessel not found.');
}

/*
|--------------------------------------------------------------------------
| Linked vessel ids
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Crew counted for drills
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Drill compliance lookups
|--------------------------------------------------------------------------
*/
$drill_types = ['Fire', 'Man Overboard', 'Abandon Ship'];
$placeholders = implode(',', array_fill(0, count($linked_ids), '?'));

$drill_stmt = $pdo->prepare("
    SELECT MAX(drill_date)
    FROM crew_drills
    WHERE crew_user_id = ?
      AND drill_type = ?
      AND vessel_id IN ($placeholders)
");

/*
|--------------------------------------------------------------------------
| Assigned drill ICRs (K*)
|--------------------------------------------------------------------------
*/
$drillListStmt = $pdo->prepare("
    SELECT
        vi.vessel_icr_id,
        vi.icr_id,
        COALESCE(vi.icr_number, i.icr_number) AS icr_number,
        COALESCE(vi.title, i.title) AS title,
        COALESCE(vi.frequency, i.frequency) AS frequency,
        i.drill_type
    FROM vessel_icrs vi
    LEFT JOIN icrs i
        ON i.icr_id = vi.icr_id
    WHERE vi.vessel_id = ?
      AND COALESCE(vi.icr_number, i.icr_number) LIKE 'K%'
    ORDER BY COALESCE(vi.icr_number, i.icr_number), COALESCE(vi.title, i.title)
");
$drillListStmt->execute([$vessel_id]);
$assignedDrills = $drillListStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drill QR Center</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .hero-card,
        .info-card {
            border: 1px solid #e5e5e5;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .drill-chip {
            display: inline-block;
            margin: 0 8px 8px 0;
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            background: #f8f9fa;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="hero-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-muted mb-1">Vessel Drill QR Center</div>
                <h2 class="mb-1"><?= safe($vessel['vesselName']) ?></h2>
                <div class="text-muted">
                    <?php if (!empty($vessel['vesselON'])): ?>
                        ON <?= safe($vessel['vesselON']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <a href="open_drill_qr.php?code=<?= urlencode($code) ?>" class="btn btn-primary">
                    Open Drill Center
                </a>
            </div>
        </div>
    </div>

    <div class="info-card p-4 mb-4">
        <div class="section-title">Assigned Drill ICRs</div>

        <?php if (!$assignedDrills): ?>
            <div class="text-muted">No drill ICRs assigned to this vessel.</div>
        <?php else: ?>
            <div>
                <?php foreach ($assignedDrills as $drill): ?>
                    <span class="drill-chip">
                        <?= safe($drill['icr_number']) ?>
                        <?php if (!empty($drill['title'])): ?>
                            — <?= safe($drill['title']) ?>
                        <?php endif; ?>
                        <?php if (!empty($drill['drill_type'])): ?>
                            (<?= safe($drill['drill_type']) ?>)
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="info-card p-4">
        <div class="section-title">Crew Drill Currency Overview</div>

        <?php if (empty($crew)): ?>
            <div class="text-muted">
                No active Master or Deckhand assignments found for this vessel.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
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
</body>
</html>