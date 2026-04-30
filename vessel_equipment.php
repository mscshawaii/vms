<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('safe')) {
    function safe($val) {
        return isset($val) && $val !== '' ? htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') : '—';
    }
}
if (!function_exists('eq_qs')) {
    function eq_qs($k, $def=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def; }
}

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($vessel_id <= 0) {
    http_response_code(400);
    exit('Missing or invalid vessel_id.');
}

if ($role_id === 1) {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id]);
} else {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
          AND company_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id, $company_id]);
}

$vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);
if (!$vessel) {
    http_response_code(404);
    exit('Vessel not found or access denied.');
}

// ---------- incoming filters ----------
$eq_cat_id     = eq_qs('eq_cat_id', '');
$eq_type_id    = eq_qs('eq_type_id', '');
$eq_subtype_id = eq_qs('eq_subtype_id', '');
$eq_loc_like   = eq_qs('eq_loc', '');
$eq_exp_state  = eq_qs('eq_exp_state', '');
$eq_sort       = eq_qs('eq_sort', 'exp_asc');
$eq_status     = eq_qs('eq_status', 'active');

// ---------- date cutoffs ----------
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

// ---------- base query ----------
$baseSql = "
    SELECT 
        e.*, 
        cat.id  AS cat_id,   cat.name AS category_name, 
        typ.id  AS type_id,  typ.name AS type_name, 
        sub.id  AS sub_id,   sub.name AS subtype_name,
        repl.equipmentName AS replaced_by_name
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type     typ ON e.equipment_type_id = typ.id
    LEFT JOIN equipment_subtype  sub ON e.equipment_subtype_id = sub.id
    LEFT JOIN equipment          repl ON e.replaced_by_eid = repl.eid
    WHERE e.vessel_id = ?
";

// snapshot for dependent dropdowns
$allStmt = $pdo->prepare($baseSql . " ORDER BY cat.name, typ.name, sub.name");
$allStmt->execute([$vessel_id]);
$ALL = $allStmt->fetchAll(PDO::FETCH_ASSOC);

$CATS = [];
$TYPES = [];
$SUBTYPES = [];
foreach ($ALL as $r) {
    if ($r['cat_id'])  { $CATS[$r['cat_id']] = ['id'=>$r['cat_id'], 'name'=>$r['category_name'] ?? '']; }
    if ($r['type_id']) { $TYPES[$r['type_id']] = ['id'=>$r['type_id'], 'name'=>$r['type_name'] ?? '', 'cat_id'=>$r['cat_id'] ?? null]; }
    if ($r['sub_id'])  { $SUBTYPES[$r['sub_id']] = ['id'=>$r['sub_id'], 'name'=>$r['subtype_name'] ?? '', 'type_id'=>$r['type_id'] ?? null]; }
}

// filtered list
$where  = [];
$params = [$vessel_id];

if ($eq_cat_id !== '') {
    $where[]  = "e.category_id = ?";
    $params[] = (int)$eq_cat_id;
}
if ($eq_type_id !== '') {
    $where[]  = "e.equipment_type_id = ?";
    $params[] = (int)$eq_type_id;
}
if ($eq_subtype_id !== '') {
    $where[]  = "e.equipment_subtype_id = ?";
    $params[] = (int)$eq_subtype_id;
}
if ($eq_loc_like !== '') {
    $where[]  = "e.equipmentLocation LIKE ?";
    $params[] = "%{$eq_loc_like}%";
}
if ($eq_status === 'archived') {
    $where[] = "COALESCE(e.is_active, 1) = 0";
} elseif ($eq_status === 'active') {
    $where[] = "COALESCE(e.is_active, 1) = 1";
}

if ($eq_exp_state === 'expired') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) < TO_DAYS(?)";
    $params[] = $today;
} elseif ($eq_exp_state === 'soon') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) BETWEEN TO_DAYS(?) AND TO_DAYS(?)";
    $params[] = $today;
    $params[] = $soon;
} elseif ($eq_exp_state === 'valid') {
    $where[]  = "TO_DAYS(e.expDate) > 0 AND TO_DAYS(e.expDate) > TO_DAYS(?)";
    $params[] = $soon;
}

