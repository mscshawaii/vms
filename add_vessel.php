<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

$role_id = $_SESSION['role_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;

// If MSCS Hawaii (ID = 1), show a dropdown to pick a company
if ($company_id == 1) {
    $stmt = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $companies = [];
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add New Vessel</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .vessels-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .form-section-card {
            border: 0;
            border-radius: 1rem;
        }
        .vessels-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
<?php
$title = 'Add New Vessel';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="vessels-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Add New Vessel</h1>
                            <p class="vessels-meta">
                                Create a vessel record and enter its core identification, classification, and inspection data.
                            </p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <form id="addVesselForm" action="insert_vessel.php" method="post">

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Company / Basic Vessel Info</div>

                        <div class="row g-3">
                            <?php if ($company_id == 1): ?>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Assign to Company</label>
                                    <select name="company_id" class="form-select" required>
                                        <option value="">-- Select Company --</option>
                                        <?php foreach ($companies as $co): ?>
                                            <option value="<?= (int)$co['owner_id'] ?>"><?= h($co['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="company_id" value="<?= (int)$company_id ?>">
                            <?php endif; ?>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Name</label>
                                <input type="text" name="vesselName" class="form-control" required>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Official Number / Registration</label>
                                <input type="text" name="vesselON" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Hailing Port</label>
                                <input type="text" name="hailingPort" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Call Sign</label>
                                <input type="text" name="callSign" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">MMSI</label>
                                <input type="number" name="mmsi" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">EPIRB Hex ID</label>
                                <input type="text" name="epirbHexId" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">HIN</label>
                                <input type="text" name="hin" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Classification / Specs</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Class</label>
                                <input list="vesselClass_options" name="vesselClass" class="form-control">
                                <datalist id="vesselClass_options">
                                    <option value="Passenger Vessel">
                                    <option value="Towing Vessel">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Class Type</label>
                                <input list="classType_options" name="classType" class="form-control">
                                <datalist id="classType_options">
                                    <option value="Excursion">
                                    <option value="Parasail">
                                    <option value="Recreational Dive">
                                    <option value="Fishing Charter">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Service</label>
                                <input list="vesselService_options" name="vesselService" class="form-control">
                                <datalist id="vesselService_options">
                                    <option value="Inspected Passenger">
                                    <option value="Uninspected Passenger">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Gross Tons</label>
                                <input type="number" step="0.1" name="grossTons" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Net Tons</label>
                                <input type="number" step="0.1" name="netTons" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Lightship Tons</label>
                                <input type="number" step="0.1" name="lightshipTons" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Length</label>
                                <input type="number" step="0.1" name="length" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">LBP</label>
                                <input type="number" step="0.1" name="lbp" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Hull / Machinery</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Propulsion Type</label>
                                <input list="propulsionType_options" name="propulsionType" class="form-control">
                                <datalist id="propulsionType_options">
                                    <option value="Diesel - Inboard">
                                    <option value="Gasoline - Outboard">
                                    <option value="Gasoline - Inboard">
                                    <option value="Diesel - Outboard">
                                    <option value="Electric">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Auxiliary Sail</label>
                                <select name="auxSail" class="form-select">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Horsepower</label>
                                <input type="number" name="horsepower" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Hull Material</label>
                                <input list="hullMaterial_options" name="hullMaterial" class="form-control">
                                <datalist id="hullMaterial_options">
                                    <option value="Aluminum">
                                    <option value="FRP - Fire Retardant">
                                    <option value="FRP - Non Fire-Retardant">
                                    <option value="Wood - Sheathed">
                                    <option value="Wood - Plank on Frame">
                                    <option value="Steel">
                                </datalist>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Inspection / Capacity</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Subchapter</label>
                                <input list="inspSubChapter_options" name="inspSubChapter" class="form-control">
                                <datalist id="inspSubChapter_options">
                                    <option value="T">
                                    <option value="K">
                                    <option value="L">
                                    <option value="R">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">SIP</label>
                                <select name="sip" class="form-select">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Keel Laid Date</label>
                                <input type="date" name="keelLaidDate" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Delivery Date</label>
                                <input type="date" name="deliveryDate" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Master</label>
                                <input type="number" name="master" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Deckhands</label>
                                <input type="number" name="deckhands" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Others in Crew</label>
                                <input type="number" name="othersInCrew" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Persons in Addition to Crew</label>
                                <input type="number" name="personInAddition" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Passengers</label>
                                <input type="number" name="passengers" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Route / Waters / Structure</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Route</label>
                                <input list="route_options" name="route" class="form-control">
                                <datalist id="route_options">
                                    <option value="Rivers">
                                    <option value="Lakes, Bays, and Sounds">
                                    <option value="Limited Coastwise">
                                    <option value="Coastwise">
                                    <option value="Oceans">
                                </datalist>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Waters</label>
                                <input list="waters_options" name="waters" class="form-control">
                                <datalist id="waters_options">
                                    <option value="Protected">
                                    <option value="Partially Protected">
                                    <option value="Exposed">
                                </datalist>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Inspection History</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Last Inspection</label>
                                <input type="date" name="lastInspection" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Next Scheduled Inspection</label>
                                <input type="date" name="nextScheduledInspection" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Last Dry Dock</label>
                                <input type="date" name="lastDrydock" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Next Dry Dock</label>
                                <input type="date" name="nextDrydock" class="form-control">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Next Un-step</label>
                                <input type="date" name="nextUnstep" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Vessel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="addVesselForm" class="btn btn-primary">
                Save Vessel
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>
</div>
</body>
</html>
