<?php
require_once __DIR__ . '/../db_connect.php';

$vessel_id = (int)($_GET['vessel_id'] ?? 0);

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

// Drill types to check
$drill_types = ['Fire', 'Man Overboard', 'Abandon Ship'];
?>

<div class="d-flex justify-content-end mb-3">
    <a href="drill_history.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-sm btn-secondary">Drill History</a>
</div>

<table class="table table-bordered table-striped">
    <thead class="table-light">
        <tr>
            <th>Crew Member</th>
            <?php foreach ($drill_types as $type): ?>
                <th><?= htmlspecialchars($type) ?> Drill</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($crew)): ?>
            <tr>
                <td colspan="<?= 1 + count($drill_types) ?>" class="text-center text-muted">
                    No active Master or Deckhand assignments found for this vessel.
                </td>
            </tr>
        <?php else: ?>
            <?php
            $placeholders = implode(',', array_fill(0, count($linked_ids), '?'));
            $drill_stmt = $pdo->prepare("
                SELECT MAX(drill_date)
                FROM crew_drills
                WHERE crew_user_id = ?
                  AND drill_type = ?
                  AND vessel_id IN ($placeholders)
            ");
            ?>

            <?php foreach ($crew as $member): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(($member['fName'] ?? '') . ' ' . ($member['lName'] ?? '')) ?>
                        <?php if (!empty($member['role'])): ?>
                            <span class="text-muted">(<?= htmlspecialchars($member['role']) ?>)</span>
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

                            echo "<td><span class='badge bg-$badge'>" . htmlspecialchars($last_drill) . "</span></td>";
                        } else {
                            echo "<td><span class='badge bg-secondary'>None</span></td>";
                        }
                        ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>