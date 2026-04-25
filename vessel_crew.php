<?php
require_once 'session_check.php';
require_once 'db_connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function safe($value) {
    return !empty($value) ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '—';
}

function formatDateOrDash($dateValue): string
{
    if (empty($dateValue) || $dateValue === '0000-00-00') {
        return '—';
    }

    $ts = strtotime($dateValue);
    if (!$ts) {
        return '—';
    }

    return date('Y-m-d', $ts);
}

function getDateStatus(?string $dateValue, int $soonDays = 30): array
{
    if (empty($dateValue) || $dateValue === '0000-00-00') {
        return [
            'label' => 'Missing',
            'class' => 'text-bg-secondary'
        ];
    }

    $today = strtotime(date('Y-m-d'));
    $target = strtotime($dateValue);

    if ($target === false) {
        return [
            'label' => 'Invalid',
            'class' => 'text-bg-secondary'
        ];
    }

    $days = (int)floor(($target - $today) / 86400);

    if ($days < 0) {
        return [
            'label' => 'Expired',
            'class' => 'text-bg-danger'
        ];
    }

    if ($days <= $soonDays) {
        return [
            'label' => 'Expiring Soon',
            'class' => 'text-bg-warning'
        ];
    }

    return [
        'label' => 'Current',
        'class' => 'text-bg-success'
    ];
}

function getReadinessSummary(array $user): array
{
    $fields = ['mmc', 'mmc_medical', 'fa', 'mrop'];

    $hasMissing = false;
    $hasExpired = false;
    $hasSoon = false;

    foreach ($fields as $field) {
        $status = getDateStatus($user[$field] ?? null);

        if ($status['label'] === 'Missing' || $status['label'] === 'Invalid') {
            $hasMissing = true;
        } elseif ($status['label'] === 'Expired') {
            $hasExpired = true;
        } elseif ($status['label'] === 'Expiring Soon') {
            $hasSoon = true;
        }
    }

    if ($hasExpired || $hasMissing) {
        return [
            'label' => 'Attention Needed',
            'class' => 'text-bg-danger'
        ];
    }

    if ($hasSoon) {
        return [
            'label' => 'Expiring Soon',
            'class' => 'text-bg-warning'
        ];
    }

    return [
        'label' => 'Ready',
        'class' => 'text-bg-success'
    ];
}

$user_id    = $_SESSION['user_id'] ?? null;
$company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs    = ($company_id === 1);
$vessel_id  = (int)($_GET['vessel_id'] ?? 0);

if (!$user_id || !$vessel_id) {
    die("Access denied or missing vessel ID.");
}

$vessel_stmt = $pdo->prepare("
    SELECT vessel_id, company_id, vesselName, archived_at, vesselON, hailingPort
    FROM vessels
    WHERE vessel_id = ?
");
$vessel_stmt->execute([$vessel_id]);
$vessel = $vessel_stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die("Vessel not found.");
}

$vessel_company_id = (int)$vessel['company_id'];
$vessel_archived = !empty($vessel['archived_at']);

if (!$is_mscs && $vessel_company_id !== $company_id) {
    http_response_code(403);
    die("Access denied.");
}

