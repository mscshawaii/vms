<?php
declare(strict_types=1);

require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/checklist_functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

$vesselId = (int)($_GET['vessel_id'] ?? 0);
$runType = checklist_normalize_run_type($_GET['type'] ?? '');
$roleId = (int)($_SESSION['role_id'] ?? 0);
$companyId = (int)($_SESSION['company_id'] ?? 0);
$canAccessAllVessels = ($companyId === 1);
$returnTo = checklist_normalize_return_to($_GET['return_to'] ?? '', $vesselId);
$formStateKey = trim((string)($_GET['form_state_key'] ?? ''));

if ($vesselId <= 0) {
    http_response_code(400);
    exit('Invalid vessel ID.');
}

if ($runType === null) {
    http_response_code(400);
    exit('Invalid checklist type.');
}

$vessel = checklist_get_accessible_vessel($pdo, $vesselId, $canAccessAllVessels, $companyId);
if (!$vessel) {
    http_response_code(404);
    exit('Access denied or vessel not found.');
}

$template = checklist_get_template_by_type($pdo, $runType);
if (!$template) {
    http_response_code(404);
    exit('Checklist template not found.');
}

$coreItems = checklist_get_template_items($pdo, (int)$template['template_id']);
$suppressedCoreItemIds = checklist_get_suppressed_core_item_ids($pdo, $vesselId, (int)$template['template_id']);
$vesselItems = checklist_get_vessel_items($pdo, $vesselId, (int)$template['template_id']);
$items = [];

foreach ($coreItems as $item) {
    if (in_array((int)$item['template_item_id'], $suppressedCoreItemIds, true)) {
        continue;
    }
    $items[] = [
        'source' => 'core',
        'source_id' => (int)$item['template_item_id'],
        'item_label' => $item['item_label'] ?? '',
    ];
}

foreach ($vesselItems as $item) {
    $items[] = [
        'source' => 'vessel',
        'source_id' => (int)$item['vessel_checklist_item_id'],
        'item_label' => $item['item_label'] ?? '',
    ];
}

if (empty($items)) {
    http_response_code(404);
    exit('Checklist has no active items.');
}

