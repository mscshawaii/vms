<?php
session_start();
require 'session_check.php';
require 'db_connect.php';
require_once 'helpers.php';

function safe($value) {
    return !empty($value) ? htmlspecialchars($value) : '�';
}

// Fetch company info including logo
$company_id = $_SESSION['company_id'];
$stmt = $pdo->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
$companyLogo = $company['logo_path'] ?? null;
$firstName = $_SESSION['fName'] ?? 'Guest';
$role_id = $_SESSION['role_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        #logoInput {
            display: none;
        }
        #companyLogo:hover {
            cursor: pointer;
            opacity: 0.8;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
<div class="d-flex justify-content-end mb-3">
    <a href="logout.php" class="btn btn-outline-danger btn-sm">Log Out</a>
</div>

<!-- ? Logo and Welcome Block -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div style="max-width: 160px;">
        <?php if ($companyLogo): ?>
            <form method="post" action="update_company_logo.php" enctype="multipart/form-data" id="logoForm">
                <input type="file" name="logo" id="logoInput" accept="image/*" onchange="document.getElementById('logoForm').submit()">
                <img src="<?= $companyLogo ?>" alt="Company Logo" class="img-fluid mb-1" id="companyLogo">
            </form>
        <?php endif; ?>
    </div>
    <div class="flex-grow-1 ps-4">
        <h1 class="mb-1">Welcome, <?= htmlspecialchars($firstName) ?>!</h1>
        <p class="text-muted mb-0">What would you like to do?</p>
    </div>
</div>

<script>
    document.getElementById('companyLogo').addEventListener('click', function() {
        document.getElementById('logoInput').click();
    });
</script>

<!-- ? Action Buttons -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php if ($company_id == 1): ?>
        <a href="add_vessel.php?company_id=<?= $company_id ?>" class="btn btn-success">Add Vessel</a>
        <a href="add_user.php" class="btn btn-primary">Add Users</a>
        <a href="view_companies.php" class="btn btn-outline-dark">View Companies</a>
        <a href="add_company.php" class="btn btn-warning">Create Company</a>
        <a href="icr_templates.php" class="btn btn-outline-dark">Manage ICR Templates</a>
        <a href="icr_assignments.php" class="btn btn-info">Manage ICR Assignments</a>
    <?php elseif ($role_id == 1): ?>
        <a href="add_user.php" class="btn btn-primary">Manage Users</a>
    <?php endif; ?>
</div>

<!-- ✅ MSCS Hawaii View -->
<?php if ($_SESSION['company_id'] == 1): ?>
    <h3 class="mt-5">All Vessels (MSCS Hawaii View)</h3>
    <input type="text" id="vesselSearch" class="form-control mb-3" placeholder="🔎 Search by Vessel Name or ON...">

    <?php
    $stmt = $pdo->query("
        SELECT 
    v.vessel_id, v.vesselName, v.vesselON, v.lastInspection, v.nextScheduledInspection,
    v.lastDrydock, v.nextDrydock, o.owner_id, o.company_name,
    (SELECT expDate FROM documents 
     WHERE vessel_id = v.vessel_id AND docType = 'Certificate of Inspection' 
     ORDER BY expDate DESC LIMIT 1) AS coiExpDate
FROM vessels v
LEFT JOIN owners o ON v.company_id = o.owner_id
ORDER BY o.company_name, v.vesselName

    ");
    $vessels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($vessels as $v) {
        $grouped[$v['company_name']][] = $v;
    }
    ?>

    <?php foreach ($grouped as $company => $vlist): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <strong><?= htmlspecialchars($company) ?></strong>
                <button class="btn btn-sm btn-light toggle-section" data-target="company-<?= md5($company) ?>">⏷ Show/Hide</button>
            </div>
            <div class="card-body p-0 company-section" id="company-<?= md5($company) ?>">
                <table class="table table-bordered table-hover m-0">
                <thead class="table-light">
                    <tr>
                        <th>Vessel Name</th>
                        <th>Official Number</th>
                        <th>Last Inspection</th>
                        <th>Next Inspection Type</th>
                        <th>Next Inspection Window</th>
                        <th>Next Inspection</th>
                        <th>Last Dry Dock</th>
                        <th>Next Dry Dock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vlist as $v): ?>
                        <?php
  $inspection = calculateNextInspection($v['lastInspection'] ?? null, $v['coiExpDate'] ?? null);
