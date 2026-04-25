<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Detect available columns in vessel_logs
$colsStmt = $pdo->query("SHOW COLUMNS FROM vessel_logs");
$cols = $colsStmt ? array_map('strtolower', $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0)) : [];
$has = function($c) use ($cols){ return in_array(strtolower($c), $cols, true); };

// Flags for optional columns
$HAS_STATUS        = $has('status');
$HAS_PASSENGERS    = $has('passenger_count');
$HAS_CASUALTY      = $has('casualty_flag');
$HAS_EH_PORT       = $has('engine_hours_port');
$HAS_EH_STBD       = $has('engine_hours_stbd');
$HAS_CREATED_AT    = $has('created_at');
$HAS_SUBMITTED_AT  = $has('submitted_at');
$HAS_DEPART_DT     = $has('depart_dt');
$HAS_RETURN_DT     = $has('return_dt');
$HAS_ORIGIN        = $has('origin_port');
$HAS_ARRIVAL       = $has('arrival_port');

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    http_response_code(400);
    exit('Missing or invalid vessel_id.');
}

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;
$off    = ($page - 1) * $per;

// Build WHERE
$params = [':vessel_id' => $vessel_id];
$where  = "WHERE vessel_id = :vessel_id";

if ($q !== '') {
    $likeParts = [];
    if ($HAS_ORIGIN)  $likeParts[] = "origin_port LIKE :q";
    if ($HAS_ARRIVAL) $likeParts[] = "arrival_port LIKE :q";
    if ($has('trip_summary')) $likeParts[] = "trip_summary LIKE :q";

    if ($likeParts) {
        $where .= " AND (" . implode(" OR ", $likeParts) . ")";
        $params[':q'] = "%$q%";
    }
}

if ($HAS_STATUS && ($status === 'draft' || $status === 'submitted')) {
    $where .= " AND status = :status";
    $params[':status'] = $status;
}

// Build SELECT list based on availability
$select = ["log_id", "vessel_id"];
if ($HAS_STATUS)       $select[] = "status";
if ($HAS_DEPART_DT)    $select[] = "depart_dt";
if ($HAS_RETURN_DT)    $select[] = "return_dt";
if ($HAS_ORIGIN)       $select[] = "origin_port";
if ($HAS_ARRIVAL)      $select[] = "arrival_port";
if ($HAS_PASSENGERS)   $select[] = "passenger_count";
if ($HAS_CASUALTY)     $select[] = "casualty_flag";
if ($HAS_EH_PORT)      $select[] = "engine_hours_port";
if ($HAS_EH_STBD)      $select[] = "engine_hours_stbd";
if ($HAS_CREATED_AT)   $select[] = "created_at";
if ($HAS_SUBMITTED_AT) $select[] = "submitted_at";

// ORDER BY
$orderExprs = [];
if ($HAS_RETURN_DT)  $orderExprs[] = "return_dt";
if ($HAS_DEPART_DT)  $orderExprs[] = "depart_dt";
if ($HAS_CREATED_AT) $orderExprs[] = "created_at";
$order = $orderExprs ? ("ORDER BY COALESCE(" . implode(",", $orderExprs) . ") DESC") : "";

// Total count
$total = $pdo->prepare("SELECT COUNT(*) FROM vessel_logs $where");
$total->execute($params);
$cnt = (int)$total->fetchColumn();

// Page rows
$sql = "
  SELECT " . implode(", ", $select) . "
  FROM vessel_logs
  $where
  $order
  LIMIT $per OFFSET $off
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pages = max(1, (int)ceil($cnt / $per));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vessel Logs - VMS</title>

  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0d6efd">

  <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
  <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/assets/css/vms-mobile.css" rel="stylesheet">

  <style>
    .logs-shell {
      background: var(--vms-bg, #f4f7fb);
      min-height: 100vh;
    }

    .logs-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }

    .logs-title {
      font-size: 1.65rem;
      font-weight: 700;
      margin: 0 0 4px;
    }

    .logs-subtitle {
      color: #6b7280;
      margin: 0;
    }

    .logs-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .logs-actions .btn {
      border-radius: 12px;
      min-height: 42px;
    }

    .logs-table td, .logs-table th {
      vertical-align: middle;
    }

    @media (max-width: 768px) {
      .logs-table thead {
        display: none;
      }

      .logs-table tr {
        display: block;
        margin-bottom: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 10px;
        background: #fff;
      }

      .logs-table td {
        display: block;
        padding: 4px 0;
      }
    }
  </style>
</head>

