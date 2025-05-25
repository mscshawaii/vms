<?php
require 'session_check.php';
require 'db_connect.php';

function safe($value) {
    return !empty($value) ? htmlspecialchars($value) : '—';
}

$role_id = $_SESSION['role_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;
$vessel_id = intval($_GET['vessel_id'] ?? 0);

if ($role_id == 1) {
    $stmt = $pdo->prepare("SELECT * FROM vessels WHERE vessel_id = ?");
    $stmt->execute([$vessel_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM vessels WHERE vessel_id = ? AND company_id = ?");
    $stmt->execute([$vessel_id, $company_id]);
}

$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("❌ Access denied or vessel not found.");
}
?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    ✅ Corrective action deleted.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>


<!DOCTYPE html>
<html>
<head>
    <title>Vessel Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="p-4">
<a href="dashboard.php" class="btn btn-outline-secondary mb-3">← Return to Company Dashboard</a>

<h2><?= htmlspecialchars($vessel['vesselName']) ?> Dashboard</h2>
<!-- Tab Buttons at the Top -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-outline-primary text-dark border-dark" data-bs-toggle="modal" data-bs-target="#documentsModal">Documents</button>
    <button class="btn btn-outline-primary text-dark border-dark" data-bs-toggle="modal" data-bs-target="#equipmentModal">Equipment</button>
    <button class="btn btn-outline-primary text-dark border-dark" data-bs-toggle="modal" data-bs-target="#icrsModal">Inspection Criteria Records</button>
    <button class="btn btn-outline-primary text-dark border-dark" data-bs-toggle="modal" data-bs-target="#tasksModal">Corrective Action Requirements</button>
    <button class="btn btn-outline-primary text-dark border-dark" data-bs-toggle="modal" data-bs-target="#crewModal">Crew</button>
</div>


<!-- Vessel Identification -->
<div class="card mb-4">
    <div class="card-header bg-secondary text-white">Vessel Identification</div>
    <div class="card-body">
        <form method="post" action="update_vessel_identification.php" enctype="multipart/form-data">
            <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

            <div class="row g-4">
                <!-- Photo -->
                <div class="col-md-3 text-center position-relative">
                    <div class="photo-container position-relative">
                        <?php if (!empty($vessel['photo_path'])): ?>
                            <img id="photoPreview" src="<?= htmlspecialchars($vessel['photo_path']) ?>" class="img-thumbnail" style="height:250px; object-fit:cover; width:100%;">
                        <?php else: ?>
                            <img id="photoPreview" src="placeholder.jpg" class="img-thumbnail" style="height:250px; object-fit:cover; width:100%;">
                            <div class="position-absolute top-50 start-50 translate-middle text-muted">No Photo</div>
                        <?php endif; ?>
                        <div class="photo-overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="cursor:pointer;">
                            </div>
                    </div>
                    <input type="file" name="photo" id="photoInput" class="d-none" accept="image/*">
                </div>

                <!-- Vessel Info -->
                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Official Number / Registration</label>
                            <input type="text" name="vesselON" class="form-control" value="<?= safe($vessel['vesselON']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Call Sign</label>
                            <input type="text" name="callSign" class="form-control" value="<?= safe($vessel['callSign']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">MMSI</label>
                            <input type="text" name="mmsi" class="form-control" value="<?= safe($vessel['mmsi']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hailing Port</label>
                            <input type="text" name="hailingPort" class="form-control" value="<?= safe($vessel['hailingPort']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">EPIRB Hex ID</label>
                            <input type="text" name="epirbHexId" class="form-control" value="<?= safe($vessel['epirbHexId']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hull ID (HIN)</label>
                            <input type="text" name="hin" class="form-control" value="<?= safe($vessel['hin']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary">Save Identification</button>
            </div>
        </form>
    </div>
</div>

<script>
// Hover to show upload overlay
document.querySelector('.photo-container').addEventListener('click', function() {
    document.getElementById('photoInput').click();
});

// Live preview
document.getElementById('photoInput').addEventListener('change', function(e) {
    const reader = new FileReader();
    reader.onload = function(event) {
        document.getElementById('photoPreview').src = event.target.result;
    };
    if (e.target.files.length) {
        reader.readAsDataURL(e.target.files[0]);
    }
});
</script>

<!-- Inspection Dates -->
<?php
function safeDate($value) {
    return (!empty($value) && $value !== '0000-00-00') ? htmlspecialchars($value) : '—';
}

$today = date('Y-m-d');
$coiIssue = '—';
$coiExp = '—';
$nextScheduled = safeDate($vessel['nextScheduledInspection'] ?? null);
$lastInspectionDate = $vessel['lastInspection'] ?? null;

$inspectionType = '—';
$nextDueWindow = '—';
$inspectionNote = '';

$coi = $pdo->prepare("
    SELECT issueDate, expDate
    FROM documents
    WHERE vessel_id = ?
      AND docType = 'Certificate of Inspection'
    ORDER BY expDate DESC
    LIMIT 1
");
$coi->execute([$vessel_id]);

$coiRow = $coi->fetch(PDO::FETCH_ASSOC);

$coiIssue = '—';
$coiExp = '—';
$inspectionType = '—';
$nextDueWindow = '—';
$nextScheduled = safeDate($vessel['nextScheduledInspection'] ?? null);
$lastInspectionDate = $vessel['lastInspection'] ?? null;

if ($coiRow) {
    $coiIssue = safeDate($coiRow['issueDate']);
    $coiExp = safeDate($coiRow['expDate']);
    $expDateRaw = $coiRow['expDate'];

    // keep your annual/renewal logic here...
}

    if ($expDateRaw && $expDateRaw !== '0000-00-00') {
        $exp = new DateTime($expDateRaw);
        $current = new DateTime();
        $lastInspection = ($lastInspectionDate && $lastInspectionDate !== '0000-00-00') ? new DateTime($lastInspectionDate) : null;

        // Loop from Annual #1 (4 years before exp) through Renewal
        for ($i = 1; $i <= 4; $i++) {
            $annualDate = (clone $exp)->modify("-" . (5 - $i) . " years");
            $startWindow = (clone $annualDate)->modify("-90 days");
            $endWindow = (clone $annualDate)->modify("+90 days");

            if (!$lastInspection || $lastInspection < $startWindow) {
                $inspectionType = "Annual (#$i)";
                $nextDueWindow = $startWindow->format('Y-m-d') . " to " . $endWindow->format('Y-m-d');
                break;
            }
        }

        // If all annuals passed, check renewal
        if ($inspectionType === '—') {
            $renewalStart = (clone $exp)->modify('-90 days');
            if (!$lastInspection || $lastInspection < $renewalStart) {
                $inspectionType = "Renewal";
                $nextDueWindow = $renewalStart->format('Y-m-d') . " to " . $exp->format('Y-m-d');
            } elseif ($lastInspection > $exp) {
                $inspectionType = "Inspection Complete";
                $nextDueWindow = "—";
            }
        }
    }



?>

<div class="card mb-4">
  <div class="card-header bg-primary text-white">Inspection Dates</div>
  <div class="card-body">
    <form method="post" action="update_inspection_dates.php">
      <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">

      <div class="row row-cols-1 row-cols-md-4 g-3 align-items-center">
        <!-- COI Issue Date -->
        <div class="col border p-2">
          <strong>COI Issue Date:</strong><br>
          <?= $coiIssue ?>
        </div>

        <!-- COI Expiration Date -->
        <div class="col border p-2">
          <strong>COI Expiration Date:</strong><br>
          <?= $coiExp ?>
        </div>

        <!-- Last Inspection Date -->
        <div class="col border p-2">
          <strong>Last Inspection Date:</strong><br>
          <input type="date" name="lastInspection" value="<?= htmlspecialchars($vessel['lastInspection'] ?? '') ?>" class="form-control">
        </div>

        <!-- Next Scheduled Inspection -->
        <div class="col border p-2">
          <strong>Next Scheduled Inspection:</strong><br>
          <input type="date" name="nextScheduledInspection" value="<?= htmlspecialchars($vessel['nextScheduledInspection'] ?? '') ?>" class="form-control">
        </div>

        <!-- Next Inspection Type -->
        <div class="col border p-2">
          <strong>Next Inspection Type:</strong><br>
          <?= $inspectionType ?>
        </div>

        <!-- Inspection Window -->
        <div class="col border p-2">
          <strong>Inspection Window:</strong><br>
          <?= $nextDueWindow ?>
        </div>

        <!-- Last Dry Dock -->
        <div class="col border p-2">
          <strong>Last Dry Dock:</strong><br>
          <input type="date" name="lastDrydock" value="<?= htmlspecialchars($vessel['lastDrydock'] ?? '') ?>" class="form-control">
        </div>

        <!-- Next Dry Dock -->
        <div class="col border p-2">
          <strong>Next Dry Dock:</strong><br>
          <input type="date" name="nextDrydock" value="<?= htmlspecialchars($vessel['nextDrydock'] ?? '') ?>" class="form-control">
        </div>

        <!-- Next Mast Un-step -->
        <div class="col border p-2">
          <strong>Next Mast Un-step:</strong><br>
          <input type="date" name="nextUnstep" value="<?= htmlspecialchars($vessel['nextUnstep'] ?? '') ?>" class="form-control">
        </div>
      </div>

      <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary">Save Inspection Dates</button>
      </div>
    </form>
  </div>
</div>

<!-- Vessel Details Section -->
<div class="card mb-4">
  <div class="card-header bg-info text-white">Vessel Details</div>
  <div class="card-body">
    <div class="row row-cols-1 row-cols-md-4 g-3 border-top">
      <?php
      $details = [
          'Class' => $vessel['vesselClass'],
          'Class Type' => $vessel['classType'],
          'Service' => $vessel['vesselService'] ?? '—',
          'Subchapter' => $vessel['inspSubChapter'] ?? '—',
          'SIP' => $vessel['sip'] ? 'Yes' : 'No',
          'Gross Tons' => $vessel['grossTons'],
          'Net Tons' => $vessel['netTons'],
          'Lightship Tons' => $vessel['lightshipTons'],
          'Length Overall' => $vessel['length'] . ' ft',
          'Length Between Perpendiculars' => $vessel['lbp'] . ' ft',
          'Hull Material' => $vessel['hullMaterial'],
          'Auxiliary Sail' => $vessel['auxSail'] ? 'Yes' : 'No',
          'Horsepower' => $vessel['horsepower'],
          'Propulsion Type' => $vessel['propulsionType'],
          'Route' => $vessel['route'],
          'Waters' => $vessel['waters'],
          'Keel Laid Date' => safeDate($vessel['keelLaidDate']),
          'Delivery Date' => safeDate($vessel['deliveryDate'])
      ];

      foreach ($details as $label => $value): ?>
        <div class="col border-bottom pb-2">
          <strong><?= $label ?>:</strong><br><?= safe($value) ?>
        </div>
      <?php endforeach; ?>
    </div>
    </div> <!-- End of .card-body -->
<div class="text-end mt-3 me-3 mb-3">
<a href="edit_vessel.php?id=<?= $vessel_id ?>" class="btn btn-primary"> Edit Vessel Details</a>
</div>
  </div>
</div>

<div class="tab-content mt-3" id="vesselTabContent">

<!-- Equipment Modal -->
<div class="modal fade" id="equipmentModal" tabindex="-1" aria-labelledby="equipmentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="equipmentModalLabel">Equipment for <?= htmlspecialchars($vessel['vesselName']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/equipment_tab.php'; ?>
      </div>
    </div>
  </div>
</div>

</div>

<!-- Documents Modal -->
<div class="modal fade" id="documentsModal" tabindex="-1" aria-labelledby="documentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="documentsModalLabel">Vessel Documents</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/documents_tab.php'; ?>
      </div>
    </div>
  </div>
</div>

<!-- Crew Tab -->
<!-- 👥 Crew Modal -->
<div class="modal fade" id="crewModal" tabindex="-1" aria-labelledby="crewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="crewModalLabel">Crew Assignments</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/crew_tab.php'; ?>
      </div>
    </div>
  </div>
</div>

<!-- Tasks Tab -->
<!-- 🛠 Tasks Modal -->
<div class="modal fade" id="tasksModal" tabindex="-1" aria-labelledby="tasksModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="tasksModalLabel">Corrective Action Records</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/tasks_tab.php'; ?>
      </div>
    </div>
  </div>
</div>

<!-- ICRs Tab -->
<!-- 📋 ICRs Modal -->
<div class="modal fade" id="icrsModal" tabindex="-1" aria-labelledby="icrsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="icrsModalLabel">Inspection Criteria Records</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include 'partials/icrs_tab.php'; ?>
      </div>
    </div>
  </div>
</div>

</div> <!-- End .tab-content -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    if (hash) {
        const modal = document.querySelector(hash);
        if (modal && modal.classList.contains('modal')) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        }
    }
});
</script>



</html>

