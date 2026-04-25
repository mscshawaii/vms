<?php
require 'db_connect.php';
require 'session_check.php';

// Restrict to MSCS users only
if (($_SESSION['company_id'] ?? 0) != 1) {
    die("Access denied.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = (int)($_POST['group_id'] ?? 0);
    $vessel_ids = $_POST['vessel_ids'] ?? [];

    if ($group_id <= 0) {
        header("Location: link_vessels.php?error=invalid_group");
        exit;
    }

    // Clear old assignments
    $stmt = $pdo->prepare("DELETE FROM linked_vessels WHERE group_id = ?");
    $stmt->execute([$group_id]);

    // Add new links
    $insert = $pdo->prepare("INSERT INTO linked_vessels (group_id, vessel_id) VALUES (?, ?)");
    foreach ($vessel_ids as $vid) {
        $insert->execute([$group_id, (int)$vid]);
    }

    header("Location: link_vessels.php?success=1&group_id=" . $group_id);
    exit;
}

// Selected group for editing
$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;

// Get all vessels, grouped with company
$vessels = $pdo->query("
    SELECT
        v.vessel_id,
        v.vesselName,
        v.company_id,
        COALESCE(o.company_name, 'Unassigned Company') AS company_name
    FROM vessels v
    LEFT JOIN owners o
        ON o.owner_id = v.company_id
    WHERE v.archived_at IS NULL
    ORDER BY o.company_name, v.vesselName
")->fetchAll(PDO::FETCH_ASSOC);

// Get existing links
$link_rows = $pdo->query("
    SELECT lv.group_id, lv.vessel_id, v.vesselName, COALESCE(o.company_name, 'Unassigned Company') AS company_name
    FROM linked_vessels lv
    LEFT JOIN vessels v
        ON v.vessel_id = lv.vessel_id
    LEFT JOIN owners o
        ON o.owner_id = v.company_id
    ORDER BY lv.group_id, o.company_name, v.vesselName
")->fetchAll(PDO::FETCH_ASSOC);

// Build map: group => vessel ids / names
$link_map = [];
foreach ($link_rows as $row) {
    $gid = (int)$row['group_id'];
    if (!isset($link_map[$gid])) {
        $link_map[$gid] = [
            'vessel_ids' => [],
            'vessels' => [],
        ];
    }

    $link_map[$gid]['vessel_ids'][] = (int)$row['vessel_id'];
    $link_map[$gid]['vessels'][] = [
        'vessel_id' => (int)$row['vessel_id'],
        'vesselName' => $row['vesselName'] ?? ('Vessel #' . $row['vessel_id']),
        'company_name' => $row['company_name'] ?? 'Unassigned Company',
    ];
}

// If no selected group but success redirect passed one, use it
if ($selected_group_id <= 0 && isset($_GET['group_id'])) {
    $selected_group_id = (int)$_GET['group_id'];
}

// If still no selected group, default to first existing group if available
if ($selected_group_id <= 0 && !empty($link_map)) {
    $selected_group_id = (int)array_key_first($link_map);
}

$selected_vessel_ids = $link_map[$selected_group_id]['vessel_ids'] ?? [];

// Group all vessels by company for display
$vessels_by_company = [];
foreach ($vessels as $v) {
    $company = $v['company_name'] ?? 'Unassigned Company';
    if (!isset($vessels_by_company[$company])) {
        $vessels_by_company[$company] = [];
    }
    $vessels_by_company[$company][] = $v;
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Linked Vessel Groups</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .groups-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .group-card,
        .editor-card,
        .company-card {
            border: 0;
            border-radius: 1rem;
        }
        .page-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .group-card.active-group {
            outline: 2px solid #0d6efd;
            box-shadow: 0 0 0 0.15rem rgba(13,110,253,.15);
        }
        .group-pill {
            font-size: .78rem;
            color: #6b7280;
        }
        .company-block + .company-block {
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #eef2f6;
        }
        .company-heading {
            font-weight: 700;
            margin-bottom: .75rem;
        }
        .vessel-check-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: .5rem;
        }
        .vessel-check-item {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
            padding: .65rem .8rem;
        }
        @media (min-width: 768px) {
            .vessel-check-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1100px) {
            .vessel-check-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
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
$title = 'Linked Vessel Groups';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="groups-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Linked Vessel Groups</h1>
                            <p class="page-meta">Create or update vessel groupings for shared workflows and linked operations.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Vessel group updated.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_group'): ?>
                <div class="alert alert-danger">Please enter a valid group ID.</div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3 group-card">
                <div class="card-body">
                    <div class="section-title">Existing Groups</div>

                    <?php if (empty($link_map)): ?>
                        <div class="text-muted">No vessel groups linked yet.</div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($link_map as $gid => $groupData): ?>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <a href="link_vessels.php?group_id=<?= (int)$gid ?>"
                                       class="card shadow-sm text-decoration-none text-reset group-card <?= ((int)$gid === (int)$selected_group_id) ? 'active-group' : '' ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <div class="fw-bold">Group <?= (int)$gid ?></div>
                                                <div class="group-pill"><?= count($groupData['vessel_ids']) ?> vessel(s)</div>
                                            </div>

                                            <div class="small text-muted">
                                                <?php
                                                $names = array_map(fn($v) => $v['vesselName'], $groupData['vessels']);
                                                echo h(implode(', ', $names));
                                                ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form id="linkVesselsForm" method="post">
                <div class="card shadow-sm editor-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Group Editor</div>

                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="group_id" class="form-label">Group ID</label>
                                <input type="number"
                                       name="group_id"
                                       id="group_id"
                                       class="form-control"
                                       value="<?= $selected_group_id > 0 ? (int)$selected_group_id : '' ?>"
                                       required>
                                <div class="form-text">
                                    Select an existing group above, or enter a new numeric group ID to create one.
                                </div>
                            </div>
                        </div>

                        <div class="section-title">Available Vessels</div>

                        <?php foreach ($vessels_by_company as $companyName => $companyVessels): ?>
                            <div class="company-block">
                                <div class="company-heading"><?= h($companyName) ?></div>

                                <div class="vessel-check-grid">
                                    <?php foreach ($companyVessels as $v): ?>
                                        <?php $checked = in_array((int)$v['vessel_id'], $selected_vessel_ids, true); ?>
                                        <label class="vessel-check-item">
                                            <div class="form-check m-0">
                                                <input class="form-check-input"
                                                       type="checkbox"
                                                       name="vessel_ids[]"
                                                       value="<?= (int)$v['vessel_id'] ?>"
                                                       id="v<?= (int)$v['vessel_id'] ?>"
                                                       <?= $checked ? 'checked' : '' ?>>
                                                <span class="form-check-label" for="v<?= (int)$v['vessel_id'] ?>">
                                                    <?= h($v['vesselName']) ?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Group</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="linkVesselsForm" class="btn btn-primary">Save Group</button>
            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>