<body>
<?php
$title = 'Vessel Logs';
$back_link = 'vessel_dashboard.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="logs-shell">
  <div class="app-page">
    <div class="app-container">

      <?php if (isset($_GET['log_saved']) && $_GET['log_saved'] == '1'): ?>
        <div class="alert alert-success">Log saved successfully.</div>
      <?php endif; ?>
      <?php if (!empty($_GET['meter_warning'])): ?>
        <div class="alert alert-warning"><?= h($_GET['meter_warning']) ?></div>
      <?php endif; ?>

      <!-- Header -->
      <div class="vms-card">
        <div class="logs-header">
          <div>
            <h1 class="logs-title">Voyage Logs</h1>
            <p class="logs-subtitle">Search, review, and manage voyage logs</p>
          </div>

          <div class="logs-actions">
            <a href="vessel_log_create.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-primary">
              + New Log
            </a>
            <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
              Back to Vessel
            </a>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="vms-card mb-3">
        <form class="row g-2" method="get">
          <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

          <div class="col-md-6">
            <input class="form-control"
                   name="q"
                   placeholder="Search origin, arrival, or notes…"
                   value="<?= h($q) ?>">
          </div>

          <div class="col-md-3">
            <select name="status" class="form-select" <?= $HAS_STATUS ? '' : 'disabled' ?>>
              <option value=""><?= $HAS_STATUS ? 'All statuses' : 'Status unavailable' ?></option>
              <?php if ($HAS_STATUS): ?>
                <option value="submitted" <?= $status === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
              <?php endif; ?>
            </select>
          </div>

          <div class="col-md-3 d-grid">
            <button class="btn btn-primary">Search</button>
          </div>
        </form>
      </div>

      <!-- Table -->
      <div class="vms-card">
        <div class="table-responsive">
          <table class="table table-sm table-striped logs-table">
            <thead class="table-light">
              <tr>
                <th>When</th>
                <th>Route</th>
                <?php if ($HAS_PASSENGERS): ?><th>Passengers</th><?php endif; ?>
                <?php if ($HAS_STATUS): ?><th>Status</th><?php endif; ?>
                <?php if ($HAS_EH_PORT || $HAS_EH_STBD): ?><th>Eng Hrs</th><?php endif; ?>
                <?php if ($HAS_CASUALTY): ?><th>Casualty</th><?php endif; ?>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="7" class="text-center text-muted">No logs found.</td>
              </tr>
            <?php endif; ?>

            <?php foreach($rows as $r): ?>
              <tr>
                <td>
                  <strong><?= h(($HAS_DEPART_DT && $r['depart_dt']) ? $r['depart_dt'] : '—') ?></strong>
                  <div class="text-muted small">
                    → <?= h(($HAS_RETURN_DT && $r['return_dt']) ? $r['return_dt'] : '—') ?>
                  </div>
                </td>

                <td>
                  <?php
                    $orig = ($HAS_ORIGIN && !empty($r['origin_port'])) ? $r['origin_port'] : '—';
                    $arr  = ($HAS_ARRIVAL && !empty($r['arrival_port'])) ? $r['arrival_port'] : '—';
                    echo h("$orig → $arr");
                  ?>
                </td>

                <?php if ($HAS_PASSENGERS): ?>
                  <td><?= array_key_exists('passenger_count', $r) && $r['passenger_count'] !== null ? (int)$r['passenger_count'] : '—' ?></td>
                <?php endif; ?>

                <?php if ($HAS_STATUS): ?>
                  <td>
                    <?php $st = isset($r['status']) ? strtolower((string)$r['status']) : ''; ?>
                    <span class="badge <?= $st === 'draft' ? 'text-bg-secondary' : ($st === 'submitted' ? 'text-bg-success' : 'text-bg-light') ?>">
                      <?= h($st ? ucfirst($st) : '—') ?>
                    </span>
                  </td>
                <?php endif; ?>

                <?php if ($HAS_EH_PORT || $HAS_EH_STBD): ?>
                  <td>
                    <?= h($HAS_EH_PORT && isset($r['engine_hours_port']) ? (string)$r['engine_hours_port'] : '—') ?>
                    /
                    <?= h($HAS_EH_STBD && isset($r['engine_hours_stbd']) ? (string)$r['engine_hours_stbd'] : '—') ?>
                  </td>
                <?php endif; ?>

                <?php if ($HAS_CASUALTY): ?>
                  <td><?= !empty($r['casualty_flag']) ? '⚠️' : '—' ?></td>
                <?php endif; ?>

                <td class="text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="log_view.php?log_id=<?= (int)$r['log_id'] ?>">View</a>
                  <a class="btn btn-sm btn-outline-secondary" href="log_edit.php?log_id=<?= (int)$r['log_id'] ?>">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <nav class="mt-3">
          <ul class="pagination">
            <?php for($i = 1; $i <= $pages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="?vessel_id=<?= (int)$vessel_id ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      <?php endif; ?>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
