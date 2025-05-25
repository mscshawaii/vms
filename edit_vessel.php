<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db_connect.php';

$vessel_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Edit Vessel Details</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h2 class="mb-4">Edit Vessel Details</h2>
  <form method="post" action="update_vessel.php">
    <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

    <div class="col-md-4">
      <label class="form-label">Company</label>
      <select name="company_id" class="form-select">
        <option value="">-- Select Company --</option>
        <?php
        $companies = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($companies as $company):
          $selected = ($company['owner_id'] == $vessel['company_id']) ? 'selected' : '';
        ?>
          <option value="<?= $company['owner_id'] ?>" <?= $selected ?>>
            <?= htmlspecialchars($company['company_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row g-3 mt-3">
      <?php
      function selectField($label, $name, $options, $selectedValue) {
          echo "<div class='col-md-4'>
                  <label class='form-label'>{$label}</label>
                  <select name='{$name}' class='form-select'>
                    <option value=''>-- Select --</option>";
          foreach ($options as $option) {
              $selected = $selectedValue === $option ? 'selected' : '';
              echo "<option value='{$option}' {$selected}>{$option}</option>";
          }
          echo "</select></div>";
      }

      selectField('Class', 'vesselClass', ['Passenger Vessel', 'Towing Vessel', 'Cargo Vessel'], $vessel['vesselClass']);
      selectField('Class Type', 'classType', ['Excursion', 'Recreational Dive', 'Parasail', 'Fishing Charter'], $vessel['classType']);
      selectField('Service', 'vesselService', ['Inspected Passenger', 'Uninspected Passenger'], $vessel['vesselService']);
      selectField('Subchapter', 'inspSubChapter', ['T', 'K', 'L', 'I', 'M', 'R', 'U'], $vessel['inspSubChapter']);
      selectField('SIP', 'sip', ['1' => 'Yes', '0' => 'No'], (string)$vessel['sip']);
      ?>

      <div class="col-md-4">
        <label class="form-label">Gross Tons</label>
        <input type="number" step="0.1" name="grossTons" value="<?= htmlspecialchars($vessel['grossTons']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Net Tons</label>
        <input type="number" step="0.1" name="netTons" value="<?= htmlspecialchars($vessel['netTons']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Lightship Tons</label>
        <input type="number" step="0.1" name="lightshipTons" value="<?= htmlspecialchars($vessel['lightshipTons']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Length Overall</label>
        <input type="number" step="0.1" name="length" value="<?= htmlspecialchars($vessel['length']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Length Between Perpendiculars</label>
        <input type="number" step="0.1" name="lbp" value="<?= htmlspecialchars($vessel['lbp']) ?>" class="form-control">
      </div>
      <?php
      selectField('Hull Material', 'hullMaterial', [
        'FRP - Fire Retardant', 'FRP - Non Fire-Retardant', 'Aluminum', 'Steel', 'Wood - Sheathed', 'Wood - Plank on Frame'
      ], $vessel['hullMaterial']);

      selectField('Auxiliary Sail', 'auxSail', ['1' => 'Yes', '0' => 'No'], (string)$vessel['auxSail']);
      ?>
      <div class="col-md-4">
        <label class="form-label">Horsepower</label>
        <input type="number" name="horsepower" value="<?= htmlspecialchars($vessel['horsepower']) ?>" class="form-control">
      </div>
      <?php
      selectField('Propulsion Type', 'propulsionType', [
        'Diesel - Inboard', 'Gasoline - Outboard', 'Gasoline - Inboard', 'Diesel - Outboard', 'Electric'
      ], $vessel['propulsionType']);

      selectField('Route', 'route', ['Oceans', 'Coastwise', 'Limited Coastwise', 'Lakes, Bays, and Sounds', 'Rivers'], $vessel['route']);
      selectField('Waters', 'waters', ['Exposed', 'Partially Protected', 'Protected'], $vessel['waters']);
      ?>
      <div class="col-md-4">
        <label class="form-label">Keel Laid Date</label>
        <input type="date" name="keelLaidDate" value="<?= htmlspecialchars($vessel['keelLaidDate']) ?>" class="form-control">
      </div>
      <div class="col-md-4">
        <label class="form-label">Delivery Date</label>
        <input type="date" name="deliveryDate" value="<?= htmlspecialchars($vessel['deliveryDate']) ?>" class="form-control">
      </div>
    </div>

    <div class="text-end mt-4">
      <button type="submit" class="btn btn-success">Save Vessel Details</button>
    </div>
  </form>
</div>
</body>
</html>
