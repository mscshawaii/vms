<?php
require 'session_check.php';
require 'db_connect.php';

if (($_SESSION['company_id'] ?? 0) != 1) {
    echo "Access denied.";
    exit;
}

// Fetch companies with vessel counts
$stmt = $pdo->query("
    SELECT o.*, COUNT(v.vessel_id) AS vessel_count
    FROM owners o
    LEFT JOIN vessels v ON o.owner_id = v.company_id
    GROUP BY o.owner_id
    ORDER BY o.company_name
");
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Companies</title>
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
        .results-card,
        .mobile-company-card {
            border: 0;
            border-radius: 1rem;
        }
        .page-meta {
            color: #6b7280;
            margin: 0;
        }
        .mobile-company-card {
            border: 1px solid #e5e7eb;
            padding: 1rem;
            background: #fff;
        }
        .mobile-row + .mobile-row {
            margin-top: .65rem;
            padding-top: .65rem;
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
$title = 'Manage Companies';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="companies-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Companies</h1>
                            <p class="page-meta">Manage company records, contacts, and vessel ownership.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="add_company.php" class="btn btn-primary">Create Company</a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Company deleted successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">Company updated successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['created'])): ?>
                <div class="alert alert-success">Company created successfully.</div>
            <?php endif; ?>

            <div class="card shadow-sm results-card p-3">
                <div class="d-none d-lg-block">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Company Name</th>
                                    <th>Contact</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th class="text-center">Vessels</th>
                                    <th style="width:320px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td><?= h($company['company_name'] ?? '') ?></td>
                                    <td><?= h($company['contact_name'] ?? '') ?></td>
                                    <td><?= h($company['email'] ?? '') ?></td>
                                    <td><?= h($company['phone'] ?? '') ?></td>
                                    <td class="text-center"><?= (int)$company['vessel_count'] ?></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="view_company.php?id=<?= (int)$company['owner_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                            <a href="edit_company.php?id=<?= (int)$company['owner_id'] ?>" class="btn btn-sm btn-primary">Edit</a>

                                            <?php if ((int)$company['vessel_count'] === 0): ?>
                                                <a href="delete_company.php?id=<?= (int)$company['owner_id'] ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Delete this company? This cannot be undone.');">
                                                   Delete
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                        title="Cannot delete a company that has vessels">
                                                    Delete
                                                </button>
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
                        <?php foreach ($companies as $company): ?>
                            <div class="mobile-company-card">
                                <div class="mobile-row">
                                    <div class="mobile-label">Company</div>
                                    <div class="mobile-value fw-semibold"><?= h($company['company_name'] ?? '') ?></div>
                                </div>

                                <div class="mobile-row">
                                    <div class="mobile-label">Contact</div>
                                    <div class="mobile-value"><?= h($company['contact_name'] ?? '') ?: '—' ?></div>
                                </div>

                                <div class="mobile-row">
                                    <div class="mobile-label">Email</div>
                                    <div class="mobile-value"><?= h($company['email'] ?? '') ?: '—' ?></div>
                                </div>

                                <div class="mobile-row">
                                    <div class="mobile-label">Phone</div>
                                    <div class="mobile-value"><?= h($company['phone'] ?? '') ?: '—' ?></div>
                                </div>

                                <div class="mobile-row">
                                    <div class="mobile-label">Vessels</div>
                                    <div class="mobile-value"><?= (int)$company['vessel_count'] ?></div>
                                </div>

                                <div class="mobile-row">
                                    <div class="d-grid gap-2">
                                        <a href="view_company.php?id=<?= (int)$company['owner_id'] ?>" class="btn btn-outline-secondary btn-sm">View</a>
                                        <a href="edit_company.php?id=<?= (int)$company['owner_id'] ?>" class="btn btn-primary btn-sm">Edit</a>

                                        <?php if ((int)$company['vessel_count'] === 0): ?>
                                            <a href="delete_company.php?id=<?= (int)$company['owner_id'] ?>"
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Delete this company? This cannot be undone.');">
                                               Delete
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>