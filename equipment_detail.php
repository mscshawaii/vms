<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';
require_once __DIR__ . '/includes/maintenance_source_finder_functions.php';
require_once __DIR__ . '/includes/maintenance_template_extraction_functions.php';

$equipment_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        e.*,
        cat.name AS category_name,
        typ.name AS type_name,
        sub.name AS subtype_name,
        v.vesselName,

        fed.extinguisher_detail_id,
        fed.rule_profile_id,
        fed.agent_type,
        fed.extinguisher_class,
        fed.ul_rating,
        fed.capacity_value,
        fed.capacity_unit,
        fed.cylinder_material,
        fed.stored_pressure,
        fed.cartridge_operated,
        fed.manufacture_date,
        fed.last_monthly_inspection_date,
        fed.next_monthly_due,
        fed.last_annual_service_date,
        fed.next_annual_due,
        fed.last_internal_exam_date,
        fed.next_internal_exam_due,
        fed.last_hydro_test_date,
        fed.next_hydro_due,
        fed.last_service_vendor,
        fed.service_tag_number,
        fed.remarks,

        frp.profile_name AS rule_profile_name
    FROM equipment e
    LEFT JOIN equipment_category cat ON e.category_id = cat.id
    LEFT JOIN equipment_type typ ON e.type_id = typ.id
    LEFT JOIN equipment_subtype sub ON e.subtype_id = sub.id
    LEFT JOIN vessels v ON e.vessel_id = v.vessel_id
    LEFT JOIN fire_extinguisher_details fed ON fed.eid = e.eid
    LEFT JOIN fire_extinguisher_rule_profiles frp ON fed.rule_profile_id = frp.rule_profile_id
    WHERE e.eid = ?
");
$stmt->execute([$equipment_id]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$equipment) {
    die("❌ Equipment not found.");
}

/* Load additional attachments from equipment_files */
$attachmentCols = [];
$attachmentStmt = $pdo->query("SHOW COLUMNS FROM equipment_files");
if ($attachmentStmt) {
    foreach ($attachmentStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $attachmentCols[strtolower($col['Field'])] = $col['Field'];
    }
}

