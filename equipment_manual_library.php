<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_source_finder_functions.php';
require_once __DIR__ . '/includes/maintenance_template_extraction_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function eml_safe($value): string
{
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function eml_value($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function eml_qs(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$manufacturer = eml_qs('manufacturer', '');
$model = eml_qs('model', '');
$equipmentType = eml_qs('equipment_type', '');
$sourceDomain = eml_qs('source_domain', '');
$keyword = eml_qs('q', '');
$statusFilter = eml_qs('status', 'active');
if (!in_array($statusFilter, ['active', 'inactive', 'all'], true)) {
    $statusFilter = 'active';
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

$libraryExists = vms_source_finder_table_exists($pdo, 'equipment_manual_sources');
$rows = [];
$manufacturers = [];
$models = [];
$equipmentTypes = [];
$domains = [];
$templatesTableExists = vms_template_table_exists($pdo);

if ($libraryExists) {
    $distinctSets = [
        'manufacturers' => ['sql' => "SELECT DISTINCT manufacturer AS value FROM equipment_manual_sources WHERE manufacturer IS NOT NULL AND manufacturer <> '' ORDER BY manufacturer", 'target' => &$manufacturers],
        'models' => ['sql' => "SELECT DISTINCT model AS value FROM equipment_manual_sources WHERE model IS NOT NULL AND model <> '' ORDER BY model", 'target' => &$models],
        'types' => ['sql' => "SELECT DISTINCT equipment_type AS value FROM equipment_manual_sources WHERE equipment_type IS NOT NULL AND equipment_type <> '' ORDER BY equipment_type", 'target' => &$equipmentTypes],
        'domains' => ['sql' => "SELECT DISTINCT source_domain AS value FROM equipment_manual_sources WHERE source_domain IS NOT NULL AND source_domain <> '' ORDER BY source_domain", 'target' => &$domains],
    ];

    foreach ($distinctSets as $set) {
        $stmt = $pdo->query($set['sql']);
        $set['target'] = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'value') : [];
    }

    $sql = "
        SELECT
            s.*,
            u.fName AS approved_fName,
            u.lName AS approved_lName,
            e.equipmentName,
            e.equipmentLocation,
            v.vessel_id,
            v.vesselName,
            o.company_name
    ";
    if ($templatesTableExists) {
        $sql .= ",
            COALESCE(tc.draft_count, 0) AS draft_template_count,
            COALESCE(tc.approved_count, 0) AS approved_template_count
        ";
    }
    $sql .= "
        FROM equipment_manual_sources s
        LEFT JOIN users u ON u.id = s.approved_by
        LEFT JOIN equipment e ON e.eid = s.equipment_id
        LEFT JOIN vessels v ON v.vessel_id = e.vessel_id
        LEFT JOIN owners o ON o.owner_id = v.company_id
    ";
    if ($templatesTableExists) {
        $sql .= "
        LEFT JOIN (
            SELECT
                source_id,
                SUM(CASE WHEN review_status = 'draft' AND COALESCE(is_active, 1) = 1 THEN 1 ELSE 0 END) AS draft_count,
                SUM(CASE WHEN review_status = 'approved' AND COALESCE(is_active, 1) = 1 THEN 1 ELSE 0 END) AS approved_count
            FROM equipment_maintenance_templates
            GROUP BY source_id
        ) tc ON tc.source_id = s.source_id
        ";
    }
    $sql .= "
        WHERE 1=1
    ";
    $params = [];

    if ($manufacturer !== '') {
        $sql .= " AND LOWER(COALESCE(s.manufacturer, '')) = LOWER(?)";
        $params[] = $manufacturer;
    }
    if ($model !== '') {
        $sql .= " AND LOWER(COALESCE(s.model, '')) = LOWER(?)";
        $params[] = $model;
    }
    if ($equipmentType !== '') {
        $sql .= " AND LOWER(COALESCE(s.equipment_type, '')) = LOWER(?)";
        $params[] = $equipmentType;
    }
    if ($sourceDomain !== '') {
        $sql .= " AND LOWER(COALESCE(s.source_domain, '')) = LOWER(?)";
        $params[] = $sourceDomain;
    }
    if ($statusFilter === 'active') {
        $sql .= " AND COALESCE(s.is_active, 1) = 1";
    } elseif ($statusFilter === 'inactive') {
        $sql .= " AND COALESCE(s.is_active, 1) = 0";
    }
    if ($keyword !== '') {
        $sql .= " AND (
            s.title LIKE ?
            OR s.source_url LIKE ?
            OR COALESCE(s.notes, '') LIKE ?
            OR COALESCE(s.manufacturer, '') LIKE ?
            OR COALESCE(s.model, '') LIKE ?
            OR COALESCE(s.equipment_type, '') LIKE ?
        )";
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $sql .= " ORDER BY COALESCE(s.is_active, 1) DESC, s.approved_at DESC, s.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Manual Library - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .eml-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .eml-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .eml-title { font-size:1.65rem; font-weight:700; margin:0 0 4px; }
        .eml-subtitle { color:#6b7280; margin:0; }
    </style>
</head>
<body>
<?php
$title = 'Equipment Manual Library';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="eml-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card mb-3">
                <div class="eml-header">
                    <div>
                        <h1 class="eml-title">Equipment Manual / Source Library</h1>
                        <p class="eml-subtitle">Approved VMS reference sources for equipment manuals and maintenance materials.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="maintenance_source_finder.php" class="btn btn-outline-info">Find Maintenance Sources</a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['status_saved'])): ?>
                <div class="alert alert-success">Source status updated.</div>
            <?php endif; ?>

            <?php if (!$libraryExists): ?>
                <div class="alert alert-info">
                    Source library table is not available yet. Apply <code>equipment_manual_sources_phase0.sql</code> first.
                </div>
            <?php else: ?>
                <div class="vms-card mb-3">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-12 col-md-2">
                            <label class="form-label">Manufacturer</label>
                            <select name="manufacturer" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($manufacturers as $value): ?>
                                    <option value="<?= eml_safe($value) ?>" <?= $manufacturer === (string)$value ? 'selected' : '' ?>><?= eml_safe($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Model</label>
                            <select name="model" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($models as $value): ?>
                                    <option value="<?= eml_safe($value) ?>" <?= $model === (string)$value ? 'selected' : '' ?>><?= eml_safe($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Equipment Type</label>
                            <select name="equipment_type" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($equipmentTypes as $value): ?>
                                    <option value="<?= eml_safe($value) ?>" <?= $equipmentType === (string)$value ? 'selected' : '' ?>><?= eml_safe($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Source Domain</label>
                            <select name="source_domain" class="form-select">
                                <option value="">All</option>
                                <?php foreach ($domains as $value): ?>
                                    <option value="<?= eml_safe($value) ?>" <?= $sourceDomain === (string)$value ? 'selected' : '' ?>><?= eml_safe($value) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="q" class="form-control" value="<?= eml_value($keyword) ?>" placeholder="Title, URL, notes">
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary">Apply</button>
                            <a href="equipment_manual_library.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="vms-card">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0">Saved Sources</h2>
                        <span class="badge bg-success"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></span>
                    </div>

                    <?php if (!$rows): ?>
                        <div class="text-muted">No saved sources match the current filters.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Manufacturer</th>
                                        <th>Model</th>
                                        <th>Equipment Type</th>
                                        <th>Source</th>
                                        <th>Approved</th>
                                        <th>Context</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <?php
                                        $approvedName = trim((string)($row['approved_fName'] ?? '') . ' ' . (string)($row['approved_lName'] ?? ''));
                                        $isActive = (int)($row['is_active'] ?? 1) === 1;
                                        $confidenceClass = 'secondary';
                                        $confidenceLabel = trim((string)($row['confidence_label'] ?? ''));
                                        if (stripos($confidenceLabel, 'likely manufacturer') !== false) {
                                            $confidenceClass = 'success';
                                        } elseif (stripos($confidenceLabel, 'possible manufacturer') !== false) {
                                            $confidenceClass = 'primary';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= eml_safe($row['title']) ?></div>
                                                <div class="d-flex gap-1 flex-wrap mt-1">
                                                    <span class="badge bg-success">Saved Source</span>
                                                    <?php if ($confidenceLabel !== ''): ?>
                                                        <span class="badge bg-<?= $confidenceClass ?>"><?= eml_safe($confidenceLabel) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($row['notes'])): ?>
                                                    <div class="small text-muted mt-1"><?= eml_safe($row['notes']) ?></div>
                                                <?php endif; ?>
                                                <?php if ($templatesTableExists): ?>
                                                    <div class="small text-muted mt-1">
                                                        Drafts: <?= (int)($row['draft_template_count'] ?? 0) ?> · Approved: <?= (int)($row['approved_template_count'] ?? 0) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= eml_safe($row['manufacturer']) ?></td>
                                            <td><?= eml_safe($row['model']) ?></td>
                                            <td><?= eml_safe($row['equipment_type']) ?></td>
                                            <td>
                                                <div><?= eml_safe($row['source_domain']) ?></div>
                                                <div class="small text-muted"><?= eml_safe($row['source_type']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= eml_safe($approvedName !== '' ? $approvedName : ('User #' . (int)$row['approved_by'])) ?></div>
                                                <div class="small text-muted"><?= eml_safe($row['approved_at']) ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['equipment_id'])): ?>
                                                    <div><a href="equipment_detail.php?id=<?= (int)$row['equipment_id'] ?>"><?= eml_safe($row['equipmentName']) ?></a></div>
                                                    <div class="small text-muted"><?= eml_safe($row['equipmentLocation']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['vessel_id'])): ?>
                                                    <div class="small"><?= eml_safe($row['vesselName']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['company_name'])): ?>
                                                    <div class="small text-muted"><?= eml_safe($row['company_name']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $isActive ? 'success' : 'secondary' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                                            </td>
                                            <td class="text-nowrap">
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <a href="<?= eml_safe($row['source_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open Source</a>
                                                    <?php if ($templatesTableExists && vms_template_user_can_manage()): ?>
                                                        <a href="maintenance_template_extract.php?source_id=<?= (int)$row['source_id'] ?><?= !empty($row['equipment_id']) ? '&equipment_id=' . (int)$row['equipment_id'] : '' ?>" class="btn btn-sm btn-outline-info">Extract Maintenance Draft</a>
                                                    <?php endif; ?>
                                                    <?php if (vms_source_finder_user_can_manage_library()): ?>
                                                        <form method="post" action="submit_equipment_manual_source_status.php" class="d-inline">
                                                            <input type="hidden" name="source_id" value="<?= (int)$row['source_id'] ?>">
                                                            <input type="hidden" name="new_status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                                            <input type="hidden" name="return_query" value="<?= eml_value((string)($_SERVER['QUERY_STRING'] ?? '')) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-<?= $isActive ? 'danger' : 'success' ?>">
                                                                <?= $isActive ? 'Deactivate' : 'Reactivate' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
