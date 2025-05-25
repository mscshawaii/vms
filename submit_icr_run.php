<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_POST['vessel_id'] ?? 0);
$icr_id = intval($_POST['icr_id'] ?? 0);
$vessel_icr_id = intval($_POST['vessel_icr_id'] ?? 0);
$inspector = trim($_POST['inspector'] ?? 'Unknown');

$step_ids = $_POST['step_id'] ?? [];
$statuses = $_POST['status'] ?? [];
$comments = $_POST['comment'] ?? [];
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// Insert ICR run
$run_stmt = $pdo->prepare("
    INSERT INTO vessel_icr_runs (vessel_id, icr_id, vessel_icr_id, run_date, inspector)
    VALUES (?, ?, ?, ?, ?)
");
$success = $run_stmt->execute([$vessel_id, $icr_id, $vessel_icr_id, $today, $inspector]);

if (!$success) {
    die("❌ Error submitting inspection: " . $run_stmt->errorInfo()[2]);
}

$run_id = $pdo->lastInsertId();

// Insert step statuses and generate corrective actions
$step_stmt = $pdo->prepare("
    INSERT INTO vessel_icr_step_status (run_id, vessel_icr_step_id, status, comment)
    VALUES (?, ?, ?, ?)
");

// Enhanced task insert includes vessel_icr_id
$task_stmt = $pdo->prepare("
    INSERT INTO tasks (
        title, description, vessel_id, due_date,
        priority, corrective_action, created_at, updated_at,
        vessel_icr_id, vessel_icr_run_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");


foreach ($step_ids as $index => $step_id) {
    $status = $statuses[$index];
    $comment = trim($comments[$index]);

    // Save the step status
    $step_stmt->execute([$run_id, $step_id, $status, $comment]);

    if ($status === 'fail') {
        // Fetch step and ICR details
        $step_info = $pdo->prepare("
            SELECT s.step_number, s.step_description, i.icr_number, i.title, i.reference_text
            FROM vessel_icr_steps s
            JOIN vessel_icrs vi ON s.vessel_icr_id = vi.vessel_icr_id
            JOIN icrs i ON vi.icr_id = i.icr_id
            WHERE s.step_id = ?
        ");
        $step_info->execute([$step_id]);
        $data = $step_info->fetch(PDO::FETCH_ASSOC);

        // Avoid duplicate task for same failed step
        $title_check = "%Step {$data['step_number']}%";
        $dup_check = $pdo->prepare("
            SELECT COUNT(*) FROM tasks
            WHERE vessel_id = ? AND vessel_icr_id = ? AND title LIKE ? AND status IN ('open', 'in_progress')
        ");
        $dup_check->execute([$vessel_id, $vessel_icr_id, $title_check]);
        $existing = $dup_check->fetchColumn();

        if ($existing == 0) {
            $due_date = date('Y-m-d', strtotime('+7 days'));
            $title = "ICR {$data['icr_number']} – Step {$data['step_number']}: {$data['step_description']}";
            $description = !empty($comment) ? $comment : '(No description provided)';
            $corrective_action = ''; // Leave blank for now — to be filled in after action is taken
            

            // Insert the new corrective action
            $task_stmt->execute([
                $title,
                $description,
                $vessel_id,
                $due_date,
                'moderate',
                $corrective_action,
                $now,
                $now,
                $vessel_icr_id,
                $run_id
            ]);
            
            
        }
    }
}

header("Location: vessel_dashboard.php?vessel_id=$vessel_id#icrs&success=icr_run");
exit;
?>
