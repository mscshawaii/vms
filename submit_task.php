<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/includes/message_functions.php';
require __DIR__ . '/includes/onesignal_service.php';
require __DIR__ . '/includes/message_link_functions.php';

// ---------- helpers ----------
function clean($value) {
    return isset($value) ? trim((string)$value) : null;
}
function null_if_empty($v) {
    $v = clean($v);
    return ($v === '' ? null : $v);
}

// Try to insert a photo record if table exists; swallow errors if not
function try_insert_task_photo(PDO $pdo, int $task_id, string $filename, ?string $caption = null): void {
    try {
        $pdo->prepare("
            INSERT INTO task_photos (task_id, file_path, caption, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$task_id, $filename, $caption]);
    } catch (Throwable $e) {
        // Table may not exist yet—ignore
    }
}

// Very light file type check (server-side)
function is_allowed_image(string $tmp_path, string $orig_name): bool {
    $allowed_ext = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) return false;

    $info = @getimagesize($tmp_path);
    if ($info === false) return false;
    return true;
}

/**
 * Store a file (already on disk) into media_attachments as BLOB.
 * Returns new media_id.
 */
function store_media_from_disk(string $absPath, string $origName, ?string $mime, ?int $company_id, ?int $user_id, PDO $pdo): int {
    if (!is_file($absPath)) {
        throw new RuntimeException('File not found: ' . $absPath);
    }

    if ($mime === null) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($absPath) ?: 'application/octet-stream';
    }

    $bytes = file_get_contents($absPath);
    if ($bytes === false) {
        throw new RuntimeException('Read failed for: ' . $absPath);
    }

    $stmt = $pdo->prepare("
        INSERT INTO media_attachments (company_id, filename, mime_type, byte_size, blob_data, created_at, created_by)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$company_id, $origName, $mime, strlen($bytes), $bytes, $user_id]);

    return (int)$pdo->lastInsertId();
}

// ---------- HST clock ----------
$tzHi    = new DateTimeZone('Pacific/Honolulu');
$todayHi = (new DateTime('now', $tzHi))->format('Y-m-d');

// ---------- read POST ----------
$vessel_id         = (int)($_POST['vessel_id'] ?? 0);
$title             = null_if_empty($_POST['title'] ?? '');
$due_date_in       = null_if_empty($_POST['due_date'] ?? '');
$description       = clean($_POST['description'] ?? '');
$notes             = clean($_POST['notes'] ?? '');
$priority          = null_if_empty($_POST['priority'] ?? 'moderate') ?: 'moderate';
$equipment_id      = isset($_POST['equipment_id']) && $_POST['equipment_id'] !== '' ? (int)$_POST['equipment_id'] : null;
$assigned_to       = isset($_POST['assigned_to'])  && $_POST['assigned_to']  !== '' ? (int)$_POST['assigned_to']  : null;
$recurrence        = null_if_empty($_POST['recurrence_interval'] ?? 'none') ?: 'none';
$corrective_action = null_if_empty($_POST['corrective_action'] ?? null);
$regulation        = null_if_empty($_POST['regulation'] ?? null);

$notify_users = isset($_POST['notify_users']) && is_array($_POST['notify_users'])
    ? array_values(array_unique(array_filter(array_map('intval', $_POST['notify_users']), fn($id) => $id > 0)))
    : [];

// NEW: event_date (occurrence/completion date — no future)
$event_date = clean($_POST['event_date'] ?? $todayHi);

// Status
$allowed_statuses = ['open','in_progress','complete','overdue'];
$status_in = strtolower(clean($_POST['status'] ?? 'open') ?: 'open');
$status    = in_array($status_in, $allowed_statuses, true) ? $status_in : 'open';

// Guard
if ($vessel_id <= 0 || !$title) {
    http_response_code(422);
    die("❌ Missing required fields.");
}

if ($assigned_to === null || $assigned_to <= 0) {
    http_response_code(422);
    die("❌ Assigned To is required.");
}

// Validate event_date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$event_date)) {
    $event_date = $todayHi;
}
if ($event_date > $todayHi) {
    $event_date = $todayHi;
}