?>

                        <tr>
                            <td><?= safe($v['vesselName']) ?></td>
                            <td><?= safe($v['vesselON']) ?></td>
                            <td><?= safe($v['lastInspection']) ?></td>
                            <td><?= htmlspecialchars($inspection['type']) ?></td>
                            <td class="<?= getInspectionWindowClass($inspection['type'], $inspection['window']) ?>">
                                <?= htmlspecialchars($inspection['window']) ?>
                            </td>
                            <td><?= safe($v['nextScheduledInspection']) ?></td>
                            <td><?= safe($v['lastDrydock']) ?></td>
                            <td class="<?= getDrydockClass($v['nextDrydock']) ?>"><?= safe($v['nextDrydock']) ?></td>
                            <td>
                                <a href="vessel_dashboard.php?vessel_id=<?= $v['vessel_id'] ?>" class="btn btn-sm btn-outline-info">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toggle-section').forEach(btn => {
            btn.addEventListener('click', () => {
                const section = document.getElementById(btn.dataset.target);
                if (section) section.classList.toggle('d-none');
            });
        });
        document.getElementById('vesselSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.company-section tbody tr').forEach(row => {
                const name = row.cells[0]?.textContent.toLowerCase() || '';
                const on = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = (name.includes(term) || on.includes(term)) ? '' : 'none';
            });
        });
    });
    </script>
<?php endif; ?>

<!-- ✅ Company’s Vessels Table -->

<h3>Your Company’s Vessels</h3>
<?php
$stmt = $pdo->prepare("
    SELECT v.vessel_id, v.vesselName, v.vesselON, v.lastInspection, v.nextScheduledInspection, v.lastDrydock, v.nextDrydock
    FROM vessels v
    WHERE v.company_id = ?
    ORDER BY v.vesselName ASC
");
$stmt->execute([$company_id]);
$vessels = $stmt->fetchAll();
?>

<?php if (count($vessels) > 0): ?>
    <table class="table table-bordered table-hover m-0">
        <thead class="table-light">
            <tr>
                <th>Vessel Name</th>
                <th>Official Number</th>
                <th>Last Inspection</th>
                <th>Next Inspection Type</th>
                <th>Next Inspection Window</th>
                <th>Next Inspection</th>
                <th>Last Dry Dock</th>
                <th>Next Dry Dock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vessels as $v): ?>
                <?php
  // Optional: You might need to re-fetch COI expiration here
  $stmt = $pdo->prepare("SELECT expDate FROM documents WHERE vessel_id = ? AND docType = 'Certificate of Inspection' ORDER BY expDate DESC LIMIT 1");
  $stmt->execute([$v['vessel_id']]);
  $coiRow = $stmt->fetch();
  $coiExp = $coiRow['expDate'] ?? null;

  $inspection = calculateNextInspection($v['lastInspection'] ?? null, $coiExp);
?>
                <tr>
                    <td><?= safe($v['vesselName']) ?></td>
                    <td><?= safe($v['vesselON']) ?></td>
                    <td><?= safe($v['lastInspection']) ?></td>
                    <td><?= htmlspecialchars($inspection['type']) ?></td>
                    <td class="<?= getInspectionWindowClass($inspection['type'], $inspection['window']) ?>">
                        <?= htmlspecialchars($inspection['window']) ?>
                    </td>
                    <td><?= safe($v['nextScheduledInspection']) ?></td>
                    <td><?= safe($v['lastDrydock']) ?></td>
                    <td class="<?= getDrydockClass($v['nextDrydock']) ?>"><?= safe($v['nextDrydock']) ?></td>
                    </td>
                    <td>
                        <a href="vessel_dashboard.php?vessel_id=<?= $v['vessel_id'] ?>" class="btn btn-sm btn-outline-info">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No vessels found for your company.</p>
<?php endif; ?>

<h3 class="mt-5">👥 Your Company’s Users</h3>
<?php
$stmt = $pdo->prepare("
    SELECT u.*, r.role_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.role_id
    WHERE u.company_id = ?
    ORDER BY u.lName, u.fName
");
$stmt->execute([$company_id]);
$users = $stmt->fetchAll();
?>
<table class="table table-bordered table-striped mt-3">
<thead class="table-dark">
    <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Role</th>
        <th>Email</th>
        <th>Phone</th>
        <th>MMC Exp</th>
        <th>FA Exp</th>
        <th>MROP Issued</th>
        <th>Documents</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['fName'] . ' ' . $user['lName']) ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= safe($user['role_name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= safe($user['phoneNumber']) ?></td>
            <td><?= safe($user['mmc']) ?></td>
            <td><?= safe($user['fa']) ?></td>
            <td><?= safe($user['mrop']) ?></td>
            <td>
                <?php if ($user['mmc_file']): ?><a href="<?= $user['mmc_file'] ?>" target="_blank">📄 MMC</a><br><?php endif; ?>
                <?php if ($user['fa_file']): ?><a href="<?= $user['fa_file'] ?>" target="_blank">🚑 FA</a><br><?php endif; ?>
                <?php if ($user['mrop_file']): ?><a href="<?= $user['mrop_file'] ?>" target="_blank">📡 MROP</a><?php endif; ?>
                <?= (!$user['mmc_file'] && !$user['fa_file'] && !$user['mrop_file']) ? '—' : '' ?>
            </td>
            <td>
                <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info me-1">View</a>
                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary me-1">Edit</a>
                <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger"
                   onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

</table>
</div>
</body>
</html>