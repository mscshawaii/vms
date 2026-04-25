<?php
// manage_vessels.php (MSCS ONLY / ALL VESSELS + TRANSFER + RESTORE + AUDIT)

session_start();

// ✅ Adjust this path to match your project
require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];

/**
 * MSCS gate (robust for multiple session styles)
 */
function is_mscs_user(): bool {
  return
    (isset($_SESSION['owner_id']) && (int)$_SESSION['owner_id'] === 1) ||
    (isset($_SESSION['company_id']) && (int)$_SESSION['company_id'] === 1) ||
    (isset($_SESSION['is_mscs']) && (int)$_SESSION['is_mscs'] === 1) ||
    (isset($_SESSION['role']) && in_array($_SESSION['role'], ['mscs','mscs_admin','super_admin','admin'], true));
}

if (!is_mscs_user()) {
  http_response_code(403);
  exit("Access denied.");
}

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'active'; // active|archived|deleted|all
$filter_company_id = (isset($_GET['company_id']) && ctype_digit($_GET['company_id'])) ? (int)$_GET['company_id'] : 0;

// Detect optional columns/tables
$has_is_deleted = false;
try {
  $has_is_deleted = (bool)$pdo->query("SHOW COLUMNS FROM vessels LIKE 'is_deleted'")->fetch();
} catch (Exception $e) { $has_is_deleted = false; }

$has_audit = false;
try {
  $has_audit = (bool)$pdo->query("SHOW TABLES LIKE 'vessel_audit_log'")->fetch();
} catch (Exception $e) { $has_audit = false; }

