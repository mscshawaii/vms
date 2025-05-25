<?php require 'db_connect.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>🧰 Equipment List</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        img.thumbnail { height: 60px; }
        .sticky-bottom {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }
    </style>
</head>
<body class="p-4">
<div class="container">
    <?php if (isset($_GET['vessel_id'])): ?>
        <a href="vessel_dashboard.php?id=<?= intval($_GET['vessel_id']) ?>" class="btn btn-secondary mb-3">← Back to Vessel Dashboard</a>
    <?php endif; ?>

<div class="container">
    <h2>🧰 Equipment Inventory</h2>

    <!-- Vessel Filter -->
    <form method="get" class="mb-3">
        <label for="vesselFilter">Filter by Vessel:</label>
        <select name="vessel_id" id="vesselFilter" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
            <option value="">All Vessels</option>
            <?php
            $selectedVessel = $_GET['vessel_id'] ?? '';
            $vessels = $mysqli->query("SELECT vessel_id, vesselName FROM vessels ORDER BY vesselName");
            while ($v = $vessels->fetch_assoc()) {
                $selected = ($v['vessel_id'] == $selectedVessel) ? 'selected' : '';
                echo "<option value='{$v['vessel_id']}' $selected>{$v['vesselName']}</option>";
            }
            ?>
        </select>
    </form>

    <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search equipment...">

    <table class="table table-bordered table-striped" id="equipmentTable">
        <thead class="table-dark">
            <tr>
                <th>Name</th>
                <th>Location</th>
                <th>Manufacturer</th>
                <th>Model</th>
                <th>Serial</th>
                <th>Installed</th>
                <th>Expires</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Category</th>
                <th>Type</th>
                <th>Subtype</th>
                <th>Onboard Req</th>
                <th>Notes</th>
                <th>Photo</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $vessel_filter = isset($_GET['vessel_id']) ? intval($_GET['vessel_id']) : 0;

        $sql = "
            SELECT e.*, cat.name AS category_name, typ.name AS type_name, sub.name AS subtype_name
            FROM equipment e
            LEFT JOIN equipment_category cat ON e.category_id = cat.id
            LEFT JOIN equipment_type typ ON e.type_id = typ.id
            LEFT JOIN equipment_subtype sub ON e.subtype_id = sub.id
        ";
        if ($vessel_filter) {
            $sql .= " WHERE e.vessel_id = $vessel_filter";
        }
        $sql .= " ORDER BY e.expDate ASC";

        $result = $mysqli->query($sql);
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+30 days'));

        while ($row = $result->fetch_assoc()) {
            $row_class = '';
            if (!empty($row['expDate']) && $row['expDate'] !== '0000-00-00') {
                if ($row['expDate'] < $today) {
                    $row_class = 'table-danger';
                } elseif ($row['expDate'] <= $soon) {
                    $row_class = 'table-warning';
                }
            }

            echo "<tr class='$row_class'>";
            echo "<td>{$row['equipmentName']}</td>";
            echo "<td>{$row['equipmentLocation']}</td>";
            echo "<td>{$row['manufacturer']}</td>";
            echo "<td>{$row['modelNumber']}</td>";
            echo "<td>{$row['serialNumber']}</td>";
            echo "<td>{$row['installDate']}</td>";
            echo "<td>{$row['expDate']}</td>";
            echo "<td>{$row['quantity']}</td>";
            echo "<td>{$row['unit']}</td>";
            echo "<td>{$row['category_name']}</td>";
            echo "<td>{$row['type_name']}</td>";
            echo "<td>{$row['subtype_name']}</td>";
            echo "<td>" . ($row['onBoardNotRequired'] ? 'No' : 'Yes') . "</td>";
            echo "<td>{$row['notes']}</td>";
            echo "<td>";
	    if (!empty($row['photo_path']) && file_exists($row['photo_path'])) {
   		 echo "<img src='" . htmlspecialchars($row['photo_path']) . "' class='thumbnail' alt='Equipment Photo'>";
	    } else {
   		 echo "�";
	    }
	    echo "</td>";
            echo "<td>
                <a href='edit_equipment.php?id={$row['eid']}' class='btn btn-sm btn-primary'>Edit</a>
                <a href='delete_equipment.php?id={$row['eid']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Delete this item?')\">Delete</a>
              </td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let search = this.value.toLowerCase();
    document.querySelectorAll("#equipmentTable tbody tr").forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(search) ? "" : "none";
    });
});
</script>
<?php if (isset($_GET['vessel_id'])): ?>
    <a href="vessel_dashboard.php?id=<?= intval($_GET['vessel_id']) ?>" class="btn btn-secondary sticky-bottom">
        ← Back to Dashboard
    </a>
<?php endif; ?>

</body>
</html>