$attachments = [];
if ($attachmentCols) {
    $idCol   = $attachmentCols['equipment_file_id'] ?? null;
    $eidCol  = $attachmentCols['eid'] ?? null;
    $pathCol = $attachmentCols['file_path'] ?? null;
    $nameCol = $attachmentCols['file_name'] ?? null;

    if ($eidCol && $pathCol) {
        $selectCols = [];
        if ($idCol) {
            $selectCols[] = $idCol . " AS equipment_file_id";
        }
        $selectCols[] = $pathCol . " AS file_path";

        if ($nameCol) {
            $selectCols[] = $nameCol . " AS file_name";
        } else {
            $selectCols[] = "NULL AS file_name";
        }

        $orderBits = [];
        if (isset($attachmentCols['uploaded_at'])) {
            $orderBits[] = $attachmentCols['uploaded_at'] . " DESC";
        }
        if ($idCol) {
            $orderBits[] = $idCol . " DESC";
        }
        $orderSql = $orderBits ? (" ORDER BY " . implode(', ', $orderBits)) : '';

        $sqlAttach = "SELECT " . implode(', ', $selectCols) . " FROM equipment_files WHERE {$eidCol} = ?" . $orderSql;
        $stmtAttach = $pdo->prepare($sqlAttach);
        $stmtAttach->execute([$equipment_id]);
        $attachments = $stmtAttach->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* Helper to display values safely */
function safe($value) {
    return isset($value) && $value !== '' && $value !== null
        ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
        : '—';
}

function yesNoDash($value) {
    if ($value === null || $value === '') return '—';
    return ((string)$value === '1') ? 'Yes' : 'No';
}

$hasFireDetails = !empty($equipment['extinguisher_detail_id']);
$vessel_id = (int)$equipment['vessel_id'];
$hourMeter = vms_hour_get_meter_by_equipment($pdo, $equipment_id);
$scheduleRows = [];
$historyRows = [];
$dueSummary = ['due_soon' => 0, 'due' => 0, 'overdue' => 0];
$manualSources = [];
$availableTemplates = [];

if ($hourMeter) {
    $scheduleStmt = $pdo->prepare("
        SELECT *
        FROM equipment_maintenance_schedules
        WHERE meter_id = ?
        ORDER BY is_active DESC, service_name ASC
    ");
    $scheduleStmt->execute([(int)$hourMeter['meter_id']]);
    $scheduleRows = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

    $historyStmt = $pdo->prepare("
        SELECT event_type, completion_date, completion_hours, performed_by, note, created_at
        FROM equipment_maintenance_events
        WHERE meter_id = ?
        ORDER BY completion_hours DESC, completion_date DESC, event_id DESC
        LIMIT 10
    ");
    $historyStmt->execute([(int)$hourMeter['meter_id']]);
    $historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($scheduleRows as $schedule) {
        $nextDueHours = (float)($schedule['next_due_hours'] ?? 0);
        $state = vms_hour_due_state((float)$hourMeter['current_hours'], $nextDueHours, (int)($schedule['advance_notice_hours'] ?? 0));
        if ($state !== null) {
            $dueSummary[$state]++;
            if ((float)$hourMeter['current_hours'] > $nextDueHours) {
                $dueSummary['overdue']++;
            }
        }
    }
}

$sourceSearch = [
    'equipment_type' => trim((string)($equipment['subtype_name'] ?? $equipment['type_name'] ?? '')),
    'manufacturer' => trim((string)($equipment['manufacturer'] ?? '')),
    'model' => trim((string)($equipment['modelNumber'] ?? '')),
    'serial_year' => trim((string)($equipment['serialNumber'] ?? '')),
];
$manualSources = vms_source_finder_get_saved_sources($pdo, $sourceSearch, $equipment_id, 6);
$availableTemplates = vms_template_get_matching_approved_templates($pdo, $sourceSearch, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Equipment Details - VMS</title>

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

        .attachment-card {
            max-width: 180px;
        }

        .attachment-thumb {
            max-width: 160px;
            max-height: 120px;
        }
    </style>
</head>
<body>
<?php
$title = 'Equipment Details';
$back_link = 'vessel_equipment.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="equip-shell">
    <div class="app-page">
        <div class="app-container">

            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'photo_deleted'): ?>
                    <div class="alert alert-success">Primary photo deleted.</div>
                <?php elseif ($_GET['success'] === 'attachment_deleted'): ?>
                    <div class="alert alert-success">Attachment deleted.</div>
                <?php elseif ($_GET['success'] === 'equipment_added'): ?>
                    <div class="alert alert-success">Equipment added successfully.</div>
                <?php elseif ($_GET['success'] === 'equipment_updated'): ?>
                    <div class="alert alert-success">Equipment updated successfully.</div>
                <?php elseif ($_GET['success'] === 'primary_set'): ?>
                    <div class="alert alert-success">Primary photo updated.</div>    
                <?php elseif ($_GET['success'] === 'schedule_added'): ?>
                    <div class="alert alert-success">Maintenance schedule added.</div>
                <?php elseif ($_GET['success'] === 'maintenance_completed'): ?>
                    <div class="alert alert-success">Maintenance completion saved.</div>
                <?php elseif ($_GET['success'] === 'maintenance_backfilled'): ?>
                    <div class="alert alert-success">Historical maintenance backfill saved.</div>
                <?php elseif ($_GET['success'] === 'equipment_replaced'): ?>
                    <div class="alert alert-success">Replacement equipment created and old unit retired.</div>
                <?php elseif ($_GET['success'] === 'meter_corrected'): ?>
                    <div class="alert alert-success">Meter correction saved and audited.</div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="vms-card">
                <div class="equip-header">
                    <div>
                        <h1 class="equip-title">Equipment Details</h1>
                        <p class="equip-subtitle"><?= safe($equipment['equipmentName']) ?> · <?= safe($equipment['vesselName']) ?></p>
                    </div>

                    <div class="equip-actions">
                        <?php if ((int)($equipment['is_active'] ?? 1) === 1): ?>
                            <a href="edit_equipment.php?id=<?= (int)$equipment['eid'] ?>" class="btn btn-primary">Edit Equipment</a>
                            <a href="equipment_replace.php?id=<?= (int)$equipment['eid'] ?>" class="btn btn-outline-warning">Replace Equipment</a>
                        <?php endif; ?>
                        <?php if (!empty(trim((string)($equipment['manufacturer'] ?? ''))) || !empty(trim((string)($equipment['modelNumber'] ?? '')))): ?>
                            <a href="maintenance_source_finder.php?equipment_id=<?= (int)$equipment['eid'] ?>" class="btn btn-outline-info">Find Maintenance Sources</a>
                        <?php endif; ?>
                        <?php if ($hourMeter): ?>
                            <a href="meter_verification.php?vessel_id=<?= (int)$vessel_id ?>&equipment_id=<?= (int)$equipment['eid'] ?>" class="btn btn-outline-primary">Verify Meter</a>
                            <a href="manual_meter_correction.php?meter_id=<?= (int)$hourMeter['meter_id'] ?>" class="btn btn-outline-dark">Correct Meter</a>
                        <?php endif; ?>
                        <a href="vessel_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Equipment</a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><strong>General Equipment Information</strong></div>
                <div class="card-body p-0">
                    <table class="table table-bordered mb-0">
                        <tr><th style="width: 240px;">Vessel</th><td><?= safe($equipment['vesselName']) ?></td></tr>
                        <tr><th>Category</th><td><?= safe($equipment['category_name']) ?></td></tr>
                        <tr><th>Type</th><td><?= safe($equipment['type_name']) ?></td></tr>
                        <tr><th>Subtype</th><td><?= safe($equipment['subtype_name']) ?></td></tr>
                        <tr><th>Name</th><td><?= safe($equipment['equipmentName']) ?></td></tr>
                        <tr><th>Location</th><td><?= safe($equipment['equipmentLocation']) ?></td></tr>
                        <tr><th>Active</th><td><?= ((int)($equipment['is_active'] ?? 1) === 1) ? 'Yes' : 'No' ?></td></tr>
                        <tr><th>Retired At</th><td><?= safe($equipment['retired_at'] ?? null) ?></td></tr>
                        <tr><th>Retirement Reason</th><td><?= safe($equipment['retirement_reason'] ?? null) ?></td></tr>
                        <tr>
                            <th>Replaced By</th>
                            <td>
                                <?php if (!empty($equipment['replaced_by_eid'])): ?>
                                    <a href="equipment_detail.php?id=<?= (int)$equipment['replaced_by_eid'] ?>">Equipment #<?= (int)$equipment['replaced_by_eid'] ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>Manufacturer</th><td><?= safe($equipment['manufacturer']) ?></td></tr>
                        <tr><th>Model</th><td><?= safe($equipment['modelNumber']) ?></td></tr>
                        <tr><th>Serial Number</th><td><?= safe($equipment['serialNumber']) ?></td></tr>
                        <tr><th>Install Date</th><td><?= safe($equipment['installDate']) ?></td></tr>
                        <tr><th>Expiration Date</th><td><?= safe($equipment['expDate']) ?></td></tr>
                        <tr><th>Quantity</th><td><?= safe($equipment['quantity']) ?></td></tr>
                        <tr><th>Unit</th><td><?= safe($equipment['unit']) ?></td></tr>
                        <tr><th>Onboard Required</th><td><?= $equipment['onBoardNotRequired'] == 0 ? 'Yes' : 'No' ?></td></tr>
                        <tr><th>Notes</th><td><?= nl2br(safe($equipment['notes'])) ?></td></tr>

                        <tr>
                            <th>Primary Photo</th>
                            <td>
                                <?php if (!empty($equipment['photo_path'])): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <img src="/<?= ltrim($equipment['photo_path'], '/') ?>" alt="Equipment Photo" style="max-width: 300px;" class="img-thumbnail">

                                        <div>
                                            <a href="delete_equipment_photo.php?id=<?= (int)$equipment['eid'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete the primary photo?');">
                                                Delete Primary Photo
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Attachments</th>
                            <td>
                                <?php if (!empty($attachments)): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($attachments as $file): ?>
                                            <?php
                                            $path = (string)($file['file_path'] ?? '');
                                            $name = (string)($file['file_name'] ?? basename($path));
                                            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                            $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true);
                                            ?>
                                            <div class="border rounded p-2 bg-light attachment-card">
                                                <?php if ($isImage): ?>
                                                    <a href="/<?= ltrim($path, '/') ?>" target="_blank">
                                                        <img src="/<?= ltrim($path, '/') ?>"
                                                             alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                                                             class="img-thumbnail mb-2 attachment-thumb">
                                                    </a>
                                                <?php endif; ?>

                                                <div class="small text-break mb-2"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="d-flex gap-1 flex-wrap">

                                                    <a href="/<?= ltrim($path, '/') ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        Open
                                                    </a>

                                                    <?php if (!empty($file['equipment_file_id'])): ?>

                                                        <?php if (!empty($equipment['photo_path']) && $equipment['photo_path'] === $path): ?>
                                                            <span class="badge bg-success align-self-center">Primary</span>
                                                        <?php else: ?>
                                                            <a href="set_equipment_primary_photo.php?attachment_id=<?= (int)$file['equipment_file_id'] ?>"
                                                            class="btn btn-sm btn-outline-success">
                                                                Make Primary
                                                            </a>
                                                        <?php endif; ?>

                                                        <a href="delete_equipment_attachment.php?attachment_id=<?= (int)$file['equipment_file_id'] ?>"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Delete this attachment?');">
                                                            Delete
                                                        </a>

                                                    <?php endif; ?>

                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if ($manualSources || !empty(trim((string)($equipment['manufacturer'] ?? ''))) || !empty(trim((string)($equipment['modelNumber'] ?? '')))): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <strong>Equipment Manuals / Sources</strong>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="maintenance_source_finder.php?equipment_id=<?= (int)$equipment['eid'] ?>" class="btn btn-sm btn-outline-info">Find Maintenance Sources</a>
                            <a href="equipment_manual_library.php" class="btn btn-sm btn-outline-secondary">Source Library</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($manualSources): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Source</th>
                                            <th>Approved</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($manualSources as $source): ?>
                                        <?php $approvedName = trim((string)($source['approved_fName'] ?? '') . ' ' . (string)($source['approved_lName'] ?? '')); ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= safe($source['title']) ?></div>
                                                <?php if (!empty($source['confidence_label'])): ?>
                                                    <div class="small text-muted"><?= safe($source['confidence_label']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= safe($source['source_domain']) ?></div>
                                                <div class="small text-muted"><?= safe($source['source_type'] ?? null) ?></div>
                                            </td>
                                            <td>
                                                <div><?= safe($source['approved_at']) ?></div>
                                                <div class="small text-muted"><?= $approvedName !== '' ? safe($approvedName) : '—' ?></div>
                                            </td>
                                            <td>
                                                <a href="<?= safe($source['source_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Open Source</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No saved VMS library sources for this equipment yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($availableTemplates): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <strong>Available Maintenance Templates</strong>
                        <a href="equipment_manual_library.php?manufacturer=<?= urlencode((string)($equipment['manufacturer'] ?? '')) ?>&model=<?= urlencode((string)($equipment['modelNumber'] ?? '')) ?>" class="btn btn-sm btn-outline-secondary">View Templates</a>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2">
                            Approved templates are reusable references only in this phase. They are not applied to live equipment schedules automatically.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service</th>
                                        <th>Interval</th>
                                        <th>Basis</th>
                                        <th>Source</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($availableTemplates as $template): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= safe($template['service_name']) ?></div>
                                            <?php if (!empty($template['confidence_label'])): ?>
                                                <div class="small text-muted"><?= safe($template['confidence_label']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($template['interval_hours'])): ?>
                                                <div><?= (int)$template['interval_hours'] ?> hours</div>
                                            <?php endif; ?>
                                            <?php if (!empty($template['interval_months'])): ?>
                                                <div><?= (int)$template['interval_months'] ?> months</div>
                                            <?php endif; ?>
                                            <?php if (empty($template['interval_hours']) && empty($template['interval_months'])): ?>
                                                — 
                                            <?php endif; ?>
                                        </td>
                                        <td><?= safe($template['interval_basis'] ?? null) ?></td>
                                        <td>
                                            <div><?= safe($template['source_title']) ?></div>
                                            <div class="small text-muted"><?= safe($template['source_domain'] ?? null) ?></div>
                                        </td>
                                        <td class="text-nowrap">
                                            <a href="maintenance_template_extract.php?source_id=<?= (int)$template['source_id'] ?>&equipment_id=<?= (int)$equipment['eid'] ?>" class="btn btn-sm btn-outline-primary">Review Template</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hourMeter): ?>
                <div class="card mb-4">
                    <div class="card-header"><strong>Hour Tracking Summary</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3"><strong>Tracked Class</strong><br><?= safe($hourMeter['tracked_class']) ?></div>
                            <div class="col-md-3"><strong>Current Hours</strong><br><?= safe(number_format((float)$hourMeter['current_hours'], 1)) ?></div>
                            <div class="col-md-3"><strong>Baseline Hours</strong><br><?= safe(number_format((float)$hourMeter['baseline_hours'], 1)) ?></div>
                            <div class="col-md-3"><strong>Last Verified</strong><br><?= safe($hourMeter['last_verified_at'] ?? '—') ?></div>
                            <div class="col-md-3"><strong>Display Order</strong><br><?= (int)$hourMeter['display_order'] ?></div>
                            <div class="col-md-3"><strong>Meter Active</strong><br><?= ((int)$hourMeter['is_active'] === 1) ? 'Yes' : 'No' ?></div>
                            <div class="col-md-3"><strong>Due Soon</strong><br><?= (int)$dueSummary['due_soon'] ?></div>
                            <div class="col-md-3"><strong>Due / Overdue</strong><br><?= (int)$dueSummary['due'] ?>/<?= (int)$dueSummary['overdue'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Maintenance Schedules</strong></div>
                    <div class="card-body">
                        <form method="post" action="submit_equipment_schedule.php" class="row g-3 mb-4">
                            <input type="hidden" name="equipment_id" value="<?= (int)$equipment['eid'] ?>">
                            <input type="hidden" name="meter_id" value="<?= (int)$hourMeter['meter_id'] ?>">
                            <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                            <div class="col-md-4">
                                <label>Service Name</label>
                                <input type="text" name="service_name" class="form-control" required>
                            </div>
                            <div class="col-md-2">
                                <label>Interval Hours</label>
                                <input type="number" name="interval_hours" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-2">
                                <label>Advance Notice</label>
                                <input type="number" name="advance_notice_hours" class="form-control" min="0" value="0" required>
                            </div>
                            <div class="col-md-2">
                                <label>Status</label>
                                <select name="is_active" class="form-select">
                                    <option value="1" selected>Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">Add Schedule</button>
                            </div>
                        </form>

                        <?php if ($scheduleRows): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Service</th>
                                            <th>Interval</th>
                                            <th>Advance</th>
                                            <th>Last Completed</th>
                                            <th>Next Due</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($scheduleRows as $schedule): ?>
                                        <?php
                                        $state = vms_hour_due_state((float)$hourMeter['current_hours'], (float)($schedule['next_due_hours'] ?? 0), (int)($schedule['advance_notice_hours'] ?? 0));
                                        if ((float)$hourMeter['current_hours'] > (float)($schedule['next_due_hours'] ?? 0)) {
                                            $state = 'overdue';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= safe($schedule['service_name']) ?></td>
                                            <td><?= (int)$schedule['interval_hours'] ?></td>
                                            <td><?= (int)$schedule['advance_notice_hours'] ?></td>
                                            <td><?= safe($schedule['last_completed_hours']) ?></td>
                                            <td><?= safe($schedule['next_due_hours']) ?></td>
                                            <td><?= safe($state ?? (((int)$schedule['is_active'] === 1) ? 'active' : 'inactive')) ?></td>
                                            <td class="text-nowrap">
                                                <a href="maintenance_complete.php?schedule_id=<?= (int)$schedule['schedule_id'] ?>" class="btn btn-sm btn-outline-success">Complete</a>
                                                <a href="maintenance_backfill.php?schedule_id=<?= (int)$schedule['schedule_id'] ?>" class="btn btn-sm btn-outline-primary">Backfill</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No hour-based schedules configured yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Maintenance History Summary</strong></div>
                    <div class="card-body">
                        <?php if ($historyRows): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Hours</th>
                                            <th>Type</th>
                                            <th>Performed By</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($historyRows as $row): ?>
                                        <tr>
                                            <td><?= safe($row['completion_date']) ?></td>
                                            <td><?= safe($row['completion_hours']) ?></td>
                                            <td><?= safe($row['event_type']) ?></td>
                                            <td><?= safe($row['performed_by']) ?></td>
                                            <td><?= nl2br(safe($row['note'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No maintenance events recorded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasFireDetails): ?>
                <div class="card mb-4">
                    <div class="card-header"><strong>🔥 Fire Extinguisher Details</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <tr><th style="width: 240px;">Rule Profile</th><td><?= safe($equipment['rule_profile_name']) ?></td></tr>
                            <tr><th>Agent Type</th><td><?= safe($equipment['agent_type']) ?></td></tr>
                            <tr><th>Extinguisher Class</th><td><?= safe($equipment['extinguisher_class']) ?></td></tr>
                            <tr><th>UL Rating</th><td><?= safe($equipment['ul_rating']) ?></td></tr>
                            <tr><th>Capacity</th><td><?= safe($equipment['capacity_value']) ?> <?= safe($equipment['capacity_unit']) !== '—' ? safe($equipment['capacity_unit']) : '' ?></td></tr>
                            <tr><th>Cylinder Material</th><td><?= safe($equipment['cylinder_material']) ?></td></tr>
                            <tr><th>Stored Pressure</th><td><?= yesNoDash($equipment['stored_pressure']) ?></td></tr>
                            <tr><th>Cartridge Operated</th><td><?= yesNoDash($equipment['cartridge_operated']) ?></td></tr>
                            <tr><th>Manufacture Date</th><td><?= safe($equipment['manufacture_date']) ?></td></tr>
                            <tr><th>Last Service Vendor</th><td><?= safe($equipment['last_service_vendor']) ?></td></tr>
                            <tr><th>Service Tag Number</th><td><?= safe($equipment['service_tag_number']) ?></td></tr>
                            <tr><th>Remarks</th><td><?= nl2br(safe($equipment['remarks'])) ?></td></tr>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>🛠 Service & Due Tracking</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <tr><th style="width: 240px;">Last Monthly Inspection</th><td><?= safe($equipment['last_monthly_inspection_date']) ?></td></tr>
                            <tr><th>Next Monthly Due</th><td><?= safe($equipment['next_monthly_due']) ?></td></tr>
                            <tr><th>Last Annual Service</th><td><?= safe($equipment['last_annual_service_date']) ?></td></tr>
                            <tr><th>Next Annual Due</th><td><?= safe($equipment['next_annual_due']) ?></td></tr>
                            <tr><th>Last Internal Exam</th><td><?= safe($equipment['last_internal_exam_date']) ?></td></tr>
                            <tr><th>Next Internal Due</th><td><?= safe($equipment['next_internal_exam_due']) ?></td></tr>
                            <tr><th>Last Hydro Test</th><td><?= safe($equipment['last_hydro_test_date']) ?></td></tr>
                            <tr><th>Next Hydro Due</th><td><?= safe($equipment['next_hydro_due']) ?></td></tr>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
