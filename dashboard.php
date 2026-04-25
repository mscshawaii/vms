<?php
session_start();
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/helpers.php';
require __DIR__ . '/includes/message_functions.php';

$onesignal_config = __DIR__ . '/private/config_onesignal.php';
if (!file_exists($onesignal_config)) {
    $onesignal_config = '/var/www/private/config_onesignal.php';
}
if (file_exists($onesignal_config)) {
    require_once $onesignal_config;
}

function safe($value) {
    return !empty($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '—';
}

$company_id    = (int)($_SESSION['company_id'] ?? 0);
$firstName     = $_SESSION['fName'] ?? 'Guest';
$role_id       = $_SESSION['role_id'] ?? null;
$isMSCS        = ($company_id === 1);
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$companyId     = $company_id;

// Company info
$stmt = $pdo->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$companyLogo = $company['logo_path'] ?? null;

// Messaging
$companyThreadId = ensureCompanyGeneralThread($pdo, $companyId, $currentUserId);
syncCompanyThreadMembers($pdo, $companyId, $currentUserId);
$companyUnreadCount = getThreadUnreadCount($pdo, $companyThreadId, $currentUserId);

// Active / archived / all filter
$show = $_GET['show'] ?? 'active';
$validShows = ['active', 'archived', 'all'];
if (!$isMSCS) {
    $show = 'active';
}
if (!in_array($show, $validShows, true)) {
    $show = 'active';
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        #logoInput { display: none; }

        #companyLogo:hover {
            cursor: pointer;
            opacity: 0.85;
        }

        tr.archived {
            opacity: 0.65;
        }

        .dash-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .dash-top-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 14px;
        }

        .dash-top-actions .btn {
            min-height: 40px;
            border-radius: 12px;
        }

        .dash-welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }

        .dash-logo-wrap {
            width: 100%;
            max-width: 140px;
            flex-shrink: 0;
        }

        .dash-logo-wrap img {
            max-width: 100%;
            max-height: 72px;
            object-fit: contain;
        }

        .dash-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .dash-subtitle {
            margin: 0;
            color: var(--vms-muted, #6b7280);
        }

        .dash-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }

        .dash-action-card {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 6px;
            justify-content: center;
            min-height: 86px;
            padding: 14px;
            background: #fff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            text-decoration: none;
            color: var(--vms-text, #1f2a37);
        }

        .dash-action-card:hover {
            background: #f8fbff;
            color: var(--vms-text, #1f2a37);
        }

        .dash-action-title {
            font-weight: 700;
            line-height: 1.2;
        }

        .dash-action-meta {
            font-size: 0.88rem;
            color: var(--vms-muted, #6b7280);
            line-height: 1.2;
        }

        .dash-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .dash-filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 16px;
        }

        .dash-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
        }

        .dash-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .dash-table-wrap table {
            min-width: 900px;
            margin-bottom: 0;
        }

        .dash-company-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dash-company-col {
            min-width: 220px;
            flex: 1;
        }

        .dash-company-form {
            min-width: 260px;
            max-width: 320px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px;
        }

        .dash-search {
            max-width: 420px;
        }

        @media (min-width: 768px) {
            .dash-action-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 992px) {
            .dash-action-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .dash-welcome {
                flex-direction: column;
                align-items: flex-start;
            }

            .dash-top-actions {
                justify-content: stretch;
            }

            .dash-top-actions .btn {
                flex: 1;
            }

            .dash-logo-wrap {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="dash-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="dash-top-actions">
                <a href="user_settings.php" class="btn btn-outline-secondary btn-sm" title="User Settings">⚙ Settings</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Log Out">⎋ Logout</a>
            </div>

            <div class="vms-card">
                <div class="dash-welcome">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <?php if ($companyLogo): ?>
                            <div class="dash-logo-wrap">
                                <form method="post" action="update_company_logo.php" enctype="multipart/form-data" id="logoForm">
                                    <input type="file" name="logo" id="logoInput" accept="image/*" onchange="document.getElementById('logoForm').submit()">
                                    <img src="<?= htmlspecialchars($companyLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Company Logo" id="companyLogo" class="img-fluid">
                                </form>
                            </div>
                        <?php endif; ?>

                        <div>
                            <h1 class="dash-title">Dashboard</h1>
                            <p class="dash-subtitle">Welcome back, <?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>.</p>
                        </div>
                    </div>
                </div>

                <div class="dash-action-grid">
                    <a href="reports.php" class="dash-action-card">
                        <div class="dash-action-title">Reports</div>
                        <div class="dash-action-meta">View reporting and summaries</div>
                    </a>

                    <a href="manage_users.php" class="dash-action-card">
                        <div class="dash-action-title">Manage Users</div>
                        <div class="dash-action-meta">Users, roles, and access</div>
                    </a>

                    <a href="company_messages.php" class="dash-action-card">
                        <?php if ($companyUnreadCount > 0): ?>
                            <span class="badge bg-danger dash-badge"><?= (int)$companyUnreadCount ?></span>
                        <?php endif; ?>
                        <div class="dash-action-title">Messages</div>
                        <div class="dash-action-meta">Company discussion and updates</div>
                    </a>

                    <a href="fleet_maintenance.php" class="dash-action-card">
                        <div class="dash-action-title">Fleet Maintenance</div>
                        <div class="dash-action-meta">Corrective actions, maintenance queues, and verifications</div>
                    </a>

                    <?php if ($company_id === 1): ?>
                        <a href="manage_vessels.php?company_id=<?= (int)$company_id ?>" class="dash-action-card">
                            <div class="dash-action-title">Manage Vessels</div>
                            <div class="dash-action-meta">All vessel records and access</div>
                        </a>

                        <a href="view_companies.php" class="dash-action-card">
                            <div class="dash-action-title">Manage Companies</div>
                            <div class="dash-action-meta">Owners and account records</div>
                        </a>

                        <a href="icr_templates.php" class="dash-action-card">
                            <div class="dash-action-title">ICR Templates</div>
                            <div class="dash-action-meta">Inspection template management</div>
                        </a>

                        <a href="uscg_contacts.php" class="dash-action-card">
                            <div class="dash-action-title">USCG Contacts</div>
                            <div class="dash-action-meta">Reference and coordination</div>
                        </a>

                        <a href="library.php" class="dash-action-card">
                            <div class="dash-action-title">Training & Library</div>
                            <div class="dash-action-meta">CFR, notes, guides, and resources</div>
                        </a>

                        <a href="fire_equipment_service_start.php" class="dash-action-card">
                            <div class="dash-action-title">Fire Equipment Service</div>
                            <div class="dash-action-meta">Annual maintenance workflow and reporting</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isMSCS): ?>
                <div class="dash-filter-row">
                    <span class="text-muted">Show:</span>
                    <a class="btn btn-sm <?= $show === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?show=active">Active</a>
                    <a class="btn btn-sm <?= $show === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?show=archived">Archived</a>
                    <a class="btn btn-sm <?= $show === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?show=all">All</a>
                </div>
            <?php endif; ?>

            <?php if ($isMSCS): ?>
                <?php
                $activeClause = '';
                if ($show === 'active') {
                    $activeClause = " AND v.is_active = 1";
                } elseif ($show === 'archived') {
                    $activeClause = " AND v.is_active = 0";
                }

                $sql = "
                    SELECT 
                        v.vessel_id, v.vesselName, v.vesselON, v.lastInspection, v.nextScheduledInspection,
                        v.lastDrydock, v.nextDrydock, v.is_active,
                        o.owner_id, o.company_name, o.address, o.phone, o.email, o.contact_name,
                        o.primary_contact_user_id, o.alt_contact_user_id,
                        u1.fName AS primary_fname, u1.lName AS primary_lname, u1.phoneNumber AS primary_phone, u1.email AS primary_email,
                        u2.fName AS alt_fname, u2.lName AS alt_lname, u2.phoneNumber AS alt_phone, u2.email AS alt_email,
                        (
                            SELECT expDate
                            FROM documents
                            WHERE vessel_id = v.vessel_id
                              AND docType = 'Certificate of Inspection'
                            ORDER BY expDate DESC
                            LIMIT 1
                        ) AS coiExpDate
                    FROM vessels v
                    LEFT JOIN owners o ON v.company_id = o.owner_id
                    LEFT JOIN users u1 ON o.primary_contact_user_id = u1.id
                    LEFT JOIN users u2 ON o.alt_contact_user_id = u2.id
                    WHERE 1=1 $activeClause
                    ORDER BY o.company_name, v.is_active DESC, v.vesselName
                ";

                $stmt = $pdo->query($sql);
                $vessels = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $grouped = [];
                foreach ($vessels as $v) {
                    $key = $v['owner_id'];
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [
                            'company_name' => $v['company_name'],
                            'company_info' => [
                                'owner_id' => $v['owner_id'],
                                'address' => $v['address'],
                                'phone' => $v['phone'],
                                'email' => $v['email'],
                                'contact_name' => $v['contact_name'],
                                'primary_contact_name' => trim(($v['primary_fname'] ?? '') . ' ' . ($v['primary_lname'] ?? '')),
                                'primary_contact_phone' => $v['primary_phone'],
                                'primary_contact_email' => $v['primary_email'],
                                'alt_contact_name' => trim(($v['alt_fname'] ?? '') . ' ' . ($v['alt_lname'] ?? '')),
                                'alt_contact_phone' => $v['alt_phone'],
                                'alt_contact_email' => $v['alt_email'],
                                'primary_contact_user_id' => $v['primary_contact_user_id'],
                                'alt_contact_user_id' => $v['alt_contact_user_id'],
                            ],
                            'vessels' => [],
                        ];
                    }
                    $grouped[$key]['vessels'][] = $v;
                }
                ?>

                <div class="vms-card">
                    <div class="vms-card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h2 class="dash-section-title">All Vessels (MSCS Hawaii View)</h2>
                            <input type="text" id="vesselSearch" class="form-control dash-search" placeholder="Search by vessel name or official number">
                        </div>
                    </div>
                </div>

                <?php foreach ($grouped as $group): ?>
                    <?php
                    $companyName = $group['company_name'];
                    $info = $group['company_info'];
                    $vlist = $group['vessels'];
                    ?>

                    <div class="vms-card">
                        <div class="vms-card-header">
                            <div class="dash-company-header">
                                <div class="dash-company-col">
                                    <strong><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></strong><br>
                                    <small>
                                        Address: <?= htmlspecialchars((string)$info['address'], ENT_QUOTES, 'UTF-8') ?><br>
                                        Phone: <?= htmlspecialchars((string)$info['phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                        Email: <?= htmlspecialchars((string)$info['email'], ENT_QUOTES, 'UTF-8') ?><br>
                                        Contact Name: <?= htmlspecialchars((string)$info['contact_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </div>

                                <div class="dash-company-col">
                                    <?php if (!empty(trim($info['primary_contact_name']))): ?>
                                        <strong>Primary Contact:</strong> <?= htmlspecialchars($info['primary_contact_name'], ENT_QUOTES, 'UTF-8') ?><br>
                                        <small>
                                            <?= htmlspecialchars((string)$info['primary_contact_phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                            <?= htmlspecialchars((string)$info['primary_contact_email'], ENT_QUOTES, 'UTF-8') ?>
                                        </small><br>
                                    <?php endif; ?>

                                    <?php if (!empty(trim($info['alt_contact_name']))): ?>
                                        <strong>Alternate Contact:</strong> <?= htmlspecialchars($info['alt_contact_name'], ENT_QUOTES, 'UTF-8') ?><br>
                                        <small>
                                            <?= htmlspecialchars((string)$info['alt_contact_phone'], ENT_QUOTES, 'UTF-8') ?><br>
                                            <?= htmlspecialchars((string)$info['alt_contact_email'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <form action="update_company_contacts.php" method="POST" class="dash-company-form">
                                    <input type="hidden" name="company_id" value="<?= (int)$info['owner_id'] ?>">

                                    <?php
                                    $usersStmt = $pdo->prepare("SELECT id, fName, lName FROM users WHERE company_id = ?");
                                    $usersStmt->execute([$info['owner_id']]);
                                    $userOptions = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>

                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Primary Contact</label>
                                        <select name="primary_contact_user_id" class="form-select form-select-sm">
                                            <option value="">— Select —</option>
                                            <?php foreach ($userOptions as $u): ?>
                                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$u['id'] === (int)$info['primary_contact_user_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars(trim(($u['fName'] ?? '') . ' ' . ($u['lName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small mb-1">Alt Contact</label>
                                        <select name="alt_contact_user_id" class="form-select form-select-sm">
                                            <option value="">— Select —</option>
                                            <?php foreach ($userOptions as $u): ?>
                                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$u['id'] === (int)$info['alt_contact_user_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars(trim(($u['fName'] ?? '') . ' ' . ($u['lName'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save Contacts</button>
                                </form>
                            </div>
                        </div>

                        <div class="dash-table-wrap company-section">
                            <table class="table table-bordered table-hover align-middle">
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vlist as $v): ?>
                                        <?php
                                        $inspection = calculateNextInspection($v['lastInspection'] ?? null, $v['coiExpDate'] ?? null);
                                        $isArchived = (int)($v['is_active'] ?? 1) === 0;
                                        ?>
                                        <tr class="<?= $isArchived ? 'archived' : '' ?>">
                                            <td>
                                                <?php if ($isArchived): ?>
                                                    <span class="badge bg-secondary me-2">Archived</span>
                                                <?php endif; ?>
                                                <a href="vessel_dashboard.php?vessel_id=<?= (int)$v['vessel_id'] ?>"
                                                class="btn btn-outline-primary btn-sm fw-semibold">
                                                    <?= safe($v['vesselName']) ?>
                                                </a>
                                            </td>
                                            <td><?= safe($v['vesselON']) ?></td>
                                            <td><?= safe($v['lastInspection']) ?></td>
                                            <td><?= htmlspecialchars((string)$inspection['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="<?= getInspectionWindowClass($inspection['type'], $inspection['window']) ?>">
                                                <?= htmlspecialchars((string)$inspection['window'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= safe($v['nextScheduledInspection']) ?></td>
                                            <td><?= safe($v['lastDrydock']) ?></td>
                                            <td class="<?= getDrydockClass($v['nextDrydock']) ?>"><?= safe($v['nextDrydock']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php
            $companyActiveClause = '';
            if ($show === 'active') {
                $companyActiveClause = " AND v.is_active = 1";
            } elseif ($show === 'archived') {
                $companyActiveClause = " AND v.is_active = 0";
            }

            $stmt = $pdo->prepare("
                SELECT v.vessel_id, v.vesselName, v.vesselON, v.lastInspection, v.nextScheduledInspection, v.lastDrydock, v.nextDrydock, v.is_active
                FROM vessels v
                WHERE v.company_id = ? $companyActiveClause
                ORDER BY v.is_active DESC, v.vesselName ASC
            ");
            $stmt->execute([$company_id]);
            $companyVessels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="vms-card">
                <div class="vms-card-header">
                    <h2 class="dash-section-title">Your Company’s Vessels</h2>
                </div>

                <?php if (count($companyVessels) > 0): ?>
                    <div class="dash-table-wrap">
                        <table class="table table-bordered table-hover align-middle">
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companyVessels as $v): ?>
                                    <?php
                                    $coiStmt = $pdo->prepare("
                                        SELECT expDate
                                        FROM documents
                                        WHERE vessel_id = ?
                                          AND docType = 'Certificate of Inspection'
                                        ORDER BY expDate DESC
                                        LIMIT 1
                                    ");
                                    $coiStmt->execute([$v['vessel_id']]);
                                    $coiRow = $coiStmt->fetch(PDO::FETCH_ASSOC);
                                    $coiExp = $coiRow['expDate'] ?? null;

                                    $inspection = calculateNextInspection($v['lastInspection'] ?? null, $coiExp);
                                    $isArchived = (int)($v['is_active'] ?? 1) === 0;
                                    ?>
                                    <tr class="<?= $isArchived ? 'archived' : '' ?>">
                                        <td>
                                            <?php if ($isArchived): ?>
                                                <span class="badge bg-secondary me-2">Archived</span>
                                            <?php endif; ?>
                                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$v['vessel_id'] ?>"
                                            class="btn btn-outline-primary btn-sm fw-semibold">
                                                <?= safe($v['vesselName']) ?>
                                            </a>
                                        </td>
                                        <td><?= safe($v['vesselON']) ?></td>
                                        <td><?= safe($v['lastInspection']) ?></td>
                                        <td><?= htmlspecialchars((string)$inspection['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="<?= getInspectionWindowClass($inspection['type'], $inspection['window']) ?>">
                                            <?= htmlspecialchars((string)$inspection['window'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= safe($v['nextScheduledInspection']) ?></td>
                                        <td><?= safe($v['lastDrydock']) ?></td>
                                        <td class="<?= getDrydockClass($v['nextDrydock']) ?>"><?= safe($v['nextDrydock']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-3 text-muted">No vessels found for your chosen filter.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
const logoEl = document.getElementById('companyLogo');
if (logoEl) {
    logoEl.addEventListener('click', function () {
        const input = document.getElementById('logoInput');
        if (input) input.click();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('vesselSearch');
    if (search) {
        search.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.company-section tbody tr').forEach(row => {
                const name = row.cells[0]?.textContent.toLowerCase() || '';
                const on = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = (name.includes(term) || on.includes(term)) ? '' : 'none';
            });
        });
    }
});
</script>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    const vmsUserId = <?= json_encode((string)($_SESSION['user_id'] ?? '')) ?>;

    async function syncPushStatus() {
        try {
            const payload = {
                external_id: String(vmsUserId || ''),
                onesignal_id: OneSignal.User.onesignalId || '',
                subscription_id: OneSignal.User.PushSubscription.id || '',
                platform: 'web',
                is_active: !!OneSignal.User.PushSubscription.optedIn
            };

            if (!payload.subscription_id) {
                return;
            }

            const res = await fetch('api/sync_push_subscription.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            console.log('Dashboard push sync:', data);
        } catch (err) {
            console.error('Dashboard push sync failed', err);
        }
    }

    try {
        await OneSignal.init({
            appId: "<?= htmlspecialchars(ONESIGNAL_APP_ID, ENT_QUOTES, 'UTF-8') ?>"
        });

        if (vmsUserId) {
            await OneSignal.login(vmsUserId);
        }

        await syncPushStatus();

        OneSignal.User.PushSubscription.addEventListener('change', async function() {
            await syncPushStatus();
        });

    } catch (err) {
        console.error('Dashboard OneSignal init/login failed', err);
    }
});
</script>

</body>
</html>
