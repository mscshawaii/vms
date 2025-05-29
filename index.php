<?php
require 'session_check.php';
require 'db_connect.php';

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
    if (subtypeText && subtypeText !== '-- Select Subtype --') parts.push(subtypeText);
    if (locationText) parts.push(locationText);

    const fullName = parts.join(' - ');

    $('#equipment_name').val(fullName); // hidden input
    $('#equipment_name_preview').val(fullName); // read-only preview
}

$(document).ready(function() {
    updateEquipmentName(); // trigger once on load

    $('#category').change(function() {
        $.post('get_types.php', { category_id: $(this).val() }, function(data) {
            $('#type').html(data);
            $('#subtype').html('<option value="">-- Select Subtype --</option>');
            updateEquipmentName();
        });
    });

$('#type').change(function() {
    $.post('get_subtypes.php', { type_id: $(this).val() }, function(data) {
        $('#subtype').html(data);
        updateEquipmentName();
    });
});


    $('#subtype, input[name="equipmentLocation"]').on('input change', updateEquipmentName);
});
</script>
</body>
</html>
