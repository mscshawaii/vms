<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'session_check.php';
require 'db_connect.php';

$vessel_id = (int)($_GET['id'] ?? $_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    die('Invalid vessel ID.');
}

$stmt = $pdo->prepare('SELECT * FROM vessels WHERE vessel_id = ?');
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die('Vessel not found.');
}

$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$session_role_id    = (int)($_SESSION['role_id'] ?? 0);
$is_mscs_admin      = ($session_company_id === 1 && $session_role_id === 1);

if (!$is_mscs_admin && (int)$vessel['company_id'] !== $session_company_id) {
    http_response_code(403);
    die('Access denied.');
}

$companies = [];
if ($is_mscs_admin) {
    $companies = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name")
        ->fetchAll(PDO::FETCH_ASSOC);
}

$contacts = $pdo->query("\n    SELECT contact_id, region_name, IFNULL(port_name,'') AS port_name, IFNULL(email_to,'') AS email_to\n    FROM uscg_contacts\n    WHERE active = 1\n    ORDER BY region_name, port_name\n")->fetchAll(PDO::FETCH_ASSOC);

$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

function h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function safeDateValue($v): string {
    return (!empty($v) && $v !== '0000-00-00') ? (string)$v : '';
}

function ocmiLabel(array $c): string {
    $label = trim((string)($c['region_name'] ?? ''));
    if (!empty($c['port_name'])) {
        $label .= ' – ' . trim((string)$c['port_name']);
    }
    if (!empty($c['email_to'])) {
        $label .= ' (' . trim((string)$c['email_to']) . ')';
    }
    return $label;
}

