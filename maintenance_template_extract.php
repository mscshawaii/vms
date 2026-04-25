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

function mte_safe($value): string
{
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function mte_value($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$sourceId = (int)($_GET['source_id'] ?? 0);
$equipmentId = (int)($_GET['equipment_id'] ?? 0);

if ($sourceId <= 0) {
    exit('Missing source_id.');
}

$source = vms_template_get_source($pdo, $sourceId);
if (!$source) {
    exit('Saved source not found.');
}

$templatesTableExists = vms_template_table_exists($pdo);
$configDiagnostics = vms_template_extraction_get_diagnostics();
$templates = $templatesTableExists ? vms_template_get_templates_for_source($pdo, $sourceId) : [];
$flash = $_SESSION['maintenance_template_extract_flash'] ?? null;
unset($_SESSION['maintenance_template_extract_flash']);
$draftCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
foreach ($templates as $template) {
    $status = (string)($template['review_status'] ?? 'draft');
    if ($status === 'approved') {
        $approvedCount++;
    } elseif ($status === 'rejected') {
        $rejectedCount++;
    } else {
        $draftCount++;
    }
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance Template Extraction - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .mte-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .mte-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
        .mte-title { font-size:1.65rem; font-weight:700; margin:0 0 4px; }
        .mte-subtitle { color:#6b7280; margin:0; }
    </style>
</head>
<body>
<?php
$title = 'Maintenance Draft Extraction';
$back_link = 'equipment_manual_library.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="mte-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card mb-3">
                <div class="mte-header">
                    <div>
                        <h1 class="mte-title">Maintenance Draft Extraction</h1>
                        <p class="mte-subtitle">Extract draft maintenance template rows from a saved manual/source. Drafts must be reviewed before approval.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= mte_safe($source['source_url'] ?? '') ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">Open Source</a>
                        <a href="equipment_manual_library.php" class="btn btn-outline-secondary">Back to Source Library</a>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['status'])): ?>
                <?php if ($_GET['status'] === 'review_saved'): ?>
                    <div class="alert alert-success">Template draft review saved.</div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= ($flash['type'] ?? 'info') === 'success' ? 'success' : (($flash['type'] ?? '') === 'warning' ? 'warning' : 'info') ?>">
                    <?= mte_safe($flash['message'] ?? '') ?>
                </div>

                <?php if (!empty($flash['debug']) && is_array($flash['debug'])): ?>
                    <div class="card mb-4">
                        <div class="card-header"><strong>Last Extraction Status</strong></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4"><strong>Input Source Used</strong><br><?= mte_safe($flash['debug']['input_source_used'] ?? '—') ?></div>
                                <div class="col-md-4"><strong>Pasted Content Length</strong><br><?= (int)($flash['debug']['pasted_content_length'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Provider Configured</strong><br><?= mte_safe($flash['debug']['provider_configured'] ?? 'No') ?></div>
                                <div class="col-md-4"><strong>Provider Attempted</strong><br><?= mte_safe($flash['debug']['provider_attempted'] ?? 'No') ?></div>
                                <div class="col-md-4"><strong>Provider Returned Row Count</strong><br><?= (int)($flash['debug']['provider_returned_row_count'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Heuristic Fallback Attempted</strong><br><?= mte_safe($flash['debug']['heuristic_fallback_attempted'] ?? 'No') ?></div>
                                <div class="col-md-4"><strong>Raw Candidate Count</strong><br><?= (int)($flash['debug']['raw_candidate_count'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Grouped Draft Row Count</strong><br><?= (int)($flash['debug']['grouped_row_count'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Grouping Applied</strong><br><?= mte_safe($flash['debug']['grouping_applied'] ?? 'No') ?></div>
                                <div class="col-md-4"><strong>PDF Page Range</strong><br><?= mte_safe($flash['debug']['pdf_page_range'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>PDF Pages Processed</strong><br><?= (int)($flash['debug']['pdf_pages_processed'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>PDF Conversion Method</strong><br><?= mte_safe($flash['debug']['pdf_conversion_method'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>Generated Image Count</strong><br><?= (int)($flash['debug']['pdf_generated_image_count'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>PDF Command Exit Code</strong><br><?= mte_safe($flash['debug']['pdf_command_exit_code'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>Image Files Included</strong><br><?= (int)($flash['debug']['provider_image_files_included_count'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Approx Payload Size (bytes)</strong><br><?= (int)($flash['debug']['provider_approx_payload_size_bytes'] ?? 0) ?></div>
                                <div class="col-md-4"><strong>Provider Model Attempted</strong><br><?= mte_safe($flash['debug']['provider_model_attempted'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>Prompt Mode Used</strong><br><?= mte_safe($flash['debug']['provider_prompt_mode_used'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>Model Response Text Length</strong><br><?= (int)($flash['debug']['provider_response_text_length'] ?? 0) ?></div>
                                <div class="col-12"><strong>Provider Models Attempted</strong><br><?= mte_safe($flash['debug']['provider_models_attempted'] ?? 'â€”') ?></div>
                                <div class="col-12"><strong>Provider Endpoint</strong><br><code><?= mte_safe($flash['debug']['provider_endpoint'] ?? 'â€”') ?></code></div>
                                <div class="col-md-4"><strong>OpenAI Error Type</strong><br><?= mte_safe($flash['debug']['provider_openai_error_type'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>OpenAI Error Code</strong><br><?= mte_safe($flash['debug']['provider_openai_error_code'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>OpenAI Error Message</strong><br><?= mte_safe($flash['debug']['provider_openai_error_message'] ?? 'â€”') ?></div>
                                <div class="col-md-4"><strong>JSON Parse Failed</strong><br><?= mte_safe($flash['debug']['provider_json_parse_failed'] ?? 'No') ?></div>
                                <div class="col-md-4"><strong>Zero Rows Returned</strong><br><?= mte_safe($flash['debug']['provider_zero_rows_returned'] ?? 'No') ?></div>
                                <div class="col-12"><strong>PDF Command</strong><br><code><?= mte_safe($flash['debug']['pdf_command'] ?? 'â€”') ?></code></div>
                                <div class="col-12"><strong>Temp Output Directory</strong><br><code><?= mte_safe($flash['debug']['pdf_output_dir'] ?? 'â€”') ?></code></div>
                                <div class="col-12"><strong>Generated Files</strong><br><?= mte_safe($flash['debug']['pdf_generated_files'] ?? 'â€”') ?></div>
                                <div class="col-12"><strong>Command Output</strong><br><?= nl2br(mte_safe($flash['debug']['pdf_command_stderr'] ?? 'â€”')) ?></div>
                                <?php if (!empty($flash['debug']['pdf_image_previews']) && is_array($flash['debug']['pdf_image_previews'])): ?>
                                    <div class="col-12">
                                        <strong>Rendered Page Previews</strong>
                                        <div class="row g-3 mt-1">
                                            <?php foreach ($flash['debug']['pdf_image_previews'] as $preview): ?>
                                                <?php if (!empty($preview['data_url'])): ?>
                                                    <div class="col-md-4">
                                                        <div class="border rounded p-2 bg-light">
                                                            <div class="small text-muted mb-2"><?= mte_safe($preview['name'] ?? 'Preview') ?></div>
                                                            <img src="<?= mte_safe($preview['data_url']) ?>" alt="<?= mte_safe($preview['name'] ?? 'Preview') ?>" class="img-fluid rounded border">
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!$templatesTableExists): ?>
                <div class="alert alert-info">
                    Maintenance template table is not available yet. Apply <code>equipment_maintenance_templates_phase2.sql</code> first.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Draft only.</strong> This workflow does not create live equipment schedules, tasks, or ICR changes. Approved rows remain reusable template references only.
                </div>

                <?php if (!vms_template_extraction_is_configured()): ?>
                    <div class="alert alert-info">
                        <?= mte_safe(vms_template_extraction_config_message()) ?>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header"><strong>Extraction Configuration Diagnostic</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><strong>private/config_maintenance_template_extraction.php</strong><br><?= $configDiagnostics['private_config_exists'] ? 'Found' : 'Not found' ?></div>
                            <div class="col-md-4"><strong>config_maintenance_template_extraction.php</strong><br><?= $configDiagnostics['local_config_exists'] ? 'Found' : 'Not found' ?></div>
                            <div class="col-md-4"><strong>Provider Loaded</strong><br><?= $configDiagnostics['provider_loaded'] ? 'Yes' : 'No' ?></div>
                            <div class="col-md-4"><strong>Provider Name</strong><br><?= $configDiagnostics['provider_loaded'] ? mte_safe($configDiagnostics['provider_name']) : '—' ?></div>
                            <div class="col-md-4"><strong>API Key Present</strong><br><?= $configDiagnostics['api_key_present'] ? 'Yes' : 'No' ?></div>
                            <div class="col-md-4"><strong>Model Loaded</strong><br><?= $configDiagnostics['model_loaded'] ? mte_safe($configDiagnostics['model_name']) : 'No' ?></div>
                            <div class="col-md-4"><strong>PDF Conversion Available</strong><br><?= !empty($configDiagnostics['pdf_support']['available']) ? 'Yes' : 'No' ?></div>
                            <div class="col-md-4"><strong>PDF Conversion Method</strong><br><?= !empty($configDiagnostics['pdf_support']['method']) ? mte_safe($configDiagnostics['pdf_support']['method']) : 'â€”' ?></div>
                            <div class="col-md-4"><strong>PDF Dependency Status</strong><br><?= !empty($configDiagnostics['pdf_support']['available']) ? 'Ready' : mte_safe($configDiagnostics['pdf_support']['dependency_message'] ?? 'Unavailable') ?></div>
                        </div>
                        <div class="small text-muted mt-3">
                            Supported configuration sources: private config file, local config file, or environment variables <code>VMS_TEMPLATE_EXTRACTION_PROVIDER</code>, <code>OPENAI_API_KEY</code>, and <code>VMS_TEMPLATE_EXTRACTION_MODEL</code>. API key values are never displayed.
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><strong>Saved Source Context</strong></div>
                            <div class="card-body">
                                <div><strong>Title:</strong> <?= mte_safe($source['title']) ?></div>
                                <div><strong>Manufacturer:</strong> <?= mte_safe($source['manufacturer']) ?></div>
                                <div><strong>Model:</strong> <?= mte_safe($source['model']) ?></div>
                                <div><strong>Equipment Type:</strong> <?= mte_safe($source['equipment_type']) ?></div>
                                <div><strong>Source Domain:</strong> <?= mte_safe($source['source_domain']) ?></div>
                                <div><strong>Source Type:</strong> <?= mte_safe($source['source_type']) ?></div>
                                <div><strong>Confidence:</strong> <?= mte_safe($source['confidence_label']) ?></div>
                                <?php if (!empty($source['equipmentName'])): ?>
                                    <div><strong>Linked Equipment:</strong> <a href="equipment_detail.php?id=<?= (int)$source['linked_equipment_id'] ?>"><?= mte_safe($source['equipmentName']) ?></a></div>
                                <?php endif; ?>
                                <?php if (!empty($source['vesselName'])): ?>
                                    <div><strong>Vessel:</strong> <?= mte_safe($source['vesselName']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($source['company_name'])): ?>
                                    <div><strong>Company:</strong> <?= mte_safe($source['company_name']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($source['notes'])): ?>
                                    <div class="mt-3">
                                        <strong>Saved Source Notes / Snippet</strong>
                                        <div class="small text-muted mt-1"><?= nl2br(mte_safe($source['notes'])) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header"><strong>Draft Status</strong></div>
                            <div class="card-body">
                                <div class="d-flex gap-2 flex-wrap mb-3">
                                    <span class="badge bg-secondary">Draft <?= (int)$draftCount ?></span>
                                    <span class="badge bg-success">Approved <?= (int)$approvedCount ?></span>
                                    <span class="badge bg-danger">Rejected <?= (int)$rejectedCount ?></span>
                                </div>

                                <form method="post" action="submit_maintenance_template_extract.php" class="mb-3">
                                    <input type="hidden" name="action" value="heuristic_extract">
                                    <input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
                                    <?php if ($equipmentId > 0): ?>
                                        <input type="hidden" name="equipment_id" value="<?= (int)$equipmentId ?>">
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label class="form-label">Paste Maintenance Schedule Content (Optional)</label>
                                        <textarea name="pasted_content" class="form-control" rows="6" placeholder="Paste maintenance schedule tables or manual text here. If provided, this will be used as the primary extraction input."></textarea>
                                        <div class="form-text">If left blank, extraction falls back to the saved source title and notes/snippet.</div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Extract Draft</button>
                                    <div class="form-text">Uses pasted content first when provided, then falls back to the saved source title and notes/snippet. Manual draft entry is always available below.</div>
                                </form>

                                <div class="small text-muted">
                                    Approved templates remain references only in this phase. Applying them to equipment schedules will happen in a later manual phase.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>PDF / Table Extraction</strong></div>
                    <div class="card-body">
                        <form method="post" action="submit_maintenance_template_extract.php" enctype="multipart/form-data" class="row g-3">
                            <input type="hidden" name="action" value="pdf_extract">
                            <input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
                            <?php if ($equipmentId > 0): ?>
                                <input type="hidden" name="equipment_id" value="<?= (int)$equipmentId ?>">
                            <?php endif; ?>

                            <div class="col-lg-6">
                                <label class="form-label">PDF Source</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="pdf_source_mode" id="pdfSourceSaved" value="saved_source" <?= !empty($source['source_url']) && preg_match('/\.pdf(?:\?|#|$)/i', (string)$source['source_url']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pdfSourceSaved">
                                        Use saved source PDF URL
                                        <?php if (!empty($source['source_url']) && preg_match('/\.pdf(?:\?|#|$)/i', (string)$source['source_url'])): ?>
                                            <span class="text-muted small d-block"><?= mte_safe($source['source_url']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small d-block">Saved source URL is not a PDF in this record.</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="pdf_source_mode" id="pdfSourceUpload" value="upload" <?= empty($source['source_url']) || !preg_match('/\.pdf(?:\?|#|$)/i', (string)$source['source_url']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pdfSourceUpload">Upload PDF manually</label>
                                </div>
                                <input type="file" name="uploaded_pdf" class="form-control mt-2" accept="application/pdf,.pdf">
                                <div class="form-text">PDF only. Uploaded files are used temporarily for extraction and then cleaned up.</div>
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label">Page Range</label>
                                <input type="text" name="page_range" class="form-control" placeholder="88-90" required>
                                <div class="form-text">Required. Up to <?= (int)(vms_template_extraction_get_config()['max_pdf_pages'] ?? 5) ?> pages per run.</div>
                            </div>

                            <div class="col-lg-3">
                                <label class="form-label">Table Extraction</label>
                                <div class="small text-muted border rounded p-3 bg-light h-100">
                                    Selected PDF pages are converted to images first, then sent to the configured OpenAI extraction provider. Results still go through draft review and approval only.
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">Extract From PDF Pages</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Add Manual Draft Row</strong></div>
                    <div class="card-body">
                        <form method="post" action="submit_maintenance_template_extract.php" class="row g-3">
                            <input type="hidden" name="action" value="manual_add">
                            <input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
                            <?php if ($equipmentId > 0): ?>
                                <input type="hidden" name="equipment_id" value="<?= (int)$equipmentId ?>">
                            <?php endif; ?>

                            <div class="col-md-4">
                                <label class="form-label">Service Name</label>
                                <input type="text" name="service_name" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Interval Hours</label>
                                <input type="number" name="interval_hours" class="form-control" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Interval Months</label>
                                <input type="number" name="interval_months" class="form-control" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Basis</label>
                                <input type="text" name="interval_basis" class="form-control" placeholder="hours / months">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Confidence</label>
                                <input type="text" name="confidence_label" class="form-control" value="Manual draft entry">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Steps / Notes</label>
                                <textarea name="steps" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Source Excerpt</label>
                                <textarea name="source_excerpt" class="form-control" rows="3" placeholder="Quote or summarize the relevant section from the source"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">Add Draft Row</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Draft / Approved Template Rows</strong></div>
                    <div class="card-body">
                        <?php if (!$templates): ?>
                            <div class="text-muted">No draft or approved template rows exist for this source yet.</div>
                        <?php else: ?>
                            <form method="post" action="submit_maintenance_template_review.php">
                                <input type="hidden" name="source_id" value="<?= (int)$sourceId ?>">
                                <?php if ($equipmentId > 0): ?>
                                    <input type="hidden" name="equipment_id" value="<?= (int)$equipmentId ?>">
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service</th>
                                                <th>Hours</th>
                                                <th>Months</th>
                                                <th>Basis</th>
                                                <th>Steps / Notes</th>
                                                <th>Source Excerpt</th>
                                                <th>Confidence</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($templates as $template): ?>
                                                <tr>
                                                    <td>
                                                        <input type="text" name="service_name[<?= (int)$template['template_id'] ?>]" class="form-control" value="<?= mte_value($template['service_name']) ?>">
                                                        <div class="small text-muted mt-1">Created <?= mte_safe($template['created_at']) ?></div>
                                                    </td>
                                                    <td><input type="number" name="interval_hours[<?= (int)$template['template_id'] ?>]" class="form-control" min="0" value="<?= mte_value($template['interval_hours']) ?>"></td>
                                                    <td><input type="number" name="interval_months[<?= (int)$template['template_id'] ?>]" class="form-control" min="0" value="<?= mte_value($template['interval_months']) ?>"></td>
                                                    <td><input type="text" name="interval_basis[<?= (int)$template['template_id'] ?>]" class="form-control" value="<?= mte_value($template['interval_basis']) ?>"></td>
                                                    <td><textarea name="steps[<?= (int)$template['template_id'] ?>]" class="form-control" rows="3"><?= mte_value($template['steps']) ?></textarea></td>
                                                    <td><textarea name="source_excerpt[<?= (int)$template['template_id'] ?>]" class="form-control" rows="3"><?= mte_value($template['source_excerpt']) ?></textarea></td>
                                                    <td><input type="text" name="confidence_label[<?= (int)$template['template_id'] ?>]" class="form-control" value="<?= mte_value($template['confidence_label']) ?>"></td>
                                                    <td>
                                                        <select name="review_status[<?= (int)$template['template_id'] ?>]" class="form-select">
                                                            <option value="draft" <?= ($template['review_status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                            <option value="approved" <?= ($template['review_status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                            <option value="rejected" <?= ($template['review_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                        </select>
                                                        <div class="small text-muted mt-1">
                                                            <?php if (!empty($template['reviewed_at'])): ?>
                                                                Reviewed <?= mte_safe($template['reviewed_at']) ?>
                                                            <?php else: ?>
                                                                Not reviewed yet
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <button type="submit" class="btn btn-success">Save Draft Review</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
