<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/maintenance_template_extraction_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!vms_template_user_can_manage()) {
    http_response_code(403);
    exit('Not authorized.');
}

function mer_safe($value): string
{
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function mer_value($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$runId = trim((string)($_GET['run_id'] ?? ''));
$sourceId = (int)($_GET['source_id'] ?? 0);
$equipmentId = (int)($_GET['equipment_id'] ?? 0);

if ($runId === '') {
    exit('Missing run_id.');
}

$run = vms_template_get_extraction_run($pdo, $runId);
if (!$run) {
    exit('Extraction run not found.');
}

$source = vms_template_get_source($pdo, (int)$run['source_id']);
if (!$source) {
    exit('Saved source not found.');
}

$rows = vms_template_get_extraction_rows($pdo, $runId);
$summary = vms_template_get_extraction_review_summary($rows);
$flash = $_SESSION['maintenance_extraction_review_flash'] ?? $_SESSION['maintenance_template_extract_flash'] ?? null;
unset($_SESSION['maintenance_extraction_review_flash'], $_SESSION['maintenance_template_extract_flash']);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extracted Table Review - VMS</title>
    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">
</head>
<body>
<?php
$title = 'Extracted Table Review';
$back_link = 'maintenance_template_extract.php?source_id=' . (int)$run['source_id'] . ($equipmentId > 0 ? '&equipment_id=' . $equipmentId : '');
include __DIR__ . '/partials/top_nav.php';
?>
<div class="app-page">
    <div class="app-container">
        <div class="vms-card mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h1 class="h3 mb-1">Extracted Table Review</h1>
                    <p class="text-muted mb-0">Review extracted rows carefully before creating draft templates. Manufacturer tables may be interpreted incorrectly.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="maintenance_template_extract.php?source_id=<?= (int)$run['source_id'] ?><?= $equipmentId > 0 ? '&equipment_id=' . $equipmentId : '' ?>" class="btn btn-outline-secondary">Back to Extraction</a>
                    <a href="<?= mer_safe($source['source_url'] ?? '') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Source</a>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= ($flash['type'] ?? 'info') === 'success' ? 'success' : (($flash['type'] ?? '') === 'warning' ? 'warning' : 'info') ?>">
                <?= mer_safe($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><strong>Run Context</strong></div>
                    <div class="card-body">
                        <div><strong>Run ID:</strong> <?= mer_safe($runId) ?></div>
                        <div><strong>Input Type:</strong> <?= mer_safe($run['input_type'] ?? 'pdf') ?></div>
                        <div><strong>Page Range:</strong> <?= mer_safe($run['page_range'] ?? '') ?></div>
                        <div><strong>Provider:</strong> <?= mer_safe($run['provider'] ?? '') ?></div>
                        <div><strong>Model:</strong> <?= mer_safe($run['model_used'] ?? '') ?></div>
                        <div><strong>Status:</strong> <?= mer_safe($run['status'] ?? '') ?></div>
                        <div><strong>Created:</strong> <?= mer_safe($run['created_at'] ?? '') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><strong>Source Context</strong></div>
                    <div class="card-body">
                        <div><strong>Title:</strong> <?= mer_safe($source['title'] ?? '') ?></div>
                        <div><strong>Manufacturer:</strong> <?= mer_safe($source['manufacturer'] ?? '') ?></div>
                        <div><strong>Model:</strong> <?= mer_safe($source['model'] ?? '') ?></div>
                        <div><strong>Equipment Type:</strong> <?= mer_safe($source['equipment_type'] ?? '') ?></div>
                        <?php if (!empty($source['equipmentName'])): ?>
                            <div><strong>Linked Equipment:</strong> <a href="equipment_detail.php?id=<?= (int)$source['linked_equipment_id'] ?>"><?= mer_safe($source['equipmentName']) ?></a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><strong>Review Summary</strong></div>
                    <div class="card-body">
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-primary">Rows <?= count($rows) ?></span>
                            <span class="badge bg-secondary">Pending <?= (int)$summary['pending'] ?></span>
                            <span class="badge bg-success">Accepted <?= (int)$summary['accepted'] ?></span>
                            <span class="badge bg-danger">Rejected <?= (int)$summary['rejected'] ?></span>
                            <span class="badge bg-warning text-dark">Low Confidence <?= (int)$summary['low_confidence'] ?></span>
                            <span class="badge bg-info text-dark">Grouped Drafts <?= (int)($run['created_grouped_template_count'] ?? 0) ?></span>
                        </div>
                        <div class="small text-muted mt-3">Accepted rows will be grouped into draft maintenance templates only after explicit confirmation.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Add Missing Row</strong></div>
            <div class="card-body">
                <form method="post" action="submit_maintenance_extraction_review.php" class="row g-3">
                    <input type="hidden" name="action" value="add_row">
                    <input type="hidden" name="run_id" value="<?= mer_safe($runId) ?>">
                    <input type="hidden" name="source_id" value="<?= (int)$run['source_id'] ?>">
                    <?php if ($equipmentId > 0): ?>
                        <input type="hidden" name="equipment_id" value="<?= $equipmentId ?>">
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Item / Component</label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Action</label>
                        <input type="text" name="action_name" class="form-control" placeholder="Replace">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Combined Step</label>
                        <input type="text" name="combined_step" class="form-control" placeholder="Replace engine oil filter">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Interval Label</label>
                        <input type="text" name="interval_label" class="form-control" placeholder="100 hours / 6 months">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Hours</label>
                        <input type="number" name="interval_hours" class="form-control" min="0">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Months</label>
                        <input type="number" name="interval_months" class="form-control" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Basis</label>
                        <input type="text" name="interval_basis" class="form-control" placeholder="hours_or_months">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Marked Cell</label>
                        <input type="text" name="marked_cell_value" class="form-control" placeholder="o">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Footnotes</label>
                        <input type="text" name="footnote_refs" class="form-control" placeholder="(2)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Confidence</label>
                        <input type="text" name="confidence_label" class="form-control" value="Manual review entry">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Source Excerpt</label>
                        <input type="text" name="source_excerpt" class="form-control" placeholder="Relevant table text or note">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Add Review Row</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Extracted Rows</strong></div>
            <div class="card-body">
                <?php if (!$rows): ?>
                    <div class="text-muted">No extracted rows are available for this run.</div>
                <?php else: ?>
                    <form method="post" action="submit_maintenance_extraction_review.php">
                        <input type="hidden" name="action" value="save_review">
                        <input type="hidden" name="run_id" value="<?= mer_safe($runId) ?>">
                        <input type="hidden" name="source_id" value="<?= (int)$run['source_id'] ?>">
                        <?php if ($equipmentId > 0): ?>
                            <input type="hidden" name="equipment_id" value="<?= $equipmentId ?>">
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Action</th>
                                        <th>Combined Step</th>
                                        <th>Interval Label</th>
                                        <th>Hours</th>
                                        <th>Months</th>
                                        <th>Basis</th>
                                        <th>Excerpt</th>
                                        <th>Confidence</th>
                                        <th>Status</th>
                                        <th>Warnings</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $warnings = vms_template_row_warning_flags($row); ?>
                                        <?php $confidenceTier = vms_template_guess_confidence_tier((string)($row['confidence_label'] ?? '')); ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="item_name[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" value="<?= mer_value($row['item_name']) ?>">
                                                <div class="small text-muted mt-1">#<?= (int)$row['extraction_row_id'] ?></div>
                                            </td>
                                            <td><input type="text" name="action_name[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" value="<?= mer_value($row['action_name']) ?>"></td>
                                            <td><input type="text" name="combined_step[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" value="<?= mer_value($row['combined_step']) ?>"></td>
                                            <td>
                                                <input type="text" name="interval_label[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" value="<?= mer_value($row['interval_label']) ?>">
                                                <div class="small text-muted mt-1"><?= mer_safe($row['marked_cell_value']) ?></div>
                                            </td>
                                            <td><input type="number" name="interval_hours[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" min="0" value="<?= mer_value($row['interval_hours']) ?>"></td>
                                            <td><input type="number" name="interval_months[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" min="0" value="<?= mer_value($row['interval_months']) ?>"></td>
                                            <td>
                                                <input type="text" name="interval_basis[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" value="<?= mer_value($row['interval_basis']) ?>">
                                                <input type="hidden" name="marked_cell_value[<?= (int)$row['extraction_row_id'] ?>]" value="<?= mer_value($row['marked_cell_value']) ?>">
                                                <input type="hidden" name="footnote_refs[<?= (int)$row['extraction_row_id'] ?>]" value="<?= mer_value($row['footnote_refs']) ?>">
                                            </td>
                                            <td>
                                                <textarea name="source_excerpt[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm" rows="3"><?= mer_value($row['source_excerpt']) ?></textarea>
                                                <?php if (!empty($row['footnote_refs'])): ?>
                                                    <div class="small text-muted mt-1">Footnotes: <?= mer_safe($row['footnote_refs']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="text" name="confidence_label[<?= (int)$row['extraction_row_id'] ?>]" class="form-control form-control-sm mb-2" value="<?= mer_value($row['confidence_label']) ?>">
                                                <span class="badge <?= $confidenceTier === 'high' ? 'bg-success' : ($confidenceTier === 'low' ? 'bg-warning text-dark' : 'bg-secondary') ?>"><?= ucfirst($confidenceTier) ?></span>
                                            </td>
                                            <td>
                                                <select name="review_status[<?= (int)$row['extraction_row_id'] ?>]" class="form-select form-select-sm">
                                                    <option value="pending" <?= ($row['review_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="accepted" <?= ($row['review_status'] ?? '') === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                                                    <option value="rejected" <?= ($row['review_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                </select>
                                            </td>
                                            <td>
                                                <?php if (!$warnings): ?>
                                                    <span class="badge bg-light text-dark border">None</span>
                                                <?php else: ?>
                                                    <?php foreach ($warnings as $warning): ?>
                                                        <span class="badge bg-warning text-dark d-block mb-1"><?= mer_safe($warning) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Review Changes</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><strong>Create Draft Templates</strong></div>
            <div class="card-body">
                <div class="text-muted mb-3">Accepted rows only will be grouped into interval-based draft templates. Existing template review/approval remains unchanged.</div>
                <form method="post" action="submit_maintenance_extraction_create_templates.php">
                    <input type="hidden" name="run_id" value="<?= mer_safe($runId) ?>">
                    <input type="hidden" name="source_id" value="<?= (int)$run['source_id'] ?>">
                    <?php if ($equipmentId > 0): ?>
                        <input type="hidden" name="equipment_id" value="<?= $equipmentId ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success" <?= $summary['accepted'] > 0 ? '' : 'disabled' ?>>Create Draft Templates from Accepted Rows</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