// Owners dropdown
$owners = [];
try {
  $owners = $pdo->query("SELECT owner_id, company_name FROM owners ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $owners = []; }

// Build vessel query
$where = [];
$params = [];

if ($filter_company_id > 0) {
  $where[] = "v.company_id = :cid";
  $params[':cid'] = $filter_company_id;
}

if ($status === 'active') {
  $where[] = "v.is_active = 1";
  if ($has_is_deleted) $where[] = "v.is_deleted = 0";
} elseif ($status === 'archived') {
  $where[] = "v.is_active = 0";
  $where[] = "v.archived_at IS NOT NULL";
  if ($has_is_deleted) $where[] = "v.is_deleted = 0";
} elseif ($status === 'deleted') {
  if ($has_is_deleted) {
    $where[] = "v.is_deleted = 1";
  } else {
    $where[] = "v.is_active = 0";
    $where[] = "v.archive_reason LIKE 'DELETED:%'";
  }
} else {
  // all = no status filter
}

if ($q !== '') {
  $where[] = "(v.vesselName LIKE :q OR v.vesselON LIKE :q OR v.callSign LIKE :q OR v.hailingPort LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
  SELECT
    v.vessel_id,
    v.vesselName,
    v.vesselON,
    v.hailingPort,
    v.callSign,
    v.company_id,
    v.is_active,
    v.archived_at,
    v.archive_reason,
    " . ($has_is_deleted ? "v.is_deleted, v.deleted_at, v.deleted_reason" : "0 AS is_deleted, NULL AS deleted_at, NULL AS deleted_reason") . ",
    o.company_name
  FROM vessels v
  LEFT JOIN owners o ON o.owner_id = v.company_id
  $where_sql
  ORDER BY v.vesselName ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vessels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Optional: recent audit (last 50)
$audit_rows = [];
if ($has_audit) {
  try {
    $audit_rows = $pdo->query("
      SELECT a.created_at, a.vessel_id, v.vesselName, a.action, a.old_company_id, a.new_company_id, a.reason, a.actor_user_id
      FROM vessel_audit_log a
      LEFT JOIN vessels v ON v.vessel_id = a.vessel_id
      ORDER BY a.created_at DESC
      LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) { $audit_rows = []; }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Vessels</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0"><i class="bi bi-ship"></i> Manage Vessels</h3>

  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="dashboard.php">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <a class="btn btn-primary" href="add_vessel.php">
      <i class="bi bi-plus-circle"></i> Add Vessel
    </a>

    <a class="btn btn-primary" href="link_vessels.php">
      <i class="bi bi-plus-circle"></i> Link Vessels
    </a>
    
  </div>
</div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-3">
      <select name="company_id" class="form-select">
        <option value="0">All Companies</option>
        <?php foreach ($owners as $o): ?>
          <option value="<?= (int)$o['owner_id'] ?>" <?= ($filter_company_id === (int)$o['owner_id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($o['company_name'] ?? ('Owner '.$o['owner_id'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <select name="status" class="form-select">
        <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="archived" <?= $status==='archived'?'selected':'' ?>>Archived</option>
        <option value="deleted"  <?= $status==='deleted'?'selected':'' ?>>Deleted</option>
        <option value="all"      <?= $status==='all'?'selected':'' ?>>All</option>
      </select>
    </div>

    <div class="col-md-4">
      <input name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search name, ON, callsign, hailing port...">
    </div>

    <div class="col-md-2 d-grid">
      <button class="btn btn-secondary"><i class="bi bi-search"></i> Filter</button>
    </div>
  </form>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Vessel</th>
              <th>ON</th>
              <th>Company</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($vessels)): ?>
            <tr><td colspan="5" class="text-center py-4 text-muted">No vessels found.</td></tr>
          <?php else: foreach ($vessels as $v): ?>
            <?php
              $is_deleted = (int)$v['is_deleted'] === 1;
              $is_active  = (int)$v['is_active'] === 1;
              $status_badge = $is_deleted ? 'danger' : ($is_active ? 'success' : 'warning');
              $status_label = $is_deleted ? 'Deleted' : ($is_active ? 'Active' : 'Archived');
            ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($v['vesselName']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($v['hailingPort'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars($v['vesselON']) ?></td>
              <td><?= htmlspecialchars($v['company_name'] ?? '') ?></td>
              <td><span class="badge bg-<?= $status_badge ?>"><?= $status_label ?></span></td>
              <td class="text-end">

                <a class="btn btn-sm btn-outline-primary" href="vessel_dashboard.php?vessel_id=<?= (int)$v['vessel_id'] ?>">
                  <i class="bi bi-box-arrow-up-right"></i> View
                </a>

                <a class="btn btn-sm btn-outline-secondary" href="edit_vessel.php?vessel_id=<?= (int)$v['vessel_id'] ?>">
                  <i class="bi bi-pencil"></i> Edit
                </a>

                <!-- Transfer (always available to MSCS on this page) -->
                <button
                  class="btn btn-sm btn-outline-info"
                  data-bs-toggle="modal"
                  data-bs-target="#transferModal"
                  data-vessel-id="<?= (int)$v['vessel_id'] ?>"
                  data-vessel-name="<?= htmlspecialchars($v['vesselName'], ENT_QUOTES) ?>"
                  data-current-company="<?= (int)$v['company_id'] ?>"
                >
                  <i class="bi bi-arrow-left-right"></i> Transfer
                </button>

                <?php if (!$is_deleted): ?>
                  <?php if ($is_active): ?>
                    <button class="btn btn-sm btn-outline-warning"
                      data-bs-toggle="modal"
                      data-bs-target="#archiveModal"
                      data-vessel-id="<?= (int)$v['vessel_id'] ?>"
                      data-vessel-name="<?= htmlspecialchars($v['vesselName'], ENT_QUOTES) ?>"
                    >
                      <i class="bi bi-archive"></i> Archive
                    </button>
                  <?php else: ?>
                    <form method="post" action="vessel_manage_action.php" class="d-inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="unarchive">
                      <input type="hidden" name="vessel_id" value="<?= (int)$v['vessel_id'] ?>">
                      <button class="btn btn-sm btn-outline-success">
                        <i class="bi bi-arrow-counterclockwise"></i> Unarchive
                      </button>
                    </form>
                  <?php endif; ?>

                  <button class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteModal"
                    data-vessel-id="<?= (int)$v['vessel_id'] ?>"
                    data-vessel-name="<?= htmlspecialchars($v['vesselName'], ENT_QUOTES) ?>"
                  >
                    <i class="bi bi-trash"></i> Delete
                  </button>

                <?php else: ?>
                  <!-- Restore Deleted -->
                  <form method="post" action="vessel_manage_action.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="vessel_id" value="<?= (int)$v['vessel_id'] ?>">
                    <button class="btn btn-sm btn-outline-success">
                      <i class="bi bi-arrow-counterclockwise"></i> Restore
                    </button>
                  </form>
                <?php endif; ?>

              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($has_audit): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white">
        <strong><i class="bi bi-clock-history"></i> Recent Vessel Actions</strong>
        <span class="text-muted ms-2">(last 50)</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0 table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Date/Time</th>
                <th>Vessel</th>
                <th>Action</th>
                <th>Old Co.</th>
                <th>New Co.</th>
                <th>Reason</th>
                <th>User</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($audit_rows)): ?>
                <tr><td colspan="7" class="text-center py-3 text-muted">No audit entries yet.</td></tr>
              <?php else: foreach ($audit_rows as $a): ?>
                <tr>
                  <td><?= htmlspecialchars($a['created_at']) ?></td>
                  <td><?= htmlspecialchars($a['vesselName'] ?? ('Vessel #'.$a['vessel_id'])) ?></td>
                  <td><?= htmlspecialchars($a['action']) ?></td>
                  <td><?= htmlspecialchars((string)($a['old_company_id'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($a['new_company_id'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($a['reason'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($a['actor_user_id'] ?? '')) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<!-- Archive Modal -->
<div class="modal fade" id="archiveModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="vessel_manage_action.php">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-archive"></i> Archive Vessel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="vessel_id" id="archive_vessel_id">
        <p class="mb-2">You are archiving: <strong id="archive_vessel_name"></strong></p>
        <label class="form-label">Reason (optional)</label>
        <input class="form-control" name="reason" maxlength="255" placeholder="e.g., Sold / Out of service / Fleet change">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" type="submit">Archive</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="vessel_manage_action.php" autocomplete="off">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle"></i> Soft Delete Vessel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="vessel_id" id="delete_vessel_id">

        <p class="mb-2">You are deleting: <strong id="delete_vessel_name"></strong></p>
        <p class="text-muted small mb-2">This hides the vessel from operations. Records remain in the database.</p>

        <label class="form-label">Type <code>DELETE</code> to confirm</label>
        <input class="form-control" name="confirm_text" id="delete_confirm_text" required>

        <label class="form-label mt-3">Reason (optional)</label>
        <input class="form-control" name="reason" maxlength="255" placeholder="e.g., Duplicate entry">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" type="submit">Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="vessel_manage_action.php">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Transfer Vessel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="transfer">
        <input type="hidden" name="vessel_id" id="transfer_vessel_id">

        <p class="mb-2">Transfer: <strong id="transfer_vessel_name"></strong></p>

        <label class="form-label">Destination Company</label>
        <select class="form-select" name="new_company_id" id="transfer_new_company_id" required>
          <option value="">-- Select --</option>
          <?php foreach ($owners as $o): ?>
            <option value="<?= (int)$o['owner_id'] ?>"><?= htmlspecialchars($o['company_name'] ?? ('Owner '.$o['owner_id'])) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="form-label mt-3">Reason (optional)</label>
        <input class="form-control" name="reason" maxlength="255" placeholder="e.g., Ownership transfer / Fleet restructure">

        <div class="alert alert-warning mt-3 mb-0">
          Transfer keeps vessel history attached to this vessel_id.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-info" type="submit">Transfer</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('archiveModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('archive_vessel_id').value = btn.getAttribute('data-vessel-id');
  document.getElementById('archive_vessel_name').textContent = btn.getAttribute('data-vessel-name');
});

document.getElementById('deleteModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_vessel_id').value = btn.getAttribute('data-vessel-id');
  document.getElementById('delete_vessel_name').textContent = btn.getAttribute('data-vessel-name');
  const input = document.getElementById('delete_confirm_text');
  if (input) input.value = '';
});

document.getElementById('transferModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('transfer_vessel_id').value = btn.getAttribute('data-vessel-id');
  document.getElementById('transfer_vessel_name').textContent = btn.getAttribute('data-vessel-name');
  document.getElementById('transfer_new_company_id').value = '';
});
</script>

</body>
</html>