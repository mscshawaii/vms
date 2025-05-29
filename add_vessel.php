<?php
require 'session_check.php';
require 'db_connect.php';

$role_id = $_SESSION['role_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;

// If MSCS Hawaii (ID = 1), show a dropdown to pick a company
if ($company_id == 1) {
    $stmt = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name");
    $companies = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add New Vessel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">? Add New Vessel</h2>
    <form action="insert_vessel.php" method="post" class="row g-3">
        <?php if ($company_id == 1): ?>
            <div class="col-md-6">
                <label class="form-label">Assign to Company</label>
                <select name="company_id" class="form-select" required>
                    <option value="">-- Select Company --</option>
                    <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['owner_id'] ?>"><?= htmlspecialchars($co['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <input type="hidden" name="company_id" value="<?= $company_id ?>">
        <?php endif; ?>

        <div class="col-12">
            <h5 class="mt-4">??? Basic Vessel Info</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input type="text" name="vesselName" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">ON</label>
            <input type="text" name="vesselON" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Hailing Port</label>
            <input type="text" name="hailingPort" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Call Sign</label>
            <input type="text" name="callSign" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">MMSI</label>
            <input type="number" name="mmsi" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">EPIRB Hex ID</label>
            <input type="text" name="epirbHexId" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">HIN</label>
            <input type="text" name="hin" class="form-control">
        </div>

        <div class="col-12">
            <h5 class="mt-4">?? Classification & Specs</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Class</label>
            <input list="vesselClass_options" name="vesselClass" class="form-control">
            <datalist id="vesselClass_options">
                <option value="Passenger Vessel"><option value="Towing Vessel">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Class Type</label>
            <input list="classType_options" name="classType" class="form-control">
            <datalist id="classType_options">
                <option value="Excursion"><option value="Parasail"><option value="Recreational Dive">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Service</label>
            <input list="vesselService_options" name="vesselService" class="form-control">
            <datalist id="vesselService_options">
                <option value="Inspected Passenger"><option value="Uninspected Passenger">
            </datalist>
        </div>

        <div class="col-md-4">
            <label class="form-label">Gross Tons</label>
            <input type="number" step="0.1" name="grossTons" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Net Tons</label>
            <input type="number" step="0.1" name="netTons" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Lightship Tons</label>
            <input type="number" step="0.1" name="lightshipTons" class="form-control">
        </div>

        <div class="col-md-4">
            <label class="form-label">Length</label>
            <input type="number" step="0.1" name="length" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">LBP</label>
            <input type="number" step="0.1" name="lbp" class="form-control">
        </div>

        <div class="col-12">
            <h5 class="mt-4">?? Hull & Machinery</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Propulsion Type</label>
            <input list="propulsionType_options" name="propulsionType" class="form-control">
            <datalist id="propulsionType_options">
                <option value="Diesel - Inboard"><option value="Gasoline - Outboard">
                <option value="Gasoline - Inboard"><option value="Diesel - Outboard"><option value="Electric">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Auxiliary Sail</label>
            <select name="auxSail" class="form-select">
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Horsepower</label>
            <input type="number" name="horsepower" class="form-control">
        </div>

        <div class="col-12">
            <h5 class="mt-4">?? Inspection & Capacity</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Subchapter</label>
            <input list="inspSubChapter_options" name="inspSubChapter" class="form-control">
            <datalist id="inspSubChapter_options">
                <option value="T"><option value="K"><option value="L"><option value="R">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">SIP</label>
            <select name="sip" class="form-select">
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Keel Laid Date</label>
            <input type="date" name="keelLaidDate" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Delivery Date</label>
            <input type="date" name="deliveryDate" class="form-control">
        </div>

        <div class="col-md-4">
            <label class="form-label">Master</label>
            <input type="number" name="master" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Deckhands</label>
            <input type="number" name="deckhands" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Others in Crew</label>
            <input type="number" name="othersInCrew" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Passengers</label>
            <input type="number" name="passengers" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Total POB</label>
            <input type="number" name="pob" class="form-control">
        </div>

        <div class="col-12">
            <h5 class="mt-4">?? Route, Waters & Structure</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Route</label>
            <input list="route_options" name="route" class="form-control">
            <datalist id="route_options">
                <option value="Rivers"><option value="Lakes, Bays, and Sounds"><option value="Limited Coastwise">
                <option value="Coastwise"><option value="Oceans">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Waters</label>
            <input list="waters_options" name="waters" class="form-control">
            <datalist id="waters_options">
                <option value="Protected"><option value="Partially Protected"><option value="Exposed">
            </datalist>
        </div>
        <div class="col-md-4">
            <label class="form-label">Hull Material</label>
            <input list="hullMaterial_options" name="hullMaterial" class="form-control">
            <datalist id="hullMaterial_options">
                <option value="Aluminum"><option value="FRP - Fire Retardant"><option value="FRP - Non Fire-Retardant">
                <option value="Wood - Sheathed"><option value="Wood - Plank on Frame"><option value="Steel">
            </datalist>
        </div>

        <div class="col-12">
            <h5 class="mt-4">?? Inspection History</h5>
        </div>

        <div class="col-md-4">
            <label class="form-label">Last Inspection</label>
            <input type="date" name="lastInspection" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Next Scheduled Inspection</label>
            <input type="date" name="nextScheduledInspection" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Last Dry Dock</label>
            <input type="date" name="lastDrydock" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Next Dry Dock</label>
            <input type="date" name="nextDrydock" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Next Un-step</label>
            <input type="date" name="nextUnstep" class="form-control">
        </div>

        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary">?? Save Vessel</button>
            <a href="dashboard.php" class="btn btn-secondary">? Cancel</a>
        </div>
    </form>
</div>
</body>
</html>
