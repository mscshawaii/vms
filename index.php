<?php
// ⚠️ LEGACY FILE
// Redirecting to modern login

header("Location: login.php");
exit;

$vessel_id = $_GET['vessel_id'] ?? null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Equipment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="p-4">
<div class="container">
    <h2>➕ Add Equipment</h2>

    <form method="post" action="submit_equipment.php" enctype="multipart/form-data">
        <?php if ($vessel_id): ?>
            <input type="hidden" name="vessel_id" value="<?= htmlspecialchars($vessel_id) ?>">
        <?php else: ?>
        <div class="mb-3">
            <label class="form-label">Assign to Vessel</label>
            <select name="vessel_id" class="form-select" required>
                <option value="">-- Select Vessel --</option>
                <?php
                $vessels = $pdo->query("SELECT vessel_id, vesselName FROM vessels ORDER BY vesselName");
                while ($v = $vessels->fetch(PDO::FETCH_ASSOC)) {
                    echo "<option value='{$v['vessel_id']}'>" . htmlspecialchars($v['vesselName']) . "</option>";
                }
                ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label>Category</label>
                <select id="category" name="category_id" class="form-select" required>
                    <option value="">-- Select Category --</option>
                    <?php
                    $cats = $pdo->query("SELECT id, name FROM equipment_category ORDER BY name");
                    while ($row = $cats->fetch(PDO::FETCH_ASSOC)) {
                        echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name']) . "</option>";
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

        <input type="hidden" name="equipment_name" id="equipment_name">

        <div class="mb-3">
            <label>Auto-Generated Equipment Name</label>
            <input type="text" id="equipment_name_preview" class="form-control bg-light" readonly>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label>Location</label>
                <input type="text" name="equipmentLocation" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label>Manufacturer</label>
                <input type="text" name="manufacturer" class="form-control">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label>Model</label>
                <input type="text" name="modelNumber" class="form-control">
            </div>
            <div class="col-md-6">
                <label>Serial Number</label>
                <input type="text" name="serialNumber" class="form-control">
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label>Install Date</label>
                <input type="date" name="installDate" class="form-control">
            </div>
            <div class="col-md-4">
                <label>Expiration Date</label>
                <input type="date" name="expDate" class="form-control">
            </div>
            <div class="col-md-4">
                <label>Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1">
            </div>
        </div>

        <div class="mb-3">
            <label>Unit</label>
            <select name="unit" class="form-select">
                <option value="">-- Select Unit --</option>
                <option value="amp">amp</option>
                <option value="amp hours">amp hours</option>
                <option value="cubic Feet">cubic feet</option>
                <option value="cubic Meters">cubic meters</option>
                <option value="GPM">GPM</option>
                <option value="GPH">GPH</option>
                <option value="liters">liters</option>
                <option value="HP">HP</option>
                <option value="KW">KW</option>
                <option value="watts">watts</option>
                <option value="inches">inches</option>
                <option value="feet-inches">feet-inches</option>
                <option value="meters">meters</option>
                <option value="persons">persons</option>
                <option value="each">Each</option>
                <option value="gallons">Gallons</option>
                <option value="PSI">PSI</option>
                <option value="volts">Volts</option>
                <option value="amps">Amps</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Onboard Requirement</label>
            <select name="onBoardNotRequired" class="form-select">
                <option value="">-- Select --</option>
                <option value="0">Yes</option>
                <option value="1">No</option>
            </select>
        </div>

        <div class="mb-3">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="mb-3">
            <label>Upload Photo</label>
            <input type="file" name="photo" class="form-control">
        </div>

        <div id="fire_extinguisher_section" class="card mt-4 d-none">
            <div class="card-header">
                <strong>🔥 Fire Extinguisher Details</strong>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary py-2">
                    Complete these fields for portable or fixed fire extinguishers. Extinguisher-specific details belong here instead of subtype.
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label>Rule Profile</label>
                        <select name="fire_rule_profile_id" class="form-select">
                            <option value="">-- Select Rule Profile --</option>
                            <?php
                            $profiles = $pdo->query("SELECT rule_profile_id, profile_name FROM fire_extinguisher_rule_profiles WHERE active = 1 ORDER BY profile_name");
                            while ($p = $profiles->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$p['rule_profile_id']}'>" . htmlspecialchars($p['profile_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Agent Type</label>
                        <select name="fire_agent_type" class="form-select">
                            <option value="">-- Select Agent Type --</option>
                            <?php
                            $agentOptions = [
                                'Dry Chemical - ABC',
                                'Dry Chemical - BC',
                                'Dry Chemical - Purple K',
                                'Carbon Dioxide (CO2)',
                                'Water',
                                'Water Mist',
                                'Foam / AFFF',
                                'Wet Chemical',
                                'Dry Powder - Class D',
                                'Clean Agent',
                                'Halotron',
                                'FK-5-1-12',
                                'FM-200',
                                'Other'
                            ];
                            $currentAgent = '';
                            foreach ($agentOptions as $opt) {
                                $selected = ($currentAgent === $opt) ? 'selected' : '';
                                echo "<option value=\"" . htmlspecialchars($opt) . "\" $selected>" . htmlspecialchars($opt) . "</option>";
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
                            <option value="A">A</option>
                            <option value="B:C">B:C</option>
                            <option value="A:B:C">A:B:C</option>
                            <option value="D">D</option>
                            <option value="K">K</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Capacity Value</label>
                        <input type="number" step="0.01" name="fire_capacity_value" class="form-control" placeholder="10">
                    </div>
                    <div class="col-md-4">
                        <label>Capacity Unit</label>
                        <select name="fire_capacity_unit" class="form-select">
                            <option value="">-- Select Unit --</option>
                            <option value="lb">lb</option>
                            <option value="kg">kg</option>
                            <option value="gal">gal</option>
                            <option value="L">L</option>
                            <option value="cuft">cu ft</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label>UL Rating</label>
                        <select name="fire_ul_rating" class="form-select">
                            <option value="">-- Select UL Rating --</option>
                            <option value="2-B:C">2-B:C</option>
                            <option value="5-B:C">5-B:C</option>
                            <option value="10-B:C">10-B:C</option>
                            <option value="20-B:C">20-B:C</option>
                            <option value="1-A:2-B:C">1-A:2-B:C</option>
                            <option value="2-A:10-B:C">2-A:10-B:C</option>
                            <option value="3-A:40-B:C">3-A:40-B:C</option>
                            <option value="4-A:60-B:C">4-A:60-B:C</option>
                            <option value="10-A:80-B:C">10-A:80-B:C</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Cylinder Material</label>
                        <select name="fire_cylinder_material" class="form-select">
                            <option value="">-- Select Material --</option>
                            <option value="Steel">Steel</option>
                            <option value="Aluminum">Aluminum</option>
                            <option value="Composite">Composite</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Manufacture Date</label>
                        <input type="date" name="fire_manufacture_date" class="form-control">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label>Last Service Vendor</label>
                        <input type="text" name="fire_last_service_vendor" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label>Service Tag Number</label>
                        <input type="text" name="fire_service_tag_number" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Fire Equipment Remarks</label>
                    <textarea name="fire_remarks" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">💾 Save Equipment</button>
        <a href="dashboard.php" class="btn btn-secondary">← Back</a>
    </form>
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

$(document).ready(function() {
    updateEquipmentName();
    toggleFireExtinguisherSection();

    $('#category').change(function() {
        $.post('get_types.php', { category_id: $(this).val() }, function(data) {
            $('#type').html(data);
            $('#subtype').html('<option value="">-- Select Subtype --</option>');
            updateEquipmentName();
            toggleFireExtinguisherSection();
        });
    });

    $('#type').change(function() {
        $.post('get_subtypes.php', { type_id: $(this).val() }, function(data) {
            $('#subtype').html(data);
            updateEquipmentName();
            toggleFireExtinguisherSection();
        });
    });

    $('#subtype, input[name="equipmentLocation"]').on('input change', function() {
        updateEquipmentName();
        toggleFireExtinguisherSection();
    });
});
</script>
</body>
</html>