$totalItems = count($items);
$title = $template['template_name'] . ' - ' . ($vessel['vesselName'] ?? 'Vessel');
$back_link = $returnTo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($template['template_name']) ?> - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .checklist-shell {
            background: #f4f7fb;
            min-height: 100vh;
        }
        .checklist-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }
        .checklist-item {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
            background: #fbfdff;
        }
        .checklist-item-label {
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1.05rem;
        }
        .checklist-choice-group {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .checklist-choice-group .form-check {
            margin: 0;
            padding: 12px 14px 12px 36px;
            border: 1px solid #dbe3ec;
            border-radius: 12px;
            background: #fff;
            min-width: 140px;
        }
        .stepper-toolbar,
        .stepper-nav,
        .stepper-feedback {
            display: none;
        }
        .stepper-progress-meta {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .stepper-step-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .stepper-step-status .badge {
            font-weight: 500;
        }
        .stepper-submit {
            min-width: 170px;
        }
        .stepper-nav .btn,
        .stepper-submit,
        .checklist-actions .btn {
            min-height: 48px;
        }
        body.stepper-enhanced .stepper-toolbar {
            display: block;
        }
        body.stepper-enhanced .stepper-step {
            display: none;
        }
        body.stepper-enhanced .stepper-step.is-active {
            display: block;
        }
        body.stepper-enhanced .stepper-nav {
            display: flex;
        }
        body.stepper-enhanced .stepper-submit {
            display: none;
        }
        body.stepper-enhanced .stepper-submit.is-final-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        body.stepper-enhanced .stepper-feedback.is-visible {
            display: block;
        }
        @media (max-width: 576px) {
            .checklist-choice-group {
                flex-direction: column;
            }
            .checklist-choice-group .form-check {
                min-width: 100%;
            }
            .stepper-nav,
            .checklist-actions {
                width: 100%;
            }
            .stepper-nav .btn,
            .stepper-submit {
                flex: 1 1 auto;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="checklist-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="checklist-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h1 class="h3 mb-2"><?= h($template['template_name']) ?></h1>
                        <p class="text-muted mb-0">
                            <?= h($vessel['vesselName'] ?? '') ?>
                            <?php if (!empty($vessel['vesselON'])): ?>
                                &middot; Official No. <?= h($vessel['vesselON']) ?>
                            <?php endif; ?>
                            <?php if (!empty($vessel['hailingPort'])): ?>
                                &middot; Hailing Port: <?= h($vessel['hailingPort']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="checklist_manage_items.php?vessel_id=<?= (int)$vesselId ?>&type=<?= h($runType) ?>" class="btn btn-outline-secondary">Manage Checklist Items</a>
                        <a href="<?= h($returnTo) ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>
            </div>

            <div class="checklist-card p-4">
                <form method="post" action="checklist_save.php" id="checklistWizardForm">
                    <input type="hidden" name="vessel_id" value="<?= (int)$vesselId ?>">
                    <input type="hidden" name="template_id" value="<?= (int)$template['template_id'] ?>">
                    <input type="hidden" name="run_type" value="<?= h($runType) ?>">
                    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                    <input type="hidden" name="form_state_key" value="<?= h($formStateKey) ?>">

                    <div class="stepper-toolbar mb-4" aria-live="polite">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="text-muted small text-uppercase">Checklist Progress</div>
                                <div class="fw-semibold" id="stepperProgressText">Item 1 of <?= (int)$totalItems ?></div>
                            </div>
                            <div class="stepper-progress-meta" id="stepperProgressMeta">Core Item</div>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div
                                class="progress-bar"
                                id="stepperProgressBar"
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="<?= (int)$totalItems ?>"
                                aria-valuenow="1"
                                style="width: <?= $totalItems > 0 ? (100 / $totalItems) : 0 ?>%;"
                            ></div>
                        </div>
                        <div class="alert alert-danger stepper-feedback mt-3 mb-0 py-2 px-3" id="stepperMessage" role="alert"></div>
                    </div>

                    <div class="d-grid gap-3 stepper-list">
                        <?php foreach ($items as $index => $item): ?>
                            <?php
                                $itemKey = ($item['source'] ?? 'core') . ':' . (int)($item['source_id'] ?? 0);
                                $itemType = ($item['source'] ?? 'core') === 'vessel' ? 'vessel' : 'core';
                                $itemTypeLabel = $itemType === 'vessel' ? 'Custom Item' : 'Core Item';
                            ?>
                            <div
                                class="checklist-item stepper-step"
                                data-step-index="<?= (int)$index ?>"
                                data-item-type="<?= h($itemType) ?>"
                            >
                                <div class="stepper-step-status">
                                    <span class="badge text-bg-light"><?= h($itemTypeLabel) ?></span>
                                    <span class="text-muted small">Item <?= (int)($index + 1) ?> of <?= (int)$totalItems ?></span>
                                </div>

                                <div class="checklist-item-label">
                                    <?= h($item['item_label'] ?? '') ?>
                                </div>

                                <div class="checklist-choice-group">
                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="responses[<?= h($itemKey) ?>]" value="complete" required>
                                        <span class="form-check-label">Complete</span>
                                    </label>

                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="responses[<?= h($itemKey) ?>]" value="not_complete" required>
                                        <span class="form-check-label">Not Complete</span>
                                    </label>

                                    <label class="form-check">
                                        <input class="form-check-input" type="radio" name="responses[<?= h($itemKey) ?>]" value="na" required>
                                        <span class="form-check-label">N/A</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between gap-2 mt-4 flex-wrap align-items-center checklist-actions">
                        <a href="<?= h($returnTo) ?>" class="btn btn-outline-secondary btn-lg">Cancel</a>

                        <div class="d-flex gap-2 flex-wrap ms-auto stepper-nav">
                            <button type="button" class="btn btn-outline-secondary btn-lg" id="stepperPrev">Previous</button>
                            <button type="button" class="btn btn-primary btn-lg" id="stepperNext">Next</button>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg stepper-submit" id="stepperSubmit">Save Checklist</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('checklistWizardForm');
    if (!form) {
        return;
    }

    var steps = Array.prototype.slice.call(form.querySelectorAll('.stepper-step'));
    if (!steps.length) {
        return;
    }

    document.body.classList.add('stepper-enhanced');

    var progressText = document.getElementById('stepperProgressText');
    var progressMeta = document.getElementById('stepperProgressMeta');
    var progressBar = document.getElementById('stepperProgressBar');
    var messageBox = document.getElementById('stepperMessage');
    var prevButton = document.getElementById('stepperPrev');
    var nextButton = document.getElementById('stepperNext');
    var submitButton = document.getElementById('stepperSubmit');
    var totalSteps = steps.length;
    var activeIndex = 0;

    Array.prototype.forEach.call(form.querySelectorAll('.stepper-step input[required]'), function (input) {
        input.removeAttribute('required');
    });

    function clearMessage() {
        if (!messageBox) {
            return;
        }
        messageBox.textContent = '';
        messageBox.classList.remove('is-visible');
    }

    function showMessage(text) {
        if (!messageBox) {
            return;
        }
        messageBox.textContent = text;
        messageBox.classList.add('is-visible');
    }

    function getCheckedInput(step) {
        return step ? step.querySelector('input[type="radio"]:checked') : null;
    }

    function focusStep(step) {
        if (!step) {
            return;
        }
        step.scrollIntoView({ behavior: 'auto', block: 'start' });
    }

    function updateProgress() {
        var currentStep = steps[activeIndex];
        var displayIndex = activeIndex + 1;
        var itemType = currentStep.getAttribute('data-item-type') === 'vessel' ? 'Custom Item' : 'Core Item';
        var width = (displayIndex / totalSteps) * 100;

        if (progressText) {
            progressText.textContent = 'Item ' + displayIndex + ' of ' + totalSteps;
        }

        if (progressMeta) {
            progressMeta.textContent = itemType;
        }

        if (progressBar) {
            progressBar.style.width = width + '%';
            progressBar.setAttribute('aria-valuenow', String(displayIndex));
        }

        if (prevButton) {
            prevButton.disabled = activeIndex === 0;
        }

        if (nextButton) {
            nextButton.style.display = activeIndex === totalSteps - 1 ? 'none' : '';
        }

        if (submitButton) {
            submitButton.classList.toggle('is-final-step', activeIndex === totalSteps - 1);
        }
    }

    function setActiveStep(index) {
        if (index < 0 || index >= totalSteps) {
            return;
        }

        activeIndex = index;

        steps.forEach(function (step, stepIndex) {
            step.classList.toggle('is-active', stepIndex === activeIndex);
        });

        clearMessage();
        updateProgress();
        focusStep(steps[activeIndex]);
    }

    function validateCurrentStep() {
        if (getCheckedInput(steps[activeIndex])) {
            clearMessage();
            return true;
        }

        showMessage('Select one response before continuing.');
        return false;
    }

    function findFirstIncompleteStepIndex() {
        for (var i = 0; i < steps.length; i += 1) {
            if (!getCheckedInput(steps[i])) {
                return i;
            }
        }
        return -1;
    }

    if (prevButton) {
        prevButton.addEventListener('click', function () {
            if (activeIndex > 0) {
                setActiveStep(activeIndex - 1);
            }
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (!validateCurrentStep()) {
                return;
            }

            if (activeIndex < totalSteps - 1) {
                setActiveStep(activeIndex + 1);
            }
        });
    }

    steps.forEach(function (step) {
        var radios = step.querySelectorAll('input[type="radio"]');
        Array.prototype.forEach.call(radios, function (radio) {
            radio.addEventListener('change', function () {
                if (steps[activeIndex] === step && getCheckedInput(step)) {
                    clearMessage();
                }
            });
        });
    });

    form.addEventListener('submit', function (event) {
        var firstIncomplete = findFirstIncompleteStepIndex();

        if (firstIncomplete !== -1) {
            event.preventDefault();
            setActiveStep(firstIncomplete);
            showMessage('Complete each checklist item before saving.');
        }
    });

    setActiveStep(0);
});
</script>
</body>
</html>
