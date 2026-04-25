<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';
require_once __DIR__ . '/includes/hour_meter_functions.php';
$onesignalConfig = __DIR__ . '/private/config_onesignal.php';
if (!file_exists($onesignalConfig)) {
    $onesignalConfig = '/var/www/private/config_onesignal.php';
}
require_once $onesignalConfig;

ini_set('display_errors', 1);
error_reporting(E_ALL);

function safe($value) {
    return !empty($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '—';
}

function safeDate($value) {
    return (!empty($value) && $value !== '0000-00-00') ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : '—';
}

$role_id    = $_SESSION['role_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;
$vessel_id  = (int)($_GET['vessel_id'] ?? 0);

$task_filter = trim($_GET['task_filter'] ?? '');

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

vms_hour_ensure_monthly_verification_task($pdo, $vessel_id);

$currentUserId  = (int)($_SESSION['user_id'] ?? 0);
$vesselThreadId = ensureVesselGeneralThread($pdo, (int)$vessel_id, $currentUserId);
syncVesselThreadMembers($pdo, (int)$vessel_id, $currentUserId);
$vesselUnreadCount = getThreadUnreadCount($pdo, $vesselThreadId, $currentUserId);

// Preload active USCG contacts
$contacts = $pdo->query("
    SELECT contact_id, region_name, IFNULL(port_name,'') AS port_name, email_to
    FROM uscg_contacts
    WHERE active = 1
    ORDER BY region_name, port_name
")->fetchAll(PDO::FETCH_ASSOC);

// CSRF
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

function ocmi_label($c) {
    $label = $c['region_name'];
    if (!empty($c['port_name'])) {
        $label .= ' – ' . $c['port_name'];
    }
    $label .= ' (' . $c['email_to'] . ')';
    return $label;
}

// Inspection summary
$today = date('Y-m-d');
$coiIssue = '—';
$coiExp = '—';
$nextScheduled = safeDate($vessel['nextScheduledInspection'] ?? null);
$lastInspectionDate = $vessel['lastInspection'] ?? null;
$inspectionType = '—';
$nextDueWindow = '—';
$expDateRaw = null;

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

if ($coiRow) {
    $coiIssue = safeDate($coiRow['issueDate']);
    $coiExp = safeDate($coiRow['expDate']);
    $expDateRaw = $coiRow['expDate'] ?? null;
}

if (!empty($expDateRaw) && $expDateRaw !== '0000-00-00') {
    $exp = new DateTime($expDateRaw);
    $lastInspection = ($lastInspectionDate && $lastInspectionDate !== '0000-00-00') ? new DateTime($lastInspectionDate) : null;

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

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vessel Dashboard - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .vessel-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .vessel-hero {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .vessel-hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
        }

        .vessel-title {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.1;
            margin: 0 0 6px;
        }

        .vessel-meta {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .vessel-quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vessel-quick-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .vessel-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .vessel-summary-card {
            background: #fff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            padding: 14px;
        }

        .vessel-summary-label {
            color: var(--vms-muted, #6b7280);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .vessel-summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .vessel-module-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr)); /* 👈 2 columns on mobile */
            gap: 12px;
            margin-bottom: 16px;
        }

        /* Tablet and up */
        @media (min-width: 768px) {
            .vessel-module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* Desktop */
        @media (min-width: 1100px) {
            .vessel-module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)); /* keep 2x4 layout */
            }
        }

        .vessel-module-card {
            background: #fff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            padding: 14px;
        }

        .vessel-module-title {
            font-size: 1rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .vessel-module-meta {
            color: var(--vms-muted, #6b7280);
            font-size: 0.92rem;
            margin: 0 0 12px;
        }

        .vessel-module-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vessel-module-actions .btn {
            border-radius: 12px;
        }

        .vessel-photo-wrap img,
        .vessel-photo-placeholder {
            height: 320px;
            width: 100%;
            object-fit: cover;
            border-radius: 16px;
        }

        .vessel-photo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border: 1px solid #dbe4ee;
            color: #6b7280;
        }

        .vessel-section-card .card-header {
            font-weight: 700;
        }

        .vessel-detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0;
        }

        .vessel-detail-item {
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }

        .vessel-detail-item:last-child {
            border-bottom: 0;
        }

        .vessel-detail-label {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .vessel-date-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .vessel-date-box {
            border: 1px solid #dbe4ee;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
        }

        .vessel-date-box strong {
            display: block;
            margin-bottom: 6px;
        }

        .icr-upcoming-scroll {
            max-height: 55vh;
            overflow-y: auto;
            padding-right: 4px;
        }

        #upcomingICRTable thead th {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 2;
        }

        @media (min-width: 768px) {
            .vessel-summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .vessel-module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .vessel-date-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .vessel-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                column-gap: 24px;
            }
        }

            @media (min-width: 1100px) {
                .vessel-module-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }
    </style>
</head>
<body class="bg-light">
<?php
$title = ($vessel['vesselName'] ?? 'Vessel') . ' Dashboard';
$back_link = "dashboard.php";
include __DIR__ . '/partials/top_nav.php';
?>

<div class="vessel-shell">
    <div class="app-page">
        <div class="app-container">

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ❌ <?= htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    ✅ Corrective action deleted.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['icr_added']) && $_GET['icr_added'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    ✅ ICR successfully added to this vessel.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="vms-card">
                <div class="vessel-hero">
                    <div class="vessel-hero-top">
                        <div>
                            <h1 class="vessel-title"><?= safe($vessel['vesselName']) ?></h1>
                            <p class="vessel-meta">
                                Official No. <?= safe($vessel['vesselON']) ?>
                                · Hailing Port: <?= safe($vessel['hailingPort']) ?>
                                · Call Sign: <?= safe($vessel['callSign']) ?>
                            </p>
                        </div>

                        <div class="vessel-quick-actions">
                            <a href="dashboard.php" class="btn btn-outline-secondary">Back</a>
                            <a href="vessel_profile_pdf.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-dark" target="_blank">Vessel Profile PDF</a>
                            <a href="vessel_qr_center.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-success">QR Codes</a>
                            <a href="vessel_messages.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
                                Messages
                                <?php if ($vesselUnreadCount > 0): ?>
                                    <span class="badge bg-danger ms-1"><?= (int)$vesselUnreadCount ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>

                    <div class="vessel-summary-grid">
                        <div class="vessel-summary-card">
                            <div class="vessel-summary-label">Unread Messages</div>
                            <div class="vessel-summary-value"><?= (int)$vesselUnreadCount ?></div>
                        </div>
                        <div class="vessel-summary-card">
                            <div class="vessel-summary-label">Next Inspection Type</div>
                            <div class="vessel-summary-value"><?= htmlspecialchars($inspectionType, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="vessel-summary-card">
                            <div class="vessel-summary-label">Inspection Window</div>
                            <div class="vessel-summary-value" style="font-size:1rem;"><?= htmlspecialchars($nextDueWindow, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="vessel-summary-card">
                            <div class="vessel-summary-label">Next Scheduled Inspection</div>
                            <div class="vessel-summary-value"><?= safeDate($vessel['nextScheduledInspection'] ?? null) ?></div>
                        </div>
                    </div>
                </div>
                       </div>

            <div class="vessel-module-grid mb-4">
                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Documents</h2>
                    <p class="vessel-module-meta">Vessel certificates, records, and uploads.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="add_document.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">Add</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Equipment</h2>
                    <p class="vessel-module-meta">Tracked onboard equipment and service records.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="add_equipment.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">Add</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Inspection Criteria Records</h2>
                    <p class="vessel-module-meta">Assigned ICRs, due items, and run history.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="add_vessel_icr.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">Assign</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Corrective Action Requirements</h2>
                    <p class="vessel-module-meta">Open issues, follow-up items, and closure tracking.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Tasks</a>
                        <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">Create CAR</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Voyage Logs</h2>
                    <p class="vessel-module-meta">Create and submit vessel voyage logs.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_log_create.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">History</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Drills</h2>
                    <p class="vessel-module-meta">Emergency drill records and history.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_drills.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="drill_history.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary btn-sm">History</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Crew</h2>
                    <p class="vessel-module-meta">Crew assignments and readiness view.</p>
                    <div class="vessel-module-actions">
                        <a href="vessel_crew.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                        <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">Manage Users</a>
                    </div>
                </div>

                <div class="vessel-module-card">
                    <h2 class="vessel-module-title">Schedule Inspection</h2>
                    <p class="vessel-module-meta">Coordinate inspection scheduling and outreach.</p>
                    <div class="vessel-module-actions">
                        <a href="schedule_inspection.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary btn-sm">Open Page</a>
                    </div>
                </div>
            </div>

            <div class="card vessel-section-card mb-4">
                <div class="card-header bg-secondary text-white">Vessel Identification</div>
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-lg-4 text-center">
                            <?php if (!empty($vessel['photo_path'])): ?>
                                <img src="<?= htmlspecialchars($vessel['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
                                     class="img-fluid rounded shadow border"
                                     alt="Vessel Photo">
                            <?php else: ?>
                                <div class="vessel-photo-placeholder position-relative shadow-sm">
                                    <span>No Photo Available</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Vessel Name</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['vesselName']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Official Number / Registration</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['vesselON']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Call Sign</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['callSign']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">MMSI</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['mmsi']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hailing Port</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['hailingPort']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">EPIRB Hex ID</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['epirbHexId']) ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Hull ID (HIN)</label>
                                    <input type="text" class="form-control" value="<?= safe($vessel['hin']) ?>" disabled>
                                </div>
                                <div class="col-md-12">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="flex-grow-1">
                                            <label class="form-label">Cognizant OCMI / Inspection Zone</label>
                                            <select class="form-select" disabled>
                                                <option>
                                                    <?php
                                                    $ocmiDisplay = '— Not Assigned —';
                                                    foreach ($contacts as $c) {
                                                        if ((int)($vessel['ocmi_contact_id'] ?? 0) === (int)$c['contact_id']) {
                                                            $ocmiDisplay = ocmi_label($c);
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($ocmiDisplay, ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <a href="edit_vessel.php?id=<?= (int)$vessel_id ?>" class="btn btn-primary">Edit Vessel</a>
                                        </div>
                                    </div>
                                    <div class="form-text mt-2">Use Edit Vessel to update vessel identification, vessel photo, OCMI assignment, and all other vessel record fields.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card vessel-section-card mb-4">
                <div class="card-header bg-primary text-white">Inspection Dates</div>
                <div class="card-body">
                    <form method="post" action="update_inspection_dates.php">
                        <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                        <div class="vessel-date-grid">
                            <div class="vessel-date-box">
                                <strong>COI Issue Date</strong>
                                <?= $coiIssue ?>
                            </div>

                            <div class="vessel-date-box">
                                <strong>COI Expiration Date</strong>
                                <?= $coiExp ?>
                            </div>

                            <div class="vessel-date-box">
                                <strong>Last Inspection Date</strong>
                                <input type="date" name="lastInspection" value="<?= htmlspecialchars($vessel['lastInspection'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                            </div>

                            <div class="vessel-date-box">
                                <strong>Next Scheduled Inspection</strong>
                                <input type="date" name="nextScheduledInspection" value="<?= htmlspecialchars($vessel['nextScheduledInspection'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                            </div>

                            <div class="vessel-date-box">
                                <strong>Next Inspection Type</strong>
                                <?= htmlspecialchars($inspectionType, ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <div class="vessel-date-box">
                                <strong>Inspection Window</strong>
                                <?= htmlspecialchars($nextDueWindow, ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <div class="vessel-date-box">
                                <strong>Last Dry Dock</strong>
                                <input type="date" name="lastDrydock" value="<?= htmlspecialchars($vessel['lastDrydock'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                            </div>

                            <div class="vessel-date-box">
                                <strong>Next Dry Dock</strong>
                                <input type="date" name="nextDrydock" value="<?= htmlspecialchars($vessel['nextDrydock'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                            </div>

                            <div class="vessel-date-box">
                                <strong>Next Mast Un-step</strong>
                                <input type="date" name="nextUnstep" value="<?= htmlspecialchars($vessel['nextUnstep'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary">Save Inspection Dates</button>
                        </div>
                    </form>
                </div>
            </div>

                <div class="card vessel-section-card mb-4">
                    <div class="card-header bg-info text-white">Vessel Details</div>
                    <div class="card-body">
                        <?php
                        function toInt($v) {
                            return (isset($v) && $v !== '') ? (int)$v : 0;
                        }

                        $pob_calculated =
                            toInt($vessel['master']) +
                            toInt($vessel['deckhands']) +
                            toInt($vessel['othersInCrew']) +
                            toInt($vessel['personInAddition']) +
                            toInt($vessel['passengers']);

                        $details = [
                        'Class' => $vessel['vesselClass'] ?? '—',
                        'Class Type' => $vessel['classType'] ?? '—',
                        'Service' => $vessel['vesselService'] ?? '—',
                        'Subchapter' => $vessel['inspSubChapter'] ?? '—',
                        'SIP' => !empty($vessel['sip']) ? 'Yes' : 'No',

                        'Master' => ($vessel['master'] !== null && $vessel['master'] !== '') ? $vessel['master'] : '—',
                        'Deckhands' => ($vessel['deckhands'] !== null && $vessel['deckhands'] !== '') ? $vessel['deckhands'] : '—',
                        'Others in Crew' => ($vessel['othersInCrew'] !== null && $vessel['othersInCrew'] !== '') ? $vessel['othersInCrew'] : '—',
                        'Persons in Addition to Crew' => ($vessel['personInAddition'] !== null && $vessel['personInAddition'] !== '') ? $vessel['personInAddition'] : '—',
                        'Passengers' => ($vessel['passengers'] !== null && $vessel['passengers'] !== '') ? $vessel['passengers'] : '—',
                        'Persons on Board' => $pob_calculated > 0 ? $pob_calculated : '—',

                        'Gross Tons' => $vessel['grossTons'] ?? '—',
                        'Net Tons' => $vessel['netTons'] ?? '—',
                        'Lightship Tons' => $vessel['lightshipTons'] ?? '—',
                        'Length Overall' => !empty($vessel['length']) ? $vessel['length'] . ' ft' : '—',
                        'Length Between Perpendiculars' => !empty($vessel['lbp']) ? $vessel['lbp'] . ' ft' : '—',
                        'Hull Material' => $vessel['hullMaterial'] ?? '—',
                        'Auxiliary Sail' => !empty($vessel['auxSail']) ? 'Yes' : 'No',
                        'Horsepower' => $vessel['horsepower'] ?? '—',
                        'Propulsion Type' => $vessel['propulsionType'] ?? '—',
                        'Route' => $vessel['route'] ?? '—',
                        'Waters' => $vessel['waters'] ?? '—',
                        'Keel Laid Date' => safeDate($vessel['keelLaidDate'] ?? null),
                        'Delivery Date' => safeDate($vessel['deliveryDate'] ?? null),
                    ];
                    ?>

                    <div class="vessel-detail-grid">
                        <?php foreach ($details as $label => $value): ?>
                            <div class="vessel-detail-item">
                                <div class="vessel-detail-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                                <div><?= safe($value) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="text-end px-3 pb-3">
                    <a href="edit_vessel.php?id=<?= (int)$vessel_id ?>" class="btn btn-primary">Edit Vessel Details</a>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const photoContainer = document.querySelector('.photo-container');
    const photoInput = document.getElementById('photoInput');

    if (photoContainer && photoInput) {
        photoContainer.addEventListener('click', function() {
            photoInput.click();
        });

        photoInput.addEventListener('change', function(e) {
            const preview = document.getElementById('photoPreview');
            if (!e.target.files.length || !preview) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                preview.src = event.target.result;
            };
            reader.readAsDataURL(e.target.files[0]);
        });
    }
});

function openModalFromHash() {
    const hash = window.location.hash;
    if (!hash) return;

    const modalEl = document.querySelector(hash);
    if (!modalEl || !modalEl.classList.contains('modal')) return;

    try {
        const instance = bootstrap.Modal.getOrCreateInstance(modalEl);
        instance.show();
    } catch (e) {
        console.error('Failed to open modal from hash:', hash, e);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    openModalFromHash();
    setTimeout(openModalFromHash, 150);
});

window.addEventListener('load', function () {
    openModalFromHash();
    setTimeout(openModalFromHash, 300);
});

window.addEventListener('hashchange', function () {
    openModalFromHash();
});

document.addEventListener('DOMContentLoaded', function () {
    const modalIds = [
        'documentsModal',
        'equipmentModal',
        'icrsModal',
        'tasksModal',
        'logsModal',
        'drillsModal',
        'crewModal'
    ];

    modalIds.forEach(function (id) {
        const modalEl = document.getElementById(id);
        if (!modalEl) return;

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (window.location.hash === '#' + id) {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const tab = new URLSearchParams(window.location.search).get('tab');
    if (tab === 'icrs') {
        const el = document.querySelector('[data-bs-toggle="tab"][data-bs-target="#icrsTab"], a[data-bs-toggle="tab"][href="#icrsTab"]');
        if (el) new bootstrap.Tab(el).show();
    }
});
</script>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
        appId: "<?= htmlspecialchars(ONESIGNAL_APP_ID, ENT_QUOTES, 'UTF-8') ?>"
    });

    const vmsUserId = <?= json_encode((string)($_SESSION['user_id'] ?? '')) ?>;
    if (vmsUserId) {
        await OneSignal.login(vmsUserId);
    }
});
</script>

</body>
</html>
