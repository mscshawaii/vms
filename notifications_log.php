<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/acl.php';

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Please sign in.";
    exit;
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
$isMSCS    = ($companyId === 1);

// -----------------------------
// Inputs / Filters
// -----------------------------
$vesselId      = isset($_GET['vessel_id']) && $_GET['vessel_id'] !== '' ? (int)$_GET['vessel_id'] : null;
$emailSearch   = trim((string)($_GET['recipient_email'] ?? ''));
$statusFilter  = trim((string)($_GET['email_status'] ?? ''));
$recipientType = trim((string)($_GET['recipient_type'] ?? ''));
$sourceFilter  = trim((string)($_GET['source'] ?? ''));
$dateFrom      = trim((string)($_GET['date_from'] ?? ''));
$dateTo        = trim((string)($_GET['date_to'] ?? ''));
$limit         = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 100;

// -----------------------------
// Allowed vessel scope
// -----------------------------
function getAllowedVesselIdsForUser(PDO $pdo, ?int $requestedVesselId): array
{
    $companyId = (int)($_SESSION['company_id'] ?? 0);
    $isMSCS    = ($companyId === 1);

    if ($isMSCS) {
        if ($requestedVesselId) {
            return [$requestedVesselId];
        }

        $stmt = $pdo->query("
            SELECT vessel_id
            FROM vessels
            WHERE is_active = 1
              AND archived_at IS NULL
              AND is_deleted = 0
            ORDER BY vesselName
        ");
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($requestedVesselId) {
        $stmt = $pdo->prepare("
            SELECT vessel_id
            FROM vessels
            WHERE vessel_id = ?
              AND company_id = ?
              AND is_active = 1
              AND archived_at IS NULL
              AND is_deleted = 0
            LIMIT 1
        ");
        $stmt->execute([$requestedVesselId, $companyId]);
        $row = $stmt->fetchColumn();
        return $row ? [(int)$row] : [];
    }

    $stmt = $pdo->prepare("
        SELECT vessel_id
        FROM vessels
        WHERE company_id = ?
          AND is_active = 1
          AND archived_at IS NULL
          AND is_deleted = 0
        ORDER BY vesselName
    ");
    $stmt->execute([$companyId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$allowedVesselIds = getAllowedVesselIdsForUser($pdo, $vesselId);

if (empty($allowedVesselIds)) {
    http_response_code(403);
    echo "Not allowed to access these vessels.";
    exit;
}

// -----------------------------
// Vessel dropdown data
// -----------------------------
$placeholders = implode(',', array_fill(0, count($allowedVesselIds), '?'));

$vesselStmt = $pdo->prepare("
    SELECT vessel_id, vesselName
    FROM vessels
    WHERE vessel_id IN ($placeholders)
    ORDER BY vesselName
");
$vesselStmt->execute($allowedVesselIds);
$vessels = $vesselStmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Build filters for UNION query
// -----------------------------
$validStatuses = [
    'sent',
    'failed',
    'skipped',
    'skipped_cooldown',
    'skipped_duplicate',
    'skipped_allow_list'
];

$validRecipientTypes = ['owner', 'mscs_staff', 'vessel_user'];
$validSources = ['document', 'equipment'];

$commonWhere = [];
$commonParams = [];

$commonWhere[] = "base.vessel_id IN ($placeholders)";
$commonParams = array_merge($commonParams, $allowedVesselIds);

if ($emailSearch !== '') {
    $commonWhere[] = "base.recipient_email LIKE ?";
    $commonParams[] = '%' . $emailSearch . '%';
}

if ($statusFilter !== '' && in_array($statusFilter, $validStatuses, true)) {
    $commonWhere[] = "base.email_status = ?";
    $commonParams[] = $statusFilter;
}

if ($recipientType !== '' && in_array($recipientType, $validRecipientTypes, true)) {
    $commonWhere[] = "base.recipient_type = ?";
    $commonParams[] = $recipientType;
}

if ($sourceFilter !== '' && in_array($sourceFilter, $validSources, true)) {
    $commonWhere[] = "base.source_type = ?";
    $commonParams[] = $sourceFilter;
}

if ($dateFrom !== '') {
    $commonWhere[] = "DATE(base.sent_at) >= ?";
    $commonParams[] = $dateFrom;
}

if ($dateTo !== '') {
    $commonWhere[] = "DATE(base.sent_at) <= ?";
    $commonParams[] = $dateTo;
}

// -----------------------------
// Combined query
// -----------------------------
$sql = "
    SELECT *
    FROM (
        SELECT
            'document' AS source_type,
            l.reminder_log_id AS log_id,
            l.document_id AS source_id,
            l.vessel_id,
            l.reminder_type,
            l.expiration_snapshot,
            l.recipient_type,
            l.recipient_email,
            l.email_subject,
            l.email_status,
            l.error_message,
            l.sent_at,
            v.vesselName,
            COALESCE(d.docName, d.docType, 'Document') AS item_name
        FROM document_reminder_log l
        LEFT JOIN vessels v
            ON v.vessel_id = l.vessel_id
        LEFT JOIN documents d
            ON d.id = l.document_id

        UNION ALL

        SELECT
            'equipment' AS source_type,
            e.equipment_reminder_log_id AS log_id,
            e.eid AS source_id,
            e.vessel_id,
            e.reminder_type,
            e.expiration_snapshot,
            e.recipient_type,
            e.recipient_email,
            e.email_subject,
            e.email_status,
            e.error_message,
            e.sent_at,
            v.vesselName,
            COALESCE(eq.equipmentName, CONCAT('Equipment #', e.eid), 'Equipment') AS item_name
        FROM equipment_reminder_log e
        LEFT JOIN vessels v
            ON v.vessel_id = e.vessel_id
        LEFT JOIN equipment eq
            ON eq.eid = e.eid
    ) base
    WHERE " . implode(' AND ', $commonWhere) . "
    ORDER BY base.sent_at DESC, base.log_id DESC
    LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($commonParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Helpers
// -----------------------------
function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function status_badge(string $status): string
{
    $status = strtolower(trim($status));

    $map = [
        'sent'               => 'success',
        'failed'             => 'danger',
        'skipped'            => 'secondary',
        'skipped_cooldown'   => 'warning',
        'skipped_duplicate'  => 'secondary',
        'skipped_allow_list' => 'info',
    ];

    $labels = [
        'sent'               => 'SENT',
        'failed'             => 'FAILED',
        'skipped'            => 'SKIPPED',
        'skipped_cooldown'   => 'SKIPPED - COOLDOWN',
        'skipped_duplicate'  => 'SKIPPED - DUPLICATE',
        'skipped_allow_list' => 'SKIPPED - ALLOW LIST',
    ];

    $color = $map[$status] ?? 'secondary';
    $label = $labels[$status] ?? strtoupper($status);

    return '<span class="badge bg-' . $color . '">' . h($label) . '</span>';
}

function source_badge(string $source): string
{
    $source = strtolower(trim($source));
    $map = [
        'document'  => 'primary',
        'equipment' => 'dark',
    ];
    $labels = [
        'document'  => 'DOCUMENT',
        'equipment' => 'EQUIPMENT',
    ];

    $color = $map[$source] ?? 'secondary';
    $label = $labels[$source] ?? strtoupper($source);

    return '<span class="badge bg-' . $color . '">' . h($label) . '</span>';
}

function reminder_label(string $type): string
{
    return match ($type) {
        '60_day'   => '60 Day',
        '30_day'   => '30 Day',
        '15_day'   => '15 Day',
        '7_day'    => '7 Day',
        'expired'  => 'Expired',
        'included' => 'Included',
        default    => $type,
    };
}

// -----------------------------
// Summary counts
// -----------------------------
$sentCount    = 0;
$failedCount  = 0;
$skippedCount = 0;

foreach ($rows as $r) {
    $status = (string)($r['email_status'] ?? '');

    if ($status === 'sent') $sentCount++;
    if ($status === 'failed') $failedCount++;
    if (str_starts_with($status, 'skipped')) $skippedCount++;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Compliance Notifications Log</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .notifications-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .page-header-card,
        .summary-card,
        .filter-card,
        .results-card {
            border: 0;
            border-radius: 1rem;
        }

        .page-meta {
            color: #6b7280;
            margin: 0;
        }

        .summary-value {
            font-size: 1.9rem;
            line-height: 1;
            font-weight: 700;
        }

        .summary-label {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
        }

        .results-table th {
            white-space: nowrap;
        }

        .results-table td {
            vertical-align: top;
        }

        .mobile-log-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.9rem;
            padding: 0.9rem;
            background: #fff;
        }

        .mobile-log-row + .mobile-log-row {
            margin-top: 0.65rem;
            padding-top: 0.65rem;
            border-top: 1px solid #eef2f6;
        }

        .mobile-log-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .mobile-log-value {
            font-size: 0.96rem;
        }
    </style>
</head>
<body>
<?php
$title = 'Notifications Log';
$back_link = 'reports.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="notifications-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">Compliance Notifications Log</h1>
                            <p class="page-meta">Document and equipment reminder email history.</p>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a class="btn btn-outline-secondary" href="reports.php">Back to Reports</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-success">
                        <div class="card-body">
                            <div class="summary-label">Sent</div>
                            <div class="summary-value"><?= (int)$sentCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-danger">
                        <div class="card-body">
                            <div class="summary-label">Failed</div>
                            <div class="summary-value"><?= (int)$failedCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-warning">
                        <div class="card-body">
                            <div class="summary-label">Skipped</div>
                            <div class="summary-value"><?= (int)$skippedCount ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm summary-card h-100 border-secondary">
                        <div class="card-body">
                            <div class="summary-label">Rows Shown</div>
                            <div class="summary-value"><?= count($rows) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="get" class="card shadow-sm filter-card p-3 mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label">Source</label>
                        <select name="source" class="form-select">
                            <option value="">Any</option>
                            <option value="document" <?= $sourceFilter === 'document' ? 'selected' : '' ?>>Document</option>
                            <option value="equipment" <?= $sourceFilter === 'equipment' ? 'selected' : '' ?>>Equipment</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label">Vessel</label>
                        <select name="vessel_id" class="form-select">
                            <option value="">All allowed vessels</option>
                            <?php foreach ($vessels as $v): ?>
                                <option value="<?= (int)$v['vessel_id'] ?>" <?= ($vesselId === (int)$v['vessel_id']) ? 'selected' : '' ?>>
                                    <?= h($v['vesselName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label">Status</label>
                        <select name="email_status" class="form-select">
                            <option value="">Any</option>
                            <option value="sent" <?= $statusFilter === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="skipped" <?= $statusFilter === 'skipped' ? 'selected' : '' ?>>Skipped</option>
                            <option value="skipped_cooldown" <?= $statusFilter === 'skipped_cooldown' ? 'selected' : '' ?>>Skipped - Cooldown</option>
                            <option value="skipped_duplicate" <?= $statusFilter === 'skipped_duplicate' ? 'selected' : '' ?>>Skipped - Duplicate</option>
                            <option value="skipped_allow_list" <?= $statusFilter === 'skipped_allow_list' ? 'selected' : '' ?>>Skipped - Allow List</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label">Recipient Type</label>
                        <select name="recipient_type" class="form-select">
                            <option value="">Any</option>
                            <option value="vessel_user" <?= $recipientType === 'vessel_user' ? 'selected' : '' ?>>Vessel User</option>
                            <option value="mscs_staff" <?= $recipientType === 'mscs_staff' ? 'selected' : '' ?>>MSCS Staff</option>
                            <option value="owner" <?= $recipientType === 'owner' ? 'selected' : '' ?>>Owner</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-lg-2">
                        <label class="form-label">Recipient Email</label>
                        <input type="text" name="recipient_email" value="<?= h($emailSearch) ?>" class="form-control" placeholder="search@email.com">
                    </div>

                    <div class="col-6 col-md-3 col-lg-1">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="form-control">
                    </div>

                    <div class="col-6 col-md-3 col-lg-1">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="form-control">
                    </div>

                    <div class="col-6 col-md-3 col-lg-1">
                        <label class="form-label">Limit</label>
                        <input type="number" name="limit" value="<?= (int)$limit ?>" min="10" max="500" class="form-control">
                    </div>

                    <div class="col-12 d-flex flex-column flex-sm-row gap-2">
                        <button class="btn btn-primary" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="notifications_log.php">Reset</a>
                    </div>
                </div>
            </form>

            <div class="card shadow-sm results-card p-3">
                <?php if (!$rows): ?>
                    <div class="alert alert-info mb-0">No notification log rows matched your filters.</div>
                <?php else: ?>

                    <div class="d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle results-table">
                                <thead>
                                    <tr>
                                        <th>Sent</th>
                                        <th>Source</th>
                                        <th>Vessel</th>
                                        <th>Item</th>
                                        <th>Reminder</th>
                                        <th>Recipient</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $rowClass = '';
                                    $status = (string)($r['email_status'] ?? '');
                                    if ($status === 'failed') {
                                        $rowClass = 'table-danger';
                                    } elseif (str_starts_with($status, 'skipped')) {
                                        $rowClass = 'table-warning';
                                    }
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><?= h($r['sent_at']) ?></td>
                                        <td><?= source_badge((string)$r['source_type']) ?></td>
                                        <td>
                                            <?php if (!empty($r['vessel_id'])): ?>
                                                <a href="vessel_dashboard.php?vessel_id=<?= (int)$r['vessel_id'] ?>#documents">
                                                    <?= h($r['vesselName'] ?? 'Unknown Vessel') ?>
                                                </a>
                                            <?php else: ?>
                                                <?= h($r['vesselName'] ?? 'Unknown Vessel') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= h($r['item_name'] ?: 'Item') ?>
                                            <?php if (!empty($r['expiration_snapshot'])): ?>
                                                <div class="text-muted small">Exp: <?= h($r['expiration_snapshot']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h(reminder_label((string)$r['reminder_type'])) ?></td>
                                        <td>
                                            <?= h($r['recipient_email']) ?>
                                            <?php if (!empty($r['email_subject'])): ?>
                                                <div class="text-muted small"><?= h($r['email_subject']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= h($r['recipient_type']) ?></td>
                                        <td><?= status_badge((string)$r['email_status']) ?></td>
                                        <td>
                                            <?php if (!empty($r['error_message'])): ?>
                                                <span class="text-danger small"><?= h($r['error_message']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
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
                                <?php
                                $status = (string)($r['email_status'] ?? '');
                                $cardBorder = 'border-light';
                                if ($status === 'failed') {
                                    $cardBorder = 'border-danger';
                                } elseif (str_starts_with($status, 'skipped')) {
                                    $cardBorder = 'border-warning';
                                }
                                ?>
                                <div class="mobile-log-card <?= h($cardBorder) ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="mobile-log-label">Vessel</div>
                                            <div class="mobile-log-value fw-semibold">
                                                <?php if (!empty($r['vessel_id'])): ?>
                                                    <a href="vessel_dashboard.php?vessel_id=<?= (int)$r['vessel_id'] ?>#documents">
                                                        <?= h($r['vesselName'] ?? 'Unknown Vessel') ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= h($r['vesselName'] ?? 'Unknown Vessel') ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?= status_badge((string)$r['email_status']) ?>
                                        </div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Source</div>
                                        <div class="mobile-log-value"><?= source_badge((string)$r['source_type']) ?></div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Item</div>
                                        <div class="mobile-log-value">
                                            <?= h($r['item_name'] ?: 'Item') ?>
                                            <?php if (!empty($r['expiration_snapshot'])): ?>
                                                <div class="text-muted small mt-1">Exp: <?= h($r['expiration_snapshot']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Reminder</div>
                                        <div class="mobile-log-value"><?= h(reminder_label((string)$r['reminder_type'])) ?></div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Recipient</div>
                                        <div class="mobile-log-value">
                                            <?= h($r['recipient_email']) ?>
                                            <?php if (!empty($r['email_subject'])): ?>
                                                <div class="text-muted small mt-1"><?= h($r['email_subject']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Recipient Type</div>
                                        <div class="mobile-log-value"><?= h($r['recipient_type']) ?></div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Sent</div>
                                        <div class="mobile-log-value"><?= h($r['sent_at']) ?></div>
                                    </div>

                                    <div class="mobile-log-row">
                                        <div class="mobile-log-label">Error</div>
                                        <div class="mobile-log-value">
                                            <?php if (!empty($r['error_message'])): ?>
                                                <span class="text-danger small"><?= h($r['error_message']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>