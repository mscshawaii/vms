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

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDateValue($value, string $fallback = ''): string
{
    if (!$value || $value === '0000-00-00') {
        return $fallback;
    }

    $ts = strtotime($value);
    if (!$ts) {
        return h($value);
    }

    return date('Y-m-d', $ts);
}

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
| LOAD REQUEST CONTEXT
|--------------------------------------------------------------------------
*/
$report_id = (int)($_GET['report_id'] ?? 0);
$vessel_id = (int)($_GET['vessel_id'] ?? 0);
$saved = isset($_GET['saved']) ? 1 : 0;

/*
|--------------------------------------------------------------------------
| LOAD OR CREATE REPORT
|--------------------------------------------------------------------------
*/
if ($report_id > 0) {
    $stmt = $pdo->prepare("
        SELECT fsr.*
        FROM fire_service_reports fsr
        WHERE fsr.fire_service_report_id = ?
        LIMIT 1
    ");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        die('Report not found.');
    }

    $report_id = (int)$report['fire_service_report_id'];
    $vessel_id = (int)$report['vessel_id'];
} else {
    if ($vessel_id <= 0) {
        die('Missing vessel_id.');
    }

    $stmt = $pdo->prepare("
        SELECT fsr.*
        FROM fire_service_reports fsr
        WHERE fsr.vessel_id = ?
          AND fsr.status = 'draft'
          AND fsr.created_by = ?
          AND fsr.archived_at IS NULL
        ORDER BY fsr.fire_service_report_id DESC
        LIMIT 1
    ");
    $stmt->execute([$vessel_id, $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($report) {
        $report_id = (int)$report['fire_service_report_id'];
    } else {
        $pdo->beginTransaction();
        try {
            $reportNumber = buildReportNumber($pdo);

            $insert = $pdo->prepare("
                INSERT INTO fire_service_reports (
                    report_number,
                    vessel_id,
                    service_date,
                    technician_name,
                    technician_license,
                    status,
                    created_by
                ) VALUES (?, ?, CURDATE(), ?, ?, 'draft', ?)
            ");
            $insert->execute([
                $reportNumber,
                $vessel_id,
                trim((string)(($_SESSION['fName'] ?? '') . ' ' . ($_SESSION['lName'] ?? ''))) ?: ($_SESSION['username'] ?? 'Sean Keeman'),
                'FPS – KFD – 2025 - 003',
                $user_id
            ]);

            $report_id = (int)$pdo->lastInsertId();

            $report = [
                'fire_service_report_id' => $report_id,
                'report_number' => $reportNumber,
                'vessel_id' => $vessel_id,
                'service_date' => date('Y-m-d'),
                'customer_name' => null,
                'facility_vessel_name' => null,
                'address' => null,
                'contact_person' => null,
                'phone' => null,
                'email' => null,
                'serviced_by' => null,
                'technician_name' => trim((string)(($_SESSION['fName'] ?? '') . ' ' . ($_SESSION['lName'] ?? ''))) ?: ($_SESSION['username'] ?? 'Sean Keeman'),
                'technician_license' => 'FPS – KFD – 2025 - 003',
                'status' => 'draft'
            ];

            /*
            |--------------------------------------------------------------------------
            | SNAPSHOT CURRENT FIRE EQUIPMENT INTO REPORT ITEMS
            |--------------------------------------------------------------------------
            */
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
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            die('Failed to create draft report: ' . $e->getMessage());
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD VESSEL
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        v.vessel_id,
        v.vesselName,
        v.vesselON,
        v.company_id
    FROM vessels v
    WHERE v.vessel_id = ?
    LIMIT 1
");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die('Vessel not found.');
}

/*
|--------------------------------------------------------------------------
| OPTIONAL OWNER / CUSTOMER LOOKUP
|--------------------------------------------------------------------------
*/
$owner = null;
if (!empty($vessel['company_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM owners
            WHERE owner_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$vessel['company_id']]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $owner = null;
    }
}

/*
|--------------------------------------------------------------------------
| DEFAULT HEADER PREFILL FOR NEW/EMPTY REPORT FIELDS
|--------------------------------------------------------------------------
*/
if (empty($report['customer_name']) && $owner) {
    $report['customer_name'] = $owner['companyName'] ?? ($owner['ownerName'] ?? null);
}
if (empty($report['facility_vessel_name'])) {
    $report['facility_vessel_name'] = trim(($vessel['vesselName'] ?? '') . ' ON. ' . ($vessel['vesselON'] ?? ''));
}
if (empty($report['contact_person']) && $owner) {
    $report['contact_person'] = $owner['contactPerson'] ?? $owner['primaryContact'] ?? null;
}
if (empty($report['phone']) && $owner) {
    $report['phone'] = $owner['phone'] ?? null;
}
if (empty($report['email']) && $owner) {
    $report['email'] = $owner['email'] ?? null;
}

/*
|--------------------------------------------------------------------------
| SAVE POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_id = (int)($_POST['report_id'] ?? 0);

    $customer_name         = trim($_POST['customer_name'] ?? '');
    $facility_vessel_name  = trim($_POST['facility_vessel_name'] ?? '');
    $address               = trim($_POST['address'] ?? '');
    $contact_person        = trim($_POST['contact_person'] ?? '');
    $phone                 = trim($_POST['phone'] ?? '');
    $email                 = trim($_POST['email'] ?? '');
    $serviced_by           = trim($_POST['serviced_by'] ?? '');
    $technician_name       = trim($_POST['technician_name'] ?? '');
    $technician_license    = trim($_POST['technician_license'] ?? '');
    $service_date          = trim($_POST['service_date'] ?? '');
    $source_notes          = trim($_POST['source_notes'] ?? '');

    $item_ids              = $_POST['item_id'] ?? [];
    $condition_codes       = $_POST['condition_code'] ?? [];
    $next_due_dates        = $_POST['next_due'] ?? [];
    $notes_map             = $_POST['notes'] ?? [];
    $service_codes_map     = $_POST['service_codes'] ?? [];

    $pdo->beginTransaction();
    try {
        $updateReport = $pdo->prepare("
            UPDATE fire_service_reports
            SET
                service_date = ?,
                customer_name = ?,
                facility_vessel_name = ?,
                address = ?,
                contact_person = ?,
                phone = ?,
                email = ?,
                serviced_by = ?,
                technician_name = ?,
                technician_license = ?,
                source_notes = ?
            WHERE fire_service_report_id = ?
            LIMIT 1
        ");
        $updateReport->execute([
            $service_date ?: date('Y-m-d'),
            $customer_name ?: null,
            $facility_vessel_name ?: null,
            $address ?: null,
            $contact_person ?: null,
            $phone ?: null,
            $email ?: null,
            $serviced_by ?: null,
            $technician_name ?: null,
            $technician_license ?: null,
            $source_notes ?: null,
            $report_id
        ]);

        $updateItem = $pdo->prepare("
            UPDATE fire_service_report_items
            SET
                condition_code = ?,
                service_codes = ?,
                next_due = ?,
                notes = ?
            WHERE fire_service_report_item_id = ?
              AND fire_service_report_id = ?
            LIMIT 1
        ");

        foreach ($item_ids as $rawItemId) {
            $itemId = (int)$rawItemId;
            $condition = trim((string)($condition_codes[$itemId] ?? ''));
            $nextDue = trim((string)($next_due_dates[$itemId] ?? ''));
            $notes = trim((string)($notes_map[$itemId] ?? ''));
            $serviceCodes = $service_codes_map[$itemId] ?? [];

            if (!is_array($serviceCodes)) {
                $serviceCodes = [];
            }

            $serviceCodes = array_values(array_filter(array_map('trim', $serviceCodes)));
            $serviceCodesValue = $serviceCodes ? implode(', ', $serviceCodes) : null;

            $updateItem->execute([
                $condition !== '' ? $condition : null,
                $serviceCodesValue,
                $nextDue !== '' ? $nextDue : null,
                $notes !== '' ? $notes : null,
                $itemId,
                $report_id
            ]);
        }

        $pdo->commit();
        header('Location: fire_equipment_service.php?report_id=' . $report_id . '&saved=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('Failed to save draft report: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| RELOAD REPORT + ITEMS FOR DISPLAY
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM fire_service_reports
    WHERE fire_service_report_id = ?
    LIMIT 1
");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM fire_service_report_items
    WHERE fire_service_report_id = ?
    ORDER BY item_order ASC, fire_service_report_item_id ASC
");
$stmt->execute([$report_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Annual Fire Equipment Service';
$back_link = 'fire_equipment_service_start.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Annual Fire Equipment Service</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .service-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .section-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 16px;
        }

        .section-card .card-body {
            padding: 18px;
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .service-table th,
        .service-table td {
            vertical-align: middle;
        }

        .service-table input,
        .service-table select,
        .service-table textarea {
            min-width: 120px;
        }

        .service-table textarea {
            min-height: 42px;
        }

        .scope-list li {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="service-shell">
    <div class="app-page">
        <div class="app-container">

            <?php if ($saved): ?>
                <div class="alert alert-success">
                    Draft fire equipment service report saved.
                </div>
            <?php endif; ?>

            <div class="section-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-muted mb-1">Annual Fire Equipment Service</div>
                            <h1 class="h4 mb-1"><?= h($vessel['vesselName']) ?></h1>
                            <div class="text-muted">
                                ON <?= h($vessel['vesselON']) ?> · Report # <?= h($report['report_number']) ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <a href="fire_equipment_service_start.php" class="btn btn-outline-secondary">
                                Back to Vessel Selection
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="report_id" value="<?= (int)$report_id ?>">

                <div class="section-card">
                    <div class="card-body">
                        <div class="section-title">Service Report Header</div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name</label>
                                <input type="text" name="customer_name" class="form-control" value="<?= h($report['customer_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Facility / Vessel</label>
                                <input type="text" name="facility_vessel_name" class="form-control" value="<?= h($report['facility_vessel_name'] ?? '') ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" value="<?= h($report['address'] ?? '') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?= h($report['contact_person'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= h($report['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="text" name="email" class="form-control" value="<?= h($report['email'] ?? '') ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Serviced By</label>
                                <input type="text" name="serviced_by" class="form-control" value="<?= h($report['serviced_by'] ?? 'Marine Safety Consulting & Surveying (MSCS Hawaii)') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Technician Name</label>
                                <input type="text" name="technician_name" class="form-control" value="<?= h($report['technician_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">License #</label>
                                <input type="text" name="technician_license" class="form-control" value="<?= h($report['technician_license'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Service Date</label>
                                <input type="date" name="service_date" class="form-control" value="<?= h(formatDateValue($report['service_date'] ?? date('Y-m-d'))) ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Service Session Notes</label>
                                <textarea name="source_notes" class="form-control" rows="3"><?= h($report['source_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="card-body">
                        <div class="section-title">Service Scope Reference</div>

                        <div class="row g-4">
                            <div class="col-md-4">
                                <h6>Portable Extinguishers</h6>
                                <ul class="scope-list mb-0">
                                    <li>Visual inspection and identification check</li>
                                    <li>Pressure / weight verification as applicable</li>
                                    <li>Hose, valve, and seal condition review</li>
                                    <li>Exterior cleaning and general condition review</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Fixed CO₂ Systems</h6>
                                <ul class="scope-list mb-0">
                                    <li>Cylinder and release hardware review</li>
                                    <li>Pressure / weight verification as applicable</li>
                                    <li>Piping / controls visual condition review</li>
                                    <li>Exterior cleaning and general condition review</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6>Fixed Clean Agent Systems</h6>
                                <ul class="scope-list mb-0">
                                    <li>Cylinder / bottle condition review</li>
                                    <li>Release head and system hardware review</li>
                                    <li>Pressure / weight verification as applicable</li>
                                    <li>Exterior cleaning and general condition review</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div class="section-title mb-0">Service Items</div>
                            <div class="small text-muted">
                                Condition Codes: 1 = Serviceable, 2 = Non-Serviceable, 3 = New |
                                Service Codes: V, R, RV, C, CL
                            </div>
                        </div>

                        <?php if (!$items): ?>
                            <div class="alert alert-warning mb-0">
                                No fire equipment rows were loaded for this vessel.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle service-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Item Type</th>
                                            <th>Subtype</th>
                                            <th>Manufacturer</th>
                                            <th>Model</th>
                                            <th>Serial #</th>
                                            <th>Location</th>
                                            <th>Size / Rating</th>
                                            <th>Condition</th>
                                            <th>Service Performed</th>
                                            <th>Next Due</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $itemId = (int)$item['fire_service_report_item_id'];
                                            $selectedCodes = array_map('trim', explode(',', (string)($item['service_codes'] ?? '')));
                                            ?>
                                            <tr>
                                                <td>
                                                    <?= (int)$item['item_order'] ?>
                                                    <input type="hidden" name="item_id[]" value="<?= $itemId ?>">
                                                </td>
                                                <td><?= h($item['item_type']) ?></td>
                                                <td><?= h($item['subtype']) ?></td>
                                                <td><?= h($item['manufacturer']) ?></td>
                                                <td><?= h($item['model_number']) ?></td>
                                                <td><?= h($item['serial_number']) ?></td>
                                                <td><?= h($item['location']) ?></td>
                                                <td><?= h($item['size_rating']) ?></td>

                                                <td>
                                                    <select name="condition_code[<?= $itemId ?>]" class="form-select form-select-sm">
                                                        <option value="">—</option>
                                                        <option value="1" <?= ($item['condition_code'] === '1') ? 'selected' : '' ?>>1 - Serviceable</option>
                                                        <option value="2" <?= ($item['condition_code'] === '2') ? 'selected' : '' ?>>2 - Non-Serviceable</option>
                                                        <option value="3" <?= ($item['condition_code'] === '3') ? 'selected' : '' ?>>3 - New</option>
                                                    </select>
                                                </td>

                                                <td>
                                                    <div class="d-grid gap-1">
                                                        <?php
                                                        $codeOptions = ['V', 'R', 'RV', 'C', 'CL'];
                                                        foreach ($codeOptions as $codeOption):
                                                        ?>
                                                            <div class="form-check">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    name="service_codes[<?= $itemId ?>][]"
                                                                    value="<?= h($codeOption) ?>"
                                                                    id="svc_<?= $itemId ?>_<?= h($codeOption) ?>"
                                                                    <?= in_array($codeOption, $selectedCodes, true) ? 'checked' : '' ?>
                                                                >
                                                                <label class="form-check-label small" for="svc_<?= $itemId ?>_<?= h($codeOption) ?>">
                                                                    <?= h($codeOption) ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>

                                                <td>
                                                    <input
                                                        type="date"
                                                        name="next_due[<?= $itemId ?>]"
                                                        class="form-control form-control-sm"
                                                        value="<?= h(formatDateValue($item['next_due'] ?? '')) ?>"
                                                    >
                                                </td>

                                                <td>
                                                    <textarea
                                                        name="notes[<?= $itemId ?>]"
                                                        class="form-control form-control-sm"
                                                        rows="2"
                                                    ><?= h($item['notes'] ?? '') ?></textarea>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap pb-4">
                    <button type="submit" class="btn btn-primary">
                        Save Draft
                    </button>

                    <button type="button" class="btn btn-outline-secondary" disabled>
                        Generate PDF (next step)
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
</body>
</html>