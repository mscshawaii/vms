<?php
require 'db_connect.php';
require 'session_check.php';

$icr_id = intval($_GET['id'] ?? 0);
if ($icr_id <= 0) {
    http_response_code(400);
    die("❌ Invalid ICR id.");
}

/* Fetch ICR */
$stmt = $pdo->prepare("
    SELECT icr_id, icr_number, title, reference_text, frequency
    FROM icrs
    WHERE icr_id = ?
");
$stmt->execute([$icr_id]);
$icr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$icr) {
    http_response_code(404);
    die("❌ ICR not found.");
}

/* Fetch steps */
$stepsStmt = $pdo->prepare("
    SELECT step_id, step_number, step_description, deficiency_action
    FROM icr_steps
    WHERE icr_id = ?
    ORDER BY step_number ASC
");
$stepsStmt->execute([$icr_id]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch substeps for all steps */
$subsByStep = [];
if ($steps) {
    $subStmt = $pdo->prepare("
        SELECT substep_id, step_id, substep_code, description, deficiency_action
        FROM icr_substeps
        WHERE step_id = ?
        ORDER BY substep_code ASC
    ");
    foreach ($steps as $s) {
        $subStmt->execute([(int)$s['step_id']]);
        $subsByStep[(int)$s['step_id']] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* Fetch linked regulations for all steps */
$regsByStep = [];
$regsBySubstep = [];

if ($steps) {
    $regStmt = $pdo->prepare("
        SELECT
            itsr.icr_template_step_id,
            itsr.icr_template_step_regulation_id AS link_id,
            itsr.reference_type,
            itsr.display_order,
            itsr.note_override,
            itsr.regulation_paragraph_id,
            rs.regulation_section_id,
            rs.citation,
            rs.heading,
            rp.paragraph_path,
            rp.paragraph_label,
            rp.text_plain AS paragraph_text
        FROM icr_template_step_regulations itsr
        JOIN regulation_sections rs
            ON itsr.regulation_section_id = rs.regulation_section_id
        LEFT JOIN regulation_paragraphs rp
            ON itsr.regulation_paragraph_id = rp.regulation_paragraph_id
        WHERE itsr.icr_template_step_id = ?
        ORDER BY itsr.display_order ASC, rs.citation ASC, rp.sort_key ASC
    ");

    $subRegStmt = $pdo->prepare("
        SELECT
            itsr.icr_template_substep_id,
            itsr.icr_template_substep_regulation_id AS link_id,
            itsr.reference_type,
            itsr.display_order,
            itsr.note_override,
            itsr.regulation_paragraph_id,
            rs.regulation_section_id,
            rs.citation,
            rs.heading,
            rp.paragraph_path,
            rp.paragraph_label,
            rp.text_plain AS paragraph_text
        FROM icr_template_substep_regulations itsr
        JOIN regulation_sections rs
            ON itsr.regulation_section_id = rs.regulation_section_id
        LEFT JOIN regulation_paragraphs rp
            ON itsr.regulation_paragraph_id = rp.regulation_paragraph_id
        WHERE itsr.icr_template_substep_id = ?
        ORDER BY itsr.display_order ASC, rs.citation ASC, rp.sort_key ASC
    ");

    foreach ($steps as $s) {
        $stepId = (int)$s['step_id'];

        $regStmt->execute([$stepId]);
        $regsByStep[$stepId] = $regStmt->fetchAll(PDO::FETCH_ASSOC);

        $subs = $subsByStep[$stepId] ?? [];
        foreach ($subs as $sub) {
            $subId = (int)$sub['substep_id'];
            $subRegStmt->execute([$subId]);
            $regsBySubstep[$subId] = $subRegStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit ICR Template - <?= h($icr['icr_number']) ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
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
            line-height: 1.15;
        }

        .edit-subtitle {
            margin: 0;
            color: var(--vms-muted, #6b7280);
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
        .substep-actions,
        .reg-chip-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .step-actions .btn,
        .substep-actions .btn,
        .reg-chip-actions .btn {
            border-radius: 12px;
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

        .reg-chip {
            background: #f8fbff;
            border: 1px solid #cfe0ff;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .reg-chip-title {
            font-weight: 700;
            margin-bottom: 3px;
        }

        .reg-chip-meta {
            font-size: 0.86rem;
            color: #6b7280;
        }

        .reg-results-item {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
            background: #fff;
        }

        .reg-preview {
            white-space: pre-wrap;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 10px;
            font-size: 0.9rem;
            max-height: 260px;
            overflow-y: auto;
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

        .search-help {
            font-size: 0.88rem;
            color: #6b7280;
        }

        .reg-filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        @media (min-width: 768px) {
            .reg-filter-grid {
                grid-template-columns: 2fr 1fr 1fr 1fr;
                align-items: end;
            }
        }

        @media (max-width: 575.98px) {
            .step-actions .btn,
            .substep-actions .btn,
            .reg-chip-actions .btn,
            .edit-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Edit ICR Template';
$back_link = 'icr_templates.php';
include 'top_nav.php';
?>

<div class="edit-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="edit-header">
                    <div>
                        <h1 class="edit-title">Edit ICR Template</h1>
                        <p class="edit-subtitle">
                            <?= h($icr['icr_number']) ?> - <?= h($icr['title']) ?>
                            <br>
                            Template editing for steps, sub-steps, and linked CFR references.
                        </p>
                    </div>

                    <div class="edit-actions">
                        <a href="icr_templates.php" class="btn btn-outline-secondary">← Back</a>
                    </div>
                </div>
            </div>

            <form method="post" action="update_icr.php" id="icrForm">
                <input type="hidden" name="icr_id" value="<?= (int)$icr['icr_id'] ?>">

                <div class="vms-card mb-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">ICR Number</label>
                            <input type="text" name="icr_number" class="form-control" value="<?= h($icr['icr_number']) ?>" required>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" value="<?= h($icr['title']) ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Frequency</label>
                            <select name="frequency" class="form-select" required>
                                <?php foreach (['Weekly','Monthly','Quarterly','Annually'] as $freq): ?>
                                    <option value="<?= h($freq) ?>" <?= $icr['frequency'] === $freq ? 'selected' : '' ?>>
                                        <?= h($freq) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Reference Text</label>
                            <textarea name="reference_text" class="form-control" rows="3"><?= h($icr['reference_text'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="vms-card mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Steps</h5>
                        <button type="button" class="btn btn-outline-secondary" onclick="addStepRow()">➕ Add Step</button>
                    </div>
                </div>

                <div id="steps" class="vstack gap-3">
                    <?php
                    $i = 0;
                    foreach ($steps as $s):
                        $sid = (int)$s['step_id'];
                        $subs = $subsByStep[$sid] ?? [];
                        $regs = $regsByStep[$sid] ?? [];
                    ?>
                    <div class="step-card">
                        <div class="step-head">
                            <div class="d-flex align-items-center gap-3">
                                <span class="step-badge"><?= (int)$s['step_number'] ?></span>
                                <div>
                                    <div class="fw-semibold">Step <?= (int)$s['step_number'] ?></div>
                                    <div class="small text-muted">Template step</div>
                                </div>
                            </div>

                            <div class="step-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSubStep(this)">➕ Add Sub-step</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRegulationPicker(<?= $sid ?>)">📘 Link Regulation</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(this)">🗑 Remove Step</button>
                            </div>
                        </div>

                        <input type="hidden" class="step-id-input" name="steps[<?= $i ?>][id]" value="<?= $sid ?>">
                        <input type="hidden" class="step-number-input" name="steps[<?= $i ?>][number]" value="<?= (int)$s['step_number'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Step Description</label>
                            <textarea class="form-control step-desc-input" name="steps[<?= $i ?>][description]" rows="2" required><?= h($s['step_description']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Deficiency Action (optional)</label>
                            <textarea class="form-control step-def-input" name="steps[<?= $i ?>][deficiency_action]" rows="2"><?= h($s['deficiency_action'] ?? '') ?></textarea>
                        </div>

                    <div class="mt-2">
                        <div class="fw-semibold mb-2">Linked Regulations</div>
                        <div class="linked-regs">
                            <?php if ($regs): ?>
                                <?php foreach ($regs as $reg): ?>
                                    <div class="reg-chip">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                            <div>
                                                <div class="reg-chip-title">
                                                    <?= h($reg['citation']) ?>
                                                    <?php if (!empty($reg['paragraph_path'])): ?>
                                                        <span class="badge bg-info text-dark ms-2">
                                                            Paragraph <?= h($reg['paragraph_path']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="text-muted mb-1"><?= h($reg['heading'] ?? '') ?></div>

                                                <?php if (!empty($reg['paragraph_text'])): ?>
                                                    <div class="small text-secondary mb-1">
                                                        <?= h($reg['paragraph_text']) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="reg-chip-meta">
                                                    Type: <?= h(ucfirst($reg['reference_type'] ?? 'requirement')) ?>
                                                    <?php if (!empty($reg['display_order'])): ?>
                                                        · Order: <?= (int)$reg['display_order'] ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="reg-chip-actions">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="unlinkRegulation(<?= (int)$reg['link_id'] ?>)">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-muted">No linked regulations yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                        <div class="mt-3">
                            <div class="fw-semibold mb-2">Sub-steps</div>
                            <div class="substeps vstack gap-2">
                                <?php $j = 0; foreach ($subs as $sub): ?>
                                    <?php $subRegs = $regsBySubstep[(int)$sub['substep_id']] ?? []; ?>
                                      <div class="substep-card">
                                          <div class="substep-actions">
                                              <div class="substep-code"><?= h($sub['substep_code']) ?></div>

                                              <div class="d-flex gap-2 flex-wrap">
                                                  <button type="button"
                                                          class="btn btn-sm btn-outline-primary"
                                                          onclick="openSubstepRegulationPicker(<?= (int)$sub['substep_id'] ?>)">
                                                      📘 Link Regulation
                                                  </button>

                                                  <button type="button"
                                                          class="btn btn-sm btn-outline-danger"
                                                          onclick="removeSubStep(this)">
                                                      🗑 Remove
                                                  </button>
                                              </div>
                                          </div>

                                          <input type="hidden" class="substep-id-input" name="steps[<?= $i ?>][substeps][<?= $j ?>][id]" value="<?= (int)$sub['substep_id'] ?>">
                                          <input type="hidden" class="substep-code-input" name="steps[<?= $i ?>][substeps][<?= $j ?>][code]" value="<?= h($sub['substep_code']) ?>">

                                          <div class="row g-3">
                                              <div class="col-md-7">
                                                  <label class="form-label">Sub-step Description</label>
                                                  <textarea class="form-control substep-desc-input" name="steps[<?= $i ?>][substeps][<?= $j ?>][description]" rows="2" required><?= h($sub['description']) ?></textarea>
                                              </div>

                                              <div>
                                                  <label class="form-label">Deficiency Action (optional)</label>
                                                  <textarea class="form-control substep-def-input" name="steps[<?= $i ?>][substeps][<?= $j ?>][deficiency_action]" rows="2"><?= h($sub['deficiency_action'] ?? '') ?></textarea>
                                              </div>
                                          </div>

                                          <div class="mt-3">
                                              <div class="fw-semibold mb-2">Linked Regulations</div>

                                              <?php if (!empty($subRegs)): ?>
                                                  <?php foreach ($subRegs as $reg): ?>
                                                      <div class="reg-chip">
                                                          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                                              <div>
                                                                  <div class="reg-chip-title">
                                                                      <?= h($reg['citation']) ?>
                                                                      <?php if (!empty($reg['paragraph_path'])): ?>
                                                                          <span class="badge bg-info text-dark ms-1">
                                                                              Paragraph <?= h($reg['paragraph_path']) ?>
                                                                          </span>
                                                                      <?php endif; ?>
                                                                  </div>

                                                                  <div class="text-muted mb-1"><?= h($reg['heading']) ?></div>

                                                                  <?php if (!empty($reg['paragraph_text'])): ?>
                                                                      <div class="small text-secondary mb-1">
                                                                          <?= h($reg['paragraph_text']) ?>
                                                                      </div>
                                                                  <?php endif; ?>

                                                                  <div class="reg-chip-meta">
                                                                      Type: <?= h(ucfirst($reg['reference_type'] ?? 'requirement')) ?>
                                                                      <?php if (!empty($reg['display_order'])): ?>
                                                                          · Order: <?= (int)$reg['display_order'] ?>
                                                                      <?php endif; ?>
                                                                  </div>
                                                              </div>

                                                              <div class="reg-chip-actions">
                                                                  <button type="button"
                                                                          class="btn btn-sm btn-outline-danger"
                                                                          onclick="unlinkSubstepRegulation(<?= (int)$reg['link_id'] ?>)">
                                                                      Remove
                                                                  </button>
                                                              </div>
                                                          </div>
                                                      </div>
                                                  <?php endforeach; ?>
                                              <?php else: ?>
                                                  <div class="text-muted">No linked regulations yet.</div>
                                              <?php endif; ?>
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
                                Auto-numbering: steps <span class="mono">1,2,3…</span>, sub-steps <span class="mono">A,B,C…</span>.
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                                <a href="icr_templates.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="regulationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Link Regulation to Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="reg-filter-grid">
                    <div>
                        <label class="form-label">Search regulations</label>
                        <input type="text"
                               id="regSearch"
                               class="form-control"
                               list="regSearchSuggestions"
                               placeholder="Try 176.810, fire protection, extinguisher, EPIRB, etc."
                               oninput="searchRegulations()">
                        <datalist id="regSearchSuggestions">
                            <option value="EPIRB">
                            <option value="fire protection">
                            <option value="lifesaving">
                            <option value="manning">
                            <option value="drills">
                            <option value="176">
                            <option value="181">
                            <option value="185">
                            <option value="46 CFR 176.810">
                        </datalist>
                        <div class="search-help mt-1">Type at least 2 characters, or use the filters below.</div>
                    </div>

                    <div>
                        <label class="form-label">Title</label>
                        <select id="regTitle" class="form-select" onchange="onTitleChange(); searchRegulations();">
                            <option value="46" selected>46 CFR</option>
                            <option value="33">33 CFR</option>
                            <option value="47">47 CFR</option>
                            <option value="49">49 CFR</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Subchapter</label>
                        <select id="regSubchapter" class="form-select" onchange="searchRegulations()">
                            <option value="">All</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Part</label>
                        <input type="text"
                               id="regPart"
                               class="form-control"
                               placeholder="176"
                               oninput="searchRegulations()">
                    </div>
                </div>

                <div id="regResults" class="mt-3">
                    <div class="text-muted">Type to search regulations...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentStepId = null;
let currentSubstepId = null;
let regulationModalInstance = null;

const subchapterOptions = {
    "46": [
        { value: "", text: "All" },
        { value: "A", text: "A - General" },
        { value: "B", text: "B - Merchant Marine Officers and Seamen" },
        { value: "C", text: "C - Uninspected Vessels" },
        { value: "D", text: "D - Tank Vessels" },
        { value: "E", text: "E - Load Lines" },
        { value: "F", text: "F - Marine Engineering" },
        { value: "H", text: "H - Passenger Vessels" },
        { value: "I", text: "I - Cargo and Miscellaneous Vessels" },
        { value: "J", text: "J - Electrical Engineering" },
        { value: "K", text: "K - Small Passenger Vessels Carrying More Than 150 Passengers or With Overnight Accommodations" },
        { value: "L", text: "L - Offshore Supply Vessels" },
        { value: "M", text: "M - Towing Vessels" },
        { value: "S", text: "S - Subdivision and Stability" },
        { value: "T", text: "T - Small Passenger Vessels (Under 100 Gross Tons)" },
        { value: "U", text: "U - Artificial Islands and Fixed Structures on the OCS" },
        { value: "W", text: "W - Lifesaving Appliances and Arrangements" }
    ],
    "33": [
        { value: "", text: "All" },
        { value: "A", text: "A - General" },
        { value: "B", text: "B - Navigable Waters" },
        { value: "C", text: "C - Inland Waterways Navigation Regulations" },
        { value: "D", text: "D - International Navigation Rules" },
        { value: "E", text: "E - Inland Navigation Rules" },
        { value: "F", text: "F - Vessel Operating Regulations" }
    ],
    "47": [
        { value: "", text: "All" }
    ],
    "49": [
        { value: "", text: "All" }
    ]
};

function nextLetter(i) {
    return String.fromCharCode(65 + i);
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m];
    });
}

function onTitleChange() {
    const title = document.getElementById('regTitle').value;
    const subchapter = document.getElementById('regSubchapter');
    const options = subchapterOptions[title] || [{ value: "", text: "All" }];

    subchapter.innerHTML = '';
    options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.value;
        option.textContent = opt.text;
        subchapter.appendChild(option);
    });
}

function openRegulationPicker(stepId) {
    if (!stepId || stepId === 'new') {
        alert('Please save the ICR first before linking regulations to newly added steps.');
        return;
    }

    currentStepId = stepId;
    currentSubstepId = null;

    document.getElementById('regSearch').value = '';
    document.getElementById('regSubchapter').value = '';
    document.getElementById('regPart').value = '';
    document.getElementById('regResults').innerHTML = '<div class="text-muted">Type to search regulations...</div>';
    document.querySelector('#regulationModal .modal-title').textContent = 'Link Regulation to Step';

    const modal = new bootstrap.Modal(document.getElementById('regulationModal'));
    modal.show();
}

function openSubstepRegulationPicker(substepId) {
    if (!substepId || substepId === 'new') {
        alert('Please save the ICR first before linking regulations to newly added sub-steps.');
        return;
    }

    currentStepId = null;
    currentSubstepId = substepId;

    document.getElementById('regSearch').value = '';
    document.getElementById('regSubchapter').value = '';
    document.getElementById('regPart').value = '';
    document.getElementById('regResults').innerHTML = '<div class="text-muted">Type to search regulations...</div>';
    document.querySelector('#regulationModal .modal-title').textContent = 'Link Regulation to Sub-step';

    const modal = new bootstrap.Modal(document.getElementById('regulationModal'));
    modal.show();
}

async function searchRegulations() {
    const q = document.getElementById('regSearch').value.trim();
    const title = document.getElementById('regTitle').value.trim();
    const subchapter = document.getElementById('regSubchapter').value.trim();
    const part = document.getElementById('regPart').value.trim();
    const resultsBox = document.getElementById('regResults');

    if (q.length < 2 && subchapter === '' && part === '') {
        resultsBox.innerHTML = '<div class="text-muted">Type at least 2 characters or choose a filter.</div>';
        return;
    }

    resultsBox.innerHTML = '<div class="text-muted">Searching...</div>';

    try {
        const params = new URLSearchParams();
        params.set('q', q);
        params.set('title', title);
        if (subchapter !== '') params.set('subchapter', subchapter);
        if (part !== '') params.set('part', part);

        const response = await fetch('search_regulations.php?' + params.toString());
        const data = await response.json();

        if (!Array.isArray(data) || data.length === 0) {
            resultsBox.innerHTML = '<div class="text-muted">No matching regulations found.</div>';
            return;
        }

        let html = '';
        data.forEach(row => {
            html += `
                <div class="reg-results-item">
                    <div><strong>${escapeHtml(row.citation)}</strong></div>
                    <div class="text-muted mb-1">${escapeHtml(row.heading || '')}</div>
                    <div class="small text-secondary mb-2">
                        Title: ${escapeHtml(row.title_number || title)} |
                        Subchapter: ${escapeHtml(row.subchapter_code || '-')} |
                        Part: ${escapeHtml(row.part_number || '-')}
                    </div>

                    <div class="reg-preview mb-2">${escapeHtml(row.text_plain || '')}</div>

                    <div class="d-flex gap-2 flex-wrap mb-2">
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick="linkRegulation(${row.regulation_section_id}, null)">
                            Link Full Section
                        </button>
                    </div>
            `;

            if (Array.isArray(row.paragraphs) && row.paragraphs.length > 0) {
                html += `<div class="mt-2"><strong class="small">Paragraph Options</strong></div>`;

                row.paragraphs.forEach(p => {
                    const paragraphLabel = p.paragraph_path || p.paragraph_label || '';
                    html += `
                        <div class="border rounded p-2 mb-2 bg-light">
                            <div class="small fw-semibold mb-1">
                                Paragraph ${escapeHtml(paragraphLabel)}
                            </div>
                            <div class="small text-secondary mb-2">
                                ${escapeHtml(p.text_plain || '')}
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="linkRegulation(${row.regulation_section_id}, ${p.regulation_paragraph_id})">
                                Link This Paragraph
                            </button>
                        </div>
                    `;
                });
            }

            html += `</div>`;
        });

        resultsBox.innerHTML = html;
    } catch (err) {
        resultsBox.innerHTML = '<div class="text-danger">Search failed.</div>';
    }
}

async function linkRegulation(regulationId, paragraphId = null) {
    if ((!currentStepId || currentStepId === 'new') && (!currentSubstepId || currentSubstepId === 'new')) {
        alert('Please save the ICR first before linking regulations.');
        return;
    }

    const endpoint = currentSubstepId ? 'link_regulation_to_substep.php' : 'link_regulation_to_step.php';
    const payload = currentSubstepId
        ? {
            substep_id: currentSubstepId,
            regulation_id: regulationId,
            paragraph_id: paragraphId
        }
        : {
            step_id: currentStepId,
            regulation_id: regulationId,
            paragraph_id: paragraphId
        };

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Failed to link regulation.');
            return;
        }

        window.location.reload();
    } catch (err) {
        alert('Failed to link regulation.');
    }
}

async function unlinkRegulation(linkId) {
    if (!confirm('Remove this linked regulation?')) return;

    try {
        const response = await fetch('unlink_regulation_from_step.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ link_id: linkId })
        });

        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Failed to remove regulation.');
            return;
        }

        window.location.reload();
    } catch (err) {
        alert('Failed to remove regulation.');
    }
}

async function unlinkSubstepRegulation(linkId) {
    if (!confirm('Remove this linked regulation?')) return;

    try {
        const response = await fetch('unlink_regulation_from_substep.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ link_id: linkId })
        });

        const data = await response.json();

        if (!data.success) {
            alert(data.message || 'Failed to remove regulation.');
            return;
        }

        window.location.reload();
    } catch (err) {
        alert('Failed to remove regulation.');
    }
}

function addStepRow() {
    const container = document.getElementById('steps');
    const idx = container.querySelectorAll('.step-card').length;
    const number = idx + 1;

    const card = document.createElement('div');
    card.className = 'step-card';
    card.innerHTML = `
        <div class="step-head">
            <div class="d-flex align-items-center gap-3">
                <span class="step-badge">${number}</span>
                <div>
                    <div class="step-title fw-semibold">Step ${number}</div>
                    <div class="small text-muted">New template step</div>
                </div>
            </div>

            <div class="step-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSubStep(this)">➕ Add Sub-step</button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRegulationPicker('new')">📘 Link Regulation</button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(this)">🗑 Remove Step</button>
            </div>
        </div>

        <input type="hidden" class="step-id-input" name="steps[${idx}][id]" value="new">
        <input type="hidden" class="step-number-input" name="steps[${idx}][number]" value="${number}">

        <div class="mb-3">
            <label class="form-label">Step Description</label>
            <textarea class="form-control step-desc-input" name="steps[${idx}][description]" rows="2" required></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Deficiency Action (optional)</label>
            <textarea class="form-control step-def-input" name="steps[${idx}][deficiency_action]" rows="2"></textarea>
        </div>

        <div class="mt-2">
            <div class="fw-semibold mb-2">Linked Regulations</div>
            <div class="text-muted">Save this ICR before linking regulations to new steps.</div>
        </div>

        <div class="mt-3">
            <div class="fw-semibold mb-2">Sub-steps</div>
            <div class="substeps vstack gap-2"></div>
        </div>
    `;

    container.appendChild(card);
    renumberAll();
}

function addSubStep(btn) {
    const stepCard = btn.closest('.step-card');
    const stepCards = Array.from(document.querySelectorAll('.step-card'));
    const i = stepCards.indexOf(stepCard);
    const stepNum = i + 1;

    const subs = stepCard.querySelector('.substeps');
    const j = subs.querySelectorAll('.substep-card').length;
    const code = nextLetter(j);

    const sub = document.createElement('div');
    sub.className = 'substep-card';
    sub.innerHTML = `
    <div class="substep-actions">
        <div class="substep-code">${code}</div>

        <div class="d-flex gap-2 flex-wrap">
            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    onclick="openSubstepRegulationPicker('new')">
                📘 Link Regulation
            </button>

            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    onclick="removeSubStep(this)">
                🗑 Remove
            </button>
        </div>
    </div>

    <input type="hidden" class="substep-id-input" name="steps[${i}][substeps][${j}][id]" value="new">
    <input type="hidden" class="substep-code-input" name="steps[${i}][substeps][${j}][code]" value="${code}">

    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">Sub-step Description</label>
            <textarea class="form-control substep-desc-input" name="steps[${i}][substeps][${j}][description]" rows="2" required></textarea>
        </div>

        <div>
            <label class="form-label">Deficiency Action (optional)</label>
            <textarea class="form-control substep-def-input" name="steps[${i}][substeps][${j}][deficiency_action]" rows="2"></textarea>
        </div>
    </div>

    <div class="mt-3">
        <div class="fw-semibold mb-2">Linked Regulations</div>
        <div class="text-muted">Save the ICR first before linking regulations to this new sub-step.</div>
    </div>
`;

    subs.appendChild(sub);
    renumberAll();
}

function removeStep(btn) {
    btn.closest('.step-card').remove();
    renumberAll();
}

function removeSubStep(btn) {
    btn.closest('.substep-card').remove();
    renumberAll();
}

function renumberAll() {
    const stepCards = Array.from(document.querySelectorAll('#steps .step-card'));

    stepCards.forEach((card, i) => {
        const stepNum = i + 1;

        const badge = card.querySelector('.step-badge');
        if (badge) badge.textContent = stepNum;

        const title = card.querySelector('.step-title');
        if (title) title.textContent = `Step ${stepNum}`;

        const idIn = card.querySelector('.step-id-input');
        const numIn = card.querySelector('.step-number-input');
        const desc = card.querySelector('.step-desc-input');
        const defa = card.querySelector('.step-def-input');

        if (idIn) idIn.name = `steps[${i}][id]`;
        if (numIn) {
            numIn.name = `steps[${i}][number]`;
            numIn.value = stepNum;
        }
        if (desc) desc.name = `steps[${i}][description]`;
        if (defa) defa.name = `steps[${i}][deficiency_action]`;

        const subs = Array.from(card.querySelectorAll('.substep-card'));
        subs.forEach((sub, j) => {
            const code = nextLetter(j);
            const codeSpan = sub.querySelector('.code');
            if (codeSpan) codeSpan.textContent = `${stepNum}${code}`;

            const id = sub.querySelector('.substep-id-input');
            const codeIn = sub.querySelector('.substep-code-input');
            const descIn = sub.querySelector('.substep-desc-input');
            const defIn = sub.querySelector('.substep-def-input');

            if (id) id.name = `steps[${i}][substeps][${j}][id]`;
            if (codeIn) {
                codeIn.name = `steps[${i}][substeps][${j}][code]`;
                codeIn.value = code;
            }
            if (descIn) descIn.name = `steps[${i}][substeps][${j}][description]`;
            if (defIn) defIn.name = `steps[${i}][substeps][${j}][deficiency_action]`;
        });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    onTitleChange();
    renumberAll();
});
</script>
</body>
</html>