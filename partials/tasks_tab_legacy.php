<?php
flush();
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (!isset($pdo)) require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/message_functions.php';

$vessel_id = $_GET['vessel_id'] ?? $vessel_id ?? null;
if (!$vessel_id) {
    echo "<p class='text-danger'>❌ Vessel ID is missing.</p>";
    return;
}

$run_id = $_GET['icr_run_id'] ?? null;

/* ------- Read filters/sort from GET ------- */
$task_filter = isset($_GET['task_filter']) && $_GET['task_filter'] !== '' ? trim($_GET['task_filter']) : null;
$status      = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$priority    = isset($_GET['priority']) && $_GET['priority'] !== '' ? trim($_GET['priority']) : null;
$due_from    = isset($_GET['due_from']) && $_GET['due_from'] !== '' ? trim($_GET['due_from']) : null; // YYYY-MM-DD
$due_to      = isset($_GET['due_to'])   && $_GET['due_to']   !== '' ? trim($_GET['due_to'])   : null;
$sort        = $_GET['sort'] ?? 'due_asc'; // due_asc, due_desc, prio_asc, prio_desc, status_asc, status_desc

/* ------- Helpers ------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dt($d){ return ($d && $d !== '0000-00-00') ? date('M j, Y', strtotime($d)) : '—'; }
function badge_status($s){
    $map = [
        'open'        => 'secondary',
        'in_progress' => 'info',
        'overdue'     => 'warning',
        'deferred'    => 'dark',
        'complete'    => 'success',
    ];
    $class = $map[$s] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.h(ucwords(str_replace('_',' ',$s))).'</span>';
}
function badge_priority($p){
    $map = ['urgent'=>'danger','moderate'=>'primary','low'=>'secondary','recommendation'=>'warning'];
    $class = $map[strtolower($p)] ?? 'secondary';
    return '<span class="badge bg-'.$class.'">'.h(ucfirst($p)).'</span>';
}
function qs($overrides = []) {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($q[$k]); else $q[$k] = $v;
    }
    return http_build_query($q);
}

/* ------- Build base query ------- */
$sql = "
    SELECT 
        t.task_id, t.title, t.status, t.priority, t.due_date,
        t.completed_date, t.corrected_date,
        t.assigned_to, u.fName, u.lName,
        t.vessel_icr_run_id, vi.icr_id, i.icr_number
    FROM tasks t
    LEFT JOIN users       u  ON t.assigned_to    = u.id
    LEFT JOIN vessel_icrs vi ON t.vessel_icr_id  = vi.vessel_icr_id
    LEFT JOIN icrs        i  ON vi.icr_id        = i.icr_id
    WHERE t.vessel_id = ?
";
$params = [$vessel_id];

/* ------- Optional filter: by ICR run ------- */
if ($run_id) {
    $sql .= " AND t.vessel_icr_run_id = ? ";
    $params[] = $run_id;
}

/* ------- Status behavior ------- */
$appliedStatusFilter = false;

/*
  Behavior:
  - If explicit status dropdown is used, honor it.
  - Else if task_filter=open is passed, force active/not-completed items.
  - Else default to active/not-completed items.
*/
if ($status !== null && $status !== '') {
    $sql .= " AND t.status = ? ";
    $params[] = $status;
    $appliedStatusFilter = true;
}

if (!$appliedStatusFilter) {
    if ($task_filter === 'open') {
        $active = ['open','in_progress','overdue','deferred'];
        $in = implode(',', array_fill(0, count($active), '?'));
        $sql .= " AND t.status IN ($in) ";
        $params = array_merge($params, $active);
    } else {
        // Default behavior remains active-only
        $active = ['open','in_progress','overdue','deferred'];
        $in = implode(',', array_fill(0, count($active), '?'));
        $sql .= " AND t.status IN ($in) ";
        $params = array_merge($params, $active);
    }
}

