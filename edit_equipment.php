<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';

$eid = intval($_GET['id'] ?? 0);
if (!$eid) die("Equipment ID missing.");

$stmt = $pdo->prepare("SELECT * FROM equipment WHERE eid = ?");
$stmt->execute([$eid]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipment) die("Equipment not found.");

$vessel_id = (int)$equipment['vessel_id'];

$fireStmt = $pdo->prepare("SELECT * FROM fire_extinguisher_details WHERE eid = ? LIMIT 1");
$fireStmt->execute([$eid]);
$fire = $fireStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$hourMeter = vms_hour_get_meter_by_equipment($pdo, $eid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Equipment - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
    </style>
</head>
<body>
<?php
$title = 'Edit Equipment';
$back_link = 'vessel_equipment.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="equip-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="equip-header">
                    <div>
                        <h1 class="equip-title">Edit Equipment</h1>
                        <p class="equip-subtitle">Update equipment details, photo, and extinguisher-specific fields.</p>
                    </div>
                    <div class="equip-actions">
                        <a href="vessel_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Equipment</a>
                    </div>
                </div>

                <form method="post" action="update_equipment.php" enctype="multipart/form-data">
                    <input type="hidden" name="eid" value="<?= (int)$eid ?>">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label>Category</label>
                            <select id="category" name="category_id" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php
                                $cats = $pdo->query("SELECT id, name FROM equipment_category ORDER BY name");
                                while ($row = $cats->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($equipment['category_id'] == $row['id']) ? 'selected' : '';
                                    echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Type</label>
                            <select id="type" name="equipment_type_id" class="form-select" required>
                                <option value="">-- Select Type --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label>Subtype</label>
                            <select id="subtype" name="equipment_subtype_id" class="form-select">
                                <option value="">-- Select Subtype --</option>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="equipment_name" id="equipment_name" value="<?= htmlspecialchars($equipment['equipmentName'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label>Auto-Generated Equipment Name</label>
                        <input type="text" id="equipment_name_preview" class="form-control bg-light" readonly value="<?= htmlspecialchars($equipment['equipmentName'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label>Location</label>
                            <input type="text" name="equipmentLocation" class="form-control" required value="<?= htmlspecialchars($equipment['equipmentLocation'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label>Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control" value="<?= htmlspecialchars($equipment['manufacturer'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label>Model</label>
                            <input type="text" name="modelNumber" class="form-control" value="<?= htmlspecialchars($equipment['modelNumber'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label>Serial Number</label>
                            <input type="text" name="serialNumber" class="form-control" value="<?= htmlspecialchars($equipment['serialNumber'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label>Install Date</label>
                            <input type="date" name="installDate" class="form-control" value="<?= htmlspecialchars($equipment['installDate'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Expiration Date</label>
                            <input type="date" name="expDate" class="form-control" value="<?= htmlspecialchars($equipment['expDate'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-4">
                            <label>Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="<?= htmlspecialchars($equipment['quantity'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Unit</label>
                        <select name="unit" class="form-select">
                            <option value="">-- Select Unit --</option>
                            <?php
                            $units = ["amp","amp hours","cubic Feet","cubic Meters","GPM","GPH","liters","HP","KW","watts","inches","feet-inches","meters","persons","each","gallons","PSI","volts","amps"];
                            foreach ($units as $unit) {
                                $selected = (($equipment['unit'] ?? '') === $unit) ? 'selected' : '';
                                echo "<option value=\"$unit\" $selected>$unit</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Onboard Requirement</label>
                        <select name="onBoardNotRequired" class="form-select">
                            <option value="">-- Select --</option>
                            <option value="0" <?= (($equipment['onBoardNotRequired'] ?? null) == 0) ? 'selected' : '' ?>>Yes</option>
                            <option value="1" <?= (($equipment['onBoardNotRequired'] ?? null) == 1) ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($equipment['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div id="hour_tracking_section" class="card mt-4 d-none">
                        <div class="card-header">
                            <strong>Hour Tracking</strong>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-secondary py-2">
                                Phase 1 hour tracking is available only for propulsion engines and generators.
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label>Hour Tracked</label>
                                    <select name="hour_tracked" id="hour_tracked" class="form-select">
                                        <option value="0" <?= empty($hourMeter) ? 'selected' : '' ?>>No</option>
                                        <option value="1" <?= !empty($hourMeter) ? 'selected' : '' ?>>Yes</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Display Order</label>
                                    <input type="number" min="0" step="1" name="hour_display_order" id="hour_display_order" class="form-control" value="<?= htmlspecialchars((string)($hourMeter['display_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label>Active Status</label>
                                    <select name="hour_meter_active" id="hour_meter_active" class="form-select">
                                        <option value="1" <?= ((int)($hourMeter['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Active</option>
                                        <option value="0" <?= ((int)($hourMeter['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label>Installed Meter Baseline</label>
                                    <input type="number" min="0" step="0.1" name="hour_baseline_hours" id="hour_baseline_hours" class="form-control" value="<?= htmlspecialchars((string)($hourMeter['baseline_hours'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label>Upload Additional Files (Optional)</label>
                        <input type="file" name="equipment_files[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf">
                        <?php if (!empty($equipment['photo_path'])): ?>
                            <div class="mt-2">
                                <div class="small text-muted mb-1">Current Primary Photo</div>
                                <img src="<?= htmlspecialchars($equipment['photo_path']) ?>" style="max-height: 150px;" alt="Current Photo">
                            </div>
                        <?php endif; ?>
                        <div class="form-text">
                            Upload one or more files. If you upload a new image, the first image will become the primary photo.
                        </div>
                    </div>

                    <div id="fire_extinguisher_section" class="card mt-4 d-none">
                        <div class="card-header">
                            <strong>🔥 Fire Extinguisher Details</strong>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-secondary py-2">
                                Edit fire extinguisher-specific details below.
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label>Rule Profile</label>
                                    <select name="fire_rule_profile_id" class="form-select">
                                        <option value="">-- Select Rule Profile --</option>
                                        <?php
                                        $profiles = $pdo->query("SELECT rule_profile_id, profile_name FROM fire_extinguisher_rule_profiles WHERE active = 1 ORDER BY profile_name");
                                        while ($p = $profiles->fetch(PDO::FETCH_ASSOC)) {
                                            $selected = ($fire && (int)$fire['rule_profile_id'] === (int)$p['rule_profile_id']) ? 'selected' : '';
                                            echo "<option value='{$p['rule_profile_id']}' $selected>" . htmlspecialchars($p['profile_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label>Agent Type</label>
                                    <select name="fire_agent_type" class="form-select">
                                        <option value="">-- Select Agent Type --</option>
                                        <?php
                                        $agentOptions = ['Dry Chemical - ABC','Dry Chemical - BC','Dry Chemical - Purple K','Carbon Dioxide (CO2)','Water','Water Mist','Foam / AFFF','Wet Chemical','Dry Powder - Class D','Clean Agent','Halotron','FK-5-1-12','FM-200','Other'];
                                        foreach ($agentOptions as $opt) {
                                            $selected = (($fire['agent_type'] ?? '') === $opt) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($opt, ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label>Extinguisher Class</label>
                                    <select name="fire_extinguisher_class" class="form-select">
                                        <option value="">-- Select Class --</option>
                                        <?php
                                        $classOptions = ['A', 'B:C', 'A:B:C', 'D', 'K'];
                                        foreach ($classOptions as $classOpt) {
                                            $selected = (($fire['extinguisher_class'] ?? '') === $classOpt) ? 'selected' : '';
                                            echo "<option value=\"$classOpt\" $selected>$classOpt</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Capacity Value</label>
                                    <input type="number" step="0.01" name="fire_capacity_value" class="form-control"
                                           value="<?= htmlspecialchars($fire['capacity_value'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label>Capacity Unit</label>
                                    <select name="fire_capacity_unit" class="form-select">
                                        <option value="">-- Select Unit --</option>
                                        <?php
                                        $capUnits = ['lb','kg','gal','L','cuft'];
                                        foreach ($capUnits as $u) {
                                            $selected = (($fire['capacity_unit'] ?? '') === $u) ? 'selected' : '';
                                            echo "<option value=\"$u\" $selected>$u</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label>UL Rating</label>
                                    <select name="fire_ul_rating" class="form-select">
                                        <option value="">-- Select UL Rating --</option>
                                        <?php
                                        $ulOptions = ['2-B:C','5-B:C','10-B:C','20-B:C','1-A:2-B:C','2-A:10-B:C','3-A:40-B:C','4-A:60-B:C','10-A:80-B:C'];
                                        foreach ($ulOptions as $ulOpt) {
                                            $selected = (($fire['ul_rating'] ?? '') === $ulOpt) ? 'selected' : '';
                                            echo "<option value=\"$ulOpt\" $selected>$ulOpt</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Cylinder Material</label>
                                    <select name="fire_cylinder_material" class="form-select">
                                        <option value="">-- Select Material --</option>
                                        <?php
                                        $matOptions = ['Steel','Aluminum','Composite'];
                                        foreach ($matOptions as $matOpt) {
                                            $selected = (($fire['cylinder_material'] ?? '') === $matOpt) ? 'selected' : '';
                                            echo "<option value=\"$matOpt\" $selected>$matOpt</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Manufacture Date</label>
                                    <input type="date" name="fire_manufacture_date" class="form-control"
                                           value="<?= htmlspecialchars($fire['manufacture_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label>Last Service Vendor</label>
                                    <input type="text" name="fire_last_service_vendor" class="form-control"
                                           value="<?= htmlspecialchars($fire['last_service_vendor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label>Service Tag Number</label>
                                    <input type="text" name="fire_service_tag_number" class="form-control"
                                           value="<?= htmlspecialchars($fire['service_tag_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label>Fire Equipment Remarks</label>
                                <textarea name="fire_remarks" class="form-control" rows="2"><?= htmlspecialchars($fire['remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <?php if ($fire): ?>
                                <hr>
                                <div class="row g-3">
                                    <div class="col-md-3"><strong>Last Monthly Inspection</strong><br><?= htmlspecialchars($fire['last_monthly_inspection_date'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Next Monthly Due</strong><br><?= htmlspecialchars($fire['next_monthly_due'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Last Annual Service</strong><br><?= htmlspecialchars($fire['last_annual_service_date'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Next Annual Due</strong><br><?= htmlspecialchars($fire['next_annual_due'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-3"><strong>Last Internal Exam</strong><br><?= htmlspecialchars($fire['last_internal_exam_date'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Next Internal Due</strong><br><?= htmlspecialchars($fire['next_internal_exam_due'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Last Hydro Test</strong><br><?= htmlspecialchars($fire['last_hydro_test_date'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="col-md-3"><strong>Next Hydro Due</strong><br><?= htmlspecialchars($fire['next_hydro_due'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="vessel_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function updateEquipmentName() {
    const typeText = $('#type option:selected').text();
    const subtypeText = $('#subtype option:selected').text();
    const locationText = $('input[name="equipmentLocation"]').val();

    let parts = [];
    if (typeText && typeText !== '-- Select Type --') parts.push(typeText);

    const subtypeIsMeaningful =
        subtypeText &&
        subtypeText !== '-- Select Subtype --' &&
        subtypeText.trim() !== '';

    const isExtinguisherType = (typeText || '').toLowerCase().includes('extinguisher');

    if (subtypeIsMeaningful && !isExtinguisherType) {
        parts.push(subtypeText);
    }

    if (locationText) parts.push(locationText);

    const fullName = parts.join(' - ');
    $('#equipment_name').val(fullName);
    $('#equipment_name_preview').val(fullName);
}

function toggleFireExtinguisherSection() {
    const categoryId = $('#category').val();
    const typeText = ($('#type option:selected').text() || '').toLowerCase();
    const equipmentName = ($('#equipment_name_preview').val() || '').toLowerCase();

    const isFireCategory = (String(categoryId) === '3');
    const looksLikeFireExt =
        typeText.includes('extinguisher') ||
        typeText.includes('suppression') ||
        equipmentName.includes('portable extinguisher') ||
        equipmentName.includes('fixed extinguisher');

    if (isFireCategory && looksLikeFireExt) {
        $('#fire_extinguisher_section').removeClass('d-none');
    } else {
        $('#fire_extinguisher_section').addClass('d-none');
    }
}

function currentTypeId() {
    return parseInt($('#type').val() || '0', 10);
}

function currentSubtypeId() {
    return parseInt($('#subtype').val() || '0', 10);
}

function isHourTrackingEligible() {
    const typeId = currentTypeId();
    const subtypeId = currentSubtypeId();
    return typeId === 20 || (typeId === 21 && subtypeId === 48);
}

function toggleHourTrackingSection() {
    const eligible = isHourTrackingEligible();
    if (eligible) {
        $('#hour_tracking_section').removeClass('d-none');
    } else {
        $('#hour_tracking_section').addClass('d-none');
        $('#hour_tracked').val('0');
        $('#hour_display_order').val('0');
        $('#hour_meter_active').val('1');
        $('#hour_baseline_hours').val('0.0');
    }
}

$(document).ready(function() {
    const initialTypeId = <?= json_encode($equipment['equipment_type_id']) ?>;
    const initialSubtypeId = <?= json_encode($equipment['equipment_subtype_id']) ?>;

    $('#category').change(function() {
        $.post('get_types.php', { category_id: $(this).val() }, function(data) {
            $('#type').html(data);
            $('#subtype').html('<option value="">-- Select Subtype --</option>');
            $('#type').val(initialTypeId).trigger('change');
            updateEquipmentName();
            toggleFireExtinguisherSection();
            toggleHourTrackingSection();
        });
    }).trigger('change');

    $('#type').change(function() {
        $.post('get_subtypes.php', { type_id: $(this).val() }, function(data) {
            $('#subtype').html(data);
            $('#subtype').val(initialSubtypeId);
            updateEquipmentName();
            toggleFireExtinguisherSection();
            toggleHourTrackingSection();
        });
    });

    $('#subtype, input[name="equipmentLocation"]').on('input change', function() {
        updateEquipmentName();
        toggleFireExtinguisherSection();
        toggleHourTrackingSection();
    });

    toggleFireExtinguisherSection();
    toggleHourTrackingSection();
});
</script>
</body>
</html>
