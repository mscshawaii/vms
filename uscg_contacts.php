<?php
// uscg_contacts.php — list + search (MSCS-only)
session_start();
require 'session_check.php';
require 'db_connect.php';

$MSCS_OWNER_ID = 1;
if ((int)($_SESSION['company_id'] ?? 0) !== $MSCS_OWNER_ID) {
  http_response_code(403);
  exit('Forbidden');
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q'] ?? '');
$params = [];
$sql = "SELECT contact_id, region_name, port_name, email_to, email_cc, phone_display, active
        FROM uscg_contacts";
if ($q !== '') {
  $sql .= " WHERE region_name LIKE :q OR IFNULL(port_name,'') LIKE :q OR email_to LIKE :q";
  $params[':q'] = "%$q%";
}
$sql .= " ORDER BY region_name, port_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF for toggle links
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));

$activeCount = 0;
$inactiveCount = 0;
foreach ($rows as $r) {
    if ((int)$r['active'] === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>USCG Contacts</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0d6efd">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
<link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link href="/assets/css/vms-mobile.css" rel="stylesheet">

<style>
    .contacts-shell {
        background: var(--vms-bg, #f4f7fb);
        min-height: 100vh;
    }

    .page-header-card,
    .summary-card,
    .filter-card,
    .results-card,
    .mobile-contact-card {
        border: 0;
        border-radius: 1rem;
    }

    .page-meta {
        color: #6b7280;
        margin: 0;
    }

    .summary-label {
        color: #6b7280;
        font-size: 0.9rem;
        margin-bottom: 0.4rem;
    }

    .summary-value {
        font-size: 1.9rem;
        line-height: 1;
        font-weight: 700;
    }

    .results-table th {
        white-space: nowrap;
    }

    .mobile-contact-card {
        border: 1px solid #e5e7eb;
        padding: 1rem;
        background: #fff;
    }

    .mobile-row + .mobile-row {
        margin-top: .7rem;
        padding-top: .7rem;
        border-top: 1px solid #eef2f6;
    }

    .mobile-label {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6b7280;
        font-weight: 700;
        margin-bottom: .2rem;
    }

    .mobile-value {
        font-size: .96rem;
    }
</style>
</head>
<body>
<?php
$title = 'USCG Contacts';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="contacts-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">USCG Contacts</h1>
                            <p class="page-meta">Manage inspection zone and contact routing used across vessel workflows.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a class="btn btn-primary" href="uscg_contact_edit.php">Add Contact</a>
                            <a class="btn btn-outline-secondary" href="dashboard.php">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-success">
                        <div class="card-body">
                            <div class="summary-label">Active</div>
                            <div class="summary-value"><?= (int)$activeCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-secondary">
                        <div class="card-body">
                            <div class="summary-label">Inactive</div>
                            <div class="summary-value"><?= (int)$inactiveCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card shadow-sm summary-card h-100">
                        <div class="card-body">
                            <div class="summary-label">Rows Shown</div>
                            <div class="summary-value"><?= count($rows) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <form class="card shadow-sm filter-card p-3 mb-4" method="get">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-8 col-lg-6">
                        <label class="form-label">Search</label>
                        <input
                            type="text"
                            class="form-control"
                            name="q"
                            placeholder="Search island, port, or email..."
                            value="<?= h($q) ?>"
                        >
                    </div>

                    <div class="col-12 col-md-4 col-lg-6">
                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <button class="btn btn-primary" type="submit">Search</button>
                            <a class="btn btn-outline-secondary" href="uscg_contacts.php">Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card shadow-sm results-card p-3">
                <?php if (!$rows): ?>
                    <div class="alert alert-info mb-0">No contacts found.</div>
                <?php else: ?>

                    <div class="d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle results-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:170px;">Island / Region</th>
                                        <th style="min-width:170px;">Port (optional)</th>
                                        <th style="min-width:260px;">To</th>
                                        <th style="min-width:240px;">CC</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th class="text-end" style="min-width:220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><?= h($r['region_name']) ?></td>
                                            <td><?= h($r['port_name'] ?? '') ?></td>
                                            <td><?= h($r['email_to']) ?></td>
                                            <td class="small"><?= h($r['email_cc'] ?? '') ?></td>
                                            <td><?= h($r['phone_display'] ?? '') ?></td>
                                            <td>
                                                <?php if ((int)$r['active'] === 1): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                                    <a class="btn btn-sm btn-outline-primary"
                                                       href="uscg_contact_edit.php?id=<?= (int)$r['contact_id'] ?>">Edit</a>

                                                    <?php if ((int)$r['active'] === 1): ?>
                                                        <a class="btn btn-sm btn-outline-warning"
                                                           href="uscg_contact_toggle.php?id=<?= (int)$r['contact_id'] ?>&do=deactivate&csrf=<?= h($_SESSION['csrf_token']) ?>"
                                                           onclick="return confirm('Deactivate this contact?');">Deactivate</a>
                                                    <?php else: ?>
                                                        <a class="btn btn-sm btn-outline-success"
                                                           href="uscg_contact_toggle.php?id=<?= (int)$r['contact_id'] ?>&do=activate&csrf=<?= h($_SESSION['csrf_token']) ?>"
                                                           onclick="return confirm('Activate this contact?');">Activate</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-lg-none">
                        <div class="d-grid gap-3">
                            <?php foreach ($rows as $r): ?>
                                <div class="mobile-contact-card">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="mobile-label">Island / Region</div>
                                            <div class="mobile-value fw-semibold"><?= h($r['region_name']) ?></div>
                                        </div>
                                        <div>
                                            <?php if ((int)$r['active'] === 1): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Port</div>
                                        <div class="mobile-value"><?= h($r['port_name'] ?? '') ?: '—' ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">To</div>
                                        <div class="mobile-value"><?= h($r['email_to']) ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">CC</div>
                                        <div class="mobile-value"><?= h($r['email_cc'] ?? '') ?: '—' ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Phone</div>
                                        <div class="mobile-value"><?= h($r['phone_display'] ?? '') ?: '—' ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="d-grid gap-2">
                                            <a class="btn btn-outline-primary btn-sm"
                                               href="uscg_contact_edit.php?id=<?= (int)$r['contact_id'] ?>">Edit</a>

                                            <?php if ((int)$r['active'] === 1): ?>
                                                <a class="btn btn-outline-warning btn-sm"
                                                   href="uscg_contact_toggle.php?id=<?= (int)$r['contact_id'] ?>&do=deactivate&csrf=<?= h($_SESSION['csrf_token']) ?>"
                                                   onclick="return confirm('Deactivate this contact?');">Deactivate</a>
                                            <?php else: ?>
                                                <a class="btn btn-outline-success btn-sm"
                                                   href="uscg_contact_toggle.php?id=<?= (int)$r['contact_id'] ?>&do=activate&csrf=<?= h($_SESSION['csrf_token']) ?>"
                                                   onclick="return confirm('Activate this contact?');">Activate</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                Tip: Assign a contact to a vessel on the vessel’s edit page. The vessel dropdown pulls from this list.
            </div>

        </div>
    </div>
</div>
</body>
</html>