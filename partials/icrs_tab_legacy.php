<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">📋 Vessel ICRs</h4>

  <a
    href="add_vessel_icr.php?vessel_id=<?= $vessel_id ?>"
    class="btn btn-sm btn-primary"
  >
    ➕ Add ICR from Template
  </a>

  <button
    class="btn btn-sm btn-outline-secondary"
    id="btnCreateCustomIcr"
    data-vessel-id="<?= $vessel_id ?>"
  >
    🆕 Create Custom ICR
  </button>
</div>

<div id="icrsUpcomingContainer" class="icr-upcoming-scroll">
  <?php include 'partials/icrs_upcoming.php'; ?>
</div>

<!-- History Button -->
<div class="text-end mt-3">
  <button
    class="btn btn-outline-primary"
    data-bs-toggle="collapse"
    data-bs-target="#icrHistoryCollapse"
    aria-expanded="false"
    aria-controls="icrHistoryCollapse"
  >
    📓 View All Completed ICRs
  </button>
</div>

<style>
  .icr-history-scroll {
    max-height: 65vh;
    overflow-y: auto;
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-radius: .375rem;
    background: #fff;
  }

  .icr-history-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8f9fa;
    white-space: nowrap;
  }

  #icrHistoryTable {
    margin-bottom: 0;
  }

  #icrHistoryTable td,
  #icrHistoryTable th {
    vertical-align: middle;
    white-space: nowrap;
  }

  #icrHistoryTable td:nth-child(3),
  #icrHistoryTable th:nth-child(3) {
    white-space: normal;
    min-width: 260px;
  }
</style>

<!-- Collapsible History Section -->
<div class="collapse mt-4" id="icrHistoryCollapse">
  <h4>📋 ICR Inspection History</h4>

  <form method="get" class="mb-3">
    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
    <input type="hidden" name="show_all" value="<?= $show_all ? '1' : '0' ?>">

    <label class="form-label">Filter History:</label>
    <select name="range" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
      <?php
      $options = [
          '30'  => 'Last 30 Days',
          '90'  => 'Last 90 Days',
          '180' => 'Last 180 Days',
          '365' => 'Last 365 Days',
          'all' => 'View All'
      ];
      $selected = $_GET['range'] ?? '90';

      foreach ($options as $val => $label) {
          $sel = ($selected === $val) ? 'selected' : '';
          echo "<option value=\"" . htmlspecialchars($val) . "\" $sel>" . htmlspecialchars($label) . "</option>";
      }
      ?>
    </select>
  </form>

  <div class="icr-history-scroll mt-3">
    <table class="table table-bordered table-striped table-sm align-middle mb-0" id="icrHistoryTable">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>ICR Code</th>
          <th>Title</th>
          <th>Inspector</th>
          <th>Failed Items</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $range = $_GET['range'] ?? '90';

        $sql = "
          SELECT
            r.run_id,
            r.run_date,
            r.inspector,
            i.icr_number,
            i.title,
            COALESCE(fs.failed_steps, 0)     AS failed_steps,
            COALESCE(fss.failed_substeps, 0) AS failed_substeps
          FROM vessel_icr_runs r
          JOIN icrs i ON r.icr_id = i.icr_id
          LEFT JOIN (
            SELECT run_id, COUNT(*) AS failed_steps
            FROM vessel_icr_step_status
            WHERE LOWER(status) = 'fail'
            GROUP BY run_id
          ) fs ON fs.run_id = r.run_id
          LEFT JOIN (
            SELECT run_id, COUNT(*) AS failed_substeps
            FROM vessel_icr_substep_status
            WHERE LOWER(status) = 'fail'
            GROUP BY run_id
          ) fss ON fss.run_id = r.run_id
          WHERE r.vessel_id = :vessel_id
            AND r.save_state = 'final'
        ";

        $params = [':vessel_id' => $vessel_id];

        if ($range !== 'all') {
          $cutoff = date('Y-m-d', strtotime("-$range days"));
          $sql .= " AND r.run_date >= :cutoff";
          $params[':cutoff'] = $cutoff;
        }

        $sql .= " ORDER BY r.run_date DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $failSteps    = (int)$row['failed_steps'];
          $failSubsteps = (int)$row['failed_substeps'];
          $failTotal    = $failSteps + $failSubsteps;

          $failBadge = $failTotal > 0 ? "❌ $failTotal" : "✅ 0";
          $rowClass  = $failTotal > 0 ? 'table-warning' : '';

          $viewBtn = "<a href='view_icr_run.php?run_id={$row['run_id']}' class='btn btn-sm btn-outline-primary me-1'>View</a>";
          $caBtn   = $failTotal > 0
            ? "<a href='vessel_dashboard.php?vessel_id={$vessel_id}&icr_run_id={$row['run_id']}#tasks' class='btn btn-sm btn-warning'>Corrective Actions</a>"
            : "";

          echo "<tr class='{$rowClass}'>
            <td>" . htmlspecialchars($row['run_date']) . "</td>
            <td>" . htmlspecialchars($row['icr_number']) . "</td>
            <td>" . htmlspecialchars($row['title']) . "</td>
            <td>" . htmlspecialchars($row['inspector']) . "</td>
            <td>{$failBadge}</td>
            <td>{$viewBtn} {$caBtn}</td>
          </tr>";
        }
      ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function bindIcrToggle() {
  document.getElementById('toggleShowAllIcrs')?.addEventListener('click', function () {
    const current = this.dataset.showAll === '1' ? 1 : 0;
    const newShowAll = current === 1 ? 0 : 1;

    fetch(`partials/icrs_upcoming.php?vessel_id=<?= $vessel_id ?>&show_all=${newShowAll}`)
      .then(res => res.text())
      .then(html => {
        document.getElementById('icrsUpcomingContainer').innerHTML = html;
        bindIcrToggle();
      })
      .catch(err => console.error('Failed to load ICRs:', err));
  });
}

document.addEventListener('DOMContentLoaded', bindIcrToggle);
</script>