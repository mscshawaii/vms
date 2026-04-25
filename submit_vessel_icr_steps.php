<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id     = intval($_POST['vessel_id'] ?? 0);
$icr_id        = intval($_POST['icr_id'] ?? 0);
$vessel_icr_id = intval($_POST['vessel_icr_id'] ?? 0);
$stepsPayload  = $_POST['steps'] ?? []; // shape: steps[i][id|number|description|substeps][j][id|code|description]

if (!$vessel_id || !$icr_id || !$vessel_icr_id) {
    die("❌ Invalid input.");
}

$pdo->beginTransaction();
try {
    // 1) Normalize and upsert steps (create/update). Also collect step_id mapping by "index position"
    $indexToStepId = [];
    $position = 0;

    foreach ($stepsPayload as $idx => $s) {
        $position++;
        $step_id     = $s['id'] ?? 'new';
        $step_num    = (int)($s['number'] ?? $position);
        $step_desc   = trim($s['description'] ?? '');

        if ($step_desc === '') continue;

        if ($step_id === 'new') {
            $ins = $pdo->prepare("INSERT INTO vessel_icr_steps (vessel_icr_id, step_number, step_description) VALUES (?, ?, ?)");
            $ins->execute([$vessel_icr_id, $step_num, $step_desc]);
            $newId = (int)$pdo->lastInsertId();
            $indexToStepId[$idx] = $newId;
        } else {
            $upd = $pdo->prepare("UPDATE vessel_icr_steps SET step_number = ?, step_description = ? WHERE step_id = ? AND vessel_icr_id = ?");
            $upd->execute([$step_num, $step_desc, (int)$step_id, $vessel_icr_id]);
            $indexToStepId[$idx] = (int)$step_id;
        }
    }

    // 2) Remove any steps that were deleted entirely (present in DB but not posted)
    $existingStmt = $pdo->prepare("SELECT step_id FROM vessel_icr_steps WHERE vessel_icr_id = ?");
    $existingStmt->execute([$vessel_icr_id]);
    $existingStepIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $submittedStepIds = array_values(array_unique(array_map('intval', array_filter($indexToStepId))));
    $toDelete = array_diff($existingStepIds, $submittedStepIds);

    if (!empty($toDelete)) {
        // cascade delete substeps for those steps, then steps
        $delSubs = $pdo->prepare("DELETE FROM vessel_icr_substeps WHERE vessel_step_id = ?");
        $delStep = $pdo->prepare("DELETE FROM vessel_icr_steps    WHERE step_id = ?");
        foreach ($toDelete as $sid) {
            $delSubs->execute([(int)$sid]);
            $delStep->execute([(int)$sid]);
        }
    }

    // 3) For each posted step, REPLACE its vessel substeps with exactly the submitted list
    $delStepSubs = $pdo->prepare("DELETE FROM vessel_icr_substeps WHERE vessel_step_id = ?");
    $insSub      = $pdo->prepare("INSERT INTO vessel_icr_substeps (vessel_step_id, substep_code, description) VALUES (?, ?, ?)");

    foreach ($stepsPayload as $idx => $s) {
        $vessel_step_id = $indexToStepId[$idx] ?? null;
        if (!$vessel_step_id) continue;

        // wipe existing vessel substeps for this step
        $delStepSubs->execute([$vessel_step_id]);

        // insert what was submitted
        $subs = $s['substeps'] ?? [];
        // auto-order by code A,B,C.. if needed
        usort($subs, function($a, $b) {
            return strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''));
        });

        foreach ($subs as $sub) {
            $code = strtoupper(trim((string)($sub['code'] ?? '')));
            $desc = trim((string)($sub['description'] ?? ''));
            if ($code === '' || $desc === '') continue;
            $insSub->execute([$vessel_step_id, $code, $desc]);
        }
    }

    $pdo->commit();
    header("Location: vessel_icrs.php?vessel_id={$vessel_id}&steps_saved=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("❌ Failed to save ICR steps: " . $e->getMessage());
}
