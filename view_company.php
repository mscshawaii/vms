<?php
require 'session_check.php';
require 'db_connect.php';

if (($_SESSION['company_id'] ?? 0) != 1) {
    echo "Access denied.";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "Invalid company id.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT o.*,
           pc.fName AS primary_fName,
           pc.lName AS primary_lName,
           ac.fName AS alt_fName,
           ac.lName AS alt_lName
    FROM owners o
    LEFT JOIN users pc ON pc.id = o.primary_contact_user_id
    LEFT JOIN users ac ON ac.id = o.alt_contact_user_id
    WHERE o.owner_id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    echo "Company not found.";
    exit;
}

$vesselStmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON, hailingPort, is_active
    FROM vessels
    WHERE company_id = ?
    ORDER BY vesselName
");
$vesselStmt->execute([$id]);
$vessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$primaryContact = trim(($company['primary_fName'] ?? '') . ' ' . ($company['primary_lName'] ?? ''));
$altContact = trim(($company['alt_fName'] ?? '') . ' ' . ($company['alt_lName'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Company</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .companies-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .detail-card,
        .results-card {
            border: 0;
            border-radius: 1rem;
        }
        .page-meta {
            color: #6b7280;
            margin: 0;
        }
        .detail-row + .detail-row {
            margin-top: .85rem;
            padding-top: .85rem;
            border-top: 1px solid #eef1f4;
        }
        .detail-label {
            font-size: .84rem;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #6b7280;
            margin-bottom: .2rem;
            font-weight: 700;
        }
        .detail-value {
            font-size: .98rem;
            color: #212529;
        }
        .logo-preview {
            max-height: 70px;
            width: auto;
            object-fit: contain;
            border: 1px solid #ddd;
            padding: 4px;
            background: #fff;
            border-radius: .5rem;
        }
        .mobile-vessel-card {
            border: 1px solid #e5e7eb;
            border-radius: .9rem;
            padding: .9rem;
            background: #fff;
        }
    </style>
</head>
<body>
<?php
$title = 'View Company';
$back_link = 'view_companies.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="companies-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1"><?= h($company['company_name'] ?? '') ?></h1>
                            <p class="page-meta">Company overview and assigned vessels.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="edit_company.php?id=<?= (int)$company['owner_id'] ?>" class="btn btn-primary">Edit</a>
                            <a href="view_companies.php" class="btn btn-outline-secondary">Back to Companies</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-lg-5">
                    <div class="card shadow-sm detail-card">
                        <div class="card-body">
                            <?php if (!empty($company['logo_path'])): ?>
                                <div class="mb-3">
                                    <img src="<?= h($company['logo_path']) ?>" alt="Company Logo" class="logo-preview">
                                </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <div class="detail-label">Contact Name</div>
                                <div class="detail-value"><?= h($company['contact_name'] ?? '') ?: '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?= h($company['email'] ?? '') ?: '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Phone</div>
                                <div class="detail-value"><?= h($company['phone'] ?? '') ?: '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Address</div>
                                <div class="detail-value"><?= nl2br(h($company['address'] ?? '')) ?: '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Primary Contact User</div>
                                <div class="detail-value"><?= $primaryContact !== '' ? h($primaryContact) : '—' ?></div>
                            </div>

                            <div class="detail-row">
                                <div class="detail-label">Alternate Contact User</div>
                                <div class="detail-value"><?= $altContact !== '' ? h($altContact) : '—' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm results-card p-3">
                        <h2 class="h5 mb-3">Vessels</h2>

                        <?php if (!$vessels): ?>
                            <div class="alert alert-info mb-0">No vessels assigned to this company.</div>
                        <?php else: ?>

                            <div class="d-none d-lg-block">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Vessel</th>
                                                <th>ON</th>
                                                <th>Hailing Port</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vessels as $v): ?>
                                                <tr>
                                                    <td><?= h($v['vesselName']) ?></td>
                                                    <td><?= h($v['vesselON']) ?></td>
                                                    <td><?= h($v['hailingPort']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= ((int)$v['is_active'] === 1) ? 'success' : 'secondary' ?>">
                                                            <?= ((int)$v['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$v['vessel_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                            View Vessel
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="d-lg-none">
                                <div class="d-grid gap-3">
                                    <?php foreach ($vessels as $v): ?>
                                        <div class="mobile-vessel-card">
                                            <div class="fw-semibold mb-2"><?= h($v['vesselName']) ?></div>
                                            <div class="small text-muted mb-1">ON: <?= h($v['vesselON']) ?: '—' ?></div>
                                            <div class="small text-muted mb-2">Hailing Port: <?= h($v['hailingPort']) ?: '—' ?></div>
                                            <div class="mb-2">
                                                <span class="badge bg-<?= ((int)$v['is_active'] === 1) ? 'success' : 'secondary' ?>">
                                                    <?= ((int)$v['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </div>
                                            <a href="vessel_dashboard.php?vessel_id=<?= (int)$v['vessel_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">
                                                View Vessel
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>