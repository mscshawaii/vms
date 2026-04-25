<?php
if (!isset($pdo)) require_once __DIR__ . '/../db_connect.php';
if (!isset($vessel_id)) exit('Vessel ID missing');

/* ---------- helpers ---------- */
if (!function_exists('safe')) {
    function safe($val) { return ($val !== null && $val !== '') ? htmlspecialchars($val) : '—'; }
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

/* ---------- read inputs ---------- */
$docType     = get_qs('docType', '');
$issue_from  = get_qs('issue_from', '');
$issue_to    = get_qs('issue_to',   '');
$exp_from    = get_qs('exp_from',   '');
$exp_to      = get_qs('exp_to',     '');
$exp_state   = get_qs('exp_state',  ''); // '', expired, soon, valid
$sort        = get_qs('sort',       'exp_asc'); // exp_asc, exp_desc, name_asc, name_desc
$include_archived = get_qs('include_archived', '') === '1';

/* ---------- expiring banner (active only) ---------- */
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

// Issue date range
if (is_valid_ymd($issue_from)) {
    $where[]  = "TO_DAYS(issueDate) > 0 AND TO_DAYS(issueDate) >= TO_DAYS(?)";
    $params[] = $issue_from;
}
if (is_valid_ymd($issue_to)) {
    $where[]  = "TO_DAYS(issueDate) > 0 AND TO_DAYS(issueDate) <= TO_DAYS(?)";
    $params[] = $issue_to;
}

// Expiration date range
if (is_valid_ymd($exp_from)) {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) >= TO_DAYS(?)";
    $params[] = $exp_from;
}
if (is_valid_ymd($exp_to)) {
    $where[]  = "TO_DAYS(expDate) > 0 AND TO_DAYS(expDate) <= TO_DAYS(?)";
    $params[] = $exp_to;
}

// Expiration status quick filter
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

// Sorting
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

?>
<h4 class="d-flex align-items-center gap-2">
  Documents
  <?php if ($expiring_count > 0): ?>
    <span class="badge bg-warning text-dark">
      ⚠️ <?= (int)$expiring_count ?> reminder-enabled expiring ≤ 60 days
    </span>
  <?php endif; ?>
</h4>

<div class="mb-3">
  <a href="add_document.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-success">➕ Add Document</a>
</div>

<form method="get" class="border rounded p-3 mb-3">
  <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
  <input type="hidden" name="tab" value="documents">

  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Type</label>
      <input type="text" name="docType" value="<?= htmlspecialchars($docType) ?>" class="form-control" placeholder="e.g. Certificate">
    </div>

    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="exp_state" class="form-select">
        <option value="">Any</option>
        <option value="expired" <?= $exp_state==='expired'?'selected':'' ?>>Expired</option>
        <option value="soon"    <?= $exp_state==='soon'?'selected':''    ?>>Expiring ≤ 60 days</option>
        <option value="valid"   <?= $exp_state==='valid'?'selected':''   ?>>Valid &gt; 60 days</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Sort By</label>
      <select name="sort" class="form-select">
        <option value="exp_asc"  <?= $sort==='exp_asc'?'selected':''  ?>>Expiration ↑</option>
        <option value="exp_desc" <?= $sort==='exp_desc'?'selected':'' ?>>Expiration ↓</option>
        <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>Name A→Z</option>
        <option value="name_desc"<?= $sort==='name_desc'?'selected':''?>>Name Z→A</option>
      </select>
    </div>

    <div class="col-md-3">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" id="incArch" name="include_archived" value="1" <?= $include_archived?'checked':'' ?>>
        <label class="form-check-label" for="incArch">Include archived</label>
      </div>
    </div>

    <div class="col-md-12 mt-2">
      <button class="btn btn-primary">Apply</button>
      <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#documents">Reset</a>
      <a class="btn btn-outline-dark" href="print_documents.php?vessel_id=<?= (int)$vessel_id ?>&<?= http_build_query([
            'docType'=>$docType,'issue_from'=>$issue_from,'issue_to'=>$issue_to,
            'exp_from'=>$exp_from,'exp_to'=>$exp_to,'exp_state'=>$exp_state,
            'sort'=>$sort,'include_archived'=>$include_archived?1:0
        ]) ?>" target="_blank">🖨 Print</a>
    </div>
  </div>
</form>

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
  <?php
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "<tr><td colspan='7' class='text-center text-muted'>📄 No documents match your filters.</td></tr>";
    } else {
        foreach ($rows as $doc) {
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

            echo "<tr class='{$row_class}'>";
            echo "<td>" . safe($doc['docType'])   . "</td>";
            echo "<td>" . safe($doc['docName'])   . "</td>";
            echo "<td>" . safe($doc['issueDate']) . "</td>";
            echo "<td>" . safe($doc['expDate'])   . "</td>";
            echo "<td>{$reminderBadge}</td>";
            echo "<td>{$archBadge}</td>";
            echo "<td>
                    <a href='view_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'>View</a> |
                    <a href='edit_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'>Edit</a> |
                    <a href='archive_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'
                       onclick=\"return confirm('Archive this document? It can be restored later.');\">Archive</a> |
                    <a href='delete_document.php?id={$doc['id']}&vessel_id={$vessel_id}#documents' class='action-link'
                       onclick=\"return confirm('Permanently delete this document?');\">Delete</a>
                 </td>";
            echo "</tr>";
        }
    }
  ?>
  </tbody>
</table>

<style>
.action-link { text-decoration: none; }
.action-link:hover { text-decoration: underline; }
</style>