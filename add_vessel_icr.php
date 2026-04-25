<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

if (!isset($_GET['vessel_id'])) {
    die('Vessel ID missing.');
}

$vessel_id = (int)$_GET['vessel_id'];

/* Vessel header */
$vstmt = $pdo->prepare("
    SELECT vessel_id, vesselName, vesselON, hailingPort
    FROM vessels
    WHERE vessel_id = ?
    LIMIT 1
");
$vstmt->execute([$vessel_id]);
$vessel = $vstmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
    die('Vessel not found.');
}

/* Fetch templates NOT currently assigned to this vessel */
$stmt = $pdo->prepare("
    SELECT icr_id, icr_number, title, frequency
    FROM icrs
    WHERE icr_id NOT IN (
        SELECT icr_id
        FROM vessel_icrs
        WHERE vessel_id = ?
          AND is_removed = 0
    )
    ORDER BY icr_number
");
$stmt->execute([$vessel_id]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

function icr_group_label(?string $icrNumber): string {
    $icrNumber = trim((string)$icrNumber);
    $prefix = strtoupper(substr($icrNumber, 0, 1));

    return match ($prefix) {
        'A' => 'A — Paperwork',
        'B' => 'B — Lifesaving',
        'C' => 'C — Fire Protection',
        'E' => 'E — Emergency Equipment',
        'F' => 'F — Ventilation',
        'G' => 'G — Navigation Equipment',
        'H' => 'H — Ground Tackle',
        'I' => 'I — Watertight Integrity',
        'J' => 'J — Accommodations / Related Spaces',
        'L' => 'L — Forms, Notices, Publications & Crew Requirements',
        'M' => 'M — Hourly Maintenance',
        'N' => 'N — Diesel Power Systems',
        'P' => 'P — Auxiliary Machinery & Equipment',
        'Q' => 'Q — Electrical Systems',
        'S' => 'S — Surveys / Third Party',
        'K' => 'K — Drills / Training',
        default => ($prefix !== '' ? $prefix . ' — Other' : 'Other'),
    };
}

$grouped = [];
foreach ($templates as $icr) {
    $label = icr_group_label($icr['icr_number'] ?? '');
    $grouped[$label][] = $icr;
}
$groupOrder = [
    'A','B','C','E','F','G','H','I','J','K','L','M','N','P','Q','S'
];

uksort($grouped, function ($a, $b) use ($groupOrder) {
    $aKey = strtoupper(substr($a, 0, 1));
    $bKey = strtoupper(substr($b, 0, 1));

    $aPos = array_search($aKey, $groupOrder);
    $bPos = array_search($bKey, $groupOrder);

    return ($aPos ?? 999) <=> ($bPos ?? 999);
});

function safe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assign ICRs to Vessel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .assign-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .assign-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .assign-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .assign-subtitle {
            color: #6b7280;
            margin: 0;
        }

        .assign-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .assign-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .icr-accordion .accordion-item {
            border: 1px solid #dbe4ee;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 10px;
            background: #fff;
        }

        .icr-accordion .accordion-button {
            font-weight: 700;
            background: #fff;
        }

        .icr-accordion .accordion-button:not(.collapsed) {
            background: #eef5ff;
            color: #0d6efd;
        }

        .icr-option {
            border: 1px solid #e9eef5;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }

        .icr-option + .icr-option {
            margin-top: 10px;
        }

        .icr-option-title {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .icr-option-meta {
            font-size: 0.92rem;
            color: #6b7280;
        }

        .sticky-actions {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: rgba(244,247,251,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dbe4ee;
            padding-top: 12px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
<?php
$title = 'Assign ICRs';
$back_link = 'vessel_icrs.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="assign-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="assign-header">
                    <div>
                        <h1 class="assign-title">Assign ICRs</h1>
                        <p class="assign-subtitle">
                            <?= safe($vessel['vesselName']) ?> · Official No. <?= safe($vessel['vesselON']) ?> · Hailing Port: <?= safe($vessel['hailingPort']) ?>
                        </p>
                    </div>

                    <div class="assign-actions">
                        <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Back to ICRs</a>
                    </div>
                </div>

                <p class="mb-0 text-muted">
                    Select one or more ICR templates to assign to this vessel. Assigned ICRs become vessel-specific and can be managed independently.
                </p>
            </div>

            <?php if (empty($templates)): ?>
                <div class="vms-card">
                    <div class="alert alert-info mb-0">
                        All available ICR templates are already assigned to this vessel.
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="submit_vessel_icr.php" id="assignIcrForm">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">

                    <div class="vms-card mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllIcrs">Select All</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearAllIcrs">Clear All</button>
                        </div>
                    </div>

                    <div class="accordion icr-accordion" id="icrAssignAccordion">
                        <?php $gIndex = 0; ?>
                        <?php foreach ($grouped as $groupLabel => $items): ?>
                            <?php $gIndex++; ?>
                            <?php
                                $headingId = 'icrGroupHeading' . $gIndex;
                                $collapseId = 'icrGroupCollapse' . $gIndex;
                            ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="<?= $headingId ?>">
                                    <button class="accordion-button <?= $gIndex === 1 ? '' : 'collapsed' ?>" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?= $collapseId ?>"
                                            aria-expanded="<?= $gIndex === 1 ? 'true' : 'false' ?>"
                                            aria-controls="<?= $collapseId ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <span><?= safe($groupLabel) ?></span>
                                            <span class="badge bg-light text-dark border"><?= count($items) ?></span>
                                        </div>
                                    </button>
                                </h2>

                                <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $gIndex === 1 ? 'show' : '' ?>" aria-labelledby="<?= $headingId ?>" data-bs-parent="#icrAssignAccordion">
                                    <div class="accordion-body">
                                        <?php foreach ($items as $icr): ?>
                                            <label class="icr-option d-block">
                                                <div class="form-check m-0">
                                                    <input
                                                        class="form-check-input icr-checkbox"
                                                        type="checkbox"
                                                        name="icr_ids[]"
                                                        value="<?= (int)$icr['icr_id'] ?>"
                                                        id="icr_<?= (int)$icr['icr_id'] ?>"
                                                    >
                                                    <div class="ms-4">
                                                        <div class="icr-option-title">
                                                            <?= safe($icr['icr_number']) ?> — <?= safe($icr['title']) ?>
                                                        </div>
                                                        <div class="icr-option-meta">
                                                            Frequency: <?= safe($icr['frequency']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="sticky-actions">
                        <div class="vms-card">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div class="text-muted">
                                    <span id="selectedIcrCount">0</span> selected
                                </div>

                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="addIcrSubmitBtn" disabled>
                                        Assign Selected ICRs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('assignIcrForm');
    if (!form) return;

    const checkboxes = Array.from(document.querySelectorAll('.icr-checkbox'));
    const countEl = document.getElementById('selectedIcrCount');
    const submitBtn = document.getElementById('addIcrSubmitBtn');
    const selectAllBtn = document.getElementById('selectAllIcrs');
    const clearAllBtn = document.getElementById('clearAllIcrs');

    function refreshCount() {
        const selected = checkboxes.filter(cb => cb.checked).length;
        if (countEl) countEl.textContent = String(selected);
        if (submitBtn) submitBtn.disabled = selected === 0;
    }

    checkboxes.forEach(cb => cb.addEventListener('change', refreshCount));

    selectAllBtn?.addEventListener('click', function () {
        checkboxes.forEach(cb => cb.checked = true);
        refreshCount();
    });

    clearAllBtn?.addEventListener('click', function () {
        checkboxes.forEach(cb => cb.checked = false);
        refreshCount();
    });

    form.addEventListener('submit', function () {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Assigning…';
        }
    });

    refreshCount();
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
