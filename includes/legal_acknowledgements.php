<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/legal_version.php';

session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Please sign in.";
    exit;
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId !== 1) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$search = trim((string)($_GET['q'] ?? ''));
$versionFilter = trim((string)($_GET['version'] ?? ''));

/* Summary counts */
$stmt = $pdo->query("SELECT COUNT(*) FROM user_legal_acknowledgements");
$totalAcknowledgements = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_legal_acknowledgements
    WHERE legal_version = ?
");
$stmt->execute([VMS_LEGAL_VERSION]);
$currentVersionAcknowledgements = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users u
    WHERE u.is_active = 1
      AND NOT EXISTS (
          SELECT 1
          FROM user_legal_acknowledgements ula
          WHERE ula.user_id = u.id
            AND ula.legal_version = ?
      )
");
$stmt->execute([VMS_LEGAL_VERSION]);
$missingCurrentAcknowledgement = (int)$stmt->fetchColumn();

/* Version dropdown options */
$versionStmt = $pdo->query("
    SELECT DISTINCT legal_version
    FROM user_legal_acknowledgements
    ORDER BY legal_version DESC
");
$availableVersions = $versionStmt->fetchAll(PDO::FETCH_COLUMN);

/* Main query */
$sql = "
    SELECT
        ula.ack_id,
        ula.legal_version,
        ula.accepted_at,
        ula.ip_address,
        ula.user_agent,
        u.id AS user_id,
        u.username,
        u.fName,
        u.lName,
        u.email,
        o.company_name
    FROM user_legal_acknowledgements ula
    INNER JOIN users u
        ON u.id = ula.user_id
    LEFT JOIN owners o
        ON o.owner_id = u.company_id
    WHERE 1 = 1
";

$params = [];

if ($versionFilter !== '') {
    $sql .= " AND ula.legal_version = ? ";
    $params[] = $versionFilter;
}

if ($search !== '') {
    $sql .= "
        AND (
            u.username LIKE ?
            OR u.email LIKE ?
            OR u.fName LIKE ?
            OR u.lName LIKE ?
            OR o.company_name LIKE ?
            OR CONCAT(COALESCE(u.fName,''), ' ', COALESCE(u.lName,'')) LIKE ?
        )
    ";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$sql .= " ORDER BY ula.accepted_at DESC, ula.ack_id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Legal Acknowledgements - VMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .legal-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .page-card,
        .summary-card,
        .filter-card,
        .table-card {
            border: 0;
            border-radius: 1rem;
        }

        .page-meta {
            color: #6b7280;
            margin: 0;
        }

        .summary-label {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.1;
            color: #1f2937;
        }

        .summary-subtext {
            margin-top: 0.35rem;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .current-version-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .4rem .8rem;
            font-size: .9rem;
            font-weight: 600;
            background: #eef5ff;
            color: #0d6efd;
            border: 1px solid #cfe2ff;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .table thead th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: top;
        }

        .ack-name {
            font-weight: 600;
            color: #1f2937;
        }

        .ack-meta {
            color: #6b7280;
            font-size: 0.88rem;
        }

        .ua-cell {
            max-width: 360px;
            white-space: normal;
            word-break: break-word;
            color: #495057;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .btn-stack-mobile .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Legal Acknowledgements';
$back_link = 'reports.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="legal-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-card p-3 p-md-4 mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h1 class="h3 mb-1">Legal Acknowledgements</h1>
                        <p class="page-meta">Review user acceptance history for Terms, Privacy Policy, and EULA.</p>
                    </div>
                    <div>
                        <span class="current-version-chip">
                            Current Version: <?= h(VMS_LEGAL_VERSION) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm summary-card p-3 h-100">
                        <div class="summary-label">Total Acknowledgements</div>
                        <div class="summary-value"><?= number_format($totalAcknowledgements) ?></div>
                        <div class="summary-subtext">All recorded acceptance events</div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm summary-card p-3 h-100">
                        <div class="summary-label">Current Version</div>
                        <div class="summary-value"><?= number_format($currentVersionAcknowledgements) ?></div>
                        <div class="summary-subtext">Accepted version <?= h(VMS_LEGAL_VERSION) ?></div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm summary-card p-3 h-100">
                        <div class="summary-label">Missing Current Ack</div>
                        <div class="summary-value"><?= number_format($missingCurrentAcknowledgement) ?></div>
                        <div class="summary-subtext">Active users without current acceptance</div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card shadow-sm summary-card p-3 h-100">
                        <div class="summary-label">Last Updated Label</div>
                        <div class="summary-value" style="font-size:1.1rem;"><?= h(VMS_LEGAL_LAST_UPDATED) ?></div>
                        <div class="summary-subtext">Current published legal date</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm filter-card p-3 mb-3">
                <form method="get">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label">Search</label>
                            <input
                                type="text"
                                class="form-control"
                                name="q"
                                value="<?= h($search) ?>"
                                placeholder="Name, username, email, or company"
                            >
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Legal Version</label>
                            <select name="version" class="form-select">
                                <option value="">All Versions</option>
                                <?php foreach ($availableVersions as $version): ?>
                                    <option value="<?= h((string)$version) ?>" <?= $versionFilter === (string)$version ? 'selected' : '' ?>>
                                        <?= h((string)$version) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <div class="d-flex flex-column flex-md-row gap-2 btn-stack-mobile">
                                <button type="submit" class="btn btn-primary">Apply</button>
                                <a href="legal_acknowledgements.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card shadow-sm table-card p-3">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h6 class="mb-0">Acknowledgement Log</h6>
                    <div class="text-muted small">
                        Showing <?= number_format(count($rows)) ?> record<?= count($rows) === 1 ? '' : 's' ?>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Company</th>
                                <th>Version</th>
                                <th>Accepted At</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No acknowledgements found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="ack-name">
                                                <?= h(trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''))) ?>
                                            </div>
                                            <div class="ack-meta"><?= h($row['username'] ?? '') ?></div>
                                            <div class="ack-meta"><?= h($row['email'] ?? '') ?></div>
                                        </td>
                                        <td><?= h($row['company_name'] ?? '—') ?></td>
                                        <td>
                                            <?= h($row['legal_version'] ?? '') ?>
                                            <?php if (($row['legal_version'] ?? '') === VMS_LEGAL_VERSION): ?>
                                                <div class="ack-meta">Current</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($row['accepted_at'] ?? '') ?></td>
                                        <td><?= h($row['ip_address'] ?? '—') ?></td>
                                        <td class="ua-cell"><?= h($row['user_agent'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="reports.php" class="btn btn-outline-secondary">Back to Reports</a>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>