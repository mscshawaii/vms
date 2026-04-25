<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/db_connect.php';

$user_id    = $_SESSION['user_id'] ?? null;
$company_id = $_SESSION['company_id'] ?? null;
$is_mscs    = ((int)$company_id === 1);

$vessel_id = intval($_GET['vessel_id'] ?? 0);
if (!$user_id || !$vessel_id) {
    die("Access denied or missing vessel ID.");
}

// Confirm vessel and get company
$company_stmt = $pdo->prepare("
    SELECT vessel_id, company_id, vesselName, vesselON, hailingPort, archived_at
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$company_stmt->execute([$vessel_id]);
$vessel = $company_stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found.");
}

$vessel_company_id = (int)$vessel['company_id'];

if (!$is_mscs && $vessel_company_id !== (int)$company_id) {
    die("Access denied.");
}

// Fetch equipment for this vessel
$equip_stmt = $pdo->prepare("
    SELECT eid, equipmentName
    FROM equipment
    WHERE vessel_id = ?
    ORDER BY equipmentName
");
$equip_stmt->execute([$vessel_id]);
$equipment_result = $equip_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active vessel-assigned users only
$crew_stmt = $pdo->prepare("
    SELECT DISTINCT
        u.id,
        u.fName,
        u.lName,
        vc.role
    FROM vessel_crew vc
    INNER JOIN users u
        ON u.id = vc.crew_id
    WHERE vc.vessel_id = ?
      AND vc.is_active = 1
      AND u.is_active = 1
    ORDER BY
        FIELD(vc.role, 'Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'),
        u.lName,
        u.fName
");
$crew_stmt->execute([$vessel_id]);
$crew_result = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Today's date in Pacific/Honolulu for default/max on event date */
$todayHi = (new DateTime('now', new DateTimeZone('Pacific/Honolulu')))->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Corrective Action • <?= h($vessel['vesselName']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .tasks-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .form-section-card {
            border: 0;
            border-radius: 1rem;
        }
        .tasks-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .thumbs img {
            max-height: 88px;
            border: 1px solid #dee2e6;
            border-radius: .5rem;
            background: #fff;
            padding: .125rem;
        }
        .muted {
            color: #6c757d;
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
$title = 'Add Corrective Action';
$back_link = 'vessel_tasks.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="tasks-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card page-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Add Corrective Action</h1>
                            <p class="tasks-meta">
                                <?= h($vessel['vesselName']) ?>
                                <?php if (!empty($vessel['vesselON'])): ?>
                                    · Official No. <?= h($vessel['vesselON']) ?>
                                <?php endif; ?>
                                <?php if (!empty($vessel['hailingPort'])): ?>
                                    · Hailing Port: <?= h($vessel['hailingPort']) ?>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                Back to Corrective Actions
                            </a>
                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                Back to Vessel
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <form action="submit_task.php" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Core Details</div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Title<span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description / Details</label>
                            <textarea
                                name="description"
                                id="description"
                                rows="4"
                                class="form-control"
                                placeholder="Describe the corrective action..."
                            ></textarea>
                            <div class="form-text">
                                Use this for the original issue or deficiency description.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="notes" class="form-label">Notes / Running History</label>
                            <textarea
                                name="notes"
                                id="notes"
                                rows="5"
                                class="form-control"
                                placeholder="Troubleshooting, parts ordered, vendor contact, follow-up notes, questions, etc."
                            ></textarea>
                            <div class="form-text muted">
                                This is for running internal history and future thread-style context. The original issue should stay in Description.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Reference / Attachments</div>

                        <div class="mb-3">
                            <label for="regulation" class="form-label">Supporting regulation (optional)</label>
                            <input
                                type="text"
                                name="regulation"
                                id="regulation"
                                class="form-control"
                                placeholder="e.g., 46 CFR 122.320 or USCG MSM Vol II 6.B.1"
                            >
                            <div class="form-text muted">
                                Shown on task and can be appended to the description server-side if desired.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="photos" class="form-label">Attach photo(s) (optional)</label>
                            <input type="file" id="photos" name="photos[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">
                                JPG/PNG/GIF/WebP accepted. Up to 10 files, 10 MB each.
                            </div>
                            <div id="photoPreview" class="thumbs mt-3"></div>
                            <div id="photoErrors" class="text-danger small mt-2"></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-3">
                    <div class="card-body">
                        <div class="section-title">Assignment</div>

                        <div class="mb-3">
                            <label for="equipment_id" class="form-label">Associated Equipment (optional)</label>
                            <select name="equipment_id" id="equipment_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($equipment_result as $row): ?>
                                    <option value="<?= (int)$row['eid'] ?>"><?= h($row['equipmentName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Assigned To<span class="text-danger">*</span></label>
                            <select name="assigned_to" id="assigned_to" class="form-select" required>
                                <option value="">-- Select Primary Owner --</option>
                                <?php foreach ($crew_result as $row):
                                    $label = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                                    if (!empty($row['role'])) {
                                        $label .= ' (' . $row['role'] . ')';
                                    }
                                ?>
                                    <option value="<?= (int)$row['id'] ?>"><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text muted">
                                This is the primary person responsible for the corrective action.
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="notify_users" class="form-label">Notify / Keep Informed</label>
                            <select name="notify_users[]" id="notify_users" class="form-select" multiple size="6">
                                <?php foreach ($crew_result as $row):
                                    $label = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                                    if (!empty($row['role'])) {
                                        $label .= ' (' . $row['role'] . ')';
                                    }
                                ?>
                                    <option value="<?= (int)$row['id'] ?>"><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text muted">
                                Optional. These users will be saved as task notification recipients for future messaging/alerts.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Dates / Status</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="event_date" class="form-label">Occurrence / Completion date</label>
                                <input
                                    type="date"
                                    name="event_date"
                                    id="event_date"
                                    class="form-control"
                                    value="<?= $todayHi ?>"
                                    max="<?= $todayHi ?>"
                                    required
                                >
                                <div class="form-text muted">
                                    Use the actual date the issue occurred or, if setting status to Complete, the date it was finished. Future dates are not allowed.
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" name="due_date" id="due_date" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="priority" class="form-label">Priority</label>
                                <select name="priority" id="priority" class="form-select">
                                    <option value="moderate">Moderate</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="low">Low</option>
                                    <option value="recommendation">Recommendation</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="complete">Complete</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        Create Corrective Action
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="taskFormMobileProxy" class="d-none"></button>
            <button type="button" class="btn btn-success" onclick="document.querySelector('form[action=\'submit_task.php\']').requestSubmit();">
                Create Corrective Action
            </button>
            <a href="vessel_tasks.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </div>
</div>

<script>
(function(){
    const input = document.getElementById('photos');
    const preview = document.getElementById('photoPreview');
    const errBox = document.getElementById('photoErrors');
    const MAX_FILES = 10;
    const MAX_MB = 10;

    if (input) {
        input.addEventListener('change', function(){
            preview.innerHTML = '';
            errBox.textContent = '';

            const files = Array.from(this.files || []);
            if (files.length > MAX_FILES) {
                errBox.textContent = `Too many files selected (${files.length}). Max ${MAX_FILES}.`;
                this.value = '';
                return;
            }

            const tooBig = files.find(f => (f.size || 0) > MAX_MB * 1024 * 1024);
            if (tooBig) {
                errBox.textContent = `File "${tooBig.name}" is larger than ${MAX_MB} MB.`;
                this.value = '';
                return;
            }

            files.forEach(f => {
                if (!f.type.startsWith('image/')) return;
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = f.name;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(f);
            });
        });
    }

    const ed = document.getElementById('event_date');
    if (ed) {
        ed.addEventListener('change', function(){
            const max = this.getAttribute('max');
            if (this.value > max) this.value = max;
        });
    }
})();
</script>
</body>
</html>
