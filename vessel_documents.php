<?php
require_once 'session_check.php';
require_once 'db_connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function safe($val) {
    return ($val !== null && $val !== '') ? htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') : '—';
}

function get_qs($key, $default = '') {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function is_valid_ymd($s) {
    if (!is_string($s) || $s === '') return false;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
    [$y,$m,$d] = explode('-', $s);
    return checkdate((int)$m,(int)$d,(int)$y);
}

function get_doc_reminder_badge($expDate, $reminderEnabled = 1) {
    if ((int)$reminderEnabled !== 1) {
        return "<span class='badge bg-secondary'>Off</span>";
    }

    if (empty($expDate) || $expDate === '0000-00-00') {
        return "<span class='badge bg-light text-dark border'>No Exp.</span>";
    }

    try {
        $today = new DateTimeImmutable('today');
        $exp   = new DateTimeImmutable($expDate);
        $days  = (int)$today->diff($exp)->format('%r%a');

        if ($days < 0) {
            return "<span class='badge bg-danger'>Expired</span>";
        } elseif ($days <= 7) {
            return "<span class='badge bg-danger'>{$days} days</span>";
        } elseif ($days <= 30) {
            return "<span class='badge bg-warning text-dark'>{$days} days</span>";
        } elseif ($days <= 90) {
            return "<span class='badge bg-info text-dark'>{$days} days</span>";
        } else {
            return "<span class='badge bg-success'>Valid</span>";
        }
    } catch (Exception $e) {
        return "<span class='badge bg-light text-dark border'>Unknown</span>";
    }
}

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$role_id = (int)($_SESSION['role_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

if ($vessel_id <= 0) {
    http_response_code(400);
    exit('Missing or invalid vessel_id.');
}

if ($role_id === 1) {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id]);
} else {
    $vesselStmt = $pdo->prepare("
        SELECT vessel_id, vesselName, vesselON, hailingPort, company_id
        FROM vessels
        WHERE vessel_id = ?
          AND company_id = ?
        LIMIT 1
    ");
    $vesselStmt->execute([$vessel_id, $company_id]);
}

$vessel = $vesselStmt->fetch(PDO::FETCH_ASSOC);
if (!$vessel) {
    http_response_code(404);
    exit('Vessel not found or access denied.');
}

/* ---------- read inputs ---------- */
$docType     = get_qs('docType', '');
$issue_from  = get_qs('issue_from', '');
$issue_to    = get_qs('issue_to',   '');
$exp_from    = get_qs('exp_from',   '');
$exp_to      = get_qs('exp_to',     '');
$exp_state   = get_qs('exp_state',  '');
$sort        = get_qs('sort',       'exp_asc');
$include_archived = get_qs('include_archived', '') === '1';

/* ---------- expiring banner ---------- */
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

$bannerSql = "
    SELECT COUNT(*)
    FROM documents
    WHERE related_to = 'vessel'
      AND vessel_id  = ?
      AND " . ($include_archived ? '1=1' : 'archived_at IS NULL') . "
      AND reminder_enabled = 1
      AND TO_DAYS(expDate) > 0
      AND TO_DAYS(expDate) <= TO_DAYS(?)
";
$expStmt = $pdo->prepare($bannerSql);
$expStmt->execute([$vessel_id, $soon]);
$expiring_count = (int)$expStmt->fetchColumn();

/* ---------- query builder ---------- */
$where  = ["related_to = 'vessel'", "vessel_id = ?"];
$params = [$vessel_id];

if (!$include_archived) {
    $where[] = "archived_at IS NULL";
}

if ($docType !== '') {
    $where[]  = "docType LIKE ?";
    $params[] = "%{$docType}%";
}

if (is_valid_ymd($issue_from)) {
    $where[]  = "TO_DAYS(issueDate) > 0 AND TO_DAYS(issueDate) >= TO_DAYS(?)";
    $params[] = $issue_from;
}
if (is_valid_ymd($issue_to)) {
    $where[]  = "TO_DAYS(issueDate) > 0 AND TO_DAYS(issueDate) <= TO_DAYS(?)";
    $params[] = $issue_to;
}
if (is_valid_ymd($exp_from)) {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) >= TO_DAYS(?)";
    $params[] = $exp_from;
}
if (is_valid_ymd($exp_to)) {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) <= TO_DAYS(?)";
    $params[] = $exp_to;
}

if ($exp_state === 'expired') {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) <= TO_DAYS(?)";
    $params[] = $today;
} elseif ($exp_state === 'soon') {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) BETWEEN TO_DAYS(?) AND TO_DAYS(?)";
    $params[] = $today;
    $params[] = $soon;
} elseif ($exp_state === 'valid') {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) > TO_DAYS(?)";
    $params[] = $soon;
}

