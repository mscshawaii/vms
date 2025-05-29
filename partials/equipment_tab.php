<?php if (!isset($vessel_id)) exit('Vessel ID missing'); ?>

<!-- Equipment Tab -->
<div class="tab-pane fade show active" id="equipment" role="tabpanel">
    <h4>Equipment</h4>

    <a href="index.php?vessel_id=<?= $vessel_id ?>" class="btn btn-success mb-3">➕ Add Equipment</a>

    <?php
    if (!function_exists('safe')) {
        function safe($val) {
            return isset($val) && $val !== '' ? htmlspecialchars($val) : '—';
        }
    }

    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+60 days'));
    
    // Fetch expiring equipment count
    $expStmt = $pdo->prepare("
        SELECT COUNT(*) AS expiring
        FROM equipment
        WHERE vessel_id = ?
          AND expDate IS NOT NULL
          AND STR_TO_DATE(expDate, '%Y-%m-%d') IS NOT NULL
          AND STR_TO_DATE(expDate, '%Y-%m-%d') <= ?
    ");
    $expStmt->execute([$vessel_id, $soon]);
    $row = $expStmt->fetch(PDO::FETCH_ASSOC);
    $expiring_count = (int)($row['expiring'] ?? 0);
    
    if ($expiring_count > 0): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2">
            ⚠️ 
            <span><strong><?= $expiring_count ?></strong> equipment item<?= $expiring_count > 1 ? 's are' : ' is' ?> expired or expiring within 60 days.</span>
        </div>
    <?php endif; ?>
    

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th>Subtype</th>
                <th>Location</th>
                <th>Expires</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
<?php
$today = new DateTime();
$soon = (clone $today)->modify('+60 days');

$eqStmt = $pdo->prepare("
    SELECT 
        e.*, 
        cat.name AS category_name, 
        typ.name AS type_name, 
        sub.name AS subtype_name
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type typ ON e.equipment_type_id = typ.id
    LEFT JOIN equipment_subtype sub ON e.equipment_subtype_id = sub.id
    WHERE e.vessel_id = ?
    ORDER BY e.expDate
");

$eqStmt->execute([$vessel_id]);

while ($item = $eqStmt->fetch(PDO::FETCH_ASSOC)) {
    $row_class = '';

    if (!empty($item['expDate']) && $item['expDate'] !== '0000-00-00') {
        $expDate = DateTime::createFromFormat('Y-m-d', $item['expDate']);
        if ($expDate) {
            if ($expDate < $today) {
                $row_class = 'table-danger'; // 🔴 Expired
            } elseif ($expDate <= $soon) {
                $row_class = 'table-warning'; // 🟡 Expiring soon
            }
        }
    }

    echo "<tr class='$row_class'>
        <td>" . safe($item['category_name']) . "</td>
        <td>" . safe($item['type_name']) . "</td>
        <td>" . safe($item['subtype_name']) . "</td>
        <td>" . safe($item['equipmentLocation']) . "</td>
        <td>" . safe($item['expDate']) . "</td>
        <td>" . safe($item['quantity']) . "</td>
        <td>" . safe($item['unit']) . "</td>
        <td class='text-center'>
            <a href='equipment_detail.php?id={$item['eid']}' class='equipment-action'>View</a> |
            <a href='edit_equipment.php?id={$item['eid']}' class='equipment-action'>Edit</a> |
            <a href='delete_equipment.php?id={$item['eid']}' class='equipment-action text-danger' onclick=\"return confirm('Are you sure you want to delete this item?')\">Delete</a>
        </td>
    </tr>";
}
?>
</tbody>

    </table>
</div>

<!-- Styles for Actions and Mobile Responsiveness -->
<style>
/* Action Links */
.action-links a {
    text-decoration: none;
    color: inherit;
    font-weight: 500;
    padding: 0 5px;
    transition: all 0.2s ease-in-out;
}

.action-links a:hover {
    text-decoration: underline;
}

/* Special glow for delete link */
.action-links a.text-danger:hover {
    color: #dc3545;
    text-decoration: underline;
    font-weight: 600;
    text-shadow: 0 0 5px rgba(220, 53, 69, 0.7);
}

/* Center and nowrap for action column */
td.text-center {
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}

/* Mobile tweaks */
@media (max-width: 768px) {
    table {
        font-size: 0.9rem;
    }
    .action-links a {
        display: block;
        margin-bottom: 5px;
    }
}
</style>