/* ------- Other filters ------- */
if ($priority !== null){ 
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

/* ------- Sorting (NULLS LAST via boolean key) ------- */
$sortSql = match ($sort) {
    'due_desc'    => " ORDER BY (t.due_date IS NULL) ASC, t.due_date DESC, t.priority ASC, t.title ASC ",
    'prio_asc'    => " ORDER BY t.priority ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'prio_desc'   => " ORDER BY t.priority DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_asc'  => " ORDER BY t.status ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    'status_desc' => " ORDER BY t.status DESC, (t.due_date IS NULL) ASC, t.due_date ASC, t.title ASC ",
    default       => " ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, t.priority ASC, t.title ASC ",
};
$sql .= $sortSql;

/* ------- Distinct values for dropdowns ------- */
$distinctStatus = $pdo->prepare("
    SELECT DISTINCT t.status 
    FROM tasks t 
    WHERE t.vessel_id = ? AND t.status IS NOT NULL AND t.status <> '' 
    ORDER BY t.status
");
$distinctStatus->execute([$vessel_id]);
$statuses = array_column($distinctStatus->fetchAll(PDO::FETCH_ASSOC), 'status');

$distinctPriority = $pdo->prepare("
    SELECT DISTINCT t.priority 
    FROM tasks t 
    WHERE t.vessel_id = ? AND t.priority IS NOT NULL AND t.priority <> '' 
    ORDER BY t.priority
");
$distinctPriority->execute([$vessel_id]);
$priorities = array_column($distinctPriority->fetchAll(PDO::FETCH_ASSOC), 'priority');

/* ------- Execute main query ------- */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
  .actions-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap: .25rem;
    width: 180px;
  }
  .actions-grid .btn {
    width: 100%;
  }
</style>

<div class="d-flex align-items-center justify-content-between mb-2">
  <h6 class="mb-0">Corrective Actions</h6>
  <div class="d-flex gap-2">
    <a href="add_task.php?vessel_id=<?= h($vessel_id) ?>" class="btn btn-sm btn-primary">➕ Add Task</a>
    <a href="print_tasks.php?<?= qs([]) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">🖨 Print</a>
  </div>
</div>

<?php if ($run_id): ?>
  <div class="alert alert-info py-2">
    Showing corrective actions generated from ICR Run #<?= h($run_id) ?>.
    <a href="vessel_dashboard.php?vessel_id=<?= h($vessel_id) ?>#tasksModal" class="ms-2">Clear Filter</a>
  </div>
<?php endif; ?>

<?php if ($task_filter === 'open' && ($status === null || $status === '')): ?>
  <div class="alert alert-warning py-2 px-3 mb-3">
    Showing open corrective actions only.
    <a href="vessel_dashboard.php?vessel_id=<?= h($vessel_id) ?>#tasksModal" class="ms-2">Show default view</a>
  </div>
<?php endif; ?>

<!-- Filters -->
<form class="row gy-2 gx-2 align-items-end mb-3" method="get" action="">
  <input type="hidden" name="vessel_id" value="<?= h($vessel_id) ?>">
  <?php if ($run_id): ?><input type="hidden" name="icr_run_id" value="<?= h($run_id) ?>"><?php endif; ?>
  <?php if ($task_filter): ?><input type="hidden" name="task_filter" value="<?= h($task_filter) ?>"><?php endif; ?>

  <div class="col-12 col-md-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="">All (Active by default)</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= h($s) ?>" <?= ($status === $s ? 'selected' : '') ?>>
          <?= h(ucwords(str_replace('_',' ',$s))) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-12 col-md-3">
    <label class="form-label">Priority</label>
    <select name="priority" class="form-select">
      <option value="">All</option>
      <?php foreach ($priorities as $p): ?>
        <option value="<?= h($p) ?>" <?= ($priority === $p ? 'selected' : '') ?>>
          <?= h(ucfirst($p)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-6 col-md-2">
    <label class="form-label">Due From</label>
    <input type="date" name="due_from" value="<?= h($due_from ?? '') ?>" class="form-control">
  </div>
  <div class="col-6 col-md-2">
    <label class="form-label">Due To</label>
    <input type="date" name="due_to" value="<?= h($due_to ?? '') ?>" class="form-control">
  </div>

  <div class="col-12 col-md-2">
    <label class="form-label">Sort</label>
    <select name="sort" class="form-select">
      <option value="due_asc"   <?= $sort==='due_asc'?'selected':''; ?>>Due Date ↑</option>
      <option value="due_desc"  <?= $sort==='due_desc'?'selected':''; ?>>Due Date ↓</option>
      <option value="prio_asc"  <?= $sort==='prio_asc'?'selected':''; ?>>Priority ↑</option>
      <option value="prio_desc" <?= $sort==='prio_desc'?'selected':''; ?>>Priority ↓</option>
      <option value="status_asc"  <?= $sort==='status_asc'?'selected':''; ?>>Status ↑</option>
      <option value="status_desc" <?= $sort==='status_desc'?'selected':''; ?>>Status ↓</option>
    </select>
  </div>

  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary">Apply</button>
    <a class="btn btn-outline-secondary" href="?vessel_id=<?= urlencode($vessel_id) ?><?= $run_id ? '&icr_run_id='.urlencode($run_id) : '' ?><?= $task_filter ? '&task_filter='.urlencode($task_filter) : '' ?>">Reset</a>
  </div>
</form>

<input type="text" id="taskSearch" class="form-control mb-3" placeholder="🔎 Search by Title, Status, or Assignee...">

<div class="table-responsive">
  <table class="table table-sm align-middle" id="taskTable">
    <thead class="table-light">
      <tr>
        <th>Title</th>
        <th>Assigned</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Due</th>
        <th>Occurred / Completed</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php
      if (!$rows) {
          echo "<tr><td colspan='7' class='text-muted'>📭 No corrective actions match your filters.</td></tr>";
      } else {
          $today = date('Y-m-d');
          foreach ($rows as $task) {
              $assignee = trim(($task['fName'] ?? '').' '.($task['lName'] ?? ''));
              $due = $task['due_date'] ?? null;
              $isComplete = ($task['status'] === 'complete');
              $isOverdue = (!$isComplete && $due && $due < $today);
              $occurredCompleted = $task['corrected_date'] ?: $task['completed_date'];
              $currentUserId = (int)($_SESSION['user_id'] ?? 0);

              $threadId = ensureTaskThread($pdo, (int)$task['task_id'], $currentUserId);
              syncTaskThreadMembers($pdo, (int)$task['task_id'], $currentUserId);

              $messageCount = getThreadMessageCount($pdo, $threadId);
              $unreadCount  = getThreadUnreadCount($pdo, $threadId, $currentUserId);
              $rowClass = $unreadCount > 0 ? 'table-warning' : '';

              echo "<tr>";
              echo "<td>".h($task['title'] ?? '—')."</td>";
              echo "<td>".($assignee !== '' ? h($assignee) : '—')."</td>";
              echo "<td>".badge_status($task['status'] ?? '')."</td>";
              echo "<td>".badge_priority($task['priority'] ?? '')."</td>";

              echo "<td>";
              if ($due) {
                  echo $isOverdue
                    ? '<span class=\"text-danger fw-semibold\">'.dt($due).'</span>'
                    : dt($due);
              } else {
                  echo '—';
              }
              echo "</td>";

              echo "<td>".dt($occurredCompleted)."</td>";

              echo "<td class='text-end'>";
              echo "<div class='actions-grid ms-auto'>";

              echo "<a href='view_task.php?id={$task['task_id']}' class='btn btn-outline-secondary btn-sm'>Open</a>";
              echo "<a href='edit_task.php?id={$task['task_id']}' class='btn btn-primary btn-sm'>Edit</a>";

              $discussionBtnClass = $unreadCount > 0 ? 'btn-outline-danger' : 'btn-outline-dark';

              echo "<a 
                  href='task_discussion.php?task_id={$task['task_id']}'
                  class='btn {$discussionBtnClass} btn-sm'
              >";
              echo "Discussion ";
              echo "<span class='badge bg-secondary ms-1'>{$messageCount}</span>";
              if ($unreadCount > 0) {
                  echo "<span class='badge bg-danger ms-1'>{$unreadCount}</span>";
              }
              echo "</a>";

              if (!empty($task['vessel_icr_run_id'])) {
                  echo "<a href='view_icr_run.php?run_id={$task['vessel_icr_run_id']}' class='btn btn-outline-secondary btn-sm' title='View completed ICR run' target='_blank'>ICR Run</a>";
              } else {
                  echo "<a href='delete_task.php?id={$task['task_id']}&vessel_id=$vessel_id' class='btn btn-danger btn-sm' onclick=\"return confirm('Are you sure you want to delete this corrective action?')\">Delete</a>";
              }

              echo "</div>";
              echo "</td>";

              echo "</tr>";
          }
      }
    ?>
    </tbody>
  </table>
</div>

<script>
document.getElementById('taskSearch').addEventListener('input', function () {
  const term = this.value.toLowerCase();
  document.querySelectorAll('#taskTable tbody tr').forEach(row => {
    const cells = Array.from(row.cells).map(td => td.textContent.toLowerCase());
    row.style.display = cells.some(text => text.includes(term)) ? '' : 'none';
  });
});
</script>