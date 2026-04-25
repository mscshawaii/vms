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

if ($company_id !== 1 && $role_id !== 1) {
    die('Access denied.');
}

$report_id = (int)($_GET['report_id'] ?? $_POST['report_id'] ?? 0);

if ($report_id <= 0) {
    die('Missing report_id.');
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

function formatDateInput($value): string
{
    if (!$value || $value === '0000-00-00') {
        return '';
    }

    $ts = strtotime($value);
    if (!$ts) {
        return '';
    }

    return date('Y-m-d', $ts);
}

function buildSizeDisplayForForm(array $item): string
{
    $snap = [];
    if (!empty($item['equipment_snapshot_json'])) {
        $decoded = json_decode($item['equipment_snapshot_json'], true);
        if (is_array($decoded)) {
            $snap = $decoded;
        }
    }

    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    $ulRating = trim((string)($snap['ul_rating'] ?? ''));
    $capacityValue = $snap['capacity_value'] ?? '';
    $capacityUnit  = trim((string)($snap['capacity_unit'] ?? ''));
    $subtype = trim((string)($item['subtype'] ?? ''));
    if ($subtype === '') {
        $subtype = trim((string)($snap['equipment_subtype_name'] ?? $snap['agent_type'] ?? ''));
    }
    $sizeRating = trim((string)($item['size_rating'] ?? ''));

    if ($itemType === 'fixed') {
        if ($capacityValue !== null && $capacityValue !== '') {
            $capacity = rtrim(rtrim((string)$capacityValue, '0'), '.');
            return trim($capacity . ($capacityUnit ? ' ' . $capacityUnit : ''));
        }

        if ($subtype !== '') {
            return $subtype;
        }

        if ($sizeRating !== '') {
            return $sizeRating;
        }

        return '';
    }

    // Portable
    if ($ulRating !== '') {
        return $ulRating;
    }

    if ($sizeRating !== '') {
        return $sizeRating;
    }

    return $subtype;
}

/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT fsr.*, v.vesselName, v.vesselON, v.company_id AS vessel_company_id
    FROM fire_service_reports fsr
    INNER JOIN vessels v
        ON v.vessel_id = fsr.vessel_id
    WHERE fsr.fire_service_report_id = ?
    LIMIT 1
");
$stmt->execute([$report_id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    die('Report not found.');
}

/*
|--------------------------------------------------------------------------
| OPTIONAL OWNER LOOKUP
|--------------------------------------------------------------------------
*/
$owner = null;
if (!empty($report['vessel_company_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM owners
            WHERE owner_id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$report['vessel_company_id']]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $owner = null;
    }
}

/*
|--------------------------------------------------------------------------
| PREFILL HEADER FROM OWNERS TABLE
|--------------------------------------------------------------------------
*/
if ($owner) {
    if (empty($report['customer_name'])) {
        $report['customer_name'] = $owner['company_name'] ?? '';
    }

    if (empty($report['facility_vessel_name'])) {
        $report['facility_vessel_name'] = trim(
            ($report['vesselName'] ?? '') . ' ON. ' . ($report['vesselON'] ?? '')
        );
    }

    if (empty($report['contact_person'])) {
        $report['contact_person'] = $owner['contact_name'] ?? '';
    }

    if (empty($report['phone'])) {
        $report['phone'] = $owner['phone'] ?? '';
    }

    if (empty($report['email'])) {
        $report['email'] = $owner['email'] ?? '';
    }

    if (empty($report['address'])) {
        $report['address'] = $owner['address'] ?? '';
    }
}

if (empty($report['serviced_by'])) {
    $report['serviced_by'] = 'Marine Safety Consulting & Surveying (MSCS Hawaii)';
}

if (empty($report['technician_license'])) {
    $report['technician_license'] = 'FPS – KFD – 2025 - 003';
}

if (empty($report['service_date'])) {
    $report['service_date'] = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| SAVE POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name        = trim($_POST['customer_name'] ?? '');
    $facility_vessel_name = trim($_POST['facility_vessel_name'] ?? '');
    $address              = trim($_POST['address'] ?? '');
    $contact_person       = trim($_POST['contact_person'] ?? '');
    $phone                = trim($_POST['phone'] ?? '');
    $email                = trim($_POST['email'] ?? '');
    $serviced_by          = trim($_POST['serviced_by'] ?? '');
    $technician_name      = trim($_POST['technician_name'] ?? '');
    $technician_license   = trim($_POST['technician_license'] ?? '');
    $service_date         = trim($_POST['service_date'] ?? '');
    $source_notes         = trim($_POST['source_notes'] ?? '');

    $item_ids          = $_POST['item_id'] ?? [];
    $condition_codes   = $_POST['condition_code'] ?? [];
    $next_due_map      = $_POST['next_due'] ?? [];
    $notes_map         = $_POST['notes'] ?? [];
    $service_codes_map = $_POST['service_codes'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
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
                source_notes = ?,
                updated_at = NOW()
            WHERE fire_service_report_id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $service_date ?: null,
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
                notes = ?,
                updated_at = NOW()
            WHERE fire_service_report_item_id = ?
              AND fire_service_report_id = ?
            LIMIT 1
        ");

        foreach ($item_ids as $rawItemId) {
            $itemId = (int)$rawItemId;
            $condition = trim((string)($condition_codes[$itemId] ?? ''));
            $nextDue = trim((string)($next_due_map[$itemId] ?? ''));
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
        die('Failed to save service report: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| LOAD ITEMS ONLY
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM fire_service_report_items
    WHERE fire_service_report_id = ?
    ORDER BY item_order ASC, fire_service_report_item_id ASC
");
$stmt->execute([$report_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fire Equipment Service</title>
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
                    Draft service report saved.
                </div>
            <?php endif; ?>

            <div class="section-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-muted mb-1">Annual Fire Equipment Service</div>
                            <h1 class="h4 mb-1"><?= h($report['vesselName']) ?></h1>
                            <div class="text-muted">
                                ON <?= h($report['vesselON']) ?> · Report # <?= h($report['report_number']) ?>
                            </div>
                        </div>

                        <div>
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
                                <input type="text" name="serviced_by" class="form-control" value="<?= h($report['serviced_by'] ?? '') ?>">
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
                                <input type="date" name="service_date" class="form-control" value="<?= h(formatDateInput($report['service_date'] ?? '')) ?>">
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
                                No fire equipment items found for this draft.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle service-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Item Type</th>
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
                                                <td><?= h($item['manufacturer']) ?></td>
                                                <td><?= h($item['model_number']) ?></td>
                                                <td><?= h($item['serial_number']) ?></td>
                                                <td><?= h($item['location']) ?></td>
                                                <td><?= h(buildSizeDisplayForForm($item)) ?></td>
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
                                                        <?php foreach (['V', 'R', 'RV', 'C', 'CL'] as $code): ?>
                                                            <div class="form-check">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    name="service_codes[<?= $itemId ?>][]"
                                                                    value="<?= h($code) ?>"
                                                                    id="svc_<?= $itemId ?>_<?= h($code) ?>"
                                                                    <?= in_array($code, $selectedCodes, true) ? 'checked' : '' ?>
                                                                >
                                                                <label class="form-check-label small" for="svc_<?= $itemId ?>_<?= h($code) ?>">
                                                                    <?= h($code) ?>
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
                                                        value="<?= h(formatDateInput($item['next_due'] ?? '')) ?>"
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

                    <button
                        type="submit"
                        formaction="finalize_fire_equipment_service.php"
                        class="btn btn-success"
                    >
                        Finalize &amp; Generate Report
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
</body>
</html>