switch ($eq_sort) {
    case 'exp_desc':
        $order = "ORDER BY cat.name ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate DESC, typ.name ASC, sub.name ASC";
        break;
    case 'name_asc':
        $order = "ORDER BY cat.name ASC, typ.name ASC, sub.name ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'name_desc':
        $order = "ORDER BY cat.name ASC, typ.name DESC, sub.name DESC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'loc_asc':
        $order = "ORDER BY cat.name ASC, e.equipmentLocation ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'loc_desc':
        $order = "ORDER BY cat.name ASC, e.equipmentLocation DESC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC";
        break;
    case 'exp_asc':
    default:
        $order = "ORDER BY cat.name ASC, (TO_DAYS(e.expDate)=0) ASC, e.expDate ASC, typ.name ASC, sub.name ASC";
        break;
}

$sql = $baseSql . ($where ? " AND ".implode(' AND ', $where) : "") . " $order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$expStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM equipment
    WHERE vessel_id = ?
      AND COALESCE(is_active, 1) = 1
      AND TO_DAYS(expDate) > 0
      AND TO_DAYS(expDate) <= TO_DAYS(?)
");
$expStmt->execute([$vessel_id, $soon]);
$expiring_count = (int)($expStmt->fetchColumn() ?: 0);

$groupedRows = [];
foreach ($rows as $item) {
    $categoryLabel = trim((string)($item['category_name'] ?? ''));
    if ($categoryLabel === '') {
        $categoryLabel = 'Uncategorized';
    }
    $groupedRows[$categoryLabel][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .equip-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .equip-header {
            display:flex; justify-content:space-between; align-items:flex-start;
            gap:12px; flex-wrap:wrap; margin-bottom:16px;
        }
        .equip-title { font-size:1.65rem; font-weight:700; margin:0 0 4px; }
        .equip-subtitle { color:#6b7280; margin:0; }
        .equip-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .equip-actions .btn { min-height:42px; border-radius:12px; }

        .equipment-category-card {
            border: 1px solid #dfe5ec !important;
            border-radius: 12px !important;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            background: #fff;
        }
        .equipment-category-card + .equipment-category-card { margin-top: 1rem; }
        .equipment-category-card .accordion-button {
            background: #f8fafc;
            color: #1f2d3d;
            font-weight: 600;
            padding: 1rem 1.25rem;
            border: 0;
            box-shadow: none;
        }
        .equipment-category-card .accordion-button:not(.collapsed) {
            background: #eaf4ff;
            color: #0d6efd;
        }
        .equipment-category-card .accordion-body {
            background: #fff;
            padding: 0;
        }
        .equipment-category-card .table { margin-bottom: 0; }
        .equipment-category-card .table thead th {
            background: #f1f5f9;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #dfe5ec;
        }
        .equipment-type-header {
            background:#f8f9fa;
            border-top:1px solid #dee2e6;
            border-bottom:1px solid #dee2e6;
            padding:0.75rem 1rem;
            font-weight:600;
            color:#344054;
        }
        .equipment-action { text-decoration: none; font-weight: 500; }
        .equipment-action:hover { text-decoration: underline; }
        .table-responsive { border-top: 1px solid #e9ecef; }
        .retired-row {
            opacity: 0.78;
            background: #f8f9fa !important;
        }

        @media (max-width: 768px) {
            table { font-size: 0.9rem; }
        }
    </style>
</head>
<body>
<?php
$title = 'Vessel Equipment';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="equip-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="equip-header">
                    <div>
                        <h1 class="equip-title">Equipment</h1>
                        <p class="equip-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Official No. <?= safe($vessel['vesselON']) ?> · Hailing Port: <?= safe($vessel['hailingPort']) ?>
                        </p>
                    </div>

                    <div class="equip-actions">
                        <a href="add_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">Add Equipment</a>
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                    </div>
                </div>

                <?php if ($expiring_count > 0): ?>
                    <div class="alert alert-warning mb-0">
                        <?= (int)$expiring_count ?> equipment item<?= $expiring_count === 1 ? '' : 's' ?> expired or expiring within 60 days.
                    </div>
                <?php endif; ?>

                <?php if (($_GET['success'] ?? '') === 'equipment_deleted'): ?>
                    <div class="alert alert-success mb-0 mt-3">
                        Erroneous duplicate equipment record deleted.
                    </div>
                <?php endif; ?>
            </div>

            <div class="vms-card mb-3">
                <form class="row g-2" method="get" id="eqFilterForm">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Category</label>
                        <select name="eq_cat_id" id="eq_cat_id" class="form-select">
                            <option value="">(all)</option>
                            <?php foreach ($CATS as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ($eq_cat_id !== '' && $eq_cat_id == $c['id']) ? 'selected' : '' ?>>
                                    <?= safe($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Type</label>
                        <select name="eq_type_id" id="eq_type_id" class="form-select">
                            <option value="">(all)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Subtype</label>
                        <select name="eq_subtype_id" id="eq_subtype_id" class="form-select">
                            <option value="">(all)</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Location</label>
                        <input type="text" name="eq_loc" value="<?= htmlspecialchars($eq_loc_like, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Search…">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Expiration</label>
                        <select name="eq_exp_state" class="form-select">
                            <option value="">(all)</option>
                            <option value="expired" <?= $eq_exp_state==='expired'?'selected':'' ?>>Expired</option>
                            <option value="soon" <?= $eq_exp_state==='soon'?'selected':'' ?>>Expiring ≤ 60d</option>
                            <option value="valid" <?= $eq_exp_state==='valid'?'selected':'' ?>>Valid &gt; 60d</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Status</label>
                        <select name="eq_status" class="form-select">
                            <option value="active" <?= $eq_status==='active'?'selected':'' ?>>Active</option>
                            <option value="archived" <?= $eq_status==='archived'?'selected':'' ?>>Retired / Replaced</option>
                            <option value="all" <?= $eq_status==='all'?'selected':'' ?>>All</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label mb-1">Sort</label>
                        <select name="eq_sort" class="form-select">
                            <option value="exp_asc" <?= $eq_sort==='exp_asc'?'selected':'' ?>>Expiration ↑</option>
                            <option value="exp_desc" <?= $eq_sort==='exp_desc'?'selected':'' ?>>Expiration ↓</option>
                            <option value="name_asc" <?= $eq_sort==='name_asc'?'selected':'' ?>>Type/Subtype A→Z</option>
                            <option value="name_desc" <?= $eq_sort==='name_desc'?'selected':'' ?>>Type/Subtype Z→A</option>
                            <option value="loc_asc" <?= $eq_sort==='loc_asc'?'selected':'' ?>>Location A→Z</option>
                            <option value="loc_desc" <?= $eq_sort==='loc_desc'?'selected':'' ?>>Location Z→A</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary">Apply</button>
                        <a class="btn btn-outline-secondary" href="vessel_equipment.php?vessel_id=<?= (int)$vessel_id ?>">Reset</a>
                        <a href="print_equipment.php?<?= http_build_query([
                            'vessel_id'     => (int)$vessel_id,
                            'eq_cat_id'     => $eq_cat_id,
                            'eq_type_id'    => $eq_type_id,
                            'eq_subtype_id' => $eq_subtype_id,
                            'eq_loc'        => $eq_loc_like,
                            'eq_exp_state'  => $eq_exp_state,
                            'eq_status'     => $eq_status,
                            'eq_sort'       => $eq_sort,
                        ]) ?>" class="btn btn-outline-secondary" target="_blank">Print</a>
                    </div>
                </form>
            </div>

            <?php if (!$rows): ?>
                <div class="vms-card">
                    <div class="text-center text-muted p-3">No equipment matches your filters.</div>
                </div>
            <?php else: ?>

                <div class="d-flex justify-content-end gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllEquipment">Expand All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllEquipment">Collapse All</button>
                </div>

                <div class="accordion" id="equipmentCategoryAccordion">
                    <?php
                    $catIndex = 0;
                    foreach ($groupedRows as $categoryName => $items):
                        $catIndex++;
                        $collapseId = 'eqCategoryCollapse_' . $catIndex;
                        $headingId  = 'eqCategoryHeading_' . $catIndex;

                        $catExpiringCount = 0;
                        foreach ($items as $tmpItem) {
                            $tmpExp = ($tmpItem['expDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$tmpItem['expDate']) && $tmpItem['expDate'] !== '0000-00-00')
                                ? $tmpItem['expDate'] : null;
                            if ($tmpExp !== null && $tmpExp <= $soon) {
                                $catExpiringCount++;
                            }
                        }
                    ?>
                        <div class="accordion-item mb-2 border rounded equipment-category-card">
                            <h2 class="accordion-header" id="<?= $headingId ?>">
                                <button class="accordion-button collapsed equipment-category-toggle" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?= $collapseId ?>"
                                        aria-expanded="false"
                                        aria-controls="<?= $collapseId ?>">
                                    <div class="w-100 d-flex justify-content-between align-items-center flex-wrap gap-2 pe-3">
                                        <div><strong><?= safe($categoryName) ?></strong></div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-secondary"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
                                            <?php if ($catExpiringCount > 0): ?>
                                                <span class="badge bg-warning text-dark"><?= $catExpiringCount ?> expiring</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </button>
                            </h2>

                            <div id="<?= $collapseId ?>" class="accordion-collapse collapse" aria-labelledby="<?= $headingId ?>" data-bs-parent="#equipmentCategoryAccordion">
                                <div class="accordion-body p-0">
                                    <?php
                                    $groupedByType = [];
                                    foreach ($items as $item) {
                                        $typeLabel = trim((string)($item['type_name'] ?? ''));
                                        if ($typeLabel === '') $typeLabel = 'Unspecified Type';
                                        $groupedByType[$typeLabel][] = $item;
                                    }
                                    ksort($groupedByType, SORT_NATURAL | SORT_FLAG_CASE);
                                    ?>

                                    <?php foreach ($groupedByType as $typeName => $typeItems): ?>
                                        <div class="equipment-type-header d-flex justify-content-between align-items-center">
                                            <span><?= safe($typeName) ?></span>
                                            <span class="badge bg-light text-dark border">
                                                <?= count($typeItems) ?> item<?= count($typeItems) !== 1 ? 's' : '' ?>
                                            </span>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Subtype</th>
                                                        <th>Location</th>
                                                        <th>Status</th>
                                                        <th>Expires</th>
                                                        <th>Retired</th>
                                                        <th>Replacement</th>
                                                        <th>Quantity</th>
                                                        <th>Unit</th>
                                                        <th class="text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($typeItems as $item): ?>
                                                    <?php
                                                    $isArchived = (int)($item['is_active'] ?? 1) !== 1;
                                                    $row_class = '';
                                                    $exp = ($item['expDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$item['expDate']) && $item['expDate'] !== '0000-00-00')
                                                        ? $item['expDate'] : '—';

                                                    if ($isArchived) {
                                                        $row_class = 'retired-row';
                                                    } elseif ($exp !== '—') {
                                                        if ($exp < $today) $row_class = 'table-danger';
                                                        elseif ($exp <= $soon) $row_class = 'table-warning';
                                                    }
                                                    ?>
                                                    <tr class="<?= $row_class ?>">
                                                        <td><?= safe($item['subtype_name']) ?></td>
                                                        <td><?= safe($item['equipmentLocation']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $isArchived ? 'bg-secondary' : 'bg-success' ?>">
                                                                <?= $isArchived ? 'Retired' : 'Active' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= safe($exp) ?></td>
                                                        <td><?= safe($item['retired_at'] ?? null) ?></td>
                                                        <td>
                                                            <?php if (!empty($item['replaced_by_eid'])): ?>
                                                                <a href="equipment_detail.php?id=<?= (int)$item['replaced_by_eid'] ?>">
                                                                    <?= safe($item['replaced_by_name'] ?: ('Equipment #' . (int)$item['replaced_by_eid'])) ?>
                                                                </a>
                                                            <?php else: ?>
                                                                —
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['retirement_reason'])): ?>
                                                                <div class="small text-muted"><?= safe($item['retirement_reason']) ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= safe($item['quantity']) ?></td>
                                                        <td><?= safe($item['unit']) ?></td>
                                                        <td class="text-center text-nowrap">
                                                            <a href="equipment_detail.php?id=<?= (int)$item['eid'] ?>" class="equipment-action">View</a>
                                                            <?php if (!$isArchived): ?>
                                                                | <a href="edit_equipment.php?id=<?= (int)$item['eid'] ?>" class="equipment-action">Edit</a>
                                                                | <a href="equipment_replace.php?id=<?= (int)$item['eid'] ?>" class="equipment-action text-warning">Replace</a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
const EQ_TYPES = [];
const EQ_SUBTYPES = [];
<?php foreach ($TYPES as $t): ?>
EQ_TYPES.push({id: "<?= (int)$t['id'] ?>", name: <?= json_encode((string)$t['name']) ?>, cat_id: "<?= (string)$t['cat_id'] ?>"});
<?php endforeach; ?>
<?php foreach ($SUBTYPES as $s): ?>
EQ_SUBTYPES.push({id: "<?= (int)$s['id'] ?>", name: <?= json_encode((string)$s['name']) ?>, type_id: "<?= (string)$s['type_id'] ?>"});
<?php endforeach; ?>

const curCat = "<?= htmlspecialchars($eq_cat_id, ENT_QUOTES, 'UTF-8') ?>";
const curType = "<?= htmlspecialchars($eq_type_id, ENT_QUOTES, 'UTF-8') ?>";
const curSubtype = "<?= htmlspecialchars($eq_subtype_id, ENT_QUOTES, 'UTF-8') ?>";

const catSel = document.getElementById('eq_cat_id');
const typeSel = document.getElementById('eq_type_id');
const subSel = document.getElementById('eq_subtype_id');

function option(el, val, label, selected=false) {
    const o = document.createElement('option');
    o.value = val;
    o.textContent = label;
    if (selected) o.selected = true;
    el.appendChild(o);
}

function renderTypes() {
    const selCatId = catSel.value;
    typeSel.innerHTML = '';
    option(typeSel, '', '(all)', curType === '' || !selCatId);

    const filtered = EQ_TYPES.filter(t => !selCatId || String(t.cat_id) === String(selCatId));
    filtered.forEach(t => option(typeSel, t.id, t.name, String(t.id) === String(curType)));

    renderSubtypes(true);
}

function renderSubtypes(resetSel=false) {
    const selTypeId = typeSel.value;
    subSel.innerHTML = '';
    option(subSel, '', '(all)', resetSel || curSubtype === '' || !selTypeId);

    const filtered = EQ_SUBTYPES.filter(s => !selTypeId || String(s.type_id) === String(selTypeId));
    filtered.forEach(s => option(subSel, s.id, s.name, !resetSel && String(s.id) === String(curSubtype)));
}

catSel.addEventListener('change', () => {
    renderTypes();
    if (typeSel.value && !EQ_TYPES.some(t => String(t.id) === String(typeSel.value) && String(t.cat_id) === String(catSel.value))) {
        typeSel.value = '';
        renderSubtypes(true);
    }
});
typeSel.addEventListener('change', () => renderSubtypes(true));

renderTypes();
renderSubtypes();

document.getElementById('expandAllEquipment')?.addEventListener('click', function () {
    document.querySelectorAll('#equipmentCategoryAccordion .accordion-collapse').forEach(el => {
        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        bsCollapse.show();
    });
});

document.getElementById('collapseAllEquipment')?.addEventListener('click', function () {
    document.querySelectorAll('#equipmentCategoryAccordion .accordion-collapse').forEach(el => {
        const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
        bsCollapse.hide();
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
