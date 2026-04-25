<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_source_finder_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function msf_safe($value): string
{
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function msf_value($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function msf_approved_name(array $row): string
{
    $name = trim((string)($row['approved_fName'] ?? '') . ' ' . (string)($row['approved_lName'] ?? ''));
    return $name !== '' ? $name : 'User #' . (int)($row['approved_by'] ?? 0);
}

$roleId = (int)($_SESSION['role_id'] ?? 0);
$companyId = (int)($_SESSION['company_id'] ?? 0);

$equipmentId = (int)($_GET['equipment_id'] ?? $_POST['equipment_id'] ?? 0);
$equipment = null;
$equipmentContextNote = '';

if ($equipmentId > 0) {
    $sql = "
        SELECT
            e.*,
            v.vesselName,
            et.name AS type_name,
            es.name AS subtype_name
        FROM equipment e
        INNER JOIN vessels v ON v.vessel_id = e.vessel_id
        LEFT JOIN equipment_type et ON et.id = e.equipment_type_id
        LEFT JOIN equipment_subtype es ON es.id = e.equipment_subtype_id
        WHERE e.eid = ?
    ";
    $params = [$equipmentId];

    if ($roleId !== 1) {
        $sql .= " AND v.company_id = ?";
        $params[] = $companyId;
    }

    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $equipment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$equipment) {
        http_response_code(404);
        exit('Equipment not found or access denied.');
    }

    $equipmentContextNote = trim((string)($equipment['equipmentName'] ?? ''));
}

$search = [
    'equipment_type' => trim((string)($_REQUEST['equipment_type'] ?? ($equipment['subtype_name'] ?? $equipment['type_name'] ?? ''))),
    'manufacturer' => trim((string)($_REQUEST['manufacturer'] ?? ($equipment['manufacturer'] ?? ''))),
    'model' => trim((string)($_REQUEST['model'] ?? ($equipment['modelNumber'] ?? ''))),
    'serial_year' => trim((string)($_REQUEST['serial_year'] ?? ($equipment['serialNumber'] ?? ''))),
];

$resultsPayload = null;
$savedSources = [];
$savedUrlMap = [];
$didSearch = isset($_GET['search']) || isset($_POST['search']);
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

if ($didSearch || $equipment) {
    $savedSources = vms_source_finder_get_saved_sources($pdo, $search, $equipment ? (int)$equipment['eid'] : null);
    foreach ($savedSources as $savedSource) {
        $savedUrlMap[vms_source_finder_normalize_url((string)($savedSource['source_url'] ?? ''))] = (int)$savedSource['source_id'];
    }
}

if ($didSearch) {
    $resultsPayload = vms_source_finder_search($search);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Source Finder - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .msf-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .msf-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }
        .msf-title { font-size: 1.65rem; font-weight: 700; margin: 0 0 4px; }
        .msf-subtitle { color: #6b7280; margin: 0; }
        .msf-result-card {
            border: 1px solid #dbe4ee;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(16,24,40,.06);
        }
        .msf-query-list code {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 4px 8px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
        }
    </style>
</head>
<body>
<?php
$title = 'Maintenance Source Finder';
$back_link = $equipment ? ('equipment_detail.php?id=' . (int)$equipment['eid']) : 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="msf-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card mb-3">
                <div class="msf-header">
                    <div>
                        <h1 class="msf-title">Maintenance Source Finder</h1>
                        <p class="msf-subtitle">Phase 0 research tool for locating manufacturer maintenance manuals and schedule references.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="equipment_manual_library.php" class="btn btn-outline-success">Open Source Library</a>
                    </div>
                    <?php if ($equipment): ?>
                        <div class="text-muted small">
                            Equipment: <?= msf_safe($equipmentContextNote) ?><br>
                            Vessel: <?= msf_safe($equipment['vesselName'] ?? null) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <?php if ($_GET['saved'] === '1'): ?>
                    <div class="alert alert-success">Source saved to the VMS library.</div>
                <?php elseif ($_GET['saved'] === 'exists'): ?>
                    <div class="alert alert-info">That source URL is already saved in the VMS library.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!vms_source_finder_is_configured()): ?>
                <div class="alert alert-info">
                    <strong>Source search not configured.</strong><br>
                    <?= msf_safe(vms_source_finder_config_message()) ?><br>
                    <span class="small text-muted">See <code>config_maintenance_source_finder.example.php</code> for the expected config pattern.</span>
                </div>
            <?php endif; ?>

            <div class="vms-card mb-4">
                <form method="get" class="row g-3">
                    <?php if ($equipment): ?>
                        <input type="hidden" name="equipment_id" value="<?= (int)$equipment['eid'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="search" value="1">

                    <div class="col-md-3">
                        <label class="form-label">Equipment Type</label>
                        <input type="text" name="equipment_type" class="form-control" value="<?= msf_value($search['equipment_type']) ?>" placeholder="Generator, propulsion engine, pump...">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Manufacturer</label>
                        <input type="text" name="manufacturer" class="form-control" value="<?= msf_value($search['manufacturer']) ?>" placeholder="Cummins, Yanmar, Caterpillar...">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="<?= msf_value($search['model']) ?>" placeholder="4BT, 6LY, C7...">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Serial / Year (Optional)</label>
                        <input type="text" name="serial_year" class="form-control" value="<?= msf_value($search['serial_year']) ?>" placeholder="Optional serial or year">
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Find Sources</button>
                        <a href="equipment_manual_library.php" class="btn btn-outline-success">View Source Library</a>
                        <a href="maintenance_source_finder.php<?= $equipment ? '?equipment_id=' . (int)$equipment['eid'] : '' ?>" class="btn btn-outline-secondary">Reset</a>
                        <?php if ($equipment): ?>
                            <a href="equipment_detail.php?id=<?= (int)$equipment['eid'] ?>" class="btn btn-outline-secondary">Back to Equipment</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if (!empty($savedSources)): ?>
                <div class="vms-card mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h2 class="h5 mb-0">Saved VMS Library Sources</h2>
                        <span class="badge bg-success"><?= count($savedSources) ?> saved</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($savedSources as $source): ?>
                            <div class="col-12">
                                <div class="card msf-result-card border-success">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                            <div class="flex-grow-1">
                                                <h3 class="h5 mb-2"><?= msf_safe($source['title']) ?></h3>
                                                <div class="small text-muted mb-2">
                                                    <?= msf_safe($source['source_domain']) ?>
                                                    <?php if (!empty($source['source_type'])): ?>
                                                        · <?= msf_safe($source['source_type']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2 d-flex gap-2 flex-wrap">
                                                    <span class="badge bg-success">Saved Source</span>
                                                    <?php if (!empty($source['confidence_label'])): ?>
                                                        <span class="badge bg-primary"><?= msf_safe($source['confidence_label']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2"><a href="<?= msf_safe($source['source_url']) ?>" target="_blank" rel="noopener"><?= msf_safe($source['source_url']) ?></a></div>
                                                <div class="text-muted"><?= msf_safe($source['notes'] ?? null) ?></div>
                                                <div class="small text-muted mt-2">
                                                    Approved <?= msf_safe($source['approved_at'] ?? null) ?> by <?= msf_safe(msf_approved_name($source)) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-start">
                                                <a href="<?= msf_safe($source['source_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Source</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($didSearch): ?>
                <div class="vms-card mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <h2 class="h5 mb-0">Search Queries</h2>
                        <?php if ($resultsPayload && !empty($resultsPayload['results'])): ?>
                            <span class="badge bg-success"><?= count($resultsPayload['results']) ?> result<?= count($resultsPayload['results']) === 1 ? '' : 's' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="msf-query-list">
                        <?php if (!empty($resultsPayload['queries'])): ?>
                            <?php foreach ($resultsPayload['queries'] as $query): ?>
                                <code><?= msf_safe($query) ?></code>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">No search queries could be built from the provided fields.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($resultsPayload && !empty($resultsPayload['message'])): ?>
                    <div class="alert <?= !empty($resultsPayload['results']) ? 'alert-warning' : 'alert-info' ?>">
                        <?= msf_safe($resultsPayload['message']) ?>
                        <?php if (!empty($resultsPayload['error_detail'])): ?>
                            <div class="small text-muted mt-1"><?= msf_safe($resultsPayload['error_detail']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($resultsPayload && !empty($resultsPayload['results'])): ?>
                    <div class="row g-3">
                        <?php foreach ($resultsPayload['results'] as $result): ?>
                            <?php $normalizedUrl = vms_source_finder_normalize_url((string)$result['url']); ?>
                            <?php $existingSourceId = $savedUrlMap[$normalizedUrl] ?? 0; ?>
                            <div class="col-12">
                                <div class="card msf-result-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                            <div class="flex-grow-1">
                                                <h3 class="h5 mb-2"><?= msf_safe($result['title']) ?></h3>
                                                <div class="small text-muted mb-2">
                                                    <?= msf_safe($result['domain']) ?>
                                                    <?php if (!empty($result['result_type'])): ?>
                                                        · <?= msf_safe($result['result_type']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-<?= msf_safe($result['confidence']['class'] ?? 'secondary') ?>">
                                                        <?= msf_safe($result['confidence']['label'] ?? 'Third-party / Unknown') ?>
                                                    </span>
                                                </div>
                                                <div class="mb-2"><a href="<?= msf_safe($result['url']) ?>" target="_blank" rel="noopener"><?= msf_safe($result['url']) ?></a></div>
                                                <div class="text-muted"><?= msf_safe($result['snippet'] ?? null) ?></div>
                                                <div class="small text-muted mt-2">Matched query: <?= msf_safe($result['query'] ?? null) ?></div>
                                            </div>
                                            <div class="d-flex align-items-start gap-2 flex-wrap">
                                                <a href="<?= msf_safe($result['url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Source</a>
                                                <?php if ($existingSourceId > 0): ?>
                                                    <span class="btn btn-outline-success disabled">Already Saved</span>
                                                <?php else: ?>
                                                    <form method="post" action="submit_equipment_manual_source.php" class="d-inline">
                                                        <?php if ($equipment): ?>
                                                            <input type="hidden" name="equipment_id" value="<?= (int)$equipment['eid'] ?>">
                                                        <?php endif; ?>
                                                        <input type="hidden" name="equipment_type" value="<?= msf_value($search['equipment_type']) ?>">
                                                        <input type="hidden" name="manufacturer" value="<?= msf_value($search['manufacturer']) ?>">
                                                        <input type="hidden" name="model" value="<?= msf_value($search['model']) ?>">
                                                        <input type="hidden" name="serial_or_year" value="<?= msf_value($search['serial_year']) ?>">
                                                        <input type="hidden" name="title" value="<?= msf_value($result['title']) ?>">
                                                        <input type="hidden" name="source_url" value="<?= msf_value($result['url']) ?>">
                                                        <input type="hidden" name="source_domain" value="<?= msf_value($result['domain']) ?>">
                                                        <input type="hidden" name="source_type" value="<?= msf_value($result['result_type']) ?>">
                                                        <input type="hidden" name="confidence_label" value="<?= msf_value($result['confidence']['label'] ?? '') ?>">
                                                        <input type="hidden" name="notes" value="<?= msf_value($result['snippet'] ?? '') ?>">
                                                        <button type="submit" class="btn btn-outline-success">Save to VMS Library</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($resultsPayload && empty($resultsPayload['message'])): ?>
                    <div class="alert alert-info">No source results were returned for this search.</div>
                <?php endif; ?>
            <?php else: ?>
                <div class="vms-card">
                    <div class="text-muted">
                        Use this Phase 0 tool to research possible maintenance manuals and schedule references. Results are informational only and are not imported into VMS.
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
