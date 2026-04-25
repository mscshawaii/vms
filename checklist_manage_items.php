<?php
declare(strict_types=1);

require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/checklist_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

$vesselId = (int)($_GET['vessel_id'] ?? 0);
$runType = checklist_normalize_run_type($_GET['type'] ?? '');
$companyId = (int)($_SESSION['company_id'] ?? 0);
$canAccessAllVessels = ($companyId === 1);

if ($vesselId <= 0) {
    http_response_code(400);
    exit('Invalid vessel ID.');
}

if ($runType === null) {
    http_response_code(400);
    exit('Invalid checklist type.');
}

$vessel = checklist_get_accessible_vessel($pdo, $vesselId, $canAccessAllVessels, $companyId);
if (!$vessel) {
    http_response_code(404);
    exit('Access denied or vessel not found.');
}

$template = checklist_get_template_by_type($pdo, $runType);
if (!$template) {
    http_response_code(404);
    exit('Checklist template not found.');
}

$coreItems = checklist_get_template_items($pdo, (int)$template['template_id']);
$suppressedCoreItems = checklist_get_suppressed_core_items($pdo, $vesselId, (int)$template['template_id']);
$suppressedCoreItemIds = array_map('intval', array_column($suppressedCoreItems, 'template_item_id'));
$activeCoreItems = array_values(array_filter($coreItems, static function (array $item) use ($suppressedCoreItemIds): bool {
    return !in_array((int)($item['template_item_id'] ?? 0), $suppressedCoreItemIds, true);
}));
$vesselItems = checklist_get_vessel_items($pdo, $vesselId, (int)$template['template_id']);

$title = 'Manage Checklist Items';
$back_link = 'vessel_dashboard.php?vessel_id=' . $vesselId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($template['template_name']) ?> Items - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .checklist-manage-shell {
            background: #f4f7fb;
            min-height: 100vh;
        }
        .checklist-manage-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }
        .checklist-item-row {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
            background: #fbfdff;
        }
        .checklist-item-row + .checklist-item-row {
            margin-top: 12px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="checklist-manage-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="checklist-manage-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h1 class="h3 mb-2"><?= h($template['template_name']) ?> Items</h1>
                        <p class="text-muted mb-0"><?= h($vessel['vesselName'] ?? '') ?></p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="checklist_run.php?vessel_id=<?= (int)$vesselId ?>&type=<?= h($runType) ?>&return_to=<?= h(urlencode('checklist_manage_items.php?vessel_id=' . $vesselId . '&type=' . $runType)) ?>" class="btn btn-outline-secondary">
                            View Checklist Form
                        </a>
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vesselId ?>" class="btn btn-outline-secondary">
                            Back to Vessel
                        </a>
                    </div>
                </div>
            </div>

            <div class="checklist-manage-card p-4 mb-4">
                <h2 class="h5 mb-3">Add Custom Item</h2>
                <form method="post" action="checklist_item_save.php" class="row g-3">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                    <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                    <input type="hidden" name="run_type" value="<?= h($runType) ?>">

                    <div class="col-md-8">
                        <label class="form-label">Item Label</label>
                        <input type="text" class="form-control" name="item_label" maxlength="255" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" value="0">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Add Item</button>
                    </div>
                </form>
            </div>

            <div class="checklist-manage-card p-4 mb-4">
                <h2 class="h5 mb-3">Active Core Items</h2>
                <?php if (empty($activeCoreItems)): ?>
                    <div class="text-muted">No active core items found.</div>
                <?php else: ?>
                    <?php foreach ($activeCoreItems as $item): ?>
                        <div class="checklist-item-row">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= h($item['item_label'] ?? '') ?></div>
                                    <div class="text-muted small">Sort Order: <?= (int)($item['sort_order'] ?? 0) ?></div>
                                </div>
                                <div class="d-flex gap-2 align-items-start flex-wrap">
                                    <span class="badge text-bg-light">Core Item</span>
                                    <form method="post" action="checklist_item_save.php" onsubmit="return confirm('Suppress this core checklist item for this vessel? This does not delete the global core item and can be restored later.');">
                                        <input type="hidden" name="action" value="suppress_core">
                                        <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                                        <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                                        <input type="hidden" name="run_type" value="<?= h($runType) ?>">
                                        <input type="hidden" name="template_item_id" value="<?= (int)$item['template_item_id'] ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm">Suppress for This Vessel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="checklist-manage-card p-4 mb-4">
                <h2 class="h5 mb-3">Suppressed Core Items</h2>
                <?php if (empty($suppressedCoreItems)): ?>
                    <div class="text-muted">No suppressed core items.</div>
                <?php else: ?>
                    <?php foreach ($suppressedCoreItems as $item): ?>
                        <div class="checklist-item-row">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="fw-semibold"><?= h($item['item_label'] ?? '') ?></div>
                                    <div class="text-muted small">Sort Order: <?= (int)($item['sort_order'] ?? 0) ?></div>
                                </div>
                                <div class="d-flex gap-2 align-items-start flex-wrap">
                                    <span class="badge text-bg-secondary">Suppressed Core Item</span>
                                    <form method="post" action="checklist_item_save.php">
                                        <input type="hidden" name="action" value="restore_core">
                                        <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                                        <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                                        <input type="hidden" name="run_type" value="<?= h($runType) ?>">
                                        <input type="hidden" name="template_item_id" value="<?= (int)$item['template_item_id'] ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm">Restore</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="checklist-manage-card p-4">
                <h2 class="h5 mb-3">Custom Vessel Items</h2>
                <?php if (empty($vesselItems)): ?>
                    <div class="text-muted">No active custom items found.</div>
                <?php else: ?>
                    <?php foreach ($vesselItems as $item): ?>
                        <div class="checklist-item-row">
                            <form method="post" action="checklist_item_save.php" class="row g-3">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                                <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                                <input type="hidden" name="run_type" value="<?= h($runType) ?>">
                                <input type="hidden" name="vessel_checklist_item_id" value="<?= (int)$item['vessel_checklist_item_id'] ?>">

                                <div class="col-md-7">
                                    <label class="form-label">Item Label</label>
                                    <input type="text" class="form-control" name="item_label" maxlength="255" value="<?= h($item['item_label'] ?? '') ?>" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" name="sort_order" value="<?= (int)($item['sort_order'] ?? 0) ?>">
                                </div>

                                <div class="col-md-3 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary w-100">Save</button>
                                </div>
                            </form>

                            <form method="post" action="checklist_item_save.php" class="mt-3">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                                <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                                <input type="hidden" name="run_type" value="<?= h($runType) ?>">
                                <input type="hidden" name="vessel_checklist_item_id" value="<?= (int)$item['vessel_checklist_item_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Deactivate</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