switch ($sort) {
    case 'exp_desc':
        $order = "ORDER BY (TO_DAYS(expDate)=0) ASC, expDate DESC, docName ASC";
        break;
    case 'name_asc':
        $order = "ORDER BY docName ASC, (TO_DAYS(expDate)=0) ASC, expDate ASC";
        break;
    case 'name_desc':
        $order = "ORDER BY docName DESC, (TO_DAYS(expDate)=0) ASC, expDate ASC";
        break;
    case 'exp_asc':
    default:
        $order = "ORDER BY (TO_DAYS(expDate)=0) ASC, expDate ASC, docName ASC";
        break;
}

$sql = "
    SELECT *
    FROM documents
    WHERE " . implode(' AND ', $where) . "
    $order
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documents - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .docs-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .docs-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .docs-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .docs-subtitle {
            color: #6b7280;
            margin: 0;
        }
        .docs-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .docs-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }
        .docs-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .docs-table-wrap table {
            min-width: 980px;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<?php
$title = 'Vessel Documents';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="docs-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="docs-header">
                    <div>
                        <h1 class="docs-title">Documents</h1>
                        <p class="docs-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Official No. <?= safe($vessel['vesselON']) ?> · Hailing Port: <?= safe($vessel['hailingPort']) ?>
                        </p>
                    </div>

                    <div class="docs-actions">
                        <a href="add_document.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">Add Document</a>
                        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to Vessel</a>
                    </div>
                </div>

                <?php if ($expiring_count > 0): ?>
                    <div class="alert alert-warning mb-0">
                        <?= (int)$expiring_count ?> reminder-enabled document<?= $expiring_count === 1 ? '' : 's' ?> expiring within 60 days.
                    </div>
                <?php endif; ?>
            </div>

            <div class="vms-card mb-3">
                <form method="get">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <input type="text" name="docType" value="<?= htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="e.g. Certificate">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="exp_state" class="form-select">
                                <option value="">Any</option>
                                <option value="expired" <?= $exp_state === 'expired' ? 'selected' : '' ?>>Expired</option>
                                <option value="soon" <?= $exp_state === 'soon' ? 'selected' : '' ?>>Expiring ≤ 60 days</option>
                                <option value="valid" <?= $exp_state === 'valid' ? 'selected' : '' ?>>Valid &gt; 60 days</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="exp_asc" <?= $sort === 'exp_asc' ? 'selected' : '' ?>>Expiration ↑</option>
                                <option value="exp_desc" <?= $sort === 'exp_desc' ? 'selected' : '' ?>>Expiration ↓</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A→Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z→A</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="incArch" name="include_archived" value="1" <?= $include_archived ? 'checked' : '' ?>>
                                <label class="form-check-label" for="incArch">Include archived</label>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap mt-2">
                            <button class="btn btn-primary">Apply</button>
                            <a class="btn btn-outline-secondary" href="vessel_documents.php?vessel_id=<?= (int)$vessel_id ?>">Reset</a>
                            <a class="btn btn-outline-dark" href="print_documents.php?vessel_id=<?= (int)$vessel_id ?>&<?= http_build_query([
                                'docType'=>$docType,'issue_from'=>$issue_from,'issue_to'=>$issue_to,
                                'exp_from'=>$exp_from,'exp_to'=>$exp_to,'exp_state'=>$exp_state,
                                'sort'=>$sort,'include_archived'=>$include_archived?1:0
                            ]) ?>" target="_blank">Print</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="vms-card">
                <div class="docs-table-wrap">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Document Name</th>
                                <th>Issue Date</th>
                                <th>Expiration Date</th>
                                <th>Reminder</th>
                                <th>Archived</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No documents match your filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $doc): ?>
                                <?php
                                $row_class = '';
                                if (!empty($doc['expDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$doc['expDate']) && $doc['expDate'] !== '0000-00-00') {
                                    if ($doc['expDate'] < $today) {
                                        $row_class = 'table-danger';
                                    } elseif ($doc['expDate'] <= $soon) {
                                        $row_class = 'table-warning';
                                    }
                                }

                                $archBadge = !empty($doc['archived_at'])
                                    ? "<span class='badge bg-secondary'>Yes</span>"
                                    : "<span class='text-muted'>No</span>";

                                $reminderBadge = get_doc_reminder_badge(
                                    $doc['expDate'] ?? null,
                                    (int)($doc['reminder_enabled'] ?? 1)
                                );
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td><?= safe($doc['docType']) ?></td>
                                    <td><?= safe($doc['docName']) ?></td>
                                    <td><?= safe($doc['issueDate']) ?></td>
                                    <td><?= safe($doc['expDate']) ?></td>
                                    <td><?= $reminderBadge ?></td>
                                    <td><?= $archBadge ?></td>
                                    <td class="text-nowrap">
                                        <a href="view_document.php?id=<?= (int)$doc['id'] ?>&vessel_id=<?= (int)$vessel_id ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="edit_document.php?id=<?= (int)$doc['id'] ?>&vessel_id=<?= (int)$vessel_id ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <a href="archive_document.php?id=<?= (int)$doc['id'] ?>&vessel_id=<?= (int)$vessel_id ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Archive this document? It can be restored later.');">Archive</a>
                                        <a href="delete_document.php?id=<?= (int)$doc['id'] ?>&vessel_id=<?= (int)$vessel_id ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this document?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>