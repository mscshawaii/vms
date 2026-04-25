<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';

// ---------- helpers ----------
function clean($v){ return isset($v) ? trim((string)$v) : null; }
function null_if_empty($v){ $v = clean($v); return ($v === '' ? null : $v); }

// Optional: write to task_photos if present
function try_insert_task_photo(PDO $pdo, int $task_id, string $relPath, ?string $caption = null): void {
    try {
        $pdo->prepare("
            INSERT INTO task_photos (task_id, file_path, caption, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$task_id, $relPath, $caption]);
    } catch (Throwable $e) {
        // table may not exist; ignore
    }
}

function is_allowed_image(string $tmp_path, string $orig_name): bool {
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) return false;
    $info = @getimagesize($tmp_path);
    return $info !== false;
}

// ---------- read POST ----------
$task_id           = (int)($_POST['task_id'] ?? 0);
$vessel_id         = (int)($_POST['vessel_id'] ?? 0);

$title             = null_if_empty($_POST['title'] ?? '');
$due_date          = null_if_empty($_POST['due_date'] ?? '');
$description       = clean($_POST['description'] ?? '');
$notes             = clean($_POST['notes'] ?? '');
$priority          = null_if_empty($_POST['priority'] ?? 'moderate') ?: 'moderate';
$assigned_to       = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
$recurrence        = null_if_empty($_POST['recurrence_interval'] ?? 'none') ?: 'none';
$corrective_action = null_if_empty($_POST['corrective_action'] ?? null);
$corrected_date    = null_if_empty($_POST['corrected_date'] ?? null);
$status            = null_if_empty($_POST['status'] ?? 'open') ?: 'open';
$regulation        = null_if_empty($_POST['regulation'] ?? null);

$notify_users = isset($_POST['notify_users']) && is_array($_POST['notify_users'])
    ? array_values(array_unique(array_filter(array_map('intval', $_POST['notify_users']), fn($id) => $id > 0)))
    : [];

// Mirror regulation into description if not already present
if ($regulation) {
    $needle = 'Supporting regulation:';
    if (stripos($description ?? '', $needle) === false) {
        $description = rtrim((string)$description);
        $description .= ($description === '' ? '' : "\n\n") . "Supporting regulation: " . $regulation;
    }
}

// Guard
if ($task_id <= 0 || $vessel_id <= 0 || !$title) {
    http_response_code(422);
    die("❌ Missing required fields.");
}

if ($assigned_to === null || $assigned_to <= 0) {
    http_response_code(422);
    die("❌ Assigned To is required.");
}

// Detect if tasks.supporting_regulations exists
$has_supporting_col = false;
$has_notes_col = false;

try {
    $chk = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tasks'
          AND COLUMN_NAME IN ('supporting_regulations', 'notes')
    ");
    $chk->execute();
    $cols = $chk->fetchAll(PDO::FETCH_COLUMN);

    $has_supporting_col = in_array('supporting_regulations', $cols, true);
    $has_notes_col = in_array('notes', $cols, true);
} catch (Throwable $e) {
    $has_supporting_col = false;
    $has_notes_col = false;
}

