<?php if (!isset($vessel_id)) exit('Vessel ID missing'); ?>

<h4 class="d-flex justify-content-between align-items-center">
  <span>Equipment</span>
  <span class="ms-2">
    <a href="index.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-success btn-sm">➕ Add Equipment</a>
  </span>
</h4>

<?php
// ---------- helpers ----------
if (!function_exists('safe')) {
    function safe($val) { return isset($val) && $val !== '' ? htmlspecialchars($val) : '—'; }
}
if (!function_exists('eq_qs')) {
    function eq_qs($k, $def=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def; }
}

// ---------- incoming filters (all prefixed) ----------
$eq_cat_id     = eq_qs('eq_cat_id', '');
$eq_type_id    = eq_qs('eq_type_id', '');
$eq_subtype_id = eq_qs('eq_subtype_id', '');
$eq_loc_like   = eq_qs('eq_loc', '');
$eq_exp_state  = eq_qs('eq_exp_state', ''); // '', expired, soon, valid
$eq_sort       = eq_qs('eq_sort', 'exp_asc'); // exp_asc|exp_desc|name_asc|name_desc|loc_asc|loc_desc

// ---------- date cutoffs ----------
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

// ---------- base query ----------
$baseSql = "
    SELECT 
        e.*, 
        cat.id  AS cat_id,   cat.name AS category_name, 
        typ.id  AS type_id,  typ.name AS type_name, 
        sub.id  AS sub_id,   sub.name AS subtype_name
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type     typ ON e.equipment_type_id = typ.id
    LEFT JOIN equipment_subtype  sub ON e.equipment_subtype_id = sub.id
    WHERE e.vessel_id = ?
";

// 1) Unfiltered snapshot to build dependent dropdown data
$allStmt = $pdo->prepare($baseSql . " ORDER BY cat.name, typ.name, sub.name");
$allStmt->execute([(int)$vessel_id]);
$ALL = $allStmt->fetchAll(PDO::FETCH_ASSOC);

// build unique lists & relationships from $ALL
$CATS = [];
$TYPES = [];
$SUBTYPES = [];
foreach ($ALL as $r) {
    if ($r['cat_id'])  { $CATS[$r['cat_id']] = ['id'=>$r['cat_id'], 'name'=>$r['category_name'] ?? '']; }
    if ($r['type_id']) { $TYPES[$r['type_id']] = ['id'=>$r['type_id'], 'name'=>$r['type_name'] ?? '', 'cat_id'=>$r['cat_id'] ?? null]; }
    if ($r['sub_id'])  { $SUBTYPES[$r['sub_id']] = ['id'=>$r['sub_id'], 'name'=>$r['subtype_name'] ?? '', 'type_id'=>$r['type_id'] ?? null]; }
}

// 2) Apply filters to main list
$where  = [];
$params = [(int)$vessel_id];

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

// Expiration state
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

// sort
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