// Compute default due date if not provided: max(event_date + 7d, today)
$due_date = $due_date_in;
if ($due_date === null) {
    $base   = DateTime::createFromFormat('Y-m-d', $event_date, $tzHi) ?: new DateTime($todayHi, $tzHi);
    $plus7  = (clone $base)->modify('+7 days');
    $todayD = new DateTime($todayHi, $tzHi);
    $due    = ($plus7 < $todayD) ? $todayD : $plus7;
    $due_date = $due->format('Y-m-d');
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$due_date)) {
        $base   = DateTime::createFromFormat('Y-m-d', $event_date, $tzHi) ?: new DateTime($todayHi, $tzHi);
        $plus7  = (clone $base)->modify('+7 days');
        $todayD = new DateTime($todayHi, $tzHi);
        $due    = ($plus7 < $todayD) ? $todayD : $plus7;
        $due_date = $due->format('Y-m-d');
    }
}

// If user marks complete, set completed_date = event_date. Otherwise null.
$completed_date = ($status === 'complete') ? $event_date : null;

// Append regulation to description if provided and not already present
if ($regulation) {
    $needle = 'Supporting regulation:';
    if (stripos($description ?? '', $needle) === false) {
        $description = rtrim((string)$description);
        $description .= ($description === '' ? '' : "\n\n") . "Supporting regulation: " . $regulation;
    }
}

// who’s uploading (for media_attachments attribution)
$company_id  = $_SESSION['company_id'] ?? null;
$uploaded_by = $_SESSION['user_id'] ?? null;

// ---------- validate assignee/notify candidates against active vessel-assigned users ----------
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

// Keep only valid notify recipients
$notify_users = array_values(array_filter($notify_users, function($id) use ($validUserSet) {
    return isset($validUserSet[$id]);
}));

// Optional smart default: ensure assigned_to is in notify list
if (!in_array($assigned_to, $notify_users, true)) {
    $notify_users[] = $assigned_to;
}

// ---------- insert task ----------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO tasks (
            vessel_id,
            title,
            due_date,
            description,
            notes,
            priority,
            equipment_id,
            assigned_to,
            recurrence_interval,
            corrective_action,
            completed_date,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $created_by = (int)($_SESSION['user_id'] ?? 0);

    $ok = $stmt->execute([
        $vessel_id,
        $title,
        $due_date,
        $description,
        ($notes !== '' ? $notes : null),
        $priority,
        $equipment_id,
        $assigned_to,
        $recurrence,
        $corrective_action,
        $completed_date,
        $status
    ]);

    if (!$ok) {
        throw new RuntimeException("Failed to save corrective action.");
    }

    $task_id = (int)$pdo->lastInsertId();

    // ---------- notification recipients ----------
    if (!empty($notify_users)) {
        $notifyStmt = $pdo->prepare("
            INSERT INTO task_notification_recipients (task_id, user_id)
            VALUES (?, ?)
        ");

        foreach ($notify_users as $notify_user_id) {
            $notifyStmt->execute([$task_id, $notify_user_id]);
        }
    }

// ---------- ensure CAR discussion thread + members ----------
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$threadId = ensureTaskThread($pdo, $task_id, $currentUserId);
syncTaskThreadMembers($pdo, $task_id, $currentUserId);

// ---------- auto-create first discussion message ----------
$userLabelMap = [];

// Build label map for assigned/notify users from valid vessel users
$labelStmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.fName, u.lName
    FROM vessel_crew vc
    INNER JOIN users u
        ON u.id = vc.crew_id
    WHERE vc.vessel_id = ?
      AND vc.is_active = 1
      AND u.is_active = 1
");
$labelStmt->execute([$vessel_id]);
foreach ($labelStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $uid = (int)$row['id'];
    $userLabelMap[$uid] = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
}

$messageLines = [];
$messageLines[] = "New corrective action created.";
$messageLines[] = "";
$messageLines[] = "Title: " . $title;

if ($assigned_to && isset($userLabelMap[$assigned_to])) {
    $messageLines[] = "Assigned to: " . $userLabelMap[$assigned_to];
}

if (!empty($notify_users)) {
    $notifyNames = [];
    foreach ($notify_users as $uid) {
        if (isset($userLabelMap[$uid])) {
            $notifyNames[] = $userLabelMap[$uid];
        }
    }
    if (!empty($notifyNames)) {
        $messageLines[] = "Notify: " . implode(', ', $notifyNames);
    }
}

$messageLines[] = "Priority: " . ucfirst((string)$priority);
$messageLines[] = "Status: " . str_replace('_', ' ', ucfirst((string)$status));

if (!empty($due_date)) {
    $messageLines[] = "Due: " . $due_date;
}

if (!empty($regulation)) {
    $messageLines[] = "Supporting regulation: " . $regulation;
}

if (!empty(trim((string)$description))) {
    $messageLines[] = "";
    $messageLines[] = "Description:";
    $messageLines[] = trim((string)$description);
}

if (!empty(trim((string)$notes))) {
    $messageLines[] = "";
    $messageLines[] = "Notes:";
    $messageLines[] = trim((string)$notes);
}

$starterMessage = implode("\n", $messageLines);

// Save the first message
$messageId = postThreadMessage($pdo, $threadId, $currentUserId, $starterMessage);

// ---------- push notification for task recipients ----------
$recipientExternalIds = array_values(array_unique(array_filter(
    array_map('strval', $notify_users),
    fn($id) => $id !== '' && $id !== (string)$currentUserId
)));

$pushTitle = 'New Corrective Action';

$preview = trim(preg_replace('/\s+/', ' ', $starterMessage));
$preview = mb_substr($preview, 0, 120);
if (mb_strlen($starterMessage) > 120) {
    $preview .= '…';
}

$pushBody = $preview;

// Reuse thread deep link helper
$deepLink = vms_get_thread_deep_link($pdo, $threadId);

// Send push, but do not break task creation if push fails
if (!empty($recipientExternalIds)) {
    try {
        vms_send_push_external_ids(
            $recipientExternalIds,
            $pushTitle,
            $pushBody,
            $deepLink,
            [
                'type' => 'task_message',
                'thread_id' => $threadId,
                'task_id' => $task_id,
                'message_id' => $messageId,
            ]
        );
    } catch (Throwable $pushError) {
        error_log('Task creation push exception in submit_task.php: ' . $pushError->getMessage());
    }
}

    // ---------- handle uploads (photos[]) ----------
    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $names  = $_FILES['photos']['name'];
        $tmp    = $_FILES['photos']['tmp_name'];
        $errors = $_FILES['photos']['error'];
        $sizes  = $_FILES['photos']['size'];
        $types  = $_FILES['photos']['type'];

        $MAX_FILES = 10;
        $MAX_BYTES = 10 * 1024 * 1024;

        $count = min(count($names), $MAX_FILES);

        $baseDirRel = 'uploads/tasks/' . $task_id . '/';
        $baseDirAbs = __DIR__ . '/' . $baseDirRel;
        if (!is_dir($baseDirAbs)) {
            @mkdir($baseDirAbs, 0775, true);
        }

        $insTaskAtt = $pdo->prepare("
            INSERT INTO task_attachments (task_id, media_id, file_path, original_name, mime_type, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        for ($i = 0; $i < $count; $i++) {
            if ((int)$errors[$i] !== UPLOAD_ERR_OK) continue;
            if ((int)$sizes[$i] > $MAX_BYTES) continue;
            if (!is_uploaded_file($tmp[$i])) continue;
            if (!is_allowed_image($tmp[$i], $names[$i])) continue;

            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', pathinfo($names[$i], PATHINFO_FILENAME));
            $destRel = $baseDirRel . sprintf('%s_%s.%s', $safeBase, bin2hex(random_bytes(4)), $ext);
            $destAbs = __DIR__ . '/' . $destRel;

            if (!@move_uploaded_file($tmp[$i], $destAbs)) {
                continue;
            }

            try_insert_task_photo($pdo, $task_id, $destRel, null);

            try {
                $mime = $types[$i] ?? null;
                if (!$mime) {
                    $fi = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $fi->file($destAbs) ?: 'application/octet-stream';
                }

                $media_id = store_media_from_disk(
                    $destAbs,
                    $names[$i],
                    $mime,
                    $company_id ? (int)$company_id : null,
                    $uploaded_by ? (int)$uploaded_by : null,
                    $pdo
                );

                $insTaskAtt->execute([$task_id, $media_id, $destRel, $names[$i], $mime]);

            } catch (Throwable $e) {
                // keep file on disk and continue
            }
        }
    }

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "❌ Failed to save corrective action: " . htmlspecialchars($e->getMessage());
    exit;
}

// ---------- redirect ----------
header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#tasksModal");
exit;