function selectField(string $label, string $name, array $options, $selectedValue, string $placeholder = '-- Select --'): void {
    $keys = array_keys($options);
    $isSequential = ($keys === range(0, count($options) - 1));

    echo "<div class='col-12 col-md-6 col-xl-4'>";
    echo "<label class='form-label'>" . h($label) . "</label>";
    echo "<select name='" . h($name) . "' class='form-select'>";
    echo "<option value=''>" . h($placeholder) . "</option>";

    foreach ($options as $key => $option) {
        $value = $isSequential ? (string)$option : (string)$key;
        $optionLabel = (string)$option;
        $selected = ((string)$value === (string)($selectedValue ?? '')) ? 'selected' : '';
        echo "<option value='" . h($value) . "' $selected>" . h($optionLabel) . "</option>";
    }

    echo '</select>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Vessel</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .vessels-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
        .page-header-card, .form-section-card { border: 0; border-radius: 1rem; }
        .vessels-meta { color: #6b7280; margin: 0; }
        .section-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
        .sticky-mobile-actions {
            position: sticky; bottom: 0; z-index: 1020; background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px); border-top: 1px solid #dee2e6;
        }
        .photo-preview-wrap img, .photo-preview-placeholder {
            width: 100%; height: 280px; object-fit: cover; border-radius: 1rem;
        }
        .photo-preview-placeholder {
            display: flex; align-items: center; justify-content: center;
            background: #f8fafc; border: 1px solid #dbe4ee; color: #6b7280;
        }
    </style>
</head>
<body>
<?php
$title = 'Edit Vessel';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
$company_id = (int)($vessel['company_id'] ?? $session_company_id ?? 0);
include __DIR__ . '/partials/top_nav.php';
?>

<div class="vessels-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Edit Vessel</h1>
                            <p class="vessels-meta">
                                <?= h($vessel['vesselName'] ?? '—') ?>
                                <?php if (!empty($vessel['vesselON'])): ?> · Official No. <?= h($vessel['vesselON']) ?><?php endif; ?>
                                <?php if (!empty($vessel['hailingPort'])): ?> · Hailing Port: <?= h($vessel['hailingPort']) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                        </div>
                    </div>
                </div>
            </div>

            <form id="editVesselForm" method="post" action="update_vessel.php" enctype="multipart/form-data">
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Identification / Photo / OCMI</div>
                        <div class="row g-3 align-items-start">
                            <div class="col-12 col-lg-4">
                                <div class="photo-preview-wrap mb-2">
                                    <?php if (!empty($vessel['photo_path'])): ?>
                                        <img id="photoPreview" src="<?= h($vessel['photo_path']) ?>" alt="Vessel Photo" class="shadow-sm border">
                                    <?php else: ?>
                                        <div id="photoPreviewPlaceholder" class="photo-preview-placeholder shadow-sm">No Photo Available</div>
                                        <img id="photoPreview" src="" alt="Vessel Photo" class="shadow-sm border d-none">
                                    <?php endif; ?>
                                </div>
                                <label class="form-label">Vessel Photo</label>
                                <input type="file" name="photo" id="photoInput" class="form-control" accept="image/*">
                                <?php if (!empty($vessel['photo_path'])): ?>
                                    <div class="form-text">Current path: <?= h($vessel['photo_path']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-lg-8">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Vessel Name</label>
                                        <input type="text" name="vesselName" class="form-control" value="<?= h($vessel['vesselName']) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Official Number / Registration</label>
                                        <input type="text" name="vesselON" class="form-control" value="<?= h($vessel['vesselON']) ?>" maxlength="9" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Hailing Port</label>
                                        <input type="text" name="hailingPort" class="form-control" value="<?= h($vessel['hailingPort']) ?>" maxlength="20" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Call Sign</label>
                                        <input type="text" name="callSign" class="form-control" value="<?= h($vessel['callSign']) ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">MMSI</label>
                                        <input type="number" name="mmsi" class="form-control" value="<?= h($vessel['mmsi']) ?>" min="0" step="1">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">EPIRB Hex ID</label>
                                        <input type="text" name="epirbHexId" class="form-control" value="<?= h($vessel['epirbHexId']) ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Hull ID (HIN)</label>
                                        <input type="text" name="hin" class="form-control" value="<?= h($vessel['hin']) ?>">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">OCMI / Inspection Zone</label>
                                        <select name="ocmi_contact_id" class="form-select">
                                            <option value="">— Not Assigned —</option>
                                            <?php foreach ($contacts as $c): ?>
                                                <option value="<?= (int)$c['contact_id'] ?>" <?= ((int)($vessel['ocmi_contact_id'] ?? 0) === (int)$c['contact_id']) ? 'selected' : '' ?>>
                                                    <?= h(ocmiLabel($c)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if ($is_mscs_admin): ?>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Company</label>
                                            <select name="company_id" class="form-select">
                                                <option value="">-- Select Company --</option>
                                                <?php foreach ($companies as $company): ?>
                                                    <option value="<?= (int)$company['owner_id'] ?>" <?= ((int)$company['owner_id'] === (int)$vessel['company_id']) ? 'selected' : '' ?>>
                                                        <?= h($company['company_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="company_id" value="<?= (int)$vessel['company_id'] ?>">
                                    <?php endif; ?>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label">Active Status</label>
                                        <select name="is_active" class="form-select">
                                            <option value="1" <?= ((int)($vessel['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= ((int)($vessel['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Ownership / Classification</div>
                        <div class="section-help">Classification and inspection basis fields from the vessel record.</div>
                        <div class="row g-3">
                            <?php
                            selectField('Class', 'vesselClass', ['Passenger Vessel', 'Towing Vessel', 'Cargo Vessel'], $vessel['vesselClass']);
                            selectField('Class Type', 'classType', ['Excursion', 'Recreational Dive', 'Parasail', 'Fishing Charter'], $vessel['classType']);
                            selectField('Service', 'vesselService', ['Inspected Passenger', 'Uninspected Passenger'], $vessel['vesselService']);
                            selectField('Subchapter', 'inspSubChapter', ['T', 'K', 'L', 'I', 'M', 'R', 'U'], $vessel['inspSubChapter']);
                            selectField('SIP', 'sip', ['1' => 'Yes', '0' => 'No'], $vessel['sip']);
                            ?>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Tonnage / Dimensions</div>
                        <div class="section-help">Primary measured characteristics used by the rest of the system.</div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Gross Tons</label><input type="number" step="0.01" name="grossTons" value="<?= h($vessel['grossTons']) ?>" class="form-control" required></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Net Tons</label><input type="number" step="0.01" name="netTons" value="<?= h($vessel['netTons']) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Lightship Tons</label><input type="number" step="0.01" name="lightshipTons" value="<?= h($vessel['lightshipTons']) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Length Overall</label><input type="number" step="0.01" name="length" value="<?= h($vessel['length']) ?>" class="form-control" required></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Length Between Perpendiculars</label><input type="number" step="0.01" name="lbp" value="<?= h($vessel['lbp']) ?>" class="form-control"></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Propulsion / Construction / Route</div>
                        <div class="section-help">Propulsion, hull construction, and operating route/waters.</div>
                        <div class="row g-3">
                            <?php
                            selectField('Propulsion Type', 'propulsionType', [
                                'Diesel - Inboard', 'Diesel - Outboard', 'Gasoline - Inboard', 'Gasoline - Outboard', 'Electric'
                            ], $vessel['propulsionType']);
                            selectField('Auxiliary Sail', 'auxSail', ['1' => 'Yes', '0' => 'No'], $vessel['auxSail']);
                            selectField('Hull Material', 'hullMaterial', [
                                'FRP - Fire Retardant', 'FRP - Non Fire-Retardant', 'Aluminum', 'Steel', 'Wood - Sheathed', 'Wood - Plank on Frame'
                            ], $vessel['hullMaterial']);
                            selectField('Route', 'route', ['Oceans', 'Coastwise', 'Limited Coastwise', 'Lakes, Bays, and Sounds', 'Rivers'], $vessel['route']);
                            selectField('Waters', 'waters', ['Exposed', 'Partially Protected', 'Protected'], $vessel['waters']);
                            ?>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Horsepower</label><input type="number" name="horsepower" value="<?= h($vessel['horsepower']) ?>" class="form-control" min="0" step="1" required></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Manning / Capacity</div>
                        <div class="section-help">POB is recalculated on save from the crew and passenger fields below.</div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Master</label><input type="number" name="master" value="<?= h($vessel['master']) ?>" class="form-control" min="0" step="1" required></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Deckhands</label><input type="number" name="deckhands" value="<?= h($vessel['deckhands']) ?>" class="form-control" min="0" step="1"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Others in Crew</label><input type="number" name="othersInCrew" value="<?= h($vessel['othersInCrew']) ?>" class="form-control" min="0" step="1"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Persons in Addition</label><input type="number" name="personInAddition" value="<?= h($vessel['personInAddition']) ?>" class="form-control" min="0" step="1"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Passengers</label><input type="number" name="passengers" value="<?= h($vessel['passengers']) ?>" class="form-control" min="0" step="1"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Calculated POB</label><input type="number" value="<?= h($vessel['pob']) ?>" class="form-control" disabled></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Dates / Inspection Tracking</div>
                        <div class="section-help">Editable date fields stored directly on the vessel record.</div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Keel Laid Date</label><input type="date" name="keelLaidDate" value="<?= h(safeDateValue($vessel['keelLaidDate'])) ?>" class="form-control" required></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Delivery Date</label><input type="date" name="deliveryDate" value="<?= h(safeDateValue($vessel['deliveryDate'])) ?>" class="form-control" required></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Last Inspection</label><input type="date" name="lastInspection" value="<?= h(safeDateValue($vessel['lastInspection'])) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Last Drydock</label><input type="date" name="lastDrydock" value="<?= h(safeDateValue($vessel['lastDrydock'])) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Next Drydock</label><input type="date" name="nextDrydock" value="<?= h(safeDateValue($vessel['nextDrydock'])) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Next Unstep</label><input type="date" name="nextUnstep" value="<?= h(safeDateValue($vessel['nextUnstep'])) ?>" class="form-control"></div>
                            <div class="col-12 col-md-6 col-xl-4"><label class="form-label">Next Scheduled Inspection</label><input type="date" name="nextScheduledInspection" value="<?= h(safeDateValue($vessel['nextScheduledInspection'])) ?>" class="form-control"></div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Vessel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="editVesselForm" class="btn btn-primary">Save Vessel</button>
            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>

<script>
const photoInput = document.getElementById('photoInput');
if (photoInput) {
    photoInput.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        const img = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPreviewPlaceholder');
        img.src = URL.createObjectURL(file);
        img.classList.remove('d-none');
        if (placeholder) placeholder.classList.add('d-none');
    });
}
</script>
</body>
</html>