$sql = $baseSql . ( $where ? " AND ".implode(' AND ', $where) : "" ) . " $order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// count expiring for banner
$expStmt = $pdo->prepare("
    SELECT COUNT(*) AS expiring
    FROM equipment
    WHERE vessel_id = ?
      AND TO_DAYS(expDate) > 0
      AND TO_DAYS(expDate) <= TO_DAYS(?)
");
$expStmt->execute([(int)$vessel_id, $soon]);
$expiring_count = (int)($expStmt->fetchColumn() ?: 0);

// current querystring for print
$qs = http_build_query([
    'vessel_id'     => (int)$vessel_id,
    'eq_cat_id'     => $eq_cat_id,
    'eq_type_id'    => $eq_type_id,
    'eq_subtype_id' => $eq_subtype_id,
    'eq_loc'        => $eq_loc_like,
    'eq_exp_state'  => $eq_exp_state,
    'eq_sort'       => $eq_sort,
]);

// ---------- group rows by category ----------
$groupedRows = [];
foreach ($rows as $item) {
    $categoryLabel = trim((string)($item['category_name'] ?? ''));
    if ($categoryLabel === '') {
        $categoryLabel = 'Uncategorized';
    }

    if (!isset($groupedRows[$categoryLabel])) {
        $groupedRows[$categoryLabel] = [];
    }
    $groupedRows[$categoryLabel][] = $item;
}
?>

<!-- Filter bar -->
<form class="row g-2 mb-3" method="get" id="eqFilterForm">
  <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
  <input type="hidden" name="#equipment" value="1">

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
    <input type="text" name="eq_loc" value="<?= htmlspecialchars($eq_loc_like) ?>" class="form-control" placeholder="Search…">
  </div>

  <div class="col-6 col-md-2">
    <label class="form-label mb-1">Expiration</label>
    <select name="eq_exp_state" class="form-select">
      <option value="">(all)</option>
      <option value="expired" <?= $eq_exp_state==='expired'?'selected':'' ?>>Expired</option>
      <option value="soon"    <?= $eq_exp_state==='soon'?'selected':''    ?>>Expiring ≤ 60d</option>
      <option value="valid"   <?= $eq_exp_state==='valid'?'selected':''   ?>>Valid &gt; 60d</option>
    </select>
  </div>

  <div class="col-6 col-md-2">
    <label class="form-label mb-1">Sort</label>
    <select name="eq_sort" class="form-select">
      <option value="exp_asc"  <?= $eq_sort==='exp_asc'?'selected':''  ?>>Expiration ↑</option>
      <option value="exp_desc" <?= $eq_sort==='exp_desc'?'selected':'' ?>>Expiration ↓</option>
      <option value="name_asc" <?= $eq_sort==='name_asc'?'selected':'' ?>>Type/Subtype A→Z</option>
      <option value="name_desc"<?= $eq_sort==='name_desc'?'selected':''?>>Type/Subtype Z→A</option>
      <option value="loc_asc"  <?= $eq_sort==='loc_asc'?'selected':''  ?>>Location A→Z</option>
      <option value="loc_desc" <?= $eq_sort==='loc_desc'?'selected':'' ?>>Location Z→A</option>
    </select>
  </div>

  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary">Apply</button>
    <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#equipment">Reset</a>
    <a href="print_equipment.php?<?= $qs ?>" class="btn btn-outline-secondary" target="_blank">🖨 Print</a>
  </div>
</form>

<?php if ($expiring_count > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    ⚠️
    <span><strong><?= $expiring_count ?></strong> equipment item<?= $expiring_count > 1 ? 's are' : ' is' ?> expired or expiring within 60 days.</span>
  </div>
<?php endif; ?>

<?php
$todayDT = new DateTimeImmutable('today');
$soonDT  = $todayDT->modify('+60 days');
?>

<?php if (!$rows): ?>
  <div class="card">
    <div class="card-body text-center text-muted">
      No equipment matches your filters.
    </div>
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
          <button
            class="accordion-button collapsed equipment-category-toggle"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#<?= $collapseId ?>"
            aria-expanded="false"
            aria-controls="<?= $collapseId ?>"
          >
            <div class="w-100 d-flex justify-content-between align-items-center flex-wrap gap-2 pe-3">
              <div>
                <strong><?= safe($categoryName) ?></strong>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
                <?php if ($catExpiringCount > 0): ?>
                  <span class="badge bg-warning text-dark"><?= $catExpiringCount ?> expiring</span>
                <?php endif; ?>
              </div>
            </div>
          </button>
        </h2>

        <div
          id="<?= $collapseId ?>"
          class="accordion-collapse collapse"
          aria-labelledby="<?= $headingId ?>"
          data-bs-parent="#equipmentCategoryAccordion"
        >
          <div class="accordion-body p-0">

          <?php
            $groupedByType = [];

            foreach ($items as $item) {

                $typeLabel = trim((string)($item['type_name'] ?? ''));

                if ($typeLabel === '') {
                    $typeLabel = 'Unspecified Type';
                }

                if (!isset($groupedByType[$typeLabel])) {
                    $groupedByType[$typeLabel] = [];
                }

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
            <th>Expires</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th class="text-center">Actions</th>
            </tr>
            </thead>

            <tbody>

            <?php foreach ($typeItems as $item): ?>

            <?php
            $row_class = '';

            $exp = ($item['expDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$item['expDate']) && $item['expDate'] !== '0000-00-00')
                ? $item['expDate'] : '—';

            if ($exp !== '—') {

                if ($exp < $today) {
                    $row_class = 'table-danger';
                }
                elseif ($exp <= $soon) {
                    $row_class = 'table-warning';
                }

            }
            ?>

            <tr class="<?= $row_class ?>">

            <td><?= safe($item['subtype_name']) ?></td>

            <td><?= safe($item['equipmentLocation']) ?></td>

            <td><?= safe($exp) ?></td>

            <td><?= safe($item['quantity']) ?></td>

            <td><?= safe($item['unit']) ?></td>

            <td class="text-center">

            <a href="equipment_detail.php?id=<?= (int)$item['eid'] ?>" class="equipment-action">View</a> |

            <a href="edit_equipment.php?id=<?= (int)$item['eid'] ?>" class="equipment-action">Edit</a> |

            <a href="delete_equipment.php?id=<?= (int)$item['eid'] ?>" class="equipment-action text-danger"
            onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>

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

<style>
.equipment-action {
    text-decoration: none;
    font-weight: 500;
}
.equipment-action:hover {
    text-decoration: underline;
}

td.text-center {
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
}

.equipment-category-card {
    border: 1px solid #dfe5ec !important;
    border-radius: 12px !important;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    background: #fff;
}

.equipment-category-card + .equipment-category-card {
    margin-top: 1rem;
}

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

.equipment-category-card .accordion-button:focus {
    box-shadow: none;
    border-color: transparent;
}

.equipment-category-card .accordion-body {
    background: #fff;
    padding: 0;
}

.equipment-category-card .table {
    margin-bottom: 0;
}

.equipment-category-card .table thead th {
    background: #f1f5f9;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    border-bottom: 1px solid #dfe5ec;
}

.equipment-category-card .badge {
    font-size: 0.78rem;
    padding: 0.45em 0.65em;
    border-radius: 999px;
}

#expandAllEquipment,
#collapseAllEquipment {
    border-radius: 999px;
    font-weight: 500;
    padding-left: 0.9rem;
    padding-right: 0.9rem;
}
.equipment-type-header {

background:#f8f9fa;

border-top:1px solid #dee2e6;

border-bottom:1px solid #dee2e6;

padding:0.75rem 1rem;

font-weight:600;

color:#344054;

}
.table-responsive {
    border-top: 1px solid #e9ecef;
}

@media (max-width: 768px) {
    table {
        font-size: 0.9rem;
    }

    .equipment-category-card .accordion-button {
        padding: 0.85rem 1rem;
    }
}

</style>

<script>
// Build dependent dropdowns from the unfiltered snapshot PHP already fetched.
const EQ_TYPES = [];
const EQ_SUBTYPES = [];
<?php foreach ($TYPES as $t): ?>
  EQ_TYPES.push({id: "<?= (int)$t['id'] ?>", name: <?= json_encode((string)$t['name']) ?>, cat_id: "<?= (string)$t['cat_id'] ?>"});
<?php endforeach; ?>
<?php foreach ($SUBTYPES as $s): ?>
  EQ_SUBTYPES.push({id: "<?= (int)$s['id'] ?>", name: <?= json_encode((string)$s['name']) ?>, type_id: "<?= (string)$s['type_id'] ?>"});
<?php endforeach; ?>

const curCat = "<?= htmlspecialchars($eq_cat_id) ?>";
const curType = "<?= htmlspecialchars($eq_type_id) ?>";
const curSubtype = "<?= htmlspecialchars($eq_subtype_id) ?>";

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

typeSel.addEventListener('change', () => {
  renderSubtypes(true);
});

renderTypes();
renderSubtypes();

// Expand / Collapse All category sections
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