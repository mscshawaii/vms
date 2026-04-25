<?php
if (!isset($pdo)) require_once __DIR__ . '/../db_connect.php';
if (!isset($vessel_id)) exit('Vessel ID is required');

$range = $_GET['range'] ?? '90';

$sqlBase = "
    SELECT
        r.run_id,
        r.run_date,
        r.inspector,
        i.icr_number,
        i.title,
        COALESCE((
            SELECT COUNT(*)
            FROM vessel_icr_step_status s
            WHERE s.run_id = r.run_id AND LOWER(s.status) = 'fail'
        ),0) AS failed_steps,
        COALESCE((
            SELECT COUNT(*)
            FROM vessel_icr_substep_status ss
            WHERE ss.run_id = r.run_id AND LOWER(ss.status) = 'fail'
        ),0) AS failed_substeps
    FROM vessel_icr_runs r
    JOIN icrs i ON r.icr_id = i.icr_id
    WHERE r.vessel_id = ?
";

if ($range !== 'all') {
    $cutoff = date('Y-m-d', strtotime("-$range days"));
    $sql = $sqlBase . " AND r.run_date >= ? ORDER BY r.run_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vessel_id, $cutoff]);
} else {
    $sql = $sqlBase . " ORDER BY r.run_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vessel_id]);
}
?>

<style>
    .icr-history-scroll {
        max-height: 65vh;
        overflow-y: auto;
        overflow-x: auto;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }

    .icr-history-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f8f9fa;
        white-space: nowrap;
    }

    #icrHistoryTable {
        margin-top: 0 !important;
        margin-bottom: 0;
    }

    #icrHistoryTable td,
    #icrHistoryTable th {
        white-space: nowrap;
        vertical-align: middle;
    }

    #icrHistoryTable td:nth-child(3),
    #icrHistoryTable th:nth-child(3) {
        white-space: normal;
        min-width: 260px;
    }
</style>

<div class="icr-history-scroll mt-3">
    <table class="table table-bordered table-striped table-sm align-middle mb-0" id="icrHistoryTable">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>ICR Code</th>
                <th>Title</th>
                <th>Inspector</th>
                <th>Failed (Steps)</th>
                <th>Failed (Substeps)</th>
                <th>Failed (Total)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $failSteps    = (int)$row['failed_steps'];
                $failSubsteps = (int)$row['failed_substeps'];
                $failTotal    = $failSteps + $failSubsteps;

                $badgeSteps    = $failSteps    > 0 ? "❌ $failSteps"    : "✅ 0";
                $badgeSubsteps = $failSubsteps > 0 ? "❌ $failSubsteps" : "✅ 0";
                $badgeTotal    = $failTotal    > 0 ? "❌ $failTotal"    : "✅ 0";
                $rowClass      = $failTotal    > 0 ? 'table-warning'    : '';

                echo "<tr class='{$rowClass}'>
                    <td>" . htmlspecialchars($row['run_date']) . "</td>
                    <td>" . htmlspecialchars($row['icr_number']) . "</td>
                    <td>" . htmlspecialchars($row['title']) . "</td>
                    <td>" . htmlspecialchars($row['inspector']) . "</td>
                    <td>{$badgeSteps}</td>
                    <td>{$badgeSubsteps}</td>
                    <td>{$badgeTotal}</td>
                    <td>
                        <a href='view_icr_run.php?run_id={$row['run_id']}' class='btn btn-sm btn-outline-primary me-1'>View</a>";

                if ($failTotal > 0) {
                    echo "<a href='vessel_dashboard.php?vessel_id={$vessel_id}&icr_run_id={$row['run_id']}#tasks' class='btn btn-sm btn-warning'>Corrective Actions</a>";
                }

                echo "</td></tr>";
            } ?>
        </tbody>
    </table>
</div>