<?php
require 'db_connect.php';

$code = trim($_GET['code'] ?? '');

if ($code === '') {
    die("Missing QR code");
}

$stmt = $pdo->prepare("
    SELECT 
        q.qr_link_id,
        q.code,
        q.asset_type,
        q.asset_id,
        q.vessel_id,
        q.is_active,
        q.created_at,

        v.vesselName,
        v.vesselON,

        e.eid,
        e.equipmentName,
        e.equipmentLocation,
        e.manufacturer,
        e.modelNumber,
        e.serialNumber,
        e.installDate,
        e.expDate,
        e.quantity,
        e.unit,
        e.notes,
        e.photo_path,
        e.equipment_type_id,
        e.equipment_subtype_id

    FROM qr_links q
    INNER JOIN vessels v
        ON v.vessel_id = q.vessel_id
    INNER JOIN equipment e
        ON e.eid = q.asset_id
    WHERE q.code = ?
      AND q.asset_type = 'equipment'
      AND q.is_active = 1
    LIMIT 1
");
$stmt->execute([$code]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    die("QR code not found or inactive");
}

$isPortable = ((int)$item['equipment_type_id'] === 14);
$isFixed    = ((int)$item['equipment_type_id'] === 15);

/*
|--------------------------------------------------------------------------
| TYPE NAME LOOKUP
|--------------------------------------------------------------------------
*/
$typeName = null;
if (!empty($item['equipment_type_id'])) {
    $typeStmt = $pdo->prepare("
        SELECT name
        FROM equipment_type
        WHERE id = ?
        LIMIT 1
    ");
    $typeStmt->execute([(int)$item['equipment_type_id']]);
    $typeName = $typeStmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| SUBTYPE NAME LOOKUP
|--------------------------------------------------------------------------
*/
$subtypeName = null;
if (!empty($item['equipment_subtype_id'])) {
    $subtypeStmt = $pdo->prepare("
        SELECT name
        FROM equipment_subtype
        WHERE id = ?
        LIMIT 1
    ");
    $subtypeStmt->execute([(int)$item['equipment_subtype_id']]);
    $subtypeName = $subtypeStmt->fetchColumn();
}

/*
|--------------------------------------------------------------------------
| FIRE EQUIPMENT SERVICING DOCUMENT
|--------------------------------------------------------------------------
*/
$serviceDoc = null;
$serviceDocStmt = $pdo->prepare("
    SELECT id, docName, docType, category, uploaded_on, expDate
    FROM documents
    WHERE vessel_id = ?
      AND archived_at IS NULL
      AND (
            docName LIKE '%Fire Equipment Servicing%'
         OR docType LIKE '%Fire Equipment Servicing%'
         OR category LIKE '%Fire Equipment Servicing%'
      )
    ORDER BY uploaded_on DESC, id DESC
    LIMIT 1
");
$serviceDocStmt->execute([(int)$item['vessel_id']]);
$serviceDoc = $serviceDocStmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| FINALIZED ICR PROGRAM HISTORY - LAST 12 MONTHS
|--------------------------------------------------------------------------
| This is vessel-level program history, not individual extinguisher history.
*/
$programRuns = [];
$icrFilterIds = [];
$historyTitle = 'Applicable Fire Equipment Program History (Last 12 Months)';
$historyNote  = 'This history reflects finalized vessel-level extinguisher program inspections for the applicable equipment category.';

if ($isPortable) {
    $icrFilterIds = [13]; // C 04 - Portable Extinguishers
    $historyTitle = 'Portable Extinguisher Program History (Last 12 Months)';
    $historyNote  = 'This history reflects finalized vessel-level portable extinguisher inspections completed within the last 12 months.';
} elseif ($isFixed) {
    $icrFilterIds = [11, 12]; // C 01 CO2 / C 02 Clean Agent
    $historyTitle = 'Fixed Fire System Program History (Last 12 Months)';
    $historyNote  = 'This history reflects finalized vessel-level fixed fire extinguishing system inspections completed within the last 12 months.';
}

if (!empty($icrFilterIds)) {
    $placeholders = implode(',', array_fill(0, count($icrFilterIds), '?'));

    $icrSql = "
        SELECT 
            vir.run_id,
            vir.run_date,
            vir.finalized_at,
            vir.inspector,
            COALESCE(vi.title, i.title) AS icr_title,
            COALESCE(vi.icr_number, i.icr_number) AS icr_number
        FROM vessel_icr_runs vir
        LEFT JOIN vessel_icrs vi
            ON vi.vessel_icr_id = vir.vessel_icr_id
        LEFT JOIN icrs i
            ON i.icr_id = vir.icr_id
        WHERE vir.vessel_id = ?
          AND vir.save_state = 'final'
          AND vir.icr_id IN ($placeholders)
          AND vir.finalized_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        ORDER BY vir.finalized_at DESC, vir.run_id DESC
    ";

    $params = array_merge([(int)$item['vessel_id']], $icrFilterIds);

    $icrStmt = $pdo->prepare($icrSql);
    $icrStmt->execute($params);
    $programRuns = $icrStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| ACTION URLS - QR-aware helpers
|--------------------------------------------------------------------------
*/
$performIcrUrl    = 'open_equipment_icr.php?code=' . urlencode($code);
$editEquipmentUrl = 'edit_equipment_qr.php?code=' . urlencode($code);
$serviceDocUrl    = 'view_equipment_service_doc.php?code=' . urlencode($code);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function displayValue($value, string $fallback = '—'): string
{
    return ($value !== null && $value !== '') ? h($value) : $fallback;
}

function formatDateValue($value, string $fallback = '—'): string
{
    if (!$value || $value === '0000-00-00') {
        return $fallback;
    }

    $ts = strtotime($value);
    if (!$ts) {
        return h($value);
    }

    return date('M j, Y', $ts);
}

function expirationBadge($expDate): string
{
    if (!$expDate || $expDate === '0000-00-00') {
        return '<span class="badge bg-secondary">No Expiration Set</span>';
    }

    $today = strtotime(date('Y-m-d'));
    $exp   = strtotime($expDate);

    if ($exp === false) {
        return '<span class="badge bg-secondary">Unknown</span>';
    }

    if ($exp < $today) {
        return '<span class="badge bg-danger">Expired</span>';
    }

    $days = (int)(($exp - $today) / 86400);

    if ($days <= 30) {
        return '<span class="badge bg-warning text-dark">Due Soon</span>';
    }

    return '<span class="badge bg-success">Current</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Equipment QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f8f9fa;
        }
        .hero-card,
        .info-card {
            border: 1px solid #e5e5e5;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.15rem;
        }
        .value {
            font-weight: 500;
            margin-bottom: 0.85rem;
            word-break: break-word;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            word-break: break-all;
        }
        .equipment-photo {
            width: 100%;
            max-height: 320px;
            object-fit: contain;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e5e5;
            padding: 8px;
        }
        .action-grid .btn {
            min-height: 46px;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="hero-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-muted mb-1">Vessel Equipment QR</div>
                <h2 class="mb-1"><?= displayValue($item['equipmentName'], 'Unnamed Equipment') ?></h2>
                <div class="text-muted">
                    <?= h($item['vesselName']) ?><?php if (!empty($item['vesselON'])): ?> (ON <?= h($item['vesselON']) ?>)<?php endif; ?>
                </div>
            </div>
            <div>
                <?= expirationBadge($item['expDate']) ?>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="label">Equipment Type</div>
                <div class="value"><?= displayValue($typeName) ?></div>
            </div>
            <div class="col-md-4">
                <div class="label">Subtype</div>
                <div class="value"><?= displayValue($subtypeName) ?></div>
            </div>
            <div class="col-md-4">
                <div class="label">Location</div>
                <div class="value"><?= displayValue($item['equipmentLocation']) ?></div>
            </div>
        </div>

        <?php if ($isPortable): ?>
            <div class="alert alert-info mt-3 mb-0">
                Portable fire extinguisher record.
            </div>
        <?php elseif ($isFixed): ?>
            <div class="alert alert-info mt-3 mb-0">
                Fixed fire extinguishing system record.
            </div>
        <?php endif; ?>
    </div>

    <div class="info-card p-4 mb-4">
        <div class="section-title">Actions</div>

        <div class="row g-2 action-grid">
            <div class="col-12 col-md-6">
                <a href="<?= h($performIcrUrl) ?>" class="btn btn-primary w-100">
                    Perform ICR
                </a>
            </div>

            <div class="col-12 col-md-6">
                <a href="<?= h($editEquipmentUrl) ?>" class="btn btn-outline-primary w-100">
                    Edit Extinguisher
                </a>
            </div>

            <?php if ($serviceDoc): ?>
                <div class="col-12">
                    <a href="<?= h($serviceDocUrl) ?>" class="btn btn-outline-success w-100">
                        View Fire Equipment Servicing Report
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-lg-7">
            <div class="info-card p-4 mb-4">
                <div class="section-title">Equipment Details</div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="label">Manufacturer</div>
                        <div class="value"><?= displayValue($item['manufacturer']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Model Number</div>
                        <div class="value"><?= displayValue($item['modelNumber']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Serial Number</div>
                        <div class="value"><?= displayValue($item['serialNumber']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Quantity</div>
                        <div class="value">
                            <?php
                            $qty = $item['quantity'];
                            $unit = $item['unit'];
                            echo ($qty !== null && $qty !== '')
                                ? h($qty . ($unit ? ' ' . $unit : ''))
                                : '—';
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Install Date</div>
                        <div class="value"><?= formatDateValue($item['installDate']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="label">Expiration Date</div>
                        <div class="value"><?= formatDateValue($item['expDate']) ?></div>
                    </div>
                </div>

                <div class="label">Notes</div>
                <div class="value"><?= nl2br(h($item['notes'] ?: '—')) ?></div>
            </div>

            <div class="info-card p-4 mb-4">
                <div class="section-title"><?= h($historyTitle) ?></div>

                <?php if (!$programRuns): ?>
                    <div class="text-muted">No finalized inspection history was found for the applicable vessel program within the last 12 months.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>ICR</th>
                                    <th>Run Date</th>
                                    <th>Finalized</th>
                                    <th>Inspector</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programRuns as $run): ?>
                                    <tr>
                                        <td>
                                            <strong><?= displayValue($run['icr_title']) ?></strong><br>
                                            <small class="text-muted"><?= displayValue($run['icr_number']) ?></small>
                                        </td>
                                        <td><?= formatDateValue($run['run_date']) ?></td>
                                        <td><?= formatDateValue($run['finalized_at']) ?></td>
                                        <td><?= displayValue($run['inspector']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2">
                        <?= h($historyNote) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="info-card p-4 mb-4">
                <div class="section-title">QR Record</div>

                <div class="label">QR Code</div>
                <div class="value mono"><?= h($item['code']) ?></div>

                <div class="label">Created</div>
                <div class="value"><?= formatDateValue($item['created_at']) ?></div>

                <div class="label">Status</div>
                <div class="value"><span class="badge bg-success">Active</span></div>
            </div>

            <?php if ($serviceDoc): ?>
                <div class="info-card p-4 mb-4">
                    <div class="section-title">Servicing Record</div>

                    <div class="label">Document</div>
                    <div class="value"><?= displayValue($serviceDoc['docName']) ?></div>

                    <div class="label">Uploaded</div>
                    <div class="value"><?= formatDateValue($serviceDoc['uploaded_on']) ?></div>

                    <div class="label">Expiration</div>
                    <div class="value"><?= formatDateValue($serviceDoc['expDate']) ?></div>

                    <a href="<?= h($serviceDocUrl) ?>" class="btn btn-outline-success w-100">
                        Open Servicing Report
                    </a>
                </div>
            <?php endif; ?>

            <div class="info-card p-4">
                <div class="section-title">Equipment Photo</div>

                <?php if (!empty($item['photo_path'])): ?>
                    <img src="<?= h($item['photo_path']) ?>" alt="Equipment Photo" class="equipment-photo">
                <?php else: ?>
                    <div class="text-muted">No photo available.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
</body>
</html>