// Validate assignee/notify candidates against active vessel-assigned users
$validUserStmt = $pdo->prepare("
    SELECT DISTINCT u.id
    FROM vessel_crew vc
    INNER JOIN users u
        ON u.id = vc.crew_id
    WHERE vc.vessel_id = ?
      AND vc.is_active = 1
      AND u.is_active = 1
");
$validUserStmt->execute([$vessel_id]);
$validUserIds = array_map('intval', $validUserStmt->fetchAll(PDO::FETCH_COLUMN));
$validUserSet = array_flip($validUserIds);

if (!isset($validUserSet[$assigned_to])) {
    http_response_code(422);
    die("❌ Selected primary assignee is not an active user assigned to this vessel.");
}

$notify_users = array_values(array_filter($notify_users, function($id) use ($validUserSet) {
    return isset($validUserSet[$id]);
}));

if (!in_array($assigned_to, $notify_users, true)) {
    $notify_users[] = $assigned_to;
}

try {
    $pdo->beginTransaction();

    // ---------- update ----------
    if ($has_supporting_col && $has_notes_col) {
        $sql = "
            UPDATE tasks
            SET title = ?, due_date = ?, description = ?, notes = ?, priority = ?,
                assigned_to = ?, recurrence_interval = ?, corrective_action = ?,
                corrected_date = ?, status = ?, supporting_regulations = ?
            WHERE task_id = ?
        ";
        $params = [
            $title, $due_date, $description, ($notes !== '' ? $notes : null), $priority,
            $assigned_to, $recurrence, $corrective_action,
            $corrected_date, $status, $regulation, $task_id
        ];
    } elseif ($has_supporting_col && !$has_notes_col) {
        $sql = "
            UPDATE tasks
            SET title = ?, due_date = ?, description = ?, priority = ?,
                assigned_to = ?, recurrence_interval = ?, corrective_action = ?,
                corrected_date = ?, status = ?, supporting_regulations = ?
            WHERE task_id = ?
        ";
        $params = [
            $title, $due_date, $description, $priority,
            $assigned_to, $recurrence, $corrective_action,
            $corrected_date, $status, $regulation, $task_id
        ];
    } elseif (!$has_supporting_col && $has_notes_col) {
        $sql = "
            UPDATE tasks
            SET title = ?, due_date = ?, description = ?, notes = ?, priority = ?,
                assigned_to = ?, recurrence_interval = ?, corrective_action = ?,
                corrected_date = ?, status = ?
            WHERE task_id = ?
        ";
        $params = [
            $title, $due_date, $description, ($notes !== '' ? $notes : null), $priority,
            $assigned_to, $recurrence, $corrective_action,
            $corrected_date, $status, $task_id
        ];
    } else {
        $sql = "
            UPDATE tasks
            SET title = ?, due_date = ?, description = ?, priority = ?,
                assigned_to = ?, recurrence_interval = ?, corrective_action = ?,
                corrected_date = ?, status = ?
            WHERE task_id = ?
        ";
        $params = [
            $title, $due_date, $description, $priority,
            $assigned_to, $recurrence, $corrective_action,
            $corrected_date, $status, $task_id
        ];
    }

    $upd = $pdo->prepare($sql);
    if (!$upd->execute($params)) {
        throw new RuntimeException('Failed to update task.');
    }

    // Replace notification recipients
    try {
        $pdo->prepare("DELETE FROM task_notification_recipients WHERE task_id = ?")->execute([$task_id]);

        if (!empty($notify_users)) {
            $notifyStmt = $pdo->prepare("
                INSERT INTO task_notification_recipients (task_id, user_id)
                VALUES (?, ?)
            ");

            foreach ($notify_users as $notify_user_id) {
                $notifyStmt->execute([$task_id, $notify_user_id]);
            }
        }
    } catch (Throwable $e) {
        // If table does not exist yet in an environment, fail loudly so schema stays aligned
        throw $e;
    }

        // ---------- ensure CAR discussion thread + members ----------
    syncTaskThreadMembers($pdo, $task_id, (int)($_SESSION['user_id'] ?? 0));

    // ---------- handle new uploads ----------
    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $names  = $_FILES['photos']['name'];
        $tmp    = $_FILES['photos']['tmp_name'];
        $errors = $_FILES['photos']['error'];
        $sizes  = $_FILES['photos']['size'];

        $MAX_FILES = 10;
        $MAX_BYTES = 10 * 1024 * 1024;
        $count = min(count($names), $MAX_FILES);

        $baseRel = 'uploads/tasks/' . $task_id . '/';
        $baseAbs = __DIR__ . '/' . $baseRel;
        if (!is_dir($baseAbs)) { @mkdir($baseAbs, 0775, true); }

        for ($i = 0; $i < $count; $i++) {
            if ((int)$errors[$i] !== UPLOAD_ERR_OK) continue;
            if ((int)$sizes[$i] > $MAX_BYTES) continue;
            if (!is_uploaded_file($tmp[$i])) continue;
            if (!is_allowed_image($tmp[$i], $names[$i])) continue;

            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', pathinfo($names[$i], PATHINFO_FILENAME));
            $destRel = $baseRel . sprintf('%s_%s.%s', $safeBase, bin2hex(random_bytes(4)), $ext);
            $destAbs = __DIR__ . '/' . $destRel;

            if (@move_uploaded_file($tmp[$i], $destAbs)) {
                try_insert_task_photo($pdo, $task_id, $destRel, null);
            }
        }
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "❌ Failed to update task: " . htmlspecialchars($e->getMessage());
    exit;
}

// ---------- redirect ----------
header("Location: vessel_tasks.php?vessel_id={$vessel_id}#tasksModal");
exit;
