<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id     = intval($_GET['vessel_id'] ?? 0);
$icr_id        = intval($_GET['icr_id'] ?? 0);
$vessel_icr_id = intval($_GET['vessel_icr_id'] ?? 0);

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}
if (!isAdmin()) {
    die("❌ Access denied. Only admin users can edit vessel-specific ICR steps.");
}

/* Vessel + ICR headers */
$stmt = $pdo->prepare("SELECT vesselName, vesselON, hailingPort FROM vessels WHERE vessel_id = ?");
$stmt->execute([$vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);
$vessel_name = $vessel['vesselName'] ?? 'Unknown Vessel';

$stmt = $pdo->prepare("SELECT icr_number, title FROM icrs WHERE icr_id = ?");
$stmt->execute([$icr_id]);
$icr_info = $stmt->fetch(PDO::FETCH_ASSOC);

if ($vessel_icr_id <= 0) {
    $stmt = $pdo->prepare("
        SELECT vessel_icr_id
        FROM vessel_icrs
        WHERE vessel_id = ? AND icr_id = ?
        LIMIT 1
    ");
    $stmt->execute([$vessel_id, $icr_id]);
    $vessel_icr_id = (int)$stmt->fetchColumn();
}

if (!$vessel_icr_id) {
    die("❌ Vessel-specific ICR assignment not found.");
}

/* Fetch existing vessel steps */
$stmt = $pdo->prepare("
    SELECT step_id, step_number, step_description
    FROM vessel_icr_steps
    WHERE vessel_icr_id = ?
    ORDER BY step_number
");
$stmt->execute([$vessel_icr_id]);
$vessel_steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Detect if vessel substeps table exists */
$has_vessel_subs = false;
try {
    $pdo->query("SELECT 1 FROM vessel_icr_substeps LIMIT 1");
    $has_vessel_subs = true;
} catch (Throwable $e) {
    $has_vessel_subs = false;
}

/* 1) Load vessel substeps (if table exists) */
$substeps_by_vessel_step = [];
if ($has_vessel_subs && $vessel_steps) {
    $ids = array_map(fn($s) => (int)$s['step_id'], $vessel_steps);
    if (count($ids) > 0) {
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare("
            SELECT substep_id, vessel_step_id, substep_code, description
            FROM vessel_icr_substeps
            WHERE vessel_step_id IN ($in)
            ORDER BY substep_code
        ");
        $q->execute($ids);
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $vsid = (int)$r['vessel_step_id'];
            $substeps_by_vessel_step[$vsid][] = $r;
        }
    }
}

/* 2) Build template step_number → icr_steps.step_id map (for fallback) */
$tpl = $pdo->prepare("
    SELECT step_id, step_number
    FROM icr_steps
    WHERE icr_id = ?
    ORDER BY step_number
");
$tpl->execute([$icr_id]);
$tplMap = [];
while ($r = $tpl->fetch(PDO::FETCH_ASSOC)) {
    $tplMap[(int)$r['step_number']] = (int)$r['step_id'];
}

/* Prepare template substeps loader */
$getTplSubs = $pdo->prepare("
    SELECT substep_id, substep_code, description
    FROM icr_substeps
    WHERE step_id = ?
    ORDER BY substep_code
");

/* 3) Fill in fallback substeps per vessel step that has none */
foreach ($vessel_steps as $s) {
    $vsid = (int)$s['step_id'];
    $num  = (int)$s['step_number'];

    $hasAnyVesselSubs = !empty($substeps_by_vessel_step[$vsid]);

    if (!$hasAnyVesselSubs) {
        if (isset($tplMap[$num])) {
            $getTplSubs->execute([$tplMap[$num]]);
            $tplSubs = $getTplSubs->fetchAll(PDO::FETCH_ASSOC);
            $substeps_by_vessel_step[$vsid] = $tplSubs ?: [];
        } else {
            $substeps_by_vessel_step[$vsid] = [];
        }
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit ICR Steps - <?= h($vessel_name) ?> - <?= h($icr_info['icr_number'] ?? '') ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .edit-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .edit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .edit-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .edit-subtitle {
            color: #6b7280;
            margin: 0;
        }

        .edit-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .edit-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .step-card {
            background: #fff;
            border: 1px solid #dbe4ee;
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            padding: 14px;
        }

        .step-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            border-radius: 999px;
            background: #e7f1ff;
            color: #0b5ed7;
            font-weight: 700;
            font-family: ui-monospace, Menlo, Consolas, monospace;
        }

        .step-actions,
        .substep-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .substep-card {
            background: #f8f9fa;
            border: 1px solid #e3e8ef;
            border-left: 4px solid #dee2e6;
            border-radius: 14px;
            padding: 12px;
        }

        .mono {
            font-family: ui-monospace, Menlo, Consolas, monospace;
        }

        .badge-tpl {
            background: #e7f1ff;
            color: #0b5ed7;
            border: 1px solid #cfe2ff;
        }

        .sticky-edit-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(244,247,251,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dbe4ee;
            padding-top: 12px;
            margin-top: 16px;
        }

        .muted {
            color: #6c757d;
        }

        @media (max-width: 575.98px) {
            .step-actions .btn,
            .substep-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Edit Vessel ICR';
$back_link = 'vessel_icrs.php?vessel_id=' . (int)$vessel_id;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="edit-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="alert alert-warning">
                ⚠️ Vessel-specific procedure. Changes here affect only <strong><?= h($vessel_name) ?></strong>.
            </div>

            <div class="vms-card">
                <div class="edit-header">
                    <div>
                        <h1 class="edit-title">Edit ICR Steps</h1>
                        <p class="edit-subtitle">
                            <?= h($icr_info['icr_number'] ?? '') ?> - <?= h($icr_info['title'] ?? '') ?>
                            <br>
                            <?= h($vessel_name) ?>
                            <?php if (!empty($vessel['vesselON'])): ?>
                                · Official No. <?= h($vessel['vesselON']) ?>
                            <?php endif; ?>
                            <?php if (!empty($vessel['hailingPort'])): ?>
                                · Hailing Port: <?= h($vessel['hailingPort']) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="edit-actions">
                        <button type="button" class="btn btn-outline-danger" id="toggleBtn">Enable Editing</button>
                        <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>

                <p class="mb-0 text-muted">
                    Edit vessel-specific steps and sub-steps. Template-sourced sub-steps shown below can be customized for this vessel once editing is enabled.
                </p>
            </div>

            <form method="post" action="submit_vessel_icr_steps.php" id="editForm">
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                <input type="hidden" name="icr_id" value="<?= (int)$icr_id ?>">
                <input type="hidden" name="vessel_icr_id" value="<?= (int)$vessel_icr_id ?>">

                <div id="steps" class="vstack gap-3">
                    <?php
                    $i = 0;
                    foreach ($vessel_steps as $s):
                        $vsid = (int)$s['step_id'];
                        $subs = $substeps_by_vessel_step[$vsid] ?? [];
                    ?>
                    <div class="step-card" data-existing="1">
                        <div class="step-head">
                            <div class="d-flex align-items-center gap-3">
                                <span class="step-badge"><?= (int)$s['step_number'] ?></span>
                                <div>
                                    <div class="fw-semibold">Step <?= (int)$s['step_number'] ?></div>
                                    <div class="small text-muted">Vessel-specific step</div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSubStep(this)" disabled>Add Sub-step</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(this)" disabled>Remove Step</button>
                            </div>
                        </div>

                        <input type="hidden" name="steps[<?= $i ?>][id]" value="<?= $vsid ?>">
                        <input type="hidden" name="steps[<?= $i ?>][number]" value="<?= (int)$s['step_number'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Step Description</label>
                            <input
                                type="text"
                                class="form-control"
                                name="steps[<?= $i ?>][description]"
                                value="<?= h($s['step_description']) ?>"
                                readonly
                                required
                            >
                        </div>

                        <div class="mt-2">
                            <div class="fw-semibold mb-2">Sub-steps</div>
                            <div class="substeps vstack gap-2">
                                <?php
                                $j = 0;
                                foreach ($subs as $sub):
                                    $is_vessel_row = isset($sub['vessel_step_id']);
                                    $sid  = $is_vessel_row ? (int)$sub['substep_id'] : 0;
                                    $code = h($sub['substep_code']);
                                    $desc = h($sub['description']);
                                ?>
                                <div class="substep-card" data-existing="<?= $is_vessel_row ? '1':'0' ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
                                        <div>
                                            <strong><span class="code mono"><?= (int)$s['step_number'] . $code ?></span></strong>
                                            <?php if (!$is_vessel_row): ?>
                                                <span class="badge badge-tpl ms-2">from template</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="substep-actions">
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSubStep(this)" disabled>Remove</button>
                                        </div>
                                    </div>

                                    <input type="hidden" name="steps[<?= $i ?>][substeps][<?= $j ?>][id]" value="<?= $sid ?: 'new' ?>">
                                    <input type="hidden" name="steps[<?= $i ?>][substeps][<?= $j ?>][code]" value="<?= $code ?>">

                                    <div>
                                        <label class="form-label">Sub-step Description</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="steps[<?= $i ?>][substeps][<?= $j ?>][description]"
                                            value="<?= $desc ?>"
                                            readonly
                                            required
                                        >
                                    </div>
                                </div>
                                <?php $j++; endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>

                <div class="sticky-edit-actions">
                    <div class="vms-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div class="muted">
                                Auto-numbering: steps <span class="mono">1,2,3…</span>, sub-steps <span class="mono">A,B,C…</span>. Removing items re-numbers everything.
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary" onclick="addStep()" id="addStepBtn" disabled>Add Step</button>
                                <button type="submit" class="btn btn-primary" id="saveBtn" disabled>Save Changes</button>
                                <a href="vessel_icrs.php?vessel_id=<?= (int)$vessel_id ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
function nextLetter(idx) {
    return String.fromCharCode(65 + idx);
}

function toggleEdit(enabled = true) {
    document.querySelectorAll('input[readonly], textarea[readonly]').forEach(el => {
        el.removeAttribute('readonly');
    });

    document.querySelectorAll('button[disabled]').forEach(b => {
        b.removeAttribute('disabled');
    });

    const toggleBtn = document.getElementById('toggleBtn');
    if (toggleBtn) {
        toggleBtn.textContent = 'Editing Enabled';
        toggleBtn.classList.remove('btn-outline-danger');
        toggleBtn.classList.add('btn-success');
        toggleBtn.disabled = true;
    }
}

document.getElementById('toggleBtn').addEventListener('click', () => toggleEdit(true));

function addStep() {
    const container = document.getElementById('steps');
    const idx = container.querySelectorAll('.step-card').length;
    const stepNumber = idx + 1;

    const card = document.createElement('div');
    card.className = 'step-card';
    card.setAttribute('data-existing', '0');

    card.innerHTML = `
        <div class="step-head">
            <div class="d-flex align-items-center gap-3">
                <span class="step-badge">${stepNumber}</span>
                <div>
                    <div class="step-title fw-semibold">Step ${stepNumber}</div>
                    <div class="small text-muted">New vessel-specific step</div>
                </div>
            </div>

            <div class="step-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSubStep(this)">Add Sub-step</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(this)">Remove Step</button>
            </div>
        </div>

        <input type="hidden" class="step-id-input" name="steps[${idx}][id]" value="new">
        <input type="hidden" class="step-number-input" name="steps[${idx}][number]" value="${stepNumber}">

        <div class="mb-3">
            <label class="form-label">Step Description</label>
            <input type="text" class="form-control step-desc-input" name="steps[${idx}][description]" required>
        </div>

        <div class="mt-2">
            <div class="fw-semibold mb-2">Sub-steps</div>
            <div class="substeps vstack gap-2"></div>
        </div>
    `;

    container.appendChild(card);
    renumberAll();
}

function addSubStep(btn) {
    const stepCard = btn.closest('.step-card');
    if (!stepCard) return;

    const stepCards = Array.from(document.querySelectorAll('#steps .step-card'));
    const stepIndex = stepCards.indexOf(stepCard);
    const stepNumber = stepIndex + 1;

    const subs = stepCard.querySelector('.substeps');
    const subIndex = subs.querySelectorAll('.substep-card').length;
    const code = nextLetter(subIndex);

    const sub = document.createElement('div');
    sub.className = 'substep-card';
    sub.setAttribute('data-existing', '0');

    sub.innerHTML = `
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2 flex-wrap">
            <div>
                <strong><span class="code mono">${stepNumber}${code}</span></strong>
            </div>
            <div class="substep-actions">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSubStep(this)">Remove</button>
            </div>
        </div>

        <input type="hidden" class="substep-id-input" name="steps[${stepIndex}][substeps][${subIndex}][id]" value="new">
        <input type="hidden" class="substep-code-input" name="steps[${stepIndex}][substeps][${subIndex}][code]" value="${code}">

        <div>
            <label class="form-label">Sub-step Description</label>
            <input type="text" class="form-control substep-desc-input" name="steps[${stepIndex}][substeps][${subIndex}][description]" required>
        </div>
    `;

    subs.appendChild(sub);
    renumberAll();
}

function removeStep(btn) {
    const card = btn.closest('.step-card');
    if (card) {
        card.remove();
        renumberAll();
    }
}

function removeSubStep(btn) {
    const sub = btn.closest('.substep-card');
    if (sub) {
        sub.remove();
        renumberAll();
    }
}

function renumberAll() {
    const stepCards = Array.from(document.querySelectorAll('#steps .step-card'));

    stepCards.forEach((card, i) => {
        const stepNumber = i + 1;

        const badge = card.querySelector('.step-badge');
        if (badge) badge.textContent = String(stepNumber);

        const title = card.querySelector('.step-title');
        if (title) title.textContent = `Step ${stepNumber}`;

        const stepIdInput = card.querySelector('.step-id-input');
        const stepNumInput = card.querySelector('.step-number-input');
        const stepDescInput = card.querySelector('.step-desc-input');

        if (stepIdInput) {
            stepIdInput.name = `steps[${i}][id]`;
        }

        if (stepNumInput) {
            stepNumInput.name = `steps[${i}][number]`;
            stepNumInput.value = String(stepNumber);
        }

        if (stepDescInput) {
            stepDescInput.name = `steps[${i}][description]`;
        }

        const subCards = Array.from(card.querySelectorAll('.substeps .substep-card'));

        subCards.forEach((sub, j) => {
            const code = nextLetter(j);

            const codeSpan = sub.querySelector('.code');
            if (codeSpan) codeSpan.textContent = `${stepNumber}${code}`;

            const subIdInput = sub.querySelector('.substep-id-input');
            const subCodeInput = sub.querySelector('.substep-code-input');
            const subDescInput = sub.querySelector('.substep-desc-input');

            if (subIdInput) {
                subIdInput.name = `steps[${i}][substeps][${j}][id]`;
            }

            if (subCodeInput) {
                subCodeInput.name = `steps[${i}][substeps][${j}][code]`;
                subCodeInput.value = code;
            }

            if (subDescInput) {
                subDescInput.name = `steps[${i}][substeps][${j}][description]`;
            }
        });
    });
}
</script>
</body>
</html>