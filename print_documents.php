<?php
// print_documents.php — print-friendly list of vessel documents with filters
require 'db_connect.php';
require 'session_check.php';

/* ---------- helpers ---------- */
function safe($v) { return ($v !== null && $v !== '') ? htmlspecialchars($v) : '—'; }
function get_qs($key, $default = '') { return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default; }

$vessel_id = (int)($_GET['vessel_id'] ?? 0);
if ($vessel_id <= 0) {
    http_response_code(400);
    echo "Missing or invalid vessel_id.";
    exit;
}

/* ---------- read filters (keep in sync with documents_tab) ---------- */
$docType          = get_qs('docType', '');
$exp_state        = get_qs('exp_state', ''); // '', expired, soon, valid
$sort             = get_qs('sort', 'exp_asc'); // exp_asc, exp_desc, name_asc, name_desc
$include_archived = get_qs('include_archived', '') === '1';

/* ---------- date cutoffs ---------- */
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$soon  = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');

/* ---------- build query ---------- */
$where  = ["related_to = 'vessel'", "vessel_id = ?"];
$params = [$vessel_id];

if (!$include_archived) {
    $where[] = "archived_at IS NULL";
}
if ($docType !== '') {
    $where[]  = "docType LIKE ?";
    $params[] = "%{$docType}%";
}

/* exp_state uses TO_DAYS guards so invalid dates don’t crash */
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

/* sort (push blanks to end via TO_DAYS(expDate)=0) */
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
    SELECT id, docType, docName, issueDate, expDate, archived_at
    FROM documents
    WHERE " . implode(' AND ', $where) . "
    $order
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- fetch vessel name for header ---------- */
$vn = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ? LIMIT 1");
$vn->execute([$vessel_id]);
$vesselName = (string)$vn->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print Documents • <?= safe($vesselName) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
  body { padding: 24px; }
  .chip { display:inline-block; padding:.25rem .5rem; border:1px solid #dee2e6; border-radius:999px; font-size:.85rem; margin-right:.5rem; color:#495057; background:#f8f9fa; }
  .table thead th { background:#f1f3f5; }
  .table-warning td, .table-danger td { background-clip: padding-box; }
  @media print {
    .no-print { display:none !important; }
    @page { margin: 12mm; }
    body { padding: 0; }
  }
</style>
</head>
<body>

<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h4 class="mb-1">Vessel Documents</h4>
    <div class="text-muted">Vessel: <?= safe($vesselName) ?></div>
    <div class="mt-2">
      <?php if ($docType !== ''): ?><span class="chip">Type: <?= safe($docType) ?></span><?php endif; ?>
      <?php if ($exp_state !== ''): ?><span class="chip">Exp: <?= safe(ucfirst($exp_state)) ?></span><?php endif; ?>
      <span class="chip">Sort: <?= safe($sort) ?></span>
      <?php if ($include_archived): ?><span class="chip">Including archived</span><?php endif; ?>
    </div>
  </div>
  <div class="no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    <a class="btn btn-outline-secondary" href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#documents">Close</a>
  </div>
</div>

<table class="table table-bordered table-striped align-middle">
  <thead class="table-light">
    <tr>
      <th style="width:18%">Type</th>
      <th>Document Name</th>
      <th style="width:16%">Issue Date</th>
      <th style="width:16%">Expiration Date</th>
      <th style="width:10%">Archived</th>
    </tr>
  </thead>
  <tbody>
  <?php
    if (!$docs) {
        echo "<tr><td colspan='5' class='text-center text-muted'>No documents match your filters.</td></tr>";
    } else {
        foreach ($docs as $d) {
            // Friendly display for bad/blank dates
            $issue = ($d['issueDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['issueDate']) && $d['issueDate'] !== '0000-00-00')
                ? $d['issueDate'] : '—';
            $exp   = ($d['expDate'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['expDate']) && $d['expDate'] !== '0000-00-00')
                ? $d['expDate'] : '—';

            // row highlight for expiration if real date
            $row_class = '';
            if ($exp !== '—') {
                if ($exp < $today)      $row_class = 'table-danger';
                elseif ($exp <= $soon)  $row_class = 'table-warning';
            }

            $arch = !empty($d['archived_at']) ? "<span class='badge bg-secondary'>Yes</span>" : "<span class='text-muted'>No</span>";

            echo "<tr class='{$row_class}'>";
            echo "<td>" . safe($d['docType']) . "</td>";
            echo "<td>" . safe($d['docName']) . "</td>";
            echo "<td>" . safe($issue) . "</td>";
            echo "<td>" . safe($exp) . "</td>";
            echo "<td>{$arch}</td>";
            echo "</tr>";
        }
    }
  ?>
  </tbody>
</table>

</body>
</html>
