<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lib/acl.php';

const EQUIPMENT_DELETE_BLOCKED_MESSAGE = 'This equipment has operational history. Use Replace or retire it instead.';

function fail_equipment_delete(string $message, int $status = 400): void
{
    http_response_code($status);
    exit($message);
}

function equipment_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function equipment_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function equipment_count_dependency(PDO $pdo, string $table, string $column, int $equipmentId, ?string $extraWhere = null, array $extraParams = []): int
{
    if (!equipment_table_exists($pdo, $table) || !equipment_column_exists($pdo, $table, $column)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
    if ($extraWhere !== null && $extraWhere !== '') {
        $sql .= " AND {$extraWhere}";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$equipmentId], $extraParams));
    return (int)$stmt->fetchColumn();
}

function equipment_dependency_counts(PDO $pdo, int $equipmentId, array $equipment): array
{
    $counts = [
        'tasks.equipment_id' => equipment_count_dependency($pdo, 'tasks', 'equipment_id', $equipmentId),
        'vessel_icr_run_equipment.equipment_id' => equipment_count_dependency($pdo, 'vessel_icr_run_equipment', 'equipment_id', $equipmentId),
        'equipment_hour_meters.equipment_id' => equipment_count_dependency($pdo, 'equipment_hour_meters', 'equipment_id', $equipmentId),
        'equipment_hour_readings.equipment_id' => equipment_count_dependency($pdo, 'equipment_hour_readings', 'equipment_id', $equipmentId),
        'equipment_hour_adjustments_audit.equipment_id' => equipment_count_dependency($pdo, 'equipment_hour_adjustments_audit', 'equipment_id', $equipmentId),
        'equipment_maintenance_schedules.equipment_id' => equipment_count_dependency($pdo, 'equipment_maintenance_schedules', 'equipment_id', $equipmentId),
        'equipment_maintenance_events.equipment_id' => equipment_count_dependency($pdo, 'equipment_maintenance_events', 'equipment_id', $equipmentId),
        'fire_extinguisher_details.eid' => equipment_count_dependency($pdo, 'fire_extinguisher_details', 'eid', $equipmentId),
        'qr_links.asset_id' => equipment_count_dependency($pdo, 'qr_links', 'asset_id', $equipmentId, 'asset_type = ?', ['equipment']),
        'equipment_manual_sources.equipment_id' => equipment_count_dependency($pdo, 'equipment_manual_sources', 'equipment_id', $equipmentId),
        'equipment.replaced_by_eid outgoing' => !empty($equipment['replaced_by_eid']) ? 1 : 0,
        'equipment.replaced_by_eid incoming' => equipment_count_dependency($pdo, 'equipment', 'replaced_by_eid', $equipmentId),
    ];

    $fileCount = 0;
    if (equipment_table_exists($pdo, 'equipment_files')) {
        if (equipment_column_exists($pdo, 'equipment_files', 'eid')) {
            $fileCount += equipment_count_dependency($pdo, 'equipment_files', 'eid', $equipmentId);
        }
        if (equipment_column_exists($pdo, 'equipment_files', 'equipment_id')) {
            $fileCount += equipment_count_dependency($pdo, 'equipment_files', 'equipment_id', $equipmentId);
        }
    }
    $counts['equipment_files'] = $fileCount;

    return $counts;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_equipment_delete('Equipment deletion requires POST.', 405);
}

if (!user_is_mscs_admin()) {
    fail_equipment_delete('Access denied.', 403);
}

$equipmentId = (int)($_POST['equipment_id'] ?? 0);
if ($equipmentId <= 0) {
    fail_equipment_delete('Invalid equipment ID.');
}

$csrf = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || $csrf === '' || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    fail_equipment_delete('Invalid CSRF token.', 403);
}

$confirmText = trim((string)($_POST['confirm_text'] ?? ''));
$expectedConfirmText = 'DELETE EQUIPMENT #' . $equipmentId;
if ($confirmText !== $expectedConfirmText) {
    fail_equipment_delete('Delete confirmation text did not match.');
}

$reason = trim((string)($_POST['reason'] ?? ''));
if ($reason === '') {
    fail_equipment_delete('A deletion reason is required.');
}
if (strlen($reason) > 255) {
    $reason = substr($reason, 0, 255);
}

$stmt = $pdo->prepare('SELECT * FROM equipment WHERE eid = ? LIMIT 1');
$stmt->execute([$equipmentId]);
$equipment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$equipment) {
    fail_equipment_delete('Equipment not found.', 404);
}

$dependencyCounts = equipment_dependency_counts($pdo, $equipmentId, $equipment);
foreach ($dependencyCounts as $count) {
    if ((int)$count > 0) {
        fail_equipment_delete(EQUIPMENT_DELETE_BLOCKED_MESSAGE, 409);
    }
}

if (!equipment_table_exists($pdo, 'equipment_audit_log')) {
    fail_equipment_delete('Equipment audit log table is missing. Apply the equipment_audit_log migration before deleting equipment.', 500);
}

try {
    $pdo->beginTransaction();

    $equipmentSnapshot = json_encode($equipment, JSON_UNESCAPED_SLASHES);
    if ($equipmentSnapshot === false) {
        throw new RuntimeException('Unable to serialize equipment audit snapshot.');
    }

    $audit = $pdo->prepare("
        INSERT INTO equipment_audit_log (
            equipment_id,
            vessel_id,
            actor_user_id,
            action,
            equipment_snapshot,
            reason,
            created_at
        ) VALUES (?, ?, ?, 'delete', ?, ?, NOW())
    ");
    $audit->execute([
        $equipmentId,
        !empty($equipment['vessel_id']) ? (int)$equipment['vessel_id'] : null,
        (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0) ?: null,
        $equipmentSnapshot,
        $reason,
    ]);

    $delete = $pdo->prepare('DELETE FROM equipment WHERE eid = ? LIMIT 1');
    $delete->execute([$equipmentId]);

    $pdo->commit();

    $vesselId = (int)($equipment['vessel_id'] ?? 0);
    header('Location: vessel_equipment.php?vessel_id=' . $vesselId . '&success=equipment_deleted');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail_equipment_delete('Failed to delete equipment: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 500);
}
