<?php require __DIR__ . '/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add ICR Template</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .step-card { background: #fff; }
        .substep-card { background: #f8f9fa; }
        .card-actions { gap: .5rem; }
        .muted { color: #6c757d; font-size: .9rem; }
        .kbd { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: .85rem; }
    </style>

<script>
    // 0 -> A, 1 -> B, etc.
    function nextLetter(idx) { return String.fromCharCode(65 + idx); }

    function addStepRow() {
        const container = document.getElementById('steps');
        const stepIndex = container.querySelectorAll('.step-card').length; // 0-based
        const stepNumber = stepIndex + 1;

        const card = document.createElement('div');
        card.className = 'mb-3 border rounded p-3 step-card';
        card.dataset.stepIndex = stepIndex;

        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-primary step-badge">Step ${stepNumber}</span>
                        <span class="muted">Template step</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label mb-1">Step Description</label>
                        <textarea name="steps[${stepIndex}][description]" class="form-control" rows="2" required></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label mb-1">Deficiency Action (optional)</label>
                        <textarea name="steps[${stepIndex}][deficiency_action]" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Substeps</div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addSubstepRow(this)">
                                + Add Substep
                            </button>
                        </div>
                        <div class="muted mb-2">Substeps are lettered (A, B, C...).</div>
                        <div class="substeps mt-2"></div>
                    </div>
                </div>

                <div class="d-flex flex-column card-actions">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(this)">Remove Step</button>
                </div>
            </div>
        `;

        container.appendChild(card);
        // start each step with one substep
        addSubstepRow(card.querySelector('button[onclick^="addSubstepRow"]'));
        updateStepNumbers();
    }

    function addSubstepRow(btn) {
        const stepCard = btn.closest('.step-card');
        const stepIndex = stepCard.dataset.stepIndex;
        const subContainer = stepCard.querySelector('.substeps');
        const subIndex = subContainer.querySelectorAll('.substep-card').length;
        const code = nextLetter(subIndex);

        const row = document.createElement('div');
        row.className = 'border rounded p-2 mb-2 substep-card';
        row.dataset.subIndex = subIndex;

        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-secondary substep-badge">${code}</span>
                        <span class="muted">Substep</span>
                    </div>

                    <input type="hidden" name="steps[${stepIndex}][substeps][${subIndex}][code]" value="${code}">

                    <div class="mb-2">
                        <label class="form-label mb-1">Substep Description</label>
                        <textarea name="steps[${stepIndex}][substeps][${subIndex}][description]" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-1">
                        <label class="form-label mb-1">Deficiency Action (optional)</label>
                        <textarea name="steps[${stepIndex}][substeps][${subIndex}][deficiency_action]" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSubstep(this)">Remove</button>
                </div>
            </div>
        `;

        subContainer.appendChild(row);
        updateSubstepCodes(stepCard);
    }

    function updateStepNumbers() {
        const cards = document.querySelectorAll('.step-card');
        cards.forEach((card, idx) => {
            card.dataset.stepIndex = idx;
            const badge = card.querySelector('.step-badge');
            if (badge) badge.textContent = `Step ${idx + 1}`;

            // Update names inside this step to match new index
            card.querySelectorAll('textarea, input').forEach(el => {
                if (!el.name) return;
                el.name = el.name.replace(/steps\[\d+]/g, `steps[${idx}]`);
            });

            updateSubstepCodes(card);
        });
    }

    function updateSubstepCodes(stepCard) {
        const stepIndex = stepCard.dataset.stepIndex;
        const subs = stepCard.querySelectorAll('.substep-card');
        subs.forEach((sub, subIdx) => {
            sub.dataset.subIndex = subIdx;
            const code = nextLetter(subIdx);

            const badge = sub.querySelector('.substep-badge');
            if (badge) badge.textContent = code;

            // Update hidden code input
            const codeInput = sub.querySelector('input[type="hidden"]');
            if (codeInput) codeInput.value = code;

            // Update names to reflect indices
            sub.querySelectorAll('textarea, input').forEach(el => {
                if (!el.name) return;
                el.name = el.name
                    .replace(/steps\[\d+]/g, `steps[${stepIndex}]`)
                    .replace(/substeps\[\d+]/g, `substeps[${subIdx}]`);
            });
        });
    }

    function removeStep(btn) {
        const card = btn.closest('.step-card');
        card.remove();
        updateStepNumbers();
    }

    function removeSubstep(btn) {
        const sub = btn.closest('.substep-card');
        const stepCard = btn.closest('.step-card');
        sub.remove();
        updateSubstepCodes(stepCard);
    }

    // ====== Drill template toggle + defaults (separate from step logic) ======
    function initDrillTemplateUI() {
        const drillType = document.getElementById('drill_type');
        const drillFields = document.getElementById('drill_fields');
        if (!drillType || !drillFields) return;

        const defaults = {
            "Fire": {
                regulatory_references: "46 CFR 176.405 / 46 CFR 185.410 (as applicable)",
                drill_name: "Fire Drill – Machinery / Engine Space",
                operating_condition: "Underway / At Dock",
                purpose:
`This template provides a standardized framework for conducting emergency drills. When used for a fire drill, it evaluates crew readiness, decision-making, communication, and safe execution of emergency procedures.`,
                performance_objective:
`Demonstrate the crew’s ability to recognize a machinery space fire, establish command and control, secure propulsion and ventilation, protect passengers, simulate activation of fixed fire suppression systems, and manage the situation safely and effectively.`,
                safety_limitations:
`- Drill may be conducted underway or at the dock.
- All alarms and fixed fire suppression actions are simulated only.
- No actual discharge of extinguishing agents.
- No entry into machinery space after simulated activation.
- Terminate immediately if unsafe conditions develop.`,
                scenario_description:
`A simulated fire is identified in the machinery space through alarms, smoke, odors, loss of propulsion, or verbal report from a crewmember.`,
                roles_captain:
`Assumes command; maintains vessel control; directs crew actions; determines emergency communications; ensures safety of crew and passengers.`,
                roles_crew:
`Secure engines/ventilation/fuel sources as directed; simulate fixed system activation; maintain boundaries; report status to the Captain.`,
                evaluation_guidance:
`Drill Result: SATISFACTORY / UNSATISFACTORY\nDeficiencies:\nCorrective Actions:`
            },
            "Man Overboard": { regulatory_references:"", drill_name:"Man Overboard Drill", operating_condition:"Underway / At Dock", purpose:"", performance_objective:"", safety_limitations:"", scenario_description:"", roles_captain:"", roles_crew:"", evaluation_guidance:"Drill Result: SATISFACTORY / UNSATISFACTORY\nDeficiencies:\nCorrective Actions:" },
            "Abandon Ship": { regulatory_references:"", drill_name:"Abandon Ship Drill", operating_condition:"Underway / At Dock", purpose:"", performance_objective:"", safety_limitations:"", scenario_description:"", roles_captain:"", roles_crew:"", evaluation_guidance:"Drill Result: SATISFACTORY / UNSATISFACTORY\nDeficiencies:\nCorrective Actions:" }
        };

        function setVal(id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            if ((el.value || '').trim() === '') el.value = val || '';
        }

        function toggle() {
            const v = (drillType.value || '').trim();
            const isDrill = v !== '';
            drillFields.style.display = isDrill ? 'block' : 'none';

            if (isDrill && defaults[v]) {
                const d = defaults[v];
                setVal('drill_reg_refs', d.regulatory_references);
                setVal('drill_name', d.drill_name);
                setVal('drill_operating_condition', d.operating_condition);
                setVal('drill_purpose', d.purpose);
                setVal('drill_objective', d.performance_objective);
                setVal('drill_safety', d.safety_limitations);
                setVal('drill_scenario', d.scenario_description);
                setVal('drill_roles_captain', d.roles_captain);
                setVal('drill_roles_crew', d.roles_crew);
                setVal('drill_eval', d.evaluation_guidance);
            }
        }

        drillType.addEventListener('change', toggle);
        toggle();
    }

    document.addEventListener('DOMContentLoaded', () => {
        addStepRow();          // start with one step
        initDrillTemplateUI(); // drill UI toggle/defaults
    });
</script>

</head>
<body class="p-4">
<div class="container">

    <h2 class="mb-4">Add ICR Template</h2>

    <form method="post" action="submit_icr.php" novalidate>
        <div class="mb-3">
            <label class="form-label">ICR Number</label>
            <input type="text" name="icr_number" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Reference Text (optional)</label>
            <textarea name="reference_text" class="form-control" rows="3"></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Frequency</label>
            <select name="frequency" class="form-select" required>
                <option value="">-- Select --</option>
                <option value="Weekly">Weekly</option>
                <option value="Monthly">Monthly</option>
                <option value="Quarterly">Quarterly</option>
                <option value="Annually">Annually</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Drill Type (optional)</label>
            <select name="drill_type" id="drill_type" class="form-select">
                <option value="">-- Not a Drill ICR --</option>
                <option value="Fire">Fire</option>
                <option value="Man Overboard">Man Overboard</option>
                <option value="Abandon Ship">Abandon Ship</option>
            </select>
            <small class="text-muted">Select only if this ICR template is a drill.</small>
        </div>

        <div id="drill_fields" class="border rounded p-3 mb-3" style="display:none;">
            <h5 class="mb-3">🚨 Drill Template Sections</h5>

            <div class="mb-3">
                <label class="form-label">Regulatory References</label>
                <input type="text" name="drill[regulatory_references]" id="drill_reg_refs" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Drill Name / Description</label>
                <input type="text" name="drill[drill_name]" id="drill_name" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Operating Condition Options</label>
                <input type="text" name="drill[operating_condition]" id="drill_operating_condition" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Purpose of Drill</label>
                <textarea name="drill[purpose]" id="drill_purpose" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Performance Objective</label>
                <textarea name="drill[performance_objective]" id="drill_objective" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Drill Conditions & Safety Limitations</label>
                <textarea name="drill[safety_limitations]" id="drill_safety" class="form-control" rows="4"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Scenario Description</label>
                <textarea name="drill[scenario_description]" id="drill_scenario" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Roles & Responsibilities – Captain</label>
                <textarea name="drill[roles_captain]" id="drill_roles_captain" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Roles & Responsibilities – Crew</label>
                <textarea name="drill[roles_crew]" id="drill_roles_crew" class="form-control" rows="3"></textarea>
            </div>

            <div class="mb-0">
                <label class="form-label">Evaluation & Corrective Actions Guidance</label>
                <textarea name="drill[evaluation_guidance]" id="drill_eval" class="form-control" rows="3"></textarea>
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex align-items-center justify-content-between mb-2">
            <h4 class="mb-0">Inspection Steps</h4>
            <button type="button" class="btn btn-outline-primary" onclick="addStepRow()">+ Add Step</button>
        </div>
        <div class="muted mb-3">Use steps/substeps for the drill action sequence.</div>

        <div id="steps"></div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Template</button>
            <a href="icr_templates.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

</div>
</body>
</html>
