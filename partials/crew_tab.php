<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// crew_tab.php is typically included inside vessel_dashboard.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require 'db_connect.php';

$user_id    = $_SESSION['user_id'] ?? null;
$company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs    = ($company_id === 1);

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
if (!$user_id || !$vessel_id) {
    die("Access denied or missing vessel ID.");
}

// Get vessel details
$vessel_stmt = $pdo->prepare("
    SELECT vessel_id, company_id, vesselName, archived_at
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

// Company admins may only view vessels in their own company
if (!$is_mscs && $vessel_company_id !== $company_id) {
    http_response_code(403);
    die("Access denied.");
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
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h4 class="mb-1">Operational Crew Readiness</h4>
        <p class="text-muted mb-0">
            This view shows only active <strong>Master</strong> and <strong>Deckhand</strong> assignments for this vessel,
            along with key credential and readiness dates. User and vessel assignment changes are managed from the User Management pages.
        </p>
    </div>

    <div>
        <a href="manage_users.php" class="btn btn-primary">Manage Users</a>
    </div>
</div>

<?php if ($vessel_archived): ?>
    <div class="alert alert-warning">
        This vessel is archived. Crew readiness is shown for reference only.
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Vessel Role</th>
                <th>MMC Expiration</th>
                <th>MMC Medical</th>
                <th>First Aid</th>
                <th>MROP</th>
                <th>Readiness</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $crewStmt = $pdo->prepare("
            SELECT
                u.id AS user_id,
                u.fName,
                u.lName,
                u.mmc,
                u.mmc_medical,
                u.fa,
                u.mrop,
                vc.role
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

        if (!$rows) {
            echo '<tr><td colspan="7" class="text-center text-muted">No active Master or Deckhand assignments found for this vessel.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $name = htmlspecialchars(trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')));
                $role = htmlspecialchars($row['role'] ?? '—');

                $mmc = formatDateOrDash($row['mmc'] ?? null);
                $mmcMedical = formatDateOrDash($row['mmc_medical'] ?? null);
                $fa = formatDateOrDash($row['fa'] ?? null);
                $mrop = formatDateOrDash($row['mrop'] ?? null);

                $readiness = getReadinessSummary($row);

                echo '<tr>';
                echo '<td>' . $name . '</td>';
                echo '<td>' . $role . '</td>';
                echo '<td>' . htmlspecialchars($mmc) . '</td>';
                echo '<td>' . htmlspecialchars($mmcMedical) . '</td>';
                echo '<td>' . htmlspecialchars($fa) . '</td>';
                echo '<td>' . htmlspecialchars($mrop) . '</td>';
                echo '<td><span class="badge ' . htmlspecialchars($readiness['class']) . '">' . htmlspecialchars($readiness['label']) . '</span></td>';
                echo '</tr>';
            }
        }
        ?>
        </tbody>
    </table>
</div>