$crewStmt = $pdo->prepare("
    SELECT
        u.id AS user_id,
        u.fName,
        u.lName,
        u.mmc,
        u.mmc_medical,
        u.fa,
        u.mrop,
        vc.role,
        vc.assigned_on
    FROM vessel_crew vc
    INNER JOIN users u ON vc.crew_id = u.id
    WHERE vc.vessel_id = ?
      AND vc.is_active = 1
      AND u.is_active = 1
      AND vc.role IN ('Master', 'Deckhand')
    ORDER BY
        FIELD(vc.role, 'Master', 'Deckhand'),
        u.lName,
        u.fName
");
$crewStmt->execute([$vessel_id]);
$rows = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crew - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .crew-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .crew-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .crew-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .crew-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .crew-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .crew-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .crew-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }

        .crew-summary-card {
            background: #fff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            padding: 14px;
        }

        .crew-summary-label {
            color: var(--vms-muted, #6b7280);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }

        .crew-summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.15;
        }

        .crew-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .crew-table-wrap table {
            min-width: 860px;
            margin-bottom: 0;
        }

        .crew-note {
            color: var(--vms-muted, #6b7280);
            margin-bottom: 0;
        }

        @media (min-width: 768px) {
            .crew-summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<?php
$title = ($vessel['vesselName'] ?? 'Vessel') . ' Crew';
$back_link = "vessel_dashboard.php?vessel_id=" . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="crew-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="crew-header">
                    <div>
                        <h1 class="crew-title">Operational Crew Readiness</h1>
                        <p class="crew-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Official No. <?= safe($vessel['vesselON']) ?> · Hailing Port: <?= safe($vessel['hailingPort']) ?>
                        </p>
                    </div>

                    <div class="crew-actions">
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                        <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
                    </div>
                </div>

                <div class="crew-summary-grid">
                    <div class="crew-summary-card">
                        <div class="crew-summary-label">Operational Crew</div>
                        <div class="crew-summary-value"><?= (int)count($rows) ?></div>
                    </div>

                    <div class="crew-summary-card">
                        <div class="crew-summary-label">Masters</div>
                        <div class="crew-summary-value"><?= (int)count(array_filter($rows, fn($r) => ($r['role'] ?? '') === 'Master')) ?></div>
                    </div>

                    <div class="crew-summary-card">
                        <div class="crew-summary-label">Deckhands</div>
                        <div class="crew-summary-value"><?= (int)count(array_filter($rows, fn($r) => ($r['role'] ?? '') === 'Deckhand')) ?></div>
                    </div>

                    <div class="crew-summary-card">
                        <div class="crew-summary-label">Archived Vessel</div>
                        <div class="crew-summary-value"><?= $vessel_archived ? 'Yes' : 'No' ?></div>
                    </div>
                </div>

                <p class="crew-note">
                    This view shows only active <strong>Master</strong> and <strong>Deckhand</strong> assignments for this vessel, along with key credential and readiness dates. User and vessel assignment changes are managed from the User Management pages.
                </p>
            </div>

            <?php if ($vessel_archived): ?>
                <div class="alert alert-warning">
                    This vessel is archived. Crew readiness is shown for reference only.
                </div>
            <?php endif; ?>

            <div class="vms-card">
                <?php if (!$rows): ?>
                    <div class="p-3 text-muted text-center">
                        No active Master or Deckhand assignments found for this vessel.
                    </div>
                <?php else: ?>
                    <div class="crew-table-wrap">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Vessel Role</th>
                                    <th>Assigned On</th>
                                    <th>MMC Expiration</th>
                                    <th>MMC Medical</th>
                                    <th>First Aid</th>
                                    <th>MROP</th>
                                    <th>Readiness</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php
                                    $name = htmlspecialchars(trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')), ENT_QUOTES, 'UTF-8');
                                    $role = htmlspecialchars($row['role'] ?? '—', ENT_QUOTES, 'UTF-8');

                                    $mmc = formatDateOrDash($row['mmc'] ?? null);
                                    $mmcMedical = formatDateOrDash($row['mmc_medical'] ?? null);
                                    $fa = formatDateOrDash($row['fa'] ?? null);
                                    $mrop = formatDateOrDash($row['mrop'] ?? null);
                                    $assignedOn = formatDateOrDash($row['assigned_on'] ?? null);

                                    $readiness = getReadinessSummary($row);
                                    ?>
                                    <tr>
                                        <td><?= $name ?></td>
                                        <td><?= $role ?></td>
                                        <td><?= htmlspecialchars($assignedOn, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($mmc, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($mmcMedical, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($fa, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($mrop, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge <?= htmlspecialchars($readiness['class'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($readiness['label'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

</body>
</html>