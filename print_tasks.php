<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = $_GET['vessel_id'] ?? null;
if (!$vessel_id) {
    http_response_code(400);
    echo "Missing vessel_id.";
    exit;
}

$run_id   = $_GET['icr_run_id'] ?? null;
$status   = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$priority = isset($_GET['priority']) && $_GET['priority'] !== '' ? trim($_GET['priority']) : null;
$due_from = isset($_GET['due_from']) && $_GET['due_from'] !== '' ? trim($_GET['due_from']) : null;
$due_to   = isset($_GET['due_to']) && $_GET['due_to'] !== '' ? trim($_GET['due_to']) : null;
$sort     = $_GET['sort'] ?? 'due_asc';

$allowedStatuses = ['open', 'in_progress', 'complete', 'overdue', 'deferred'];
$defaultPrintStatuses = ['open', 'in_progress', 'overdue', 'deferred'];

if ($status !== null && !in_array($status, $allowedStatuses, true)) {
    $status = null;
}

$hdr = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ? LIMIT 1");
$hdr->execute([$vessel_id]);
$vesselName = $hdr->fetchColumn();

/* Query */
$sql = "
    SELECT t.*
    FROM tasks t
    WHERE t.vessel_id = ?
";
$params = [$vessel_id];

if ($run_id) {
    $sql .= " AND t.vessel_icr_run_id = ? ";
    $params[] = $run_id;
}

/* Status filtering */
if ($status !== null) {
    $sql .= " AND t.status = ? ";
    $params[] = $status;
    $statusLabel = ucfirst(str_replace('_', ' ', $status));
} else {
    $placeholders = implode(',', array_fill(0, count($defaultPrintStatuses), '?'));
    $sql .= " AND t.status IN ($placeholders) ";
    foreach ($defaultPrintStatuses as $s) {
        $params[] = $s;
    }
    $statusLabel = 'Active (Open, In Progress, Overdue, Deferred)';
}

if ($priority !== null) {
    $sql .= " AND t.priority = ? ";
    $params[] = $priority;
}

if ($due_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_from)) {
    $sql .= " AND (t.due_date IS NOT NULL AND t.due_date >= ?) ";
    $params[] = $due_from;
}

if ($due_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_to)) {
    $sql .= " AND (t.due_date IS NOT NULL AND t.due_date <= ?) ";
    $params[] = $due_to;
}

/* Sorting */
$sortSql = match ($sort) {
    'due_desc'    => " ORDER BY (t.due_date IS NULL) ASC, t.due_date DESC, t.priority ASC, t.title ASC ",
    'prio_asc'    => " ORDER BY t.priority ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'prio_desc'   => " ORDER BY t.priority DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_asc'  => " ORDER BY t.status ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_desc' => " ORDER BY t.status DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    default       => " ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.priority ASC, t.title ASC ",
};

$sql .= $sortSql;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function safe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print Corrective Actions • <?= safe($vesselName ?? 'Vessel') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --line:#dee2e6; }

body { padding: 24px; color:#111; }
.no-print { margin-bottom: 12px; }

h1 { font-size: 20px; margin: 0 0 4px 0; }
h2 { font-size: 14px; color:#555; margin: 0 0 6px 0; }
h3 { font-size: 13px; color:#666; margin: 0 0 12px 0; font-weight: normal; }

table.sheet { width:100%; border-collapse: collapse; }
.sheet th, .sheet td { border:1px solid var(--line); padding:6px 8px; vertical-align:top; }
.sheet th { background:#f7f7f7; }

.col-title { width:26%; }
.col-desc  { width:46%; font-size:0.92em; }
.col-due   { width:10%; white-space:nowrap; }
.col-stat  { width:9%;  white-space:nowrap; }
.col-prio  { width:9%;  white-space:nowrap; }

tr.overdue-row { background:#fff5f5; }
tr.soon { background:#fffbea; }

@media print {
  .no-print { display:none !important; }
  body { padding: 0; }
  @page { margin: 12mm; }
}
</style>
</head>
<body>

<div class="no-print d-flex gap-2">
  <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  <button class="btn btn-outline-dark" onclick="window.close()">✖ Close</button>
</div>

<h1>Corrective Actions</h1>
<h2><?= safe($vesselName ?? 'Vessel') ?><?= $run_id ? ' • From ICR Run #'.safe($run_id) : '' ?></h2>
<h3>Status Filter: <?= safe($statusLabel) ?></h3>

<table class="sheet">
  <thead>
    <tr>
      <th class="col-title">Title</th>
      <th class="col-desc">Description</th>
      <th class="col-due">Due</th>
      <th class="col-stat">Status</th>
      <th class="col-prio">Priority</th>
    </tr>
  </thead>
  <tbody>
<?php
if (!$rows) {
    echo "<tr><td colspan='5' class='text-center text-muted'>No tasks for the selected filters.</td></tr>";
} else {
    foreach ($rows as $t) {
        $due = $t['due_date'] ?? null;
        $rowClass = '';

        if (($t['status'] ?? '') === 'overdue') {
            $rowClass = 'overdue-row';
        } elseif ($due) {
            $d = new DateTime($due);
            $n = new DateTime();
            if ($d < $n) {
                $rowClass = 'overdue-row';
            } else {
                $n->modify('+7 days');
                if ($d <= $n) {
                    $rowClass = 'soon';
                }
            }
        }

        echo "<tr class='{$rowClass}'>";
        echo "<td>" . safe($t['title'] ?? '—') . "</td>";
        echo "<td>" . nl2br(safe($t['description'] ?? '—')) . "</td>";
        echo "<td>" . safe($due ?? '—') . "</td>";
        echo "<td>" . safe(ucfirst(str_replace('_', ' ', $t['status'] ?? '—'))) . "</td>";
        echo "<td>" . safe(ucfirst($t['priority'] ?? '—')) . "</td>";
        echo "</tr>";
    }
}
?>
  </tbody>
</table>

</body>
</html>