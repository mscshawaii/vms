<?php
require 'session_check.php';
require 'db_connect.php';

// Allow MSCS Admin and Company Admin
if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2], true)) {
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id'])) {
    echo "No user ID specified.";
    exit;
}

$user_id = (int)$_GET['id'];
$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs = ($session_company_id === 1);

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

// Company admins may only edit users in their own company
if (!$is_mscs && (int)$user['company_id'] !== $session_company_id) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Fetch companies / roles / vessels
$companies = [];
if ($is_mscs) {
    $companies = $pdo->query("
        SELECT owner_id, company_name
        FROM owners
        ORDER BY company_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $vesselStmt = $pdo->query("
        SELECT vessel_id, vesselName, company_id
        FROM vessels
        WHERE archived_at IS NULL
        ORDER BY vesselName
    ");
    $vessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, company_id
        FROM vessels
        WHERE company_id = ?
          AND archived_at IS NULL
        ORDER BY vesselName
    ");
    $vesselStmt->execute([$session_company_id]);
    $vessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);
}

$roles = $pdo->query("
    SELECT role_id, role_name
    FROM roles
    ORDER BY role_name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch active vessel assignments for this user
$assignmentStmt = $pdo->prepare("
    SELECT
        vc.id,
        vc.vessel_id,
        vc.role,
        vc.is_active,
        vc.counts_for_drills,
        vc.counts_for_voyage_logs
    FROM vessel_crew vc
    WHERE vc.crew_id = ?
      AND vc.is_active = 1
    ORDER BY vc.vessel_id, vc.id
");
$assignmentStmt->execute([$user_id]);
$assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);

$vesselRoleOptions = ['Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'];

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit User</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .users-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .form-section-card {
            border: 0;
            border-radius: 1rem;
        }
        .users-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .assignment-row {
            border: 1px solid #dee2e6;
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        .assignment-row:last-child {
            margin-bottom: 0;
        }
        .doc-link {
            font-size: 0.9rem;
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
$title = 'Edit User';
$back_link = 'manage_users.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="users-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Edit User</h1>
                            <p class="users-meta"><?= h(($user['fName'] ?? '') . ' ' . ($user['lName'] ?? '')) ?></p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="manage_users.php" class="btn btn-outline-secondary">
                                Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <form id="editUserForm" action="update_user.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Basic Information</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="fName" value="<?= h($user['fName']) ?>" class="form-control" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lName" value="<?= h($user['lName']) ?>" class="form-control" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phoneNumber" value="<?= h($user['phoneNumber'] ?? '') ?>" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="<?= h($user['email']) ?>" class="form-control" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" value="<?= h($user['username']) ?>" class="form-control" required>
                            </div>

                            <?php if ($is_mscs): ?>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Company</label>
                                    <select name="company_id" id="company_id" class="form-select" required>
                                        <?php foreach ($companies as $c): ?>
                                            <option value="<?= (int)$c['owner_id'] ?>" <?= ((int)$user['company_id'] === (int)$c['owner_id']) ? 'selected' : '' ?>>
                                                <?= h($c['company_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="company_id" id="company_id" value="<?= (int)$session_company_id ?>">
                            <?php endif; ?>

                            <div class="col-12 col-md-6">
                                <label class="form-label">System Role</label>
                                <select name="role_id" class="form-select" required>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?= (int)$r['role_id'] ?>" <?= ((int)$user['role_id'] === (int)$r['role_id']) ? 'selected' : '' ?>>
                                            <?= h($r['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 d-flex align-items-end">
                                <div class="form-check pb-2">
                                    <input class="form-check-input" type="checkbox" name="receive_notifications" id="receive_notifications" value="1"
                                        <?= !empty($user['receive_notifications']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="receive_notifications">
                                        Receive notifications
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                            <div>
                                <div class="section-title mb-1">Vessel Assignments</div>
                                <p class="text-muted mb-0">Manage this user's active vessel assignments.</p>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm" id="addAssignmentBtn">
                                Add Vessel
                            </button>
                        </div>

                        <div id="assignmentContainer"></div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Credentials / Expirations</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="form-label">MMC Expiration</label>
                                <input type="date" name="mmc" class="form-control" value="<?= h($user['mmc'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">MMC Medical</label>
                                <input type="date" name="mmc_medical" class="form-control" value="<?= h($user['mmc_medical'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">First Aid Exp.</label>
                                <input type="date" name="fa" class="form-control" value="<?= h($user['fa'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-3">
                                <label class="form-label">MROP Issued</label>
                                <input type="date" name="mrop" class="form-control" value="<?= h($user['mrop'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Credential Documents</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">MMC Document</label>
                                <input type="file" name="mmc_path" class="form-control">
                                <?php if (!empty($user['mmc_path'])): ?>
                                    <div class="mt-2 doc-link text-muted">Current: <a href="<?= h($user['mmc_path']) ?>" target="_blank">View</a></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">MMC Medical Document</label>
                                <input type="file" name="mmc_medical_path" class="form-control">
                                <?php if (!empty($user['mmc_medical_path'])): ?>
                                    <div class="mt-2 doc-link text-muted">Current: <a href="<?= h($user['mmc_medical_path']) ?>" target="_blank">View</a></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">First Aid Document</label>
                                <input type="file" name="fa_path" class="form-control">
                                <?php if (!empty($user['fa_path'])): ?>
                                    <div class="mt-2 doc-link text-muted">Current: <a href="<?= h($user['fa_path']) ?>" target="_blank">View</a></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">MROP Document</label>
                                <input type="file" name="mrop_path" class="form-control">
                                <?php if (!empty($user['mrop_path'])): ?>
                                    <div class="mt-2 doc-link text-muted">Current: <a href="<?= h($user['mrop_path']) ?>" target="_blank">View</a></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Password</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control">
                                <div class="form-text">Leave blank to keep the existing password.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="manage_users.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="editUserForm" class="btn btn-primary">
                Save Changes
            </button>
            <a href="manage_users.php" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>
</div>

<script>
const vesselData = <?= json_encode($vessels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const vesselRoleOptions = <?= json_encode($vesselRoleOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const existingAssignments = <?= json_encode($assignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const isMscs = <?= $is_mscs ? 'true' : 'false' ?>;

const assignmentContainer = document.getElementById('assignmentContainer');
const addAssignmentBtn = document.getElementById('addAssignmentBtn');
const companySelect = document.getElementById('company_id');

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

function getSelectedVesselIds(excludeIndex = null) {
    const selects = assignmentContainer.querySelectorAll('.assignment-vessel');
    const selected = [];

    selects.forEach((select, index) => {
        if (excludeIndex !== null && index === excludeIndex) return;
        if (select.value) selected.push(select.value);
    });

    return selected;
}

function getFilteredVessels() {
    if (!isMscs) return vesselData;

    const companyId = companySelect ? companySelect.value : '';
    if (!companyId) return [];

    return vesselData.filter(v => String(v.company_id) === String(companyId));
}

function buildVesselOptions(selectedValue = '', rowIndex = null) {
    const selectedElsewhere = getSelectedVesselIds(rowIndex);
    const availableVessels = getFilteredVessels();

    let html = '<option value="">-- Select Vessel --</option>';

    availableVessels.forEach(vessel => {
        const vesselId = String(vessel.vessel_id);
        const isCurrent = vesselId === String(selectedValue);

        if (!isCurrent && selectedElsewhere.includes(vesselId)) {
            return;
        }

        const selectedAttr = isCurrent ? 'selected' : '';
        html += `<option value="${vesselId}" ${selectedAttr}>${escapeHtml(vessel.vesselName)}</option>`;
    });

    return html;
}

function buildRoleOptions(selectedValue = '') {
    let html = '<option value="">-- Select Vessel Role --</option>';

    vesselRoleOptions.forEach(role => {
        const selectedAttr = role === selectedValue ? 'selected' : '';
        html += `<option value="${escapeHtml(role)}" ${selectedAttr}>${escapeHtml(role)}</option>`;
    });

    return html;
}

function applyRoleDefaultsToRow(row) {
    const roleSelect = row.querySelector('.assignment-role');
    const drills = row.querySelector('.assignment-drills');
    const logs = row.querySelector('.assignment-logs');
    const role = roleSelect.value;

    if (role === 'Master' || role === 'Deckhand') {
        drills.checked = true;
        logs.checked = true;
    } else if (role === 'Owner' || role === 'Admin' || role === 'Maintenance') {
        drills.checked = false;
        logs.checked = false;
    }
}

function refreshAllVesselDropdowns() {
    const rows = assignmentContainer.querySelectorAll('.assignment-row');

    rows.forEach((row, index) => {
        const select = row.querySelector('.assignment-vessel');
        const currentValue = select.value;
        select.innerHTML = buildVesselOptions(currentValue, index);
    });
}

function createAssignmentRow(data = {}) {
    const row = document.createElement('div');
    row.className = 'assignment-row';

    row.innerHTML = `
        <div class="row g-3">
            <input type="hidden" name="assignment_existing_id[]" value="${data.id ? escapeHtml(data.id) : ''}">

            <div class="col-12 col-md-5">
                <label class="form-label">Vessel</label>
                <select name="assignment_vessel_id[]" class="form-select assignment-vessel">
                    ${buildVesselOptions(data.vessel_id || '')}
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="form-label">Vessel Role</label>
                <select name="assignment_role[]" class="form-select assignment-role">
                    ${buildRoleOptions(data.role || '')}
                </select>
            </div>

            <div class="col-6 col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input assignment-drills" type="checkbox" name="assignment_counts_for_drills[]" value="1" ${parseInt(data.counts_for_drills || 0, 10) ? 'checked' : ''}>
                    <label class="form-check-label">Drills</label>
                </div>
            </div>

            <div class="col-6 col-md-2 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input assignment-logs" type="checkbox" name="assignment_counts_for_voyage_logs[]" value="1" ${parseInt(data.counts_for_voyage_logs || 0, 10) ? 'checked' : ''}>
                    <label class="form-check-label">Voyage Logs</label>
                </div>
            </div>

            <div class="col-12">
                <button type="button" class="btn btn-outline-danger btn-sm remove-assignment-btn">Remove Vessel</button>
            </div>
        </div>
    `;

    const roleSelect = row.querySelector('.assignment-role');
    const vesselSelect = row.querySelector('.assignment-vessel');
    const removeBtn = row.querySelector('.remove-assignment-btn');

    roleSelect.addEventListener('change', function () {
        applyRoleDefaultsToRow(row);
    });

    vesselSelect.addEventListener('change', function () {
        refreshAllVesselDropdowns();
    });

    removeBtn.addEventListener('click', function () {
        row.remove();
        refreshAllVesselDropdowns();
    });

    assignmentContainer.appendChild(row);
    refreshAllVesselDropdowns();
}

addAssignmentBtn.addEventListener('click', function () {
    if (isMscs && companySelect && !companySelect.value) {
        alert('Please select a company first.');
        return;
    }

    createAssignmentRow();
});

if (companySelect) {
    companySelect.addEventListener('change', function () {
        assignmentContainer.innerHTML = '';
    });
}

if (existingAssignments.length > 0) {
    existingAssignments.forEach(function (assignment) {
        createAssignmentRow(assignment);
    });
} else {
    createAssignmentRow();
}
</script>
</body>
</html>