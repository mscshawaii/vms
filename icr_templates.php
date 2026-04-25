<?php
require 'session_check.php';
require 'db_connect.php';

if (($_SESSION['company_id'] ?? 0) != 1) {
    die("❌ Access Denied: ICR templates can only be managed by MSCS Hawaii.");
}

$stmt = $pdo->query("
    SELECT i.icr_id, i.icr_number, i.title, i.reference_text, i.frequency, COUNT(s.step_id) AS steps
    FROM icrs i
    LEFT JOIN icr_steps s ON i.icr_id = s.icr_id
    GROUP BY i.icr_id
    ORDER BY i.icr_number
");
$icrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ICR Templates</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .icr-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .page-header-card,
        .results-card,
        .mobile-icr-card {
            border: 0;
            border-radius: 1rem;
        }

        .page-meta {
            color: #6b7280;
            margin: 0;
        }

        .mobile-icr-card {
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

        .results-table th {
            white-space: nowrap;
        }
    </style>
</head>
<body>
<?php
$title = 'ICR Templates';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="icr-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Inspection Criteria Reports (ICRs)</h1>
                            <p class="page-meta">Manage ICR templates, references, frequency, and step counts.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a href="add_icr.php" class="btn btn-primary">New ICR Template</a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Return to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm results-card p-3">
                <?php if (!$icrs): ?>
                    <div class="alert alert-info mb-0">No ICR templates found.</div>
                <?php else: ?>

                    <div class="d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle results-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ICR #</th>
                                        <th>Title</th>
                                        <th>Reference / Authorization</th>
                                        <th>Frequency</th>
                                        <th># Steps</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($icrs as $row): ?>
                                    <tr>
                                        <td><?= h($row['icr_number']) ?></td>
                                        <td><?= h($row['title']) ?></td>
                                        <td><?= nl2br(h($row['reference_text'])) ?></td>
                                        <td><?= h($row['frequency']) ?></td>
                                        <td><?= (int)$row['steps'] ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="edit_icr.php?id=<?= (int)$row['icr_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                                <a href="delete_icr.php?id=<?= (int)$row['icr_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this ICR?')">Delete</a>
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
                            <?php foreach ($icrs as $row): ?>
                                <div class="mobile-icr-card">
                                    <div class="mobile-row">
                                        <div class="mobile-label">ICR #</div>
                                        <div class="mobile-value fw-semibold"><?= h($row['icr_number']) ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Title</div>
                                        <div class="mobile-value"><?= h($row['title']) ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Reference / Authorization</div>
                                        <div class="mobile-value"><?= nl2br(h($row['reference_text'])) ?: '—' ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Frequency</div>
                                        <div class="mobile-value"><?= h($row['frequency']) ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="mobile-label">Steps</div>
                                        <div class="mobile-value"><?= (int)$row['steps'] ?></div>
                                    </div>

                                    <div class="mobile-row">
                                        <div class="d-grid gap-2">
                                            <a href="edit_icr.php?id=<?= (int)$row['icr_id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                            <a href="delete_icr.php?id=<?= (int)$row['icr_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this ICR?')">Delete</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>