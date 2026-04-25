<?php
require 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/** ---------- Helpers ---------- */
function table_exists(PDO $pdo, string $name): bool {
    try { $pdo->query("SELECT 1 FROM `$name` LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

function normalize_code($code, $index): string {
    $code = strtoupper(trim((string)$code));
    return $code === '' ? chr(65 + $index) : $code; // A + index
}

/** Parse nested steps payload (with legacy fallback) */
function get_nested_steps_from_post(): array {
    if (!empty($_POST['steps']) && is_array($_POST['steps'])) {
        $out = [];
        foreach ($_POST['steps'] as $s) {
            $row = [
                'id'                 => isset($s['id']) ? (string)$s['id'] : 'new',
                'number'             => isset($s['number']) ? (int)$s['number'] : 0,
                'description'        => isset($s['description']) ? trim((string)$s['description']) : '',
                'deficiency_action'  => isset($s['deficiency_action']) ? trim((string)$s['deficiency_action']) : null,
                'substeps'           => []
            ];
            if (!empty($s['substeps']) && is_array($s['substeps'])) {
                foreach ($s['substeps'] as $j => $sub) {
                    $row['substeps'][] = [
                        'id'                => isset($sub['id']) ? (string)$sub['id'] : 'new',
                        'code'              => normalize_code($sub['code'] ?? '', $j),
                        'description'       => isset($sub['description']) ? trim((string)$sub['description']) : '',
                        'deficiency_action' => isset($sub['deficiency_action']) ? trim((string)$sub['deficiency_action']) : null,
                    ];
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    // Legacy flat (no substeps)
    $step_ids            = $_POST['step_id']            ?? [];
    $step_numbers        = $_POST['step_number']        ?? [];
    $step_descriptions   = $_POST['step_description']   ?? [];
    $deficiency_actions  = $_POST['deficiency_action']  ?? [];
    $out = [];
    $n = max(count($step_ids), count($step_numbers), count($step_descriptions), count($deficiency_actions));
    for ($i=0;$i<$n;$i++){
        $out[] = [
            'id'                 => isset($step_ids[$i]) ? (string)$step_ids[$i] : 'new',
            'number'             => isset($step_numbers[$i]) ? (int)$step_numbers[$i] : ($i+1),
            'description'        => isset($step_descriptions[$i]) ? trim((string)$step_descriptions[$i]) : '',
            'deficiency_action'  => isset($deficiency_actions[$i]) ? trim((string)$deficiency_actions[$i]) : null,
            'substeps'           => []
        ];
    }
    return $out;
}

/** Snapshot master steps/substeps keyed by step_number / substep_code */
function get_master_snapshot(PDO $pdo, int $icr_id): array {
    $snap = [];
    $steps = $pdo->prepare("
        SELECT step_id, step_number, step_description, deficiency_action
        FROM icr_steps WHERE icr_id=? ORDER BY step_number
    ");
    $steps->execute([$icr_id]);
    while ($s = $steps->fetch(PDO::FETCH_ASSOC)) {
        $num = (int)$s['step_number'];
        $snap[$num] = [
            'step_id' => (int)$s['step_id'],
            'description' => (string)$s['step_description'],
            'deficiency_action' => $s['deficiency_action'],
            'substeps' => []
        ];
        $subs = $pdo->prepare("
            SELECT substep_id, substep_code, description, deficiency_action
            FROM icr_substeps WHERE step_id=? ORDER BY substep_code
        ");
        $subs->execute([$s['step_id']]);
        while ($sub = $subs->fetch(PDO::FETCH_ASSOC)) {
            $snap[$num]['substeps'][strtoupper($sub['substep_code'])] = [
                'substep_id' => (int)$sub['substep_id'],
                'description' => (string)$sub['description'],
                'deficiency_action' => $sub['deficiency_action']
            ];
        }
    }
    return $snap;
}

/**
 * Merge-sync to a vessel:
 * - Add master steps/substeps missing on vessel.
 * - Update vessel text only if it matched the OLD master (not customized).
 * - Never delete or renumber vessel content.
 */
function merge_icr_to_vessel(PDO $pdo, int $icr_id, int $vessel_icr_id, array $oldMaster, array $newMaster): void {
    $has_vessel_subs = table_exists($pdo, 'vessel_icr_substeps');

    // Vessel steps keyed by step_number
    $vSteps = [];
    $vs = $pdo->prepare("
        SELECT step_id, step_number, step_description
        FROM vessel_icr_steps
        WHERE vessel_icr_id = ?
    ");
    $vs->execute([$vessel_icr_id]);
    while ($r = $vs->fetch(PDO::FETCH_ASSOC)) {
        $vSteps[(int)$r['step_number']] = [
            'step_id' => (int)$r['step_id'],
            'description' => (string)$r['step_description']
        ];
    }

    // Prepared statements
    $ins_vs = $pdo->prepare("
        INSERT INTO vessel_icr_steps (vessel_icr_id, step_number, step_description)
        VALUES (?,?,?)
    ");
    $upd_vs = $pdo->prepare("
        UPDATE vessel_icr_steps SET step_description=? WHERE step_id=? AND vessel_icr_id=?
    ");

    if ($has_vessel_subs) {
        $load_subs = $pdo->prepare("
            SELECT substep_id, substep_code, description
            FROM vessel_icr_substeps
            WHERE vessel_step_id = ?
        ");
        $ins_vsub = $pdo->prepare("
            INSERT INTO vessel_icr_substeps (vessel_step_id, substep_code, description)
            VALUES (?,?,?)
        ");
        $upd_vsub = $pdo->prepare("
            UPDATE vessel_icr_substeps SET description=? WHERE substep_id=? AND vessel_step_id=?
        ");
    }

    $pdo->beginTransaction();
    try {
        foreach ($newMaster as $num => $mStep) {
            $exists = isset($vSteps[$num]);
            if (!$exists) {
                // Add missing step with NEW master text
                $ins_vs->execute([$vessel_icr_id, $num, $mStep['description']]);
                $vessel_step_id = (int)$pdo->lastInsertId();
            } else {
                $vessel_step_id = $vSteps[$num]['step_id'];
                $v_desc = $vSteps[$num]['description'];
                // If vessel equals OLD master (not customized), update to NEW master
                $old_desc = $oldMaster[$num]['description'] ?? null;
                if ($old_desc !== null && $v_desc === $old_desc) {
                    $upd_vs->execute([$mStep['description'], $vessel_step_id, $vessel_icr_id]);
                }
            }

            // Substeps
            if ($has_vessel_subs) {
                // Load vessel substeps keyed by code
                $vSubs = [];
                $load_subs->execute([$vessel_step_id]);
                while ($sr = $load_subs->fetch(PDO::FETCH_ASSOC)) {
                    $vSubs[strtoupper($sr['substep_code'])] = [
                        'substep_id' => (int)$sr['substep_id'],
                        'description' => (string)$sr['description']
                    ];
                }

                $mSubsNew = $mStep['substeps'] ?? [];
                foreach ($mSubsNew as $code => $mSub) {
                    $codeU = strtoupper($code);
                    if (!isset($vSubs[$codeU])) {
                        // Add missing substep with NEW master text
                        $ins_vsub->execute([$vessel_step_id, $codeU, $mSub['description']]);
                    } else {
                        $v_sdesc = $vSubs[$codeU]['description'];
                        $old_sdesc = $oldMaster[$num]['substeps'][$codeU]['description'] ?? null;
                        // Update only if vessel matches OLD master (not customized)
                        if ($old_sdesc !== null && $v_sdesc === $old_sdesc) {
                            $upd_vsub->execute([$mSub['description'], $vSubs[$codeU]['substep_id'], $vessel_step_id]);
                        }
                    }
                }
                // Note: master-removed substeps are kept on vessel (custom retention).
            }
        }

        // Note: master-removed steps are kept on vessel (custom retention).

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** ---------- Inputs ---------- */
$icr_id        = (int)($_POST['icr_id'] ?? 0);
$icr_number    = trim($_POST['icr_number'] ?? '');
$title         = trim($_POST['title'] ?? '');
$reference_text= isset($_POST['reference_text']) ? trim($_POST['reference_text']) : null;
$frequency     = trim($_POST['frequency'] ?? '');
$do_sync       = !empty($_POST['sync_to_vessels']);

if ($icr_id <= 0 || $icr_number === '' || $title === '' || $frequency === '') {
    http_response_code(422);
    die("❌ Missing required ICR fields.");
}

$steps = get_nested_steps_from_post();
if (!is_array($steps)) $steps = [];

/** ---------- 1) Snapshot OLD master ---------- */
$oldMaster = get_master_snapshot($pdo, $icr_id);

/** ---------- 2) Update master (transaction) ---------- */
$pdo->beginTransaction();
try {
    // Update header
    $pdo->prepare("
        UPDATE icrs SET icr_number=?, title=?, reference_text=?, frequency=? WHERE icr_id=?
    ")->execute([$icr_number, $title, $reference_text, $frequency, $icr_id]);

    // Existing step ids
    $stmt = $pdo->prepare("SELECT step_id FROM icr_steps WHERE icr_id = ?");
    $stmt->execute([$icr_id]);
    $existing_step_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $submitted_existing_step_ids = [];
    foreach ($steps as $s) {
        if ($s['id'] !== 'new' && ctype_digit((string)$s['id'])) {
            $submitted_existing_step_ids[] = (int)$s['id'];
        }
    }

    // Delete removed steps + their substeps
    $to_delete_steps = array_diff($existing_step_ids, $submitted_existing_step_ids);
    if (!empty($to_delete_steps)) {
        $in = implode(',', array_fill(0, count($to_delete_steps), '?'));
        $pdo->prepare("DELETE FROM icr_substeps WHERE step_id IN ($in)")->execute(array_values($to_delete_steps));
        $pdo->prepare("DELETE FROM icr_steps WHERE step_id IN ($in)")->execute(array_values($to_delete_steps));
    }

    // Prep statements
    $ins_step = $pdo->prepare("
        INSERT INTO icr_steps (icr_id, step_number, step_description, deficiency_action)
        VALUES (?,?,?,?)
    ");
    $upd_step = $pdo->prepare("
        UPDATE icr_steps SET step_number=?, step_description=?, deficiency_action=?
        WHERE step_id=? AND icr_id=?
    ");

    $sel_sub_ids = $pdo->prepare("SELECT substep_id FROM icr_substeps WHERE step_id = ?");
    $del_sub     = $pdo->prepare("DELETE FROM icr_substeps WHERE substep_id = ? AND step_id = ?");
    $ins_sub     = $pdo->prepare("
        INSERT INTO icr_substeps (step_id, substep_code, description, deficiency_action)
        VALUES (?,?,?,?)
    ");
    $upd_sub     = $pdo->prepare("
        UPDATE icr_substeps SET substep_code=?, description=?, deficiency_action=?
        WHERE substep_id=? AND step_id=?
    ");

    // Upsert steps + substeps
    foreach ($steps as $i => $s) {
        $number = (int)$s['number']; if ($number <= 0) $number = $i + 1;
        $desc   = (string)$s['description'];
        $defa   = $s['deficiency_action'] !== '' ? (string)$s['deficiency_action'] : null;
        $idRaw  = (string)$s['id'];

        if ($idRaw === 'new' || !ctype_digit($idRaw)) {
            $ins_step->execute([$icr_id, $number, $desc, $defa]);
            $step_id = (int)$pdo->lastInsertId();
        } else {
            $step_id = (int)$idRaw;
            $upd_step->execute([$number, $desc, $defa, $step_id, $icr_id]);
        }

        // Substeps for this step
        $sel_sub_ids->execute([$step_id]);
        $existing_sub_ids = array_map('intval', $sel_sub_ids->fetchAll(PDO::FETCH_COLUMN));

        $submitted_existing_sub_ids = [];
        foreach ($s['substeps'] as $sub) {
            if ($sub['id'] !== 'new' && ctype_digit((string)$sub['id'])) {
                $submitted_existing_sub_ids[] = (int)$sub['id'];
            }
        }

        // Delete removed substeps
        $subs_to_delete = array_diff($existing_sub_ids, $submitted_existing_sub_ids);
        foreach ($subs_to_delete as $del_id) {
            $del_sub->execute([$del_id, $step_id]);
        }

        foreach ($s['substeps'] as $j => $sub) {
            $code  = normalize_code($sub['code'] ?? '', $j);
            $sdesc = (string)($sub['description'] ?? '');
            $sdefa = ($sub['deficiency_action'] ?? '') !== '' ? (string)$sub['deficiency_action'] : null;

            if (($sub['id'] ?? 'new') === 'new' || !ctype_digit((string)$sub['id'])) {
                $ins_sub->execute([$step_id, $code, $sdesc, $sdefa]);
            } else {
                $sub_id = (int)$sub['id'];
                $upd_sub->execute([$code, $sdesc, $sdefa, $sub_id, $step_id]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo '<pre style="white-space:pre-wrap;">❌ Failed to update ICR: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}

/** ---------- 3) Snapshot NEW master (for merge baseline) ---------- */
$newMaster = get_master_snapshot($pdo, $icr_id);

/** ---------- 4) Optional merge-sync to vessels ---------- */
$do_sync = !empty($_POST['sync_to_vessels']);
if ($do_sync) {
    try {
        $q = $pdo->prepare("SELECT vessel_icr_id FROM vessel_icrs WHERE icr_id = ?");
        $q->execute([$icr_id]);
        $vessel_icr_ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));

        $merged = 0;
        foreach ($vessel_icr_ids as $vid) {
            merge_icr_to_vessel($pdo, $icr_id, $vid, $oldMaster, $newMaster);
            $merged++;
        }
        header("Location: icr_templates.php?updated=1&merged={$merged}");
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap;">⚠️ ICR saved, but merge-sync had an issue: '
             . htmlspecialchars($e->getMessage()) . '</pre>';
        exit;
    }
}

// Done
header("Location: icr_templates.php?updated=1");
exit;
