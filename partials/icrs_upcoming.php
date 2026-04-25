<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/../db_connect.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_GET['vessel_id'])) exit('Vessel ID missing');
$vessel_id = (int) $_GET['vessel_id'];

$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$today = date('Y-m-d');
$future = date('Y-m-d', strtotime('+45 days'));

$inspector = isset($_SESSION['username']) ? urlencode($_SESSION['username']) : 'unknown';

// Fetch ICRs assigned to this vessel
$stmt = $pdo->prepare("
    SELECT
        vi.vessel_icr_id,
        i.icr_id,
        i.icr_number,
        i.title,
        i.frequency,

        /* last completed/finalized run only */
        (
            SELECT MAX(r.run_date)
            FROM vessel_icr_runs r
            WHERE r.vessel_icr_id = vi.vessel_icr_id
              AND r.save_state = 'final'
        ) AS last_run,

        /* latest draft run id, if any */
        (
            SELECT r2.run_id
            FROM vessel_icr_runs r2
            WHERE r2.vessel_icr_id = vi.vessel_icr_id
              AND r2.save_state = 'draft'
            ORDER BY r2.run_id DESC
            LIMIT 1
        ) AS draft_run_id

    FROM vessel_icrs vi
    JOIN icrs i ON vi.icr_id = i.icr_id
    WHERE vi.vessel_id = ?
      AND vi.is_removed = 0
    ORDER BY i.icr_number
");

$stmt->execute([$vessel_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h4 class="mt-4">Upcoming ICRs <?= $show_all ? "(All Assigned)" : "(Next 45 Days)" ?></h4>
<div class="mb-2 text-end">
  <button id="toggleShowAllIcrs" class="btn btn-sm btn-outline-secondary" data-show-all="<?= $show_all ? '1' : '0' ?>">
    <?= $show_all ? '🔍 Show Due Soon Only' : '📋 Show All Assigned ICRs' ?>
  </button>
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
    $draftRunId = (int)($icr['draft_run_id'] ?? 0);

    $actionLink = "run_icr.php?vessel_id=$vessel_id&vessel_icr_id={$icr['vessel_icr_id']}&icr_id={$icr['icr_id']}&inspector=$inspector";

    $actionBtnClass = $draftRunId > 0 ? 'btn btn-sm btn-warning' : 'btn btn-sm btn-primary';
    $actionBtnLabel = $draftRunId > 0 ? 'Resume Draft' : 'Perform ICR';
    $draftBadge     = $draftRunId > 0 ? "<span class='badge bg-warning text-dark ms-2'>Draft In Progress</span>" : "";

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

    $removeUrl = "remove_vessel_icr.php?vessel_icr_id={$icr['vessel_icr_id']}&vessel_id=$vessel_id";

echo <<<HTML
<tr class="$rowClass">
    <td>{$icr['icr_number']}</td>
    <td>{$icr['title']}</td>
    <td>$freq</td>
    <td>$lastRun</td>
    <td>$nextDue</td>
    <td>$status $draftBadge</td>
    <td>
        <a href="$actionLink" class="$actionBtnClass">$actionBtnLabel</a>
        <a href="edit_vessel_icr.php?vessel_id=$vessel_id&icr_id={$icr['icr_id']}" class="btn btn-sm btn-outline-secondary">Edit</a>
        <form method="POST" action="remove_vessel_icr.php" style="display:inline;">
            <input type="hidden" name="vessel_icr_id" value="{$icr['vessel_icr_id']}">
            <input type="hidden" name="vessel_id" value="$vessel_id">
            <button type="submit"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Remove this ICR from the vessel? This will hide it from the active list but preserves history.')">
            🗑 Remove
            </button>
        </form>

    </td>
</tr>
HTML;

}
?>
  </tbody>
</table>
