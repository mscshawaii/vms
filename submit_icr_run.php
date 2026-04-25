<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db_connect.php';
require 'session_check.php';
require 'includes/message_functions.php';
require 'includes/onesignal_service.php';
require 'includes/message_link_functions.php';

$created_by = (int)($_SESSION['user_id'] ?? 0);

$vessel_id      = intval($_POST['vessel_id'] ?? 0);
$icr_id         = intval($_POST['icr_id'] ?? 0);
$vessel_icr_id  = intval($_POST['vessel_icr_id'] ?? 0);
$inspector      = trim($_POST['inspector'] ?? 'Unknown');
$run_id         = (int)($_POST['run_id'] ?? 0);

$save_mode = $_POST['save_mode'] ?? 'draft';
$save_mode = ($save_mode === 'final') ? 'final' : 'draft';

$task_assigned_to = (int)($_POST['task_assigned_to'] ?? 0);
$task_notify_users = array_values(array_unique(array_filter(
    array_map('intval', $_POST['task_notify_users'] ?? []),
    fn($id) => $id > 0
)));

$step_statuses  = $_POST['status']            ?? [];
$step_notes     = $_POST['notes']             ?? [];

$sub_statuses_m = $_POST['sub_status']        ?? [];
$sub_notes_m    = $_POST['sub_notes']         ?? [];

$sub_statuses_v = $_POST['sub_status_vessel'] ?? [];
$sub_notes_v    = $_POST['sub_notes_vessel']  ?? [];

$step_regs      = $_POST['regulation']             ?? [];
$sub_regs_m     = $_POST['regulation_sub']         ?? [];
$sub_regs_v     = $_POST['regulation_sub_vessel']  ?? [];

$linked_regs_payload       = $_POST['linked_regulations_payload'] ?? [];
$linked_regs_payload_sub_m = $_POST['linked_regulations_payload_sub'] ?? [];
$linked_regs_payload_sub_v = $_POST['linked_regulations_payload_sub_vessel'] ?? [];

/* NEW: reusable note inputs */
$save_note_step       = $_POST['save_note_step'] ?? [];
$note_type_step       = $_POST['note_type_step'] ?? [];
$master_step_context  = $_POST['master_step_context'] ?? [];

$save_note_sub        = $_POST['save_note_sub'] ?? [];
$note_type_sub        = $_POST['note_type_sub'] ?? [];
$master_substep_context = $_POST['master_substep_context'] ?? [];

$is_equipment_driven = (int)($_POST['is_equipment_driven'] ?? 0);
$equipment_scope     = trim($_POST['equipment_scope'] ?? 'none');

$equipment_statuses  = $_POST['equipment_status'] ?? [];
$equipment_notes     = $_POST['equipment_notes'] ?? [];
$equipment_regs      = $_POST['equipment_regulation'] ?? [];

$tzHi = new DateTimeZone('Pacific/Honolulu');
$todayHi = (new DateTime('now', $tzHi))->format('Y-m-d');
$nowHi   = (new DateTime('now', $tzHi))->format('Y-m-d H:i:s');

$completed_on = trim($_POST['completed_on'] ?? $todayHi);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completed_on)) $completed_on = $todayHi;
if ($completed_on > $todayHi) $completed_on = $todayHi;

$dueBase = DateTime::createFromFormat('Y-m-d', $completed_on, $tzHi) ?: new DateTime($todayHi, $tzHi);
$duePlus7 = clone $dueBase;
$duePlus7->modify('+7 days');
$dueToday = new DateTime($todayHi, $tzHi);
$computedDue = ($duePlus7 < $dueToday) ? $dueToday : $duePlus7;

const ALLOWED_STEP = ['pass','fail','n/a'];
const ALLOWED_SUB  = ['pass','fail','na'];

function norm_step($status) {
    $s = strtolower(trim((string)$status));
    if ($s === 'pass' || $s === 'p' || $s === 'passed') return 'pass';
    if ($s === 'fail' || $s === 'f' || $s === 'failed') return 'fail';
    if ($s === 'n/a'  || $s === 'na' || $s === 'n a' || $s === 'na.') return 'n/a';
    return 'n/a';
}

function norm_sub($status) {
    $s = strtolower(trim((string)$status));
    if ($s === 'pass' || $s === 'p' || $s === 'passed') return 'pass';
    if ($s === 'fail' || $s === 'f' || $s === 'failed') return 'fail';
    if ($s === 'n/a'  || $s === 'na' || $s === 'n a' || $s === 'na.') return 'na';
    return 'na';
}

function norm_equipment_step($status) {
    return norm_step($status);
}

function equipment_scope_match_clause(string $scope): string {
    switch ($scope) {
        case 'portable_fire':
            return " AND e.category_id = 3 AND LOWER(COALESCE(e.equipmentName, '')) LIKE '%portable extinguisher%' ";
        case 'fixed_fire':
            return " AND e.category_id = 3 AND LOWER(COALESCE(e.equipmentName, '')) LIKE '%fixed extinguisher%' ";
        case 'all_fire':
            return " AND e.category_id = 3 ";
        default:
            return " AND 1 = 0 ";
    }
}

function file_ext_from_mime($mime) {
    static $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'  => 'bmp'
    ];
    return $map[$mime] ?? null;
}

function ensure_dir($dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return is_dir($dir) && is_writable($dir);
}

function nested_upload_meta(string $field, $key): ?array {
    if (!isset($_FILES[$field])) return null;
    $f = $_FILES[$field];
    if (!isset($f['error'][$key]) || $f['error'][$key] !== UPLOAD_ERR_OK) return null;
    return [
        'tmp'  => $f['tmp_name'][$key] ?? null,
        'name' => $f['name'][$key]     ?? ('upload_'.$key),
        'type' => $f['type'][$key]     ?? null,
    ];
}

function move_run_upload(int $run_id, string $tmp, ?string $origName, ?string $mime): ?array {
    if (!is_uploaded_file($tmp)) return null;

    if (!$mime) {
        $fi = new finfo(FILEINFO_MIME_TYPE);
        $mime = $fi->file($tmp) ?: 'application/octet-stream';
    }
    if (strpos($mime, 'image/') !== 0) return null;

    $ext = file_ext_from_mime($mime) ?: strtolower(pathinfo((string)$origName, PATHINFO_EXTENSION) ?: 'bin');
    $safeBase = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo((string)$origName, PATHINFO_FILENAME));

    $dirAbs = __DIR__ . '/uploads/icr_runs/' . $run_id;
    if (!ensure_dir($dirAbs)) return null;

    $fname = sprintf('%s_%s.%s', $safeBase !== '' ? $safeBase : 'img', bin2hex(random_bytes(4)), $ext);
    $destAbs = $dirAbs . '/' . $fname;
    if (!move_uploaded_file($tmp, $destAbs)) return null;

    $rel = 'uploads/icr_runs/' . $run_id . '/' . $fname;
    return [$rel, $mime, $origName ?: $fname];
}

