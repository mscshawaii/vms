<?php
require 'db_connect.php';
require 'session_check.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
| MSCS-only for now.
| Adjust this if you later want tighter user-specific control.
*/
$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id    = (int)($_SESSION['role_id'] ?? 0);

if ($company_id !== 1 && $role_id !== 1) {
    die('Access denied.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/
$q = trim($_GET['q'] ?? '');

/*
|--------------------------------------------------------------------------
| VESSELS + LAST FINAL FIRE SERVICE REPORT + LAST ACTIVE DOCUMENT
|--------------------------------------------------------------------------
|
| For now, the page only uses the active vessel document link for prior
| report viewing. The status/history box is intentionally removed until
| the new fire_service_reports workflow is fully populated.
|
*/
$sql = "
    SELECT
        v.vessel_id,
        v.vesselName,
        v.vesselON,
        v.company_id,

        fsr.report_number AS last_report_number,
        fsr.service_date AS last_service_date,
        fsr.fire_service_report_id AS last_report_id,

        d.id AS last_document_id
    FROM vessels v
    LEFT JOIN fire_service_reports fsr
        ON fsr.fire_service_report_id = (
            SELECT fsr2.fire_service_report_id
            FROM fire_service_reports fsr2
            WHERE fsr2.vessel_id = v.vessel_id
              AND fsr2.status = 'final'
              AND fsr2.archived_at IS NULL
            ORDER BY fsr2.service_date DESC, fsr2.fire_service_report_id DESC
            LIMIT 1
        )
    LEFT JOIN documents d
        ON d.id = (
            SELECT d2.id
            FROM documents d2
            WHERE d2.vessel_id = v.vessel_id
              AND d2.archived_at IS NULL
              AND (
                    d2.docType = 'Fire Equipment Servicing'
                 OR d2.docName = 'Fire Equipment Servicing'
              )
            ORDER BY d2.uploaded_on DESC, d2.id DESC
            LIMIT 1
        )
    WHERE v.is_active = 1
      AND v.archived_at IS NULL
";

$params = [];

if ($q !== '') {
    $sql .= " AND (
        v.vesselName LIKE ?
        OR v.vesselON LIKE ?
    )";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY v.vesselName ASC, v.vesselON ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vessels = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGE META
|--------------------------------------------------------------------------
*/
$title = 'Annual Fire Equipment Service';
$back_link = 'dashboard.php?company_id=' . (int)$company_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Annual Fire Equipment Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .service-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .service-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .service-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .search-form .form-control,
        .search-form .btn {
            min-height: 44px;
            border-radius: 12px;
        }

        .vessel-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            height: 100%;
        }

        .vessel-card .card-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .vessel-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .vessel-meta {
            color: #6b7280;
            margin-bottom: 18px;
        }

        .empty-state {
            text-align: center;
            color: #6b7280;
            padding: 28px 16px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="service-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card mb-4">
                <div class="service-header">
                    <div>
                        <h1 class="service-title">Annual Fire Equipment Service</h1>
                        <p class="service-subtitle">
                            Select a vessel to begin the annual fire equipment maintenance workflow.
                        </p>
                    </div>

                    <div>
                        <a href="dashboard.php?company_id=<?= (int)$company_id ?>" class="btn btn-outline-secondary">
                            Back to Company Dashboard
                        </a>
                    </div>
                </div>

                <form method="get" class="search-form">
                    <div class="row g-2">
                        <div class="col-md-9">
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                placeholder="Search by vessel name or official number"
                                value="<?= h($q) ?>"
                            >
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary">
                                Search Vessels
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!$vessels): ?>
                <div class="vms-card">
                    <div class="empty-state">
                        No matching vessels found.
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($vessels as $v): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <div class="vessel-card">
                                <div class="card-body">
                                    <div class="vessel-name">
                                        <?= h($v['vesselName']) ?>
                                    </div>

                                    <div class="vessel-meta">
                                        ON <?= h($v['vesselON']) ?>
                                    </div>

                                    <div class="mt-auto d-grid gap-2">
                                        <a
                                            href="start_fire_equipment_service.php?vessel_id=<?= (int)$v['vessel_id'] ?>"
                                            class="btn btn-primary"
                                        >
                                            Start Service
                                        </a>

                                        <?php if (!empty($v['last_document_id'])): ?>
                                            <a
                                                href="view_document.php?id=<?= (int)$v['last_document_id'] ?>"
                                                class="btn btn-outline-secondary"
                                            >
                                                View Last Report
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>