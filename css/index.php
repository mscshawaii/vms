<?php require 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Equipment</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<h2>Add Equipment</h2>

<form method="post" action="submit_equipment.php" enctype="multipart/form-data">
    <!-- Dependent Dropdowns -->
    <label>Category:</label>
    <select id="category" name="category_id" required>
        <option value="">-- Select Category --</option>
        <?php
        $result = $mysqli->query("SELECT id, name FROM equipment_category");
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']}</option>";
        }
        ?>
    </select><br><br>

    <label>Type:</label>
    <select id="type" name="type_id" required>
        <option value="">-- Select Type --</option>
    </select><br><br>

    <label>Subtype:</label>
    <select id="subtype" name="subtype_id">
        <option value="">-- Select Subtype --</option>
    </select><br><br>

    <!-- Other Fields -->
    <label>Equipment Name:</label>
    <input type="text" name="equipment_name" required><br><br>

    <label>Location:</label>
    <input type="text" name="location"><br><br>

    <label>Manufacturer:</label>
    <input type="text" name="manufacturer"><br><br>

    <label>Model:</label>
    <input type="text" name="model"><br><br>

    <label>Serial Number:</label>
    <input type="text" name="serial_number"><br><br>

    <label>Install Date:</label>
    <input type="date" name="install_date"><br><br>

    <label>Expiration Date:</label>
    <input type="date" name="expiration_date"><br><br>

    <label>Quantity:</label>
    <input type="number" name="quantity" step="1" min="1"><br><br>

    <label>Unit:</label>
    <input type="text" name="unit"><br><br>

    <label>Notes:</label>
    <textarea name="notes"></textarea><br><br>

    <label>Onboard Requirement:</label>
    <select name="onboard_requirement">
        <option value="">-- Select --</option>
        <option value="Yes">Yes</option>
        <option value="No">No</option>
    </select><br><br>

    <label>Upload Photo:</label>
    <input type="file" name="photo"><br><br>

    <input type="submit" value="Save Equipment">
</form>

<script>
$(document).ready(function() {
    $('#category').change(function() {
        $.post('get_types.php', { category_id: $(this).val() }, function(data) {
            $('#type').html(data);
            $('#subtype').html('<option value="">-- Select Subtype --</option>');
        });
    });

    $('#type').change(function() {
        $.post('get_subtypes.php', { type_id: $(this).val() }, function(data) {
            $('#subtype').html(data);
        });
    });
});
</script>

</body>
</html>