function insert_media_attachment(PDO $pdo, string $entityType, int $entityId, string $relPath, ?int $uploaded_by): int {
    $stmt = $pdo->prepare("
        INSERT INTO media_attachments (entity_type, entity_id, file_path, caption, uploaded_by, uploaded_at)
        VALUES (?, ?, ?, NULL, ?, NOW())
    ");
    $stmt->execute([$entityType, $entityId, $relPath, $uploaded_by]);
    return (int)$pdo->lastInsertId();
}

function insert_icr_run_attachment(
    PDO $pdo,
    int $run_id,
    int $vessel_step_id,
    ?int $icr_substep_id,
    ?int $vessel_substep_id,
    string $scope,
    string $relPath,
    string $origName,
    ?string $mime,
    ?int $media_id
): void {
    $stmt = $pdo->prepare("
        INSERT INTO icr_run_attachments
            (run_id, vessel_icr_step_id, icr_substep_id, vessel_substep_id, scope, file_path, original_name, mime_type, media_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $run_id,
        $vessel_step_id,
        $icr_substep_id,
        $vessel_substep_id,
        $scope,
        $relPath,
        $origName,
        $mime,
        $media_id
    ]);
}

function create_icr_task_and_notify(
    PDO $pdo,
    string $insertSql,
    array $insertParams,
    int $assignedTo,
    array $notifyUsers,
    int $createdBy,
    string $title,
    string $messagePrefix = "Corrective action created from ICR."
): int {
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute($insertParams);
    $task_id = (int)$pdo->lastInsertId();

    $recipients = $notifyUsers;
    if ($assignedTo > 0 && !in_array($assignedTo, $recipients, true)) {
        $recipients[] = $assignedTo;
    }
    $recipients = array_values(array_unique(array_filter(array_map('intval', $recipients), fn($id) => $id > 0)));

    if (!empty($recipients)) {
        $notifyStmt = $pdo->prepare("
            INSERT INTO task_notification_recipients (task_id, user_id)
            VALUES (?, ?)
        ");
        foreach ($recipients as $uid) $notifyStmt->execute([$task_id, $uid]);
    }

    $thread_id = ensureTaskThread($pdo, $task_id, $createdBy);
    syncTaskThreadMembers($pdo, $task_id, $createdBy);

    $message = $messagePrefix . "\n\n" . $title;
    postThreadMessage($pdo, $thread_id, $createdBy, $message);
    $message_id = getLatestThreadMessageId($pdo, $thread_id);

    $recipientExternalIds = array_values(array_unique(array_filter(
        array_map('strval', $recipients),
        fn($id) => $id !== '' && $id !== (string)$createdBy
    )));

    if (!empty($recipientExternalIds)) {
        $pushTitle = 'New Corrective Action';
        $preview = trim(preg_replace('/\s+/', ' ', $message));
        $preview = mb_substr($preview, 0, 120);
        if (mb_strlen($message) > 120) $preview .= '…';

        $deepLink = vms_get_thread_deep_link($pdo, $thread_id);

        try {
            vms_send_push_external_ids(
                $recipientExternalIds,
                $pushTitle,
                $preview,
                $deepLink,
                [
                    'type' => 'task_message',
                    'thread_id' => $thread_id,
                    'task_id' => $task_id,
                    'message_id' => $message_id,
                ]
            );
        } catch (Throwable $pushError) {
            error_log('ICR task push exception in submit_icr_run.php: ' . $pushError->getMessage());
        }
    }

    return $task_id;
}

function decode_linked_reg_payload($raw): array {
    if (!is_string($raw) || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    $clean = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) continue;
        $clean[] = [
            'citation'       => trim((string)($row['citation'] ?? '')),
            'heading'        => trim((string)($row['heading'] ?? '')),
            'paragraph_path' => trim((string)($row['paragraph_path'] ?? '')),
            'text'           => trim((string)($row['text'] ?? '')),
            'reference_type' => trim((string)($row['reference_type'] ?? 'requirement')),
        ];
    }
    return $clean;
}

function format_linked_regulations_for_task(array $regs): string {
    if (empty($regs)) return '';
    $lines = [];
    $lines[] = "Linked supporting regulations:";
    foreach ($regs as $reg) {
        $line = "- " . ($reg['citation'] !== '' ? $reg['citation'] : 'Unknown citation');
        if (!empty($reg['paragraph_path'])) $line .= " (Paragraph " . $reg['paragraph_path'] . ")";
        if (!empty($reg['heading'])) $line .= " — " . $reg['heading'];
        $lines[] = $line;
        if (!empty($reg['text'])) $lines[] = $reg['text'];
    }
    return implode("\n", $lines);
}

/* NEW: reusable note helpers */
function clean_note_type($type): string {
    $allowed = ['general','observation','deficiency','recommendation','disclosure'];
    $type = strtolower(trim((string)$type));
    return in_array($type, $allowed, true) ? $type : 'general';
}

function find_existing_predefined_note(PDO $pdo, string $noteText, string $noteType, ?int $createdBy): ?int {
    $stmt = $pdo->prepare("
        SELECT note_id
        FROM predefined_notes
        WHERE note_text = ?
          AND note_type = ?
          AND is_active = 1
        ORDER BY note_id ASC
        LIMIT 1
    ");
    $stmt->execute([$noteText, $noteType]);
    $noteId = $stmt->fetchColumn();
    return $noteId ? (int)$noteId : null;
}

function save_predefined_note_with_link(
    PDO $pdo,
    string $noteText,
    string $noteType,
    int $createdBy,
    ?int $icrId,
    ?int $masterStepId,
    ?int $masterSubstepId,
    string $linkScope
): ?int {
    $noteText = trim($noteText);
    if ($noteText === '') return null;

    $noteType = clean_note_type($noteType);
    $existingNoteId = find_existing_predefined_note($pdo, $noteText, $noteType, $createdBy);

    if ($existingNoteId) {
        $noteId = $existingNoteId;
        $pdo->prepare("
            UPDATE predefined_notes
            SET usage_count = usage_count + 1,
                updated_at = NOW()
            WHERE note_id = ?
        ")->execute([$noteId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO predefined_notes
                (note_text, note_type, is_active, usage_count, created_by, created_at, updated_at)
            VALUES
                (?, ?, 1, 1, ?, NOW(), NOW())
        ");
        $stmt->execute([$noteText, $noteType, $createdBy > 0 ? $createdBy : null]);
        $noteId = (int)$pdo->lastInsertId();
    }

    $check = $pdo->prepare("
        SELECT note_link_id
        FROM predefined_note_links
        WHERE note_id = ?
          AND IFNULL(icr_id, 0) = IFNULL(?, 0)
          AND IFNULL(master_step_id, 0) = IFNULL(?, 0)
          AND IFNULL(master_substep_id, 0) = IFNULL(?, 0)
          AND link_scope = ?
        LIMIT 1
    ");
    $check->execute([
        $noteId,
        $icrId,
        $masterStepId,
        $masterSubstepId,
        $linkScope
    ]);

    if (!$check->fetchColumn()) {
        $ins = $pdo->prepare("
            INSERT INTO predefined_note_links
                (note_id, icr_id, master_step_id, master_substep_id, regulation_section_id, regulation_paragraph_id, link_scope, created_at)
            VALUES
                (?, ?, ?, ?, NULL, NULL, ?, NOW())
        ");
        $ins->execute([
            $noteId,
            $icrId,
            $masterStepId,
            $masterSubstepId,
            $linkScope
        ]);
    }

    return $noteId;
}

if ($vessel_icr_id > 0 && ($vessel_id <= 0 || $icr_id <= 0)) {
    $q = $pdo->prepare("SELECT vessel_id, icr_id FROM vessel_icrs WHERE vessel_icr_id = ?");
    $q->execute([$vessel_icr_id]);
    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        if ($vessel_id <= 0) $vessel_id = (int)$row['vessel_id'];
        if ($icr_id <= 0)    $icr_id    = (int)$row['icr_id'];
    }
}

if ($vessel_id <= 0 || $icr_id <= 0) die("❌ Missing vessel_id or icr_id.");

$ck = $pdo->prepare("SELECT 1 FROM vessels WHERE vessel_id = ? LIMIT 1");
$ck->execute([$vessel_id]);
if (!$ck->fetchColumn()) die("❌ Vessel not found (vessel_id=$vessel_id).");

$ck = $pdo->prepare("SELECT 1 FROM icrs WHERE icr_id = ? LIMIT 1");
$ck->execute([$icr_id]);
if (!$ck->fetchColumn()) die("❌ ICR not found (icr_id=$icr_id).");

$icrMetaStmt = $pdo->prepare("
    SELECT is_equipment_driven, equipment_scope, icr_number, title
    FROM icrs
    WHERE icr_id = ?
    LIMIT 1
");
$icrMetaStmt->execute([$icr_id]);
$icrMeta = $icrMetaStmt->fetch(PDO::FETCH_ASSOC);

$db_is_equipment_driven = !empty($icrMeta['is_equipment_driven']) ? 1 : 0;
$db_equipment_scope = $icrMeta['equipment_scope'] ?? 'none';
$icr_number_cached = (string)($icrMeta['icr_number'] ?? '');
$icr_title_cached  = (string)($icrMeta['title'] ?? '');

$is_equipment_driven = $db_is_equipment_driven;
$equipment_scope = $db_equipment_scope;

$map = $pdo->prepare("
    SELECT vs.step_id AS vessel_step_id, ists.step_id AS icr_step_id, vs.step_number
    FROM vessel_icr_steps vs
    JOIN icr_steps ists
      ON ists.icr_id = ?
     AND ists.step_number = vs.step_number
    WHERE vs.vessel_icr_id = ?
");
$map->execute([$icr_id, $vessel_icr_id]);

$icrStep_to_vesselStep = [];
$icrStep_to_num        = [];
while ($r = $map->fetch(PDO::FETCH_ASSOC)) {
    $icrStep_to_vesselStep[(int)$r['icr_step_id']] = (int)$r['vessel_step_id'];
    $icrStep_to_num[(int)$r['icr_step_id']]        = (int)$r['step_number'];
}

$vesselStep_to_num = [];
$vesselStep_to_desc = [];
$vesselStepLookupStmt = $pdo->prepare("
    SELECT step_id, step_number, step_description
    FROM vessel_icr_steps
    WHERE vessel_icr_id = ?
");
$vesselStepLookupStmt->execute([$vessel_icr_id]);
while ($r = $vesselStepLookupStmt->fetch(PDO::FETCH_ASSOC)) {
    $sid = (int)$r['step_id'];
    $vesselStep_to_num[$sid]  = (int)$r['step_number'];
    $vesselStep_to_desc[$sid] = (string)$r['step_description'];
}

$vesselStepMeta = [];
$vesselStepMetaStmt = $pdo->prepare("
    SELECT step_id, step_number, step_description, deficiency_action
    FROM vessel_icr_steps
    WHERE vessel_icr_id = ?
    ORDER BY step_number ASC
");
$vesselStepMetaStmt->execute([$vessel_icr_id]);
while ($r = $vesselStepMetaStmt->fetch(PDO::FETCH_ASSOC)) {
    $vesselStepMeta[(int)$r['step_id']] = [
        'step_number'       => (int)$r['step_number'],
        'step_description'  => (string)$r['step_description'],
        'deficiency_action' => (string)($r['deficiency_action'] ?? ''),
    ];
}

$pdo->beginTransaction();

try {
    if ($run_id > 0) {
        if ($save_mode === 'final') {
            $run_stmt = $pdo->prepare("
                UPDATE vessel_icr_runs
                SET run_date = ?, inspector = ?, save_state = 'final', last_saved_at = NOW(), finalized_at = NOW(), finalized_by = ?
                WHERE run_id = ?
            ");
            $run_stmt->execute([$completed_on, $inspector, $_SESSION['user_id'] ?? null, $run_id]);
        } else {
            $run_stmt = $pdo->prepare("
                UPDATE vessel_icr_runs
                SET run_date = ?, inspector = ?, save_state = 'draft', last_saved_at = NOW()
                WHERE run_id = ?
            ");
            $run_stmt->execute([$completed_on, $inspector, $run_id]);
        }
    } else {
        if ($save_mode === 'final') {
            $run_stmt = $pdo->prepare("
                INSERT INTO vessel_icr_runs
                    (vessel_id, icr_id, vessel_icr_id, run_date, inspector, save_state, last_saved_at, finalized_at, finalized_by, lock_version)
                VALUES
                    (?, ?, ?, ?, ?, 'final', NOW(), NOW(), ?, 0)
            ");
            $run_stmt->execute([$vessel_id, $icr_id, ($vessel_icr_id ?: null), $completed_on, $inspector, $_SESSION['user_id'] ?? null]);
        } else {
            $run_stmt = $pdo->prepare("
                INSERT INTO vessel_icr_runs
                    (vessel_id, icr_id, vessel_icr_id, run_date, inspector, save_state, last_saved_at, lock_version)
                VALUES
                    (?, ?, ?, ?, ?, 'draft', NOW(), 0)
            ");
            $run_stmt->execute([$vessel_id, $icr_id, ($vessel_icr_id ?: null), $completed_on, $inspector]);
        }
        $run_id = (int)$pdo->lastInsertId();
    }

    if ($is_equipment_driven) {
        $pdo->prepare("
            DELETE vires
            FROM vessel_icr_run_equipment_steps vires
            JOIN vessel_icr_run_equipment vire ON vire.run_equipment_id = vires.run_equipment_id
            WHERE vire.run_id = ?
        ")->execute([$run_id]);

        $pdo->prepare("DELETE FROM vessel_icr_step_status WHERE run_id = ?")->execute([$run_id]);
        $pdo->prepare("DELETE FROM vessel_icr_substep_status WHERE run_id = ?")->execute([$run_id]);
    } else {
        $pdo->prepare("DELETE FROM vessel_icr_step_status WHERE run_id = ?")->execute([$run_id]);
        $pdo->prepare("DELETE FROM vessel_icr_substep_status WHERE run_id = ?")->execute([$run_id]);
    }

    $runEquipmentUpsert = $pdo->prepare("
        INSERT INTO vessel_icr_run_equipment (
            run_id, equipment_id, equipment_name_snapshot, equipment_location_snapshot, manufacturer_snapshot,
            model_snapshot, serial_snapshot, agent_type_snapshot, extinguisher_class_snapshot, capacity_value_snapshot,
            capacity_unit_snapshot, ul_rating_snapshot, manufacture_date_snapshot, overall_status, overall_comment, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NULL, ?)
        ON DUPLICATE KEY UPDATE
            equipment_name_snapshot = VALUES(equipment_name_snapshot),
            equipment_location_snapshot = VALUES(equipment_location_snapshot),
            manufacturer_snapshot = VALUES(manufacturer_snapshot),
            model_snapshot = VALUES(model_snapshot),
            serial_snapshot = VALUES(serial_snapshot),
            agent_type_snapshot = VALUES(agent_type_snapshot),
            extinguisher_class_snapshot = VALUES(extinguisher_class_snapshot),
            capacity_value_snapshot = VALUES(capacity_value_snapshot),
            capacity_unit_snapshot = VALUES(capacity_unit_snapshot),
            ul_rating_snapshot = VALUES(ul_rating_snapshot),
            manufacture_date_snapshot = VALUES(manufacture_date_snapshot),
            sort_order = VALUES(sort_order),
            updated_at = NOW()
    ");

    $getRunEquipmentId = $pdo->prepare("
        SELECT run_equipment_id
        FROM vessel_icr_run_equipment
        WHERE run_id = ? AND equipment_id = ?
        LIMIT 1
    ");

    $insertRunEquipmentStep = $pdo->prepare("
        INSERT INTO vessel_icr_run_equipment_steps (
            run_equipment_id, vessel_icr_step_id, status, comment, supporting_regulations, deficiency_action_snapshot
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $updateRunEquipmentOverall = $pdo->prepare("
        UPDATE vessel_icr_run_equipment
        SET overall_status = ?, overall_comment = ?, updated_at = NOW()
        WHERE run_equipment_id = ?
    ");

    $updateFireExtMonthly = $pdo->prepare("
        UPDATE fire_extinguisher_details
        SET last_monthly_inspection_date = ?, next_monthly_due = ?, updated_at = NOW()
        WHERE eid = ?
    ");

    $insertFireExtHistory = $pdo->prepare("
        INSERT INTO fire_extinguisher_service_history (
            eid, service_type, service_date, result, vendor_name, technician_name, notes,
            source_vessel_icr_id, source_vessel_icr_run_id, source_run_equipment_id, next_due_date, created_at
        ) VALUES (?, 'monthly_inspection', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $taskEquipmentInsertSql = "
        INSERT INTO tasks (
            title, description, supporting_regulations, corrective_action, vessel_id, assigned_to, vessel_icr_id,
            vessel_icr_run_id, equipment_id, due_date, status, priority, created_by, created_at, updated_at,
            source_vessel_icr_step_id, source_run_equipment_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', 'moderate', ?, ?, ?, ?, ?)
    ";

    $taskEquipmentDupStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tasks
        WHERE vessel_icr_run_id = ?
          AND equipment_id = ?
          AND source_vessel_icr_step_id = ?
          AND status IN ('open','in_progress')
    ");

    $getEquipmentSnapshot = function(PDO $pdo, int $vessel_id, int $eid, string $scope) {
        $sql = "
            SELECT
                e.eid, e.equipmentName, e.equipmentLocation, e.manufacturer, e.modelNumber, e.serialNumber,
                fed.rule_profile_id, fed.agent_type, fed.extinguisher_class, fed.capacity_value, fed.capacity_unit,
                fed.ul_rating, fed.manufacture_date, fed.last_monthly_inspection_date, fed.next_monthly_due,
                COALESCE(rp.monthly_interval_months, 1) AS monthly_interval_months
            FROM equipment e
            LEFT JOIN fire_extinguisher_details fed ON fed.eid = e.eid
            LEFT JOIN fire_extinguisher_rule_profiles rp ON rp.rule_profile_id = fed.rule_profile_id
            LEFT JOIN equipment_type et ON et.id = e.type_id
            WHERE e.vessel_id = ?
              AND e.eid = ?
              " . equipment_scope_match_clause($scope) . "
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vessel_id, $eid]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    };

    if ($is_equipment_driven) {
        $sortOrder = 0;

        foreach ($equipment_statuses as $eidRaw => $stepSet) {
            $eid = (int)$eidRaw;
            if ($eid <= 0 || !is_array($stepSet)) continue;

            $snapshot = $getEquipmentSnapshot($pdo, $vessel_id, $eid, $equipment_scope);
            if (!$snapshot) continue;

            $sortOrder++;

            $runEquipmentUpsert->execute([
                $run_id, $eid,
                $snapshot['equipmentName'] ?? null,
                $snapshot['equipmentLocation'] ?? null,
                $snapshot['manufacturer'] ?? null,
                $snapshot['modelNumber'] ?? null,
                $snapshot['serialNumber'] ?? null,
                $snapshot['agent_type'] ?? null,
                $snapshot['extinguisher_class'] ?? null,
                $snapshot['capacity_value'] ?? null,
                $snapshot['capacity_unit'] ?? null,
                $snapshot['ul_rating'] ?? null,
                $snapshot['manufacture_date'] ?? null,
                $sortOrder
            ]);

            $getRunEquipmentId->execute([$run_id, $eid]);
            $run_equipment_id = (int)$getRunEquipmentId->fetchColumn();
            if ($run_equipment_id <= 0) throw new Exception("Unable to resolve run_equipment_id for equipment {$eid}.");

            $overallStatus = 'na';
            $overallCommentBits = [];
            $hasPass = false;
            $hasFail = false;

            foreach ($stepSet as $vessel_step_id_raw => $statusRaw) {
                $vessel_step_id = (int)$vessel_step_id_raw;
                if ($vessel_step_id <= 0 || !isset($vesselStepMeta[$vessel_step_id])) continue;

                $statusNorm = norm_equipment_step($statusRaw);
                $comment = trim($equipment_notes[$eid][$vessel_step_id] ?? '');
                $reg     = trim($equipment_regs[$eid][$vessel_step_id] ?? '');

                $stepMeta = $vesselStepMeta[$vessel_step_id];
                $deficiencyAction = trim($stepMeta['deficiency_action'] ?? '');

                $insertRunEquipmentStep->execute([
                    $run_equipment_id,
                    $vessel_step_id,
                    $statusNorm,
                    $comment !== '' ? $comment : null,
                    $reg !== '' ? $reg : null,
                    $deficiencyAction !== '' ? $deficiencyAction : null
                ]);

                if ($statusNorm === 'fail') {
                    $hasFail = true;
                    $overallCommentBits[] = 'Step ' . $stepMeta['step_number'] . ' failed';

                    if ($save_mode === 'final') {
                        $taskEquipmentDupStmt->execute([$run_id, $eid, $vessel_step_id]);
                        $dupCount = (int)$taskEquipmentDupStmt->fetchColumn();

                        if ($dupCount === 0) {
                            $title = sprintf(
                                'ICR %s – %s – Step %d: %s',
                                $icr_number_cached,
                                ($snapshot['equipmentLocation'] ?: ('Equipment #' . $eid)),
                                $stepMeta['step_number'],
                                $stepMeta['step_description']
                            );

                            $descParts = [];
                            $descParts[] = 'Equipment: ' . ($snapshot['equipmentName'] ?? 'Extinguisher');
                            if (!empty($snapshot['equipmentLocation'])) $descParts[] = 'Location: ' . $snapshot['equipmentLocation'];
                            if (!empty($snapshot['serialNumber'])) $descParts[] = 'Serial: ' . $snapshot['serialNumber'];
                            if ($comment !== '') $descParts[] = 'Inspector notes: ' . $comment;

                            $description = implode("\n", $descParts);
                            $supportingRegulations = $reg !== '' ? $reg : null;
                            $correctiveAction = $deficiencyAction !== '' ? $deficiencyAction : null;
                            $due_date = $computedDue->format('Y-m-d');

                            create_icr_task_and_notify(
                                $pdo,
                                $taskEquipmentInsertSql,
                                [
                                    $title,
                                    $description,
                                    $supportingRegulations,
                                    $correctiveAction,
                                    $vessel_id,
                                    ($task_assigned_to > 0 ? $task_assigned_to : null),
                                    $vessel_icr_id,
                                    $run_id,
                                    $eid,
                                    $due_date,
                                    $created_by,
                                    $nowHi,
                                    $nowHi,
                                    $vessel_step_id,
                                    $run_equipment_id
                                ],
                                $task_assigned_to,
                                $task_notify_users,
                                $created_by,
                                $title
                            );
                        }
                    }
                } elseif ($statusNorm === 'pass') {
                    $hasPass = true;
                }
            }

            if ($hasFail) $overallStatus = 'fail';
            elseif ($hasPass) $overallStatus = 'pass';
            else $overallStatus = 'na';

            $overallComment = !empty($overallCommentBits) ? implode('; ', $overallCommentBits) : null;

            $updateRunEquipmentOverall->execute([$overallStatus, $overallComment, $run_equipment_id]);

            if ($save_mode === 'final') {
                $serviceDate = $completed_on;
                $intervalMonths = max(1, (int)($snapshot['monthly_interval_months'] ?? 1));

                $dtNext = DateTime::createFromFormat('Y-m-d', $serviceDate, $tzHi);
                if (!$dtNext) $dtNext = new DateTime($todayHi, $tzHi);
                $dtNext->modify('+' . $intervalMonths . ' months');
                $nextDue = $dtNext->format('Y-m-d');

                $updateFireExtMonthly->execute([$serviceDate, $nextDue, $eid]);

                $historyResult = ($overallStatus === 'fail') ? 'fail' : 'pass';

                $historyNotesBits = [];
                $historyNotesBits[] = 'ICR: ' . $icr_number_cached . ' – ' . $icr_title_cached;
                if ($overallComment) $historyNotesBits[] = 'Summary: ' . $overallComment;

                $insertFireExtHistory->execute([
                    $eid,
                    $serviceDate,
                    $historyResult,
                    null,
                    $inspector,
                    implode("\n", $historyNotesBits),
                    $vessel_icr_id,
                    $run_id,
                    $run_equipment_id,
                    $nextDue
                ]);
            }
        }

        $pdo->commit();

        if ($save_mode === 'draft') {
            header("Location: run_icr.php?vessel_icr_id={$vessel_icr_id}&vessel_id={$vessel_id}&icr_id={$icr_id}&inspector=" . urlencode($inspector) . "&saved=draft");
            exit;
        }

        header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#icrs&success=icr_run");
        exit;
    }

    $step_photos   = [];
    $sub_photos_m  = [];
    $sub_photos_v  = [];
    $uploaded_by = $_SESSION['user_id'] ?? null;

    if (!empty($_FILES['photo_step']['name']) && is_array($_FILES['photo_step']['name'])) {
        foreach (array_keys($_FILES['photo_step']['name']) as $icr_step_id) {
            $icr_step_id = (int)$icr_step_id;
            $meta = nested_upload_meta('photo_step', $icr_step_id);
            if (!$meta) continue;

            [$relPath, $mime, $origName] = move_run_upload($run_id, $meta['tmp'], $meta['name'], $meta['type']) ?: [null, null, null];
            if (!$relPath) continue;

            $media_id = insert_media_attachment($pdo, 'icr_step', $icr_step_id, $relPath, $uploaded_by);

            $vessel_step_id = $icrStep_to_vesselStep[$icr_step_id] ?? null;
            if ($vessel_step_id) {
                insert_icr_run_attachment($pdo, $run_id, (int)$vessel_step_id, null, null, 'step', $relPath, $origName, $mime, $media_id);
            }

            $step_photos[$icr_step_id] = $relPath;
        }
    }

    if (!empty($_FILES['photo_sub']['name']) && is_array($_FILES['photo_sub']['name'])) {
        foreach (array_keys($_FILES['photo_sub']['name']) as $icr_sub_id) {
            $icr_sub_id = (int)$icr_sub_id;
            $meta = nested_upload_meta('photo_sub', $icr_sub_id);
            if (!$meta) continue;

            [$relPath, $mime, $origName] = move_run_upload($run_id, $meta['tmp'], $meta['name'], $meta['type']) ?: [null, null, null];
            if (!$relPath) continue;

            $q = $pdo->prepare("SELECT step_id FROM icr_substeps WHERE substep_id = ?");
            $q->execute([$icr_sub_id]);
            $parent_icr_step_id = (int)$q->fetchColumn();

            $vessel_step_id = $parent_icr_step_id ? ($icrStep_to_vesselStep[$parent_icr_step_id] ?? null) : null;
            $media_id = insert_media_attachment($pdo, 'icr_substep', $icr_sub_id, $relPath, $uploaded_by);

            if ($vessel_step_id) {
                $stmtSub = $pdo->prepare("
                    INSERT INTO icr_run_attachments
                        (run_id, vessel_icr_step_id, icr_substep_id, vessel_substep_id, scope, file_path, original_name, mime_type, media_id, created_at)
                    VALUES (?, ?, ?, NULL, 'sub', ?, ?, ?, ?, NOW())
                ");
                $stmtSub->execute([$run_id, (int)$vessel_step_id, $icr_sub_id, $relPath, $origName, $mime, $media_id]);
            }

            $sub_photos_m[$icr_sub_id] = $relPath;
        }
    }

    if (!empty($_FILES['photo_sub_vessel']['name']) && is_array($_FILES['photo_sub_vessel']['name'])) {
        foreach (array_keys($_FILES['photo_sub_vessel']['name']) as $vessel_sub_id) {
            $vessel_sub_id = (int)$vessel_sub_id;
            $meta = nested_upload_meta('photo_sub_vessel', $vessel_sub_id);
            if (!$meta) continue;

            [$relPath, $mime, $origName] = move_run_upload($run_id, $meta['tmp'], $meta['name'], $meta['type']) ?: [null, null, null];
            if (!$relPath) continue;

            $p = $pdo->prepare("SELECT vessel_step_id FROM vessel_icr_substeps WHERE substep_id = ?");
            $p->execute([$vessel_sub_id]);
            $vessel_step_id = (int)$p->fetchColumn() ?: null;

            if ($vessel_step_id) {
                insert_icr_run_attachment($pdo, $run_id, (int)$vessel_step_id, null, $vessel_sub_id, 'sub', $relPath, $origName, $mime, null);
            }

            $sub_photos_v[$vessel_sub_id] = $relPath;
        }
    }

    $step_stmt = $pdo->prepare("
        INSERT INTO vessel_icr_step_status (run_id, vessel_icr_step_id, status, comment)
        VALUES (?, ?, ?, ?)
    ");

    $sub_stmt_master = $pdo->prepare("
        INSERT INTO vessel_icr_substep_status (run_id, vessel_icr_step_id, icr_substep_id, status, comment)
        VALUES (?, ?, ?, ?, ?)
    ");

    $sub_stmt_vessel = $pdo->prepare("
        INSERT INTO vessel_icr_substep_status (run_id, vessel_icr_step_id, vessel_substep_id, status, comment)
        VALUES (?, ?, ?, ?, ?)
    ");

    $taskInsertSql = "
        INSERT INTO tasks (
            title, description, vessel_id, assigned_to, due_date, priority,
            corrective_action, created_by, created_at, updated_at, vessel_icr_id, vessel_icr_run_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    foreach ($step_statuses as $postedStepId => $statusRaw) {
        $postedStepId = (int)$postedStepId;
        $statusNorm   = norm_step($statusRaw);
        if (!in_array($statusNorm, ALLOWED_STEP, true)) $statusNorm = 'n/a';

        if (isset($vesselStep_to_num[$postedStepId])) {
            $vessel_step_id = $postedStepId;
            $icr_step_id = 0;
        } else {
            $icr_step_id = $postedStepId;
            $vessel_step_id = $icrStep_to_vesselStep[$icr_step_id] ?? null;
        }

        if ($vessel_step_id === null || $vessel_step_id <= 0) continue;

        $comment = trim($step_notes[$postedStepId] ?? '');
        $step_stmt->execute([$run_id, $vessel_step_id, $statusNorm, $comment]);

        /* NEW: save reusable step note if requested */
        if (
            !empty($save_note_step[$postedStepId]) &&
            $comment !== '' &&
            !empty($master_step_context[$postedStepId])
        ) {
            save_predefined_note_with_link(
                $pdo,
                $comment,
                $note_type_step[$postedStepId] ?? 'general',
                $created_by,
                $icr_id > 0 ? $icr_id : null,
                (int)$master_step_context[$postedStepId],
                null,
                'step'
            );
        }

        if ($statusNorm === 'fail' && $save_mode === 'final') {
            $stepNumber = $vesselStep_to_num[$vessel_step_id] ?? null;
            $stepDescription = $vesselStep_to_desc[$vessel_step_id] ?? '';

            if ($stepNumber === null) {
                $q = $pdo->prepare("
                    SELECT s.step_number, s.step_description, i.icr_number
                    FROM vessel_icr_steps s
                    JOIN vessel_icrs vi ON s.vessel_icr_id = vi.vessel_icr_id
                    JOIN icrs i ON vi.icr_id = i.icr_id
                    WHERE s.step_id = ?
                ");
                $q->execute([$vessel_step_id]);
                $d = $q->fetch(PDO::FETCH_ASSOC);
                $stepNumber = (int)($d['step_number'] ?? 0);
                $stepDescription = (string)($d['step_description'] ?? '');
                $icrNumberForTask = (string)($d['icr_number'] ?? $icr_number_cached);
            } else {
                $icrNumberForTask = $icr_number_cached;
            }

            $title_check = "%Step {$stepNumber}%";
            $dup = $pdo->prepare("
                SELECT COUNT(*) FROM tasks
                WHERE vessel_id = ? AND vessel_icr_id = ? AND title LIKE ? AND status IN ('open','in_progress')
            ");
            $dup->execute([$vessel_id, $vessel_icr_id, $title_check]);

            if ((int)$dup->fetchColumn() === 0) {
                $due_date = $computedDue->format('Y-m-d');
                $title    = "ICR {$icrNumberForTask} – Step {$stepNumber}: {$stepDescription}";

                $descParts = [];
                $descParts[] = ($comment !== '' ? $comment : '(No description provided)');

                $linkedRegs = decode_linked_reg_payload($linked_regs_payload[$postedStepId] ?? '');
                $linkedRegsText = format_linked_regulations_for_task($linkedRegs);

                $reg = trim($step_regs[$postedStepId] ?? '');
                if ($linkedRegsText !== '') $descParts[] = $linkedRegsText;
                if ($reg !== '') $descParts[] = "Additional regulation note: " . $reg;
                if (!empty($step_photos[$postedStepId])) $descParts[] = "Photo: " . $step_photos[$postedStepId];

                $desc = implode("\n\n", $descParts);

                create_icr_task_and_notify(
                    $pdo,
                    $taskInsertSql,
                    [
                        $title,
                        $desc,
                        $vessel_id,
                        ($task_assigned_to > 0 ? $task_assigned_to : null),
                        $due_date,
                        'moderate',
                        '',
                        $created_by,
                        $nowHi,
                        $nowHi,
                        $vessel_icr_id,
                        $run_id
                    ],
                    $task_assigned_to,
                    $task_notify_users,
                    $created_by,
                    $title
                );
            }
        }
    }

    $meta_icr_sub = $pdo->prepare("
        SELECT s.step_number, sub.substep_id, sub.substep_code, sub.description
        FROM icr_substeps sub
        JOIN icr_steps s ON s.step_id = sub.step_id
        WHERE s.icr_id = ?
    ");
    $meta_icr_sub->execute([$icr_id]);
    $map_icr_sub = [];
    while ($r = $meta_icr_sub->fetch(PDO::FETCH_ASSOC)) {
        $map_icr_sub[(int)$r['substep_id']] = [
            'num'  => (int)$r['step_number'],
            'code' => (string)$r['substep_code'],
            'desc' => (string)$r['description'],
        ];
    }

    foreach ($sub_statuses_m as $icr_substep_id => $statusRaw) {
        $icr_substep_id = (int)$icr_substep_id;
        $statusNorm     = norm_sub($statusRaw);
        if (!in_array($statusNorm, ALLOWED_SUB, true)) $statusNorm = 'na';

        $comment = trim($sub_notes_m[$icr_substep_id] ?? '');
        $meta = $map_icr_sub[$icr_substep_id] ?? null;
        if (!$meta) continue;

        $q = $pdo->prepare("SELECT step_id FROM icr_steps WHERE icr_id = ? AND step_number = ?");
        $q->execute([$icr_id, $meta['num']]);
        $icr_step_id = (int)$q->fetchColumn();

        $vessel_step_id = $icrStep_to_vesselStep[$icr_step_id] ?? null;
        if ($vessel_step_id === null) continue;

        $sub_stmt_master->execute([$run_id, $vessel_step_id, $icr_substep_id, $statusNorm, $comment]);

        /* NEW: save reusable master substep note if requested */
        if (
            !empty($save_note_sub[$icr_substep_id]) &&
            $comment !== '' &&
            !empty($master_substep_context[$icr_substep_id])
        ) {
            save_predefined_note_with_link(
                $pdo,
                $comment,
                $note_type_sub[$icr_substep_id] ?? 'general',
                $created_by,
                $icr_id > 0 ? $icr_id : null,
                null,
                (int)$master_substep_context[$icr_substep_id],
                'substep'
            );
        }

        if ($statusNorm === 'fail' && $save_mode === 'final') {
            $due_date = $computedDue->format('Y-m-d');
            $title    = "ICR {$icr_number_cached} – Step {$meta['num']}{$meta['code']}: {$meta['desc']}";

            $descParts = [];
            $descParts[] = ($comment !== '' ? $comment : '(No description provided)');

            $linkedRegs = decode_linked_reg_payload($linked_regs_payload_sub_m[$icr_substep_id] ?? '');
            $linkedRegsText = format_linked_regulations_for_task($linkedRegs);

            $reg = trim($sub_regs_m[$icr_substep_id] ?? '');
            if ($linkedRegsText !== '') {
                $descParts[] = $linkedRegsText;
            }
            if ($reg !== '') {
                $descParts[] = "Additional regulation note: " . $reg;
            }
            if (!empty($sub_photos_m[$icr_substep_id])) {
                $descParts[] = "Photo: " . $sub_photos_m[$icr_substep_id];
            }

            $desc = implode("\n\n", $descParts);

            $title_check = "%Step {$meta['num']}{$meta['code']}%";
            $dup = $pdo->prepare("
                SELECT COUNT(*) FROM tasks
                WHERE vessel_id = ? AND vessel_icr_id = ? AND title LIKE ? AND status IN ('open','in_progress')
            ");
            $dup->execute([$vessel_id, $vessel_icr_id, $title_check]);

            if ((int)$dup->fetchColumn() === 0) {
                create_icr_task_and_notify(
                    $pdo,
                    $taskInsertSql,
                    [
                        $title,
                        $desc,
                        $vessel_id,
                        ($task_assigned_to > 0 ? $task_assigned_to : null),
                        $due_date,
                        'moderate',
                        '',
                        $created_by,
                        $nowHi,
                        $nowHi,
                        $vessel_icr_id,
                        $run_id
                    ],
                    $task_assigned_to,
                    $task_notify_users,
                    $created_by,
                    $title
                );
            }
        }
    }

    if (!empty($sub_statuses_v)) {
        $ids = array_map('intval', array_keys($sub_statuses_v));
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $meta_v_stmt = $pdo->prepare("
            SELECT vsu.substep_id AS vessel_substep_id,
                   vsu.vessel_step_id,
                   vsu.substep_code,
                   vsu.description,
                   vs.step_number
            FROM vessel_icr_substeps vsu
            JOIN vessel_icr_steps vs ON vs.step_id = vsu.vessel_step_id
            WHERE vsu.substep_id IN ($in)
        ");
        $meta_v_stmt->execute($ids);
        $map_v = [];
        while ($r = $meta_v_stmt->fetch(PDO::FETCH_ASSOC)) {
            $map_v[(int)$r['vessel_substep_id']] = $r;
        }

        foreach ($sub_statuses_v as $vessel_substep_id => $statusRaw) {
            $vessel_substep_id = (int)$vessel_substep_id;
            $statusNorm        = norm_sub($statusRaw);
            if (!in_array($statusNorm, ALLOWED_SUB, true)) $statusNorm = 'na';

            $comment = trim($sub_notes_v[$vessel_substep_id] ?? '');
            $meta    = $map_v[$vessel_substep_id] ?? null;
            if (!$meta) continue;

            $vessel_step_id = (int)$meta['vessel_step_id'];
            $sub_stmt_vessel->execute([$run_id, $vessel_step_id, $vessel_substep_id, $statusNorm, $comment]);

            /* NEW: save reusable vessel substep note only if it maps to a master substep */
            if (
                !empty($save_note_sub[$vessel_substep_id]) &&
                $comment !== '' &&
                !empty($master_substep_context[$vessel_substep_id])
            ) {
                save_predefined_note_with_link(
                    $pdo,
                    $comment,
                    $note_type_sub[$vessel_substep_id] ?? 'general',
                    $created_by,
                    $icr_id > 0 ? $icr_id : null,
                    null,
                    (int)$master_substep_context[$vessel_substep_id],
                    'substep'
                );
            }

            if ($statusNorm === 'fail' && $save_mode === 'final') {
                $due_date = $computedDue->format('Y-m-d');
                $title    = "ICR {$icr_number_cached} – Step {$meta['step_number']}{$meta['substep_code']}: {$meta['description']}";

                $descParts = [];
                $descParts[] = ($comment !== '' ? $comment : '(No description provided)');

                $linkedRegs = decode_linked_reg_payload($linked_regs_payload_sub_v[$vessel_substep_id] ?? '');
                $linkedRegsText = format_linked_regulations_for_task($linkedRegs);

                $reg = trim($sub_regs_v[$vessel_substep_id] ?? '');
                if ($linkedRegsText !== '') {
                    $descParts[] = $linkedRegsText;
                }
                if ($reg !== '') {
                    $descParts[] = "Additional regulation note: " . $reg;
                }
                if (!empty($sub_photos_v[$vessel_substep_id])) {
                    $descParts[] = "Photo: " . $sub_photos_v[$vessel_substep_id];
                }

                $desc = implode("\n\n", $descParts);

                $title_check = "%Step {$meta['step_number']}{$meta['substep_code']}%";
                $dup = $pdo->prepare("
                    SELECT COUNT(*) FROM tasks
                    WHERE vessel_id = ? AND vessel_icr_id = ? AND title LIKE ? AND status IN ('open','in_progress')
                ");
                $dup->execute([$vessel_id, $vessel_icr_id, $title_check]);

                if ((int)$dup->fetchColumn() === 0) {
                    create_icr_task_and_notify(
                        $pdo,
                        $taskInsertSql,
                        [
                            $title,
                            $desc,
                            $vessel_id,
                            ($task_assigned_to > 0 ? $task_assigned_to : null),
                            $due_date,
                            'moderate',
                            '',
                            $created_by,
                            $nowHi,
                            $nowHi,
                            $vessel_icr_id,
                            $run_id
                        ],
                        $task_assigned_to,
                        $task_notify_users,
                        $created_by,
                        $title
                    );
                }
            }
        }
    }

    if (isset($_POST['crew_present']) && is_array($_POST['crew_present'])) {
        $crew_ids = array_values(array_unique(array_filter(array_map('intval', $_POST['crew_present']), fn($id) => $id > 0)));

        if (!empty($crew_ids)) {
            $stmt = $pdo->prepare("SELECT drill_type FROM icrs WHERE icr_id = ?");
            $stmt->execute([$icr_id]);
            $drill_type = $stmt->fetchColumn();

            if (!empty($drill_type)) {
                $placeholders = implode(',', array_fill(0, count($crew_ids), '?'));

                $validCrewSql = "
                    SELECT DISTINCT u.id
                    FROM vessel_crew vc
                    INNER JOIN users u ON u.id = vc.crew_id
                    WHERE vc.vessel_id = ?
                      AND vc.is_active = 1
                      AND u.is_active = 1
                      AND vc.counts_for_drills = 1
                      AND vc.role IN ('Master', 'Deckhand')
                      AND u.id IN ($placeholders)
                ";

                $params = array_merge([$vessel_id], $crew_ids);
                $validCrewStmt = $pdo->prepare($validCrewSql);
                $validCrewStmt->execute($params);
                $validCrewIds = array_map('intval', $validCrewStmt->fetchAll(PDO::FETCH_COLUMN));

                $insert_stmt = $pdo->prepare("
                    INSERT INTO crew_drills (crew_user_id, vessel_id, icr_run_id, drill_type, drill_date)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($validCrewIds as $crew_id) {
                    $insert_stmt->execute([$crew_id, $vessel_id, $run_id, $drill_type, $completed_on]);
                }
            }
        }
    }

    $pdo->commit();

    if ($save_mode === 'draft') {
        header("Location: run_icr.php?vessel_icr_id={$vessel_icr_id}&vessel_id={$vessel_id}&icr_id={$icr_id}&inspector=" . urlencode($inspector) . "&saved=draft");
        exit;
    }

    header("Location: vessel_dashboard.php?vessel_id={$vessel_id}#icrs&success=icr_run");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    die("❌ Error submitting inspection: " . htmlspecialchars($e->getMessage()));
}