<?php
require 'db_connect.php';
require 'session_check.php';

function safe($v){ return ($v !== null && $v !== '') ? htmlspecialchars($v) : '—'; }
function qs($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) { http_response_code(400); echo "Missing or invalid vessel_id."; exit; }

$cat_id     = qs('cat_id','');
$type_id    = qs('type_id','');
$subtype_id = qs('subtype_id','');
$loc_like   = qs('loc','');
$exp_state  = qs('exp_state','');
$sort       = qs('sort','exp_asc');

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

// Vessel name for header
$vn = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ? LIMIT 1");
$vn->execute([$vessel_id]);
$vesselName = (string)$vn->fetchColumn();

$where  = ["e.vessel_id = ?"];
$params = [$vessel_id];

if ($cat_id !== '')     { $where[]="e.category_id = ?";           $params[]=(int)$cat_id; }
if ($type_id !== '')    { $where[]="e.equipment_type_id = ?";     $params[]=(int)$type_id; }
if ($subtype_id !== '') { $where[]="e.equipment_subtype_id = ?";  $params[]=(int)$subtype_id; }
if ($loc_like !== '')   { $where[]="e.equipmentLocation LIKE ?";  $params[]="%{$loc_like}%"; }

if ($exp_state === 'expired') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) < TO_DAYS(?)";
    $params[] = $today;
} elseif ($exp_state === 'soon') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) BETWEEN TO_DAYS(?) AND TO_DAYS(?)";
    $params[] = $today;
    $params[] = $soon;
} elseif ($exp_state === 'valid') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) > TO_DAYS(?)";
    $params[] = $soon;
}

switch ($sort) {
    case 'exp_desc':
        $order = "ORDER BY (TO_DAYS(e.expDate)=0) ASC, e.expDate DESC, cat.name ASC, typ.name ASC, sub.name ASC";
        break;
    case 'name_asc':
        $order = "ORDER BY typ.name ASC, sub.name ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'name_desc':
        $order = "ORDER BY typ.name DESC, sub.name DESC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'loc_asc':
        $order = "ORDER BY e.equipmentLocation ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'loc_desc':
        $order = "ORDER BY e.equipmentLocation DESC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'exp_asc':
    default:
        $order = "ORDER BY (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC, cat.name ASC, typ.name ASC, sub.name ASC";
        break;
}

$sql = "
    SELECT
        e.*,
        cat.name AS category_name,
        typ.name AS type_name,
        sub.name AS subtype_name
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type typ ON e.equipment_type_id = typ.id
    LEFT JOIN equipment_subtype sub ON e.equipment_subtype_id = sub.id
    WHERE ".implode(' AND ', $where)."
    $order
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print Equipment • <?= safe($vesselName) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
  body { padding: 24px; }
  .chip { display:inline-block; padding:.25rem .5rem; border:1px solid #dee2e6; border-radius:999px; font-size:.85rem; margin-right:.5rem; color:#495057; background:#f8f9fa; }
  .table thead th { background:#f1f3f5; }
  .table-warning td, .table-danger td { background-clip: padding-box; }
  @media print { .no-print { display:none !important; } @page { margin:12mm; } body{ padding:0; } }
</style>
</head>
<body>

<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h4 class="mb-1">Vessel Equipment</h4>
    <div class="text-muted">Vessel: <?= safe($vesselName) ?></div>
    <div class="mt-2">
      <?php if ($cat_id!==''): ?><span class="chip">Category #<?= safe($cat_id) ?></span><?php endif; ?>
      <?php if ($type_id!==''): ?><span class="chip">Type #<?= safe($type_id) ?></span><?php endif; ?>
      <?php if ($subtype_id!==''): ?><span class="chip">Subtype #<?= safe($subtype_id) ?></span><?php endif; ?>
      <?php if ($loc_like!==''): ?><span class="chip">Location: <?= safe($loc_like) ?></span><?php endif; ?>
      <?php if ($exp_state!==''): ?><span class="chip">Exp: <?= safe(ucfirst($exp_state)) ?></span><?php endif; ?>
      <span class="chip">Sort: <?= safe($sort) ?></span>
    </div>
  </div>
  <div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#equipment">Close</a>
  </div>
</div>

<table class="table table-bordered table-striped align-middle">
  <thead class="table-light">
    <tr>
      <th>Category</th>
      <th>Type</th>
      <th>Subtype</th>
      <th>Location</th>
      <th style="width:16%">Expires</th>
      <th style="width:10%">Qty</th>
      <th style="width:10%">Unit</th>
    </tr>
  </thead>
  <tbody>
  <?php
    if (!$rows) {
        echo "<tr><td colspan='7' class='text-center text-muted'>No equipment matches your filters.</td></tr>";
    } else {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

        foreach ($rows as $r) {
            $exp = ($r['expDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r['expDate']) && $r['expDate'] !== '0000-00-00')
                ? $r['expDate'] : '—';
            $row_class = '';
            if ($exp !== '—') {
                if ($exp < $today)      $row_class = 'table-danger';
                elseif ($exp <= $soon)  $row_class = 'table-warning';
            }
            echo "<tr class='{$row_class}'>";
            echo "<td>".safe($r['category_name'])."</td>";
            echo "<td>".safe($r['type_name'])."</td>";
            echo "<td>".safe($r['subtype_name'])."</td>";
            echo "<td>".safe($r['equipmentLocation'])."</td>";
            echo "<td>".safe($exp)."</td>";
            echo "<td>".safe($r['quantity'])."</td>";
            echo "<td>".safe($r['unit'])."</td>";
            echo "</tr>";
        }
    }
  ?>
  </tbody>
</table>

</body>
</html>
