<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../db_connect.php';
}
if (!isset($vessel_id)) exit('Vessel ID missing');

$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$today = date('Y-m-d');
$future = date('Y-m-d', strtotime('+45 days'));

// Fetch all ICRs assigned to this vessel
$stmt = $pdo->prepare("
    SELECT vi.vessel_icr_id, i.icr_id, i.icr_number, i.title, i.frequency,
        (SELECT MAX(run_date) FROM vessel_icr_runs r 
         WHERE r.vessel_id = vi.vessel_id AND r.icr_id = i.icr_id) AS last_run
    FROM vessel_icrs vi
    JOIN icrs i ON vi.icr_id = i.icr_id
    WHERE vi.vessel_id = ?
    ORDER BY i.icr_number
");
$stmt->execute([$vessel_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h4 class="mt-4">Upcoming ICRs <?= $show_all ? "(All Assigned)" : "(Next 45 Days)" ?></h4>
<div class="mb-2 text-end">
  <a href="vessel_dashboard.php?vessel_id=<?= $vessel_id ?>&tab=icrs&show_all=<?= $show_all ? '0' : '1' ?>" class="btn btn-sm btn-outline-secondary">
    <?= $show_all ? '🔍 Show Due Soon Only' : '📋 Show All Assigned ICRs' ?>
  </a>
</div>

<table class="table table-bordered table-sm align-middle" id="upcomingICRTable">
  <thead class="table-light">
    <tr>
      <th>ICR Code</th>
      <th>Title</th>
      <th>Frequency</th>
      <th>Last Completed</th>
      <th>Next Due</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($rows as $icr) {
    $lastRun = $icr['last_run'];
    $freq = $icr['frequency'];
    $nextDue = '—';
    $status = '✅ OK';
    $rowClass = '';
    $actionLink = "run_icr.php?vessel_id=$vessel_id&icr_id={$icr['icr_id']}&inspector=" . urlencode($_SESSION['username']);

    if ($lastRun) {
        $next = new DateTime($lastRun);
        switch ($freq) {
            case 'Weekly':    $next->modify('+1 week'); break;
            case 'Monthly':   $next->modify('+1 month'); break;
            case 'Quarterly': $next->modify('+3 months'); break;
            case 'Annually':  $next->modify('+1 year'); break;
        }
        $nextDue = $next->format('Y-m-d');

        if ($next < new DateTime()) {
            $status = '❌ Overdue';
            $rowClass = 'table-danger';
        } elseif ($next <= new DateTime('+45 days')) {
            $status = '⚠️ Due Soon';
            $rowClass = 'table-warning';
        } elseif (!$show_all) {
            continue;
        }
    } else {
        $status = '❌ Overdue';
        $lastRun = 'Never';
        $rowClass = 'table-danger';
    }

    echo "<tr class='$rowClass'>
        <td>" . htmlspecialchars($icr['icr_number']) . "</td>
        <td>" . htmlspecialchars($icr['title']) . "</td>
        <td>" . htmlspecialchars($freq) . "</td>
        <td>" . htmlspecialchars($lastRun) . "</td>
        <td>" . htmlspecialchars($nextDue) . "</td>
        <td>$status</td>
        <td>
            <a href='$actionLink' class='btn btn-sm btn-primary'>Perform ICR</a>
            <a href='edit_vessel_icr.php?vessel_id=$vessel_id&icr_id={$icr['icr_id']}' class='btn btn-sm btn-outline-secondary'>Edit</a>
        </td>
    </tr>";
}
?>
  </tbody>
</table>

<!-- History Button -->
<div class="text-end mt-3">
  <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#icrHistoryCollapse" aria-expanded="false" aria-controls="icrHistoryCollapse">
    📖 View All Completed ICRs
  </button>
</div>

<!-- Collapsible History Section -->
<div class="collapse mt-4" id="icrHistoryCollapse">
  <h4>📋 ICR Inspection History</h4>
  <!-- History Filter -->
<form method="get" class="mb-3">
  <input type="hidden" name="vessel_id" value="<?= $vessel_id ?>">
  <input type="hidden" name="show_all" value="<?= $show_all ? '1' : '0' ?>">
  <label class="form-label">Filter History:</label>
  <select name="range" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
    <?php
    $options = ['30' => 'Last 30 Days', '90' => 'Last 90 Days', '180' => 'Last 180 Days', '365' => 'Last 365 Days', 'all' => 'View All'];
    $selected = $_GET['range'] ?? '90';
    foreach ($options as $val => $label) {
        $sel = ($selected == $val) ? 'selected' : '';
        echo "<option value='$val' $sel>$label</option>";
    }
    ?>
  </select>
</form>
  <table class="table table-bordered table-striped table-sm align-middle" id="icrHistoryTable">
    <thead class="table-light">
      <tr>
        <th>Date</th>
        <th>ICR Code</th>
        <th>Title</th>
        <th>Inspector</th>
        <th>Failed Steps</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $range = $_GET['range'] ?? '90';
    if ($range !== 'all') {
        $cutoff = date('Y-m-d', strtotime("-$range days"));
        $stmt = $pdo->prepare("
          SELECT r.run_id, r.run_date, r.inspector, i.icr_number, i.title,
            (SELECT COUNT(*) FROM vessel_icr_step_status s
             JOIN vessel_icr_steps vs ON s.vessel_icr_step_id = vs.step_id
             WHERE s.run_id = r.run_id AND s.status = 'fail') AS failed_steps
          FROM vessel_icr_runs r
          JOIN icrs i ON r.icr_id = i.icr_id
          WHERE r.vessel_id = ? AND r.run_date >= ?
          ORDER BY r.run_date DESC
        ");
        $stmt->execute([$vessel_id, $cutoff]);
    } else {
        $stmt = $pdo->prepare("
          SELECT r.run_id, r.run_date, r.inspector, i.icr_number, i.title,
            (SELECT COUNT(*) FROM vessel_icr_step_status s
             JOIN vessel_icr_steps vs ON s.vessel_icr_step_id = vs.step_id
             WHERE s.run_id = r.run_id AND s.status = 'fail') AS failed_steps
          FROM vessel_icr_runs r
          JOIN icrs i ON r.icr_id = i.icr_id
          WHERE r.vessel_id = ?
          ORDER BY r.run_date DESC
        ");
        $stmt->execute([$vessel_id]);
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $failCount = intval($row['failed_steps']);
      $failBadge = $failCount > 0 ? "❌ $failCount" : "✅ 0";
      $rowClass = $failCount > 0 ? 'table-warning' : '';

      $viewBtn = "<a href='view_icr_run.php?run_id={$row['run_id']}' class='btn btn-sm btn-outline-primary me-1'>View</a>";
      $caBtn = $failCount > 0
        ? "<a href='vessel_dashboard.php?vessel_id=$vessel_id&run_id={$row['run_id']}#tasks' class='btn btn-sm btn-warning'>Corrective Actions</a>"
        : "";

      echo "<tr class='$rowClass'>
        <td>" . htmlspecialchars($row['run_date']) . "</td>
        <td>" . htmlspecialchars($row['icr_number']) . "</td>
        <td>" . htmlspecialchars($row['title']) . "</td>
        <td>" . htmlspecialchars($row['inspector']) . "</td>
        <td>$failBadge</td>
        <td>$viewBtn $caBtn</td>
      </tr>";
    }
    ?>
    </tbody>
  </table>
</div>
