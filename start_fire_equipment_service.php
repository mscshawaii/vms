<?php
require 'db_connect.php';
require 'session_check.php';

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
*/
$company_id = (int)($_SESSION['company_id'] ?? 0);
$role_id    = (int)($_SESSION['role_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id'] ?? 0);

if ($company_id !== 1 && $role_id !== 1) {
    die('Access denied.');
}

$vessel_id = (int)($_GET['vessel_id'] ?? 0);

if ($vessel_id <= 0) {
    die('Missing vessel_id.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function buildReportNumber(PDO $pdo): string
{
    $year = (int)date('Y');

    $pdo->prepare("
        INSERT INTO fire_service_report_sequences (sequence_year, last_number)
        VALUES (?, 0)
        ON DUPLICATE KEY UPDATE sequence_year = sequence_year
    ")->execute([$year]);

    $pdo->prepare("
        UPDATE fire_service_report_sequences
        SET last_number = last_number + 1
        WHERE sequence_year = ?
    ")->execute([$year]);

    $stmt = $pdo->prepare("
        SELECT last_number
        FROM fire_service_report_sequences
        WHERE sequence_year = ?
        LIMIT 1
    ");
    $stmt->execute([$year]);
    $seq = (int)$stmt->fetchColumn();

    return sprintf('AFSR-%04d-%04d', $year, $seq);
}

function detectItemType(array $row): string
{
    $scope = strtolower((string)($row['category_scope'] ?? ''));
    if ($scope === 'portable') {
        return 'Portable';
    }
    if ($scope === 'fixed') {
        return 'Fixed';
    }

    $typeName = strtolower((string)($row['equipment_type_name'] ?? ''));
    if (strpos($typeName, 'portable') !== false) {
        return 'Portable';
    }
    if (strpos($typeName, 'fixed') !== false) {
        return 'Fixed';
    }

    return 'Portable';
}

function deriveSubtype(array $row): string
{
    if (!empty($row['equipment_subtype_name'])) {
        return (string)$row['equipment_subtype_name'];
    }
    if (!empty($row['agent_type'])) {
        return (string)$row['agent_type'];
    }
    return '';
}

function deriveSizeRating(array $row): string
{
    $parts = [];

    if (!empty($row['ul_rating'])) {
        $parts[] = trim((string)$row['ul_rating']);
    }

    if (!empty($row['capacity_value'])) {
        $capacity = rtrim(rtrim((string)$row['capacity_value'], '0'), '.');
        $unit = trim((string)($row['capacity_unit'] ?? ''));
        $parts[] = trim($capacity . ($unit ? ' ' . $unit : ''));
    }

    return implode(' / ', array_filter($parts));
}

/*
|--------------------------------------------------------------------------
| VALIDATE VESSEL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON
    FROM vessels
    WHERE vessel_id = ?
      AND is_active = 1
      AND archived_at IS NULL
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die('Vessel not found.');
}

/*
|--------------------------------------------------------------------------
| REUSE MOST RECENT DRAFT FOR THIS USER + VESSEL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        fsr.fire_service_report_id,
        COUNT(fsri.fire_service_report_item_id) AS item_count
    FROM fire_service_reports fsr
    LEFT JOIN fire_service_report_items fsri
        ON fsri.fire_service_report_id = fsr.fire_service_report_id
    WHERE fsr.vessel_id = ?
      AND fsr.created_by = ?
      AND fsr.status = 'draft'
      AND fsr.archived_at IS NULL
    GROUP BY fsr.fire_service_report_id
    ORDER BY fsr.fire_service_report_id DESC
    LIMIT 1
");
$stmt->execute([$vessel_id, $user_id]);
$existingDraft = $stmt->fetch(PDO::FETCH_ASSOC);
$existing_report_id = (int)($existingDraft['fire_service_report_id'] ?? 0);
$existing_item_count = (int)($existingDraft['item_count'] ?? 0);
$stale_report_id = 0;

if ($existing_report_id > 0) {
    if ($existing_item_count > 0) {
        header('Location: fire_equipment_service.php?report_id=' . $existing_report_id);
        exit;
    }

    $currentItemsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment e
        WHERE e.vessel_id = ?
          AND e.equipment_type_id IN (14, 15)
    ");
    $currentItemsStmt->execute([$vessel_id]);
    $current_item_count = (int)$currentItemsStmt->fetchColumn();

    if ($current_item_count === 0) {
        header('Location: fire_equipment_service.php?report_id=' . $existing_report_id);
        exit;
    }

    // A prior empty draft can hide equipment added later; archive it before rebuilding.
    $stale_report_id = $existing_report_id;
}

/*
|--------------------------------------------------------------------------
| CREATE NEW DRAFT + SNAPSHOT ITEMS
|--------------------------------------------------------------------------
*/
try {
    $pdo->beginTransaction();

    if ($stale_report_id > 0) {
        $archiveStale = $pdo->prepare("
            UPDATE fire_service_reports fsr
            LEFT JOIN fire_service_report_items fsri
                ON fsri.fire_service_report_id = fsr.fire_service_report_id
            SET
                fsr.status = 'archived',
                fsr.archived_at = NOW(),
                fsr.updated_at = NOW()
            WHERE fsr.fire_service_report_id = ?
              AND fsr.vessel_id = ?
              AND fsr.created_by = ?
              AND fsr.status = 'draft'
              AND fsr.archived_at IS NULL
              AND fsri.fire_service_report_item_id IS NULL
        ");
        $archiveStale->execute([$stale_report_id, $vessel_id, $user_id]);
    }

    $report_number = buildReportNumber($pdo);

    $technician_name = trim((string)(($_SESSION['fName'] ?? '') . ' ' . ($_SESSION['lName'] ?? '')));
    if ($technician_name === '') {
        $technician_name = (string)($_SESSION['username'] ?? 'Sean Keeman');
    }

    $insertReport = $pdo->prepare("
        INSERT INTO fire_service_reports (
            report_number,
            vessel_id,
            service_date,
            facility_vessel_name,
            serviced_by,
            technician_name,
            technician_license,
            status,
            created_by
        ) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'draft', ?)
    ");
    $insertReport->execute([
        $report_number,
        $vessel_id,
        trim(($vessel['vesselName'] ?? '') . ' ON. ' . ($vessel['vesselON'] ?? '')),
        'Marine Safety Consulting & Surveying (MSCS Hawaii)',
        $technician_name,
        'FPS – KFD – 2025 - 003',
        $user_id
    ]);

    $report_id = (int)$pdo->lastInsertId();

    $itemsStmt = $pdo->prepare("
        SELECT
            e.eid,
            e.equipmentName,
            e.equipmentLocation,
            e.manufacturer,
            e.modelNumber,
            e.serialNumber,
            e.quantity,
            e.unit,
            e.notes AS equipment_notes,
            et.name AS equipment_type_name,
            est.name AS equipment_subtype_name,

            fed.extinguisher_detail_id,
            fed.rule_profile_id,
            fed.agent_type,
            fed.extinguisher_class,
            fed.ul_rating,
            fed.capacity_value,
            fed.capacity_unit,
            fed.remarks AS detail_remarks,
            fed.next_annual_due,

            ferp.category_scope
        FROM equipment e
        LEFT JOIN equipment_type et
            ON et.id = e.equipment_type_id
        LEFT JOIN equipment_subtype est
            ON est.id = e.equipment_subtype_id
        LEFT JOIN fire_extinguisher_details fed
            ON fed.eid = e.eid
        LEFT JOIN fire_extinguisher_rule_profiles ferp
            ON ferp.rule_profile_id = fed.rule_profile_id
        WHERE e.vessel_id = ?
          AND e.equipment_type_id IN (14, 15)
        ORDER BY
            FIELD(COALESCE(ferp.category_scope, ''), 'fixed', 'portable'),
            e.equipmentLocation ASC,
            e.equipmentName ASC,
            e.eid ASC
    ");
    $itemsStmt->execute([$vessel_id]);
    $equipmentRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $insertItem = $pdo->prepare("
        INSERT INTO fire_service_report_items (
            fire_service_report_id,
            equipment_id,
            extinguisher_detail_id,
            rule_profile_id,
            item_order,
            item_type,
            subtype,
            manufacturer,
            model_number,
            serial_number,
            location,
            size_rating,
            quantity,
            unit,
            next_due,
            notes,
            equipment_snapshot_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $order = 1;
    foreach ($equipmentRows as $row) {
        $snapshot = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $insertItem->execute([
            $report_id,
            !empty($row['eid']) ? (int)$row['eid'] : null,
            !empty($row['extinguisher_detail_id']) ? (int)$row['extinguisher_detail_id'] : null,
            !empty($row['rule_profile_id']) ? (int)$row['rule_profile_id'] : null,
            $order++,
            detectItemType($row),
            deriveSubtype($row),
            $row['manufacturer'] ?? null,
            $row['modelNumber'] ?? null,
            $row['serialNumber'] ?? null,
            $row['equipmentLocation'] ?? null,
            deriveSizeRating($row),
            $row['quantity'] ?? null,
            $row['unit'] ?? null,
            $row['next_annual_due'] ?? null,
            $row['detail_remarks'] ?: ($row['equipment_notes'] ?? null),
            $snapshot
        ]);
    }

    $pdo->commit();

    header('Location: fire_equipment_service.php?report_id=' . $report_id);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die('Failed to start service workflow: ' . $e->getMessage());
}
