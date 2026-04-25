<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/session_check.php';

/* ------- Inputs ------- */
$vessel_icr_id = isset($_GET['vessel_icr_id']) ? (int)$_GET['vessel_icr_id'] : 0;
$icr_id        = isset($_GET['icr_id'])        ? (int)$_GET['icr_id']        : 0;
$vessel_id     = isset($_GET['vessel_id'])     ? (int)$_GET['vessel_id']     : 0;
$inspector     = trim($_GET['inspector'] ?? 'Unknown');
$debug         = isset($_GET['debug']) && $_GET['debug'] ? true : false;

$todayHi = (new DateTime('now', new DateTimeZone('Pacific/Honolulu')))->format('Y-m-d');

if ($vessel_icr_id <= 0 && $vessel_id > 0 && $icr_id > 0) {
    $q = $pdo->prepare("SELECT vessel_icr_id FROM vessel_icrs WHERE vessel_id = ? AND icr_id = ? LIMIT 1");
    $q->execute([$vessel_id, $icr_id]);
    $vessel_icr_id = (int)$q->fetchColumn();
}

if (($vessel_id <= 0 || $icr_id <= 0) && $vessel_icr_id > 0) {
    $q = $pdo->prepare("SELECT vessel_id, icr_id FROM vessel_icrs WHERE vessel_icr_id = ? LIMIT 1");
    $q->execute([$vessel_icr_id]);
    if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        if ($vessel_id <= 0) $vessel_id = (int)$row['vessel_id'];
        if ($icr_id   <= 0)  $icr_id    = (int)$row['icr_id'];
    }
}

if ($icr_id <= 0) {
    http_response_code(400);
    echo "Missing ICR context. Open this page via a vessel or template link.";
    exit;
}

$backUrl = $vessel_id > 0
    ? "vessel_icrs.php?vessel_id={$vessel_id}#icrs"
    : "icr_templates.php";

$vesselName = null;
if ($vessel_id > 0) {
    $vn = $pdo->prepare("SELECT vesselName FROM vessels WHERE vessel_id = ? LIMIT 1");
    $vn->execute([$vessel_id]);
    $vesselName = $vn->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT
        icr_id,
        icr_number,
        title,
        reference_text,
        frequency,
        drill_type,
        is_equipment_driven,
        equipment_scope,
        created_at,
        updated_at
    FROM icrs
    WHERE icr_id = :icr_id
    LIMIT 1
");
$stmt->execute([':icr_id' => $icr_id]);
$icr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$icr) {
    http_response_code(404);
    echo "ICR not found.";
    exit;
}

$isDrill = !empty($icr['drill_type']);
$isEquipmentDriven = !empty($icr['is_equipment_driven']);
$equipmentScope = $icr['equipment_scope'] ?? 'none';

$drillTpl = null;
if ($isDrill) {
    try {
        $dt = $pdo->prepare("
            SELECT
                regulatory_references,
                drill_name,
                operating_condition,
                purpose,
                performance_objective,
                safety_limitations,
                scenario_description,
                roles_captain,
                roles_crew,
                evaluation_guidance
            FROM icr_drill_templates
            WHERE icr_id = ?
            LIMIT 1
        ");
        $dt->execute([$icr_id]);
        $drillTpl = $dt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $drillTpl = null;
    }
}

$crew_list = [];
if ($isDrill && $vessel_id > 0) {
    $crew_stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            u.fName,
            u.lName,
            vc.role
        FROM vessel_crew vc
        INNER JOIN users u
            ON u.id = vc.crew_id
        WHERE vc.vessel_id = ?
          AND vc.is_active = 1
          AND u.is_active = 1
          AND vc.counts_for_drills = 1
          AND vc.role IN ('Master', 'Deckhand')
        ORDER BY
            FIELD(vc.role, 'Master', 'Deckhand'),
            u.lName,
            u.fName
    ");
    $crew_stmt->execute([$vessel_id]);
    $crew_list = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$task_user_list = [];
if ($vessel_id > 0) {
    $task_user_stmt = $pdo->prepare("
        SELECT DISTINCT
            u.id,
            u.fName,
            u.lName,
            vc.role
        FROM vessel_crew vc
        INNER JOIN users u
            ON u.id = vc.crew_id
        WHERE vc.vessel_id = ?
          AND vc.is_active = 1
          AND u.is_active = 1
        ORDER BY
            FIELD(vc.role, 'Owner', 'Admin', 'Maintenance', 'Master', 'Deckhand'),
            u.lName,
            u.fName
    ");
    $task_user_stmt->execute([$vessel_id]);
    $task_user_list = $task_user_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function equipmentScopeSql(string $scope): string {
    switch ($scope) {
        case 'portable_fire':
            return " AND e.category_id = 3 AND LOWER(COALESCE(e.equipmentName, '')) LIKE '%portable extinguisher%' ";
        case 'fixed_fire':
            return " AND e.category_id = 3 AND LOWER(COALESCE(e.equipmentName, '')) LIKE '%fixed extinguisher%' ";
        case 'all_fire':
            return " AND e.category_id = 3 ";
        default:
            return " AND 1 = 0 ";
    }
}

function extinguisherDisplayLabel(array $row): string {
    $bits = [];

    if (!empty($row['capacity_value'])) {
        $cap = rtrim(rtrim((string)$row['capacity_value'], '0'), '.');
        $bits[] = $cap . (!empty($row['capacity_unit']) ? ' ' . $row['capacity_unit'] : '');
    }

    if (!empty($row['agent_type'])) {
        $bits[] = $row['agent_type'];
    } elseif (!empty($row['equipmentName'])) {
        $bits[] = $row['equipmentName'];
    }

    if (!empty($row['equipmentLocation'])) {
        $bits[] = '– ' . $row['equipmentLocation'];
    }

    return trim(implode(' ', $bits));
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$hasVesselSubsteps = false;
$hasVesselSubDef   = false;
try {
    $pdo->query("SELECT 1 FROM vessel_icr_substeps LIMIT 1");
    $hasVesselSubsteps = true;

    $chk = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vessel_icr_substeps'
          AND COLUMN_NAME = 'deficiency_action'
    ");
    $chk->execute();
    $hasVesselSubDef = ((int)$chk->fetchColumn() > 0);
} catch (Throwable $e) {
    $hasVesselSubsteps = false;
    $hasVesselSubDef = false;
}

$steps  = [];
$source = 'master';
$icrIdByNumber = [];
$vesselStepIdByIcrStepId = [];
$icrStepIdByVesselStepId = [];

if ($vessel_icr_id > 0) {
    $vs = $pdo->prepare("
        SELECT
            vs.step_id AS vessel_step_id,
            vs.step_number,
            vs.step_description,
            vs.master_step_id
        FROM vessel_icr_steps vs
        WHERE vs.vessel_icr_id = :vessel_icr_id
        ORDER BY vs.step_number ASC
    ");
    $vs->execute([':vessel_icr_id' => $vessel_icr_id]);
    $steps  = $vs->fetchAll(PDO::FETCH_ASSOC);
    $source = 'vessel';

    $map = $pdo->prepare("
        SELECT
            ists.step_id AS icr_step_id,
            ists.step_number,
            vs.step_id AS vessel_step_id
        FROM icr_steps ists
        LEFT JOIN vessel_icr_steps vs
          ON vs.vessel_icr_id = :vessel_icr_id
         AND vs.step_number = ists.step_number
        WHERE ists.icr_id = :icr_id
    ");
    $map->execute([
        ':icr_id' => $icr_id,
        ':vessel_icr_id' => $vessel_icr_id
    ]);

    while ($r = $map->fetch(PDO::FETCH_ASSOC)) {
        $icr_step_id = (int)$r['icr_step_id'];
        $step_number = (int)$r['step_number'];
        $vessel_step_id = (int)($r['vessel_step_id'] ?? 0);

        $icrIdByNumber[$step_number] = $icr_step_id;

        if ($vessel_step_id > 0) {
            $vesselStepIdByIcrStepId[$icr_step_id] = $vessel_step_id;
            $icrStepIdByVesselStepId[$vessel_step_id] = $icr_step_id;
        }
    }
} else {
    $ms = $pdo->prepare("
        SELECT step_id AS icr_step_id, step_number, step_description, deficiency_action
        FROM icr_steps
        WHERE icr_id = :icr_id
        ORDER BY step_number ASC
    ");
    $ms->execute([':icr_id' => $icr_id]);
    $steps  = $ms->fetchAll(PDO::FETCH_ASSOC);
    $source = 'master';
}

$getMasterSubs = $pdo->prepare("
    SELECT substep_id, substep_code, description, deficiency_action
    FROM icr_substeps
    WHERE step_id = :icr_step_id
    ORDER BY substep_code ASC
");

$getVesselSubs = $pdo->prepare("
    SELECT substep_id, vessel_step_id, substep_code, description, master_substep_id" . ($hasVesselSubDef ? ", deficiency_action" : "") . "
    FROM vessel_icr_substeps
    WHERE vessel_step_id = :vessel_step_id
    ORDER BY substep_code ASC
");

$getStepRegs = $pdo->prepare("
    SELECT
        itsr.icr_template_step_regulation_id AS link_id,
        itsr.reference_type,
        itsr.display_order,
        itsr.note_override,
        itsr.regulation_paragraph_id,
        rs.citation,
        rs.heading,
        rs.text_plain AS section_text,
        rp.paragraph_path,
        rp.paragraph_label,
        rp.text_plain AS paragraph_text
    FROM icr_template_step_regulations itsr
    JOIN regulation_sections rs
      ON rs.regulation_section_id = itsr.regulation_section_id
    LEFT JOIN regulation_paragraphs rp
      ON rp.regulation_paragraph_id = itsr.regulation_paragraph_id
    WHERE itsr.icr_template_step_id = ?
    ORDER BY itsr.display_order ASC, rs.citation ASC, rp.sort_key ASC
");

$getSubstepRegs = $pdo->prepare("
    SELECT
        itsr.icr_template_substep_regulation_id AS link_id,
        itsr.reference_type,
        itsr.display_order,
        itsr.note_override,
        itsr.regulation_paragraph_id,
        rs.citation,
        rs.heading,
        rs.text_plain AS section_text,
        rp.paragraph_path,
        rp.paragraph_label,
        rp.text_plain AS paragraph_text
    FROM icr_template_substep_regulations itsr
    JOIN regulation_sections rs
      ON rs.regulation_section_id = itsr.regulation_section_id
    LEFT JOIN regulation_paragraphs rp
      ON rp.regulation_paragraph_id = itsr.regulation_paragraph_id
    WHERE itsr.icr_template_substep_id = ?
    ORDER BY itsr.display_order ASC, rs.citation ASC, rp.sort_key ASC
");

$getSavedStepNotes = $pdo->prepare("
    SELECT DISTINCT
        pn.note_id,
        pn.note_text,
        pn.note_type,
        pn.usage_count
    FROM predefined_notes pn
    INNER JOIN predefined_note_links pnl
        ON pnl.note_id = pn.note_id
    WHERE pn.is_active = 1
      AND pnl.link_scope = 'step'
      AND pnl.icr_id = ?
      AND pnl.master_step_id = ?
    ORDER BY pn.usage_count DESC, pn.note_id DESC
");

$getSavedSubstepNotes = $pdo->prepare("
    SELECT DISTINCT
        pn.note_id,
        pn.note_text,
        pn.note_type,
        pn.usage_count
    FROM predefined_notes pn
    INNER JOIN predefined_note_links pnl
        ON pnl.note_id = pn.note_id
    WHERE pn.is_active = 1
      AND pnl.link_scope = 'substep'
      AND pnl.icr_id = ?
      AND pnl.master_substep_id = ?
    ORDER BY pn.usage_count DESC, pn.note_id DESC
");

$run_id = 0;

if ($vessel_id > 0 && $icr_id > 0 && $vessel_icr_id > 0) {
    $r = $pdo->prepare("
        SELECT run_id
        FROM vessel_icr_runs
        WHERE vessel_id = ?
          AND icr_id = ?
          AND vessel_icr_id = ?
          AND save_state = 'draft'
        ORDER BY run_id DESC
        LIMIT 1
    ");
    $r->execute([$vessel_id, $icr_id, $vessel_icr_id]);
    $run_id = (int)($r->fetchColumn() ?: 0);
}

$completedOnValue = $todayHi;
if ($run_id > 0) {
    $d = $pdo->prepare("
        SELECT run_date
        FROM vessel_icr_runs
        WHERE run_id = ?
        LIMIT 1
    ");
    $d->execute([$run_id]);
    $savedRunDate = $d->fetchColumn();
    if (!empty($savedRunDate)) {
        $completedOnValue = $savedRunDate;
    }
}

$existingStepStatus = [];
$existingStepNotes  = [];

$existingSubStatusMaster = [];
$existingSubNotesMaster  = [];

$existingSubStatusVessel = [];
$existingSubNotesVessel  = [];

$extinguishers = [];
$existingEqStepStatus = [];
$existingEqStepNotes = [];
$existingEqStepRegs = [];

if ($isEquipmentDriven && $vessel_id > 0) {
    $sql = "
        SELECT
            e.eid,
            e.equipmentName,
            e.equipmentLocation,
            e.manufacturer,
            e.modelNumber,
            e.serialNumber,
            fed.agent_type,
            fed.extinguisher_class,
            fed.capacity_value,
            fed.capacity_unit,
            fed.ul_rating,
            fed.manufacture_date,
            et.name AS equipment_type_name,
            es.name AS equipment_subtype_name
        FROM equipment e
        LEFT JOIN fire_extinguisher_details fed ON fed.eid = e.eid
        LEFT JOIN equipment_type et ON et.id = e.type_id
        LEFT JOIN equipment_subtype es ON es.id = e.subtype_id
        WHERE e.vessel_id = :vessel_id
        " . equipmentScopeSql($equipmentScope) . "
        ORDER BY
            COALESCE(e.equipmentLocation, ''),
            COALESCE(e.equipmentName, ''),
            e.eid
    ";
    $eq = $pdo->prepare($sql);
    $eq->execute([':vessel_id' => $vessel_id]);
    $extinguishers = $eq->fetchAll(PDO::FETCH_ASSOC);

    if ($run_id > 0) {
        $stmt = $pdo->prepare("
            SELECT
                vire.equipment_id,
                vires.vessel_icr_step_id,
                vires.status,
                vires.comment,
                vires.supporting_regulations
            FROM vessel_icr_run_equipment vire
            JOIN vessel_icr_run_equipment_steps vires
              ON vires.run_equipment_id = vire.run_equipment_id
            WHERE vire.run_id = ?
        ");
        $stmt->execute([$run_id]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int)$row['equipment_id'];
            $vesselStepId = (int)$row['vessel_icr_step_id'];

            $existingEqStepStatus[$eid][$vesselStepId] = $row['status'];
            $existingEqStepNotes[$eid][$vesselStepId]  = $row['comment'];
            $existingEqStepRegs[$eid][$vesselStepId]   = $row['supporting_regulations'] ?? '';
        }
    }
}

if ($run_id > 0) {
    $stmt = $pdo->prepare("
        SELECT vessel_icr_step_id, status, comment
        FROM vessel_icr_step_status
        WHERE run_id = ?
    ");
    $stmt->execute([$run_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vessel_step_id = (int)$row['vessel_icr_step_id'];
        $icr_step_id = $icrStepIdByVesselStepId[$vessel_step_id] ?? 0;

        if ($icr_step_id > 0) {
            $existingStepStatus[$icr_step_id] = $row['status'];
            $existingStepNotes[$icr_step_id]  = $row['comment'];
        }
    }

    $stmt = $pdo->prepare("
        SELECT vessel_icr_step_id, icr_substep_id, vessel_substep_id, status, comment
        FROM vessel_icr_substep_status
        WHERE run_id = ?
    ");
    $stmt->execute([$run_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['icr_substep_id'])) {
            $sid = (int)$row['icr_substep_id'];
            $existingSubStatusMaster[$sid] = $row['status'];
            $existingSubNotesMaster[$sid]  = $row['comment'];
        }

        if (!empty($row['vessel_substep_id'])) {
            $sid = (int)$row['vessel_substep_id'];
            $existingSubStatusVessel[$sid] = $row['status'];
            $existingSubNotesVessel[$sid]  = $row['comment'];
        }
    }
}

function regulationPayloadForHiddenField(array $linkedRegs): string {
    $payload = [];
    foreach ($linkedRegs as $reg) {
        $payload[] = [
            'citation' => $reg['citation'] ?? '',
            'heading' => $reg['heading'] ?? '',
            'paragraph_path' => $reg['paragraph_path'] ?? '',
            'text' => !empty($reg['paragraph_text']) ? $reg['paragraph_text'] : ($reg['section_text'] ?? ''),
            'reference_type' => $reg['reference_type'] ?? 'requirement'
        ];
    }
    return htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
}

$wizardStepCount = $isEquipmentDriven ? count($extinguishers) : count($steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Run ICR • <?= htmlspecialchars($icr['icr_number']) ?> – <?= htmlspecialchars($icr['title']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0d6efd">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
<link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link href="/assets/css/vms-mobile.css" rel="stylesheet">

<style>
    .run-shell { background: var(--vms-bg, #f4f7fb); min-height: 100vh; }
    .run-header-card, .run-section-card, .step-card, .substep-card { border: 0; border-radius: 1rem; }
    .muted { color: #6c757d; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
    .run-meta { color: #6b7280; margin: 0; }

    .status-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        border-radius: 999px; padding: .35rem .75rem; font-size: .85rem; font-weight: 600;
        background: #fff; border: 1px solid #dee2e6;
    }

    .choice-group { display: flex; flex-wrap: wrap; gap: .5rem; }
    .choice-pill { position: relative; }
    .choice-pill input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
    .choice-pill label {
        display: inline-block; min-width: 76px; text-align: center; padding: .65rem .9rem;
        border-radius: 999px; border: 1px solid #ced4da; background: #fff; font-weight: 600;
        cursor: pointer; user-select: none;
    }
    .choice-pill input[type="radio"]:checked + label {
        border-color: #0d6efd; background: #e7f1ff; color: #0b5ed7;
    }

    .step-card { background: #fff; overflow: hidden; }
    .step-card .accordion-button { align-items: flex-start; gap: .75rem; padding: 1rem 1rem; }
    .step-card .accordion-button:not(.collapsed) { background: #f8fbff; box-shadow: none; }

    .step-number-badge {
        min-width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 999px; background: #e7f1ff; color: #0b5ed7; font-weight: 700;
    }

    .substep-card { background: #f8f9fa; border-left: 4px solid #dee2e6; padding: 1rem; }
    .help-text { font-size: .85rem; color: #6c757d; }
    .completion-date-help { font-size: .9rem; color: #6c757d; }

    .sticky-run-actions {
        position: sticky; bottom: 0; z-index: 1020;
        background: rgba(248,249,250,.96); backdrop-filter: blur(6px); border-top: 1px solid #dee2e6;
    }

    .equipment-summary { font-size: .9rem; color: #6b7280; }

    .section-title {
        font-size: .95rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
        color: #6c757d; margin-bottom: .75rem;
    }

    .linked-reg-box {
        background: #f8fbff; border: 1px solid #d7e6ff; border-radius: 12px; padding: 12px;
    }

    .linked-reg-item + .linked-reg-item {
        border-top: 1px solid #e4edf9; margin-top: 10px; padding-top: 10px;
    }

    .linked-reg-text {
        white-space: pre-wrap; background: #fff; border: 1px solid #dee2e6; border-radius: 10px;
        padding: 10px; font-size: .9rem; max-height: 220px; overflow-y: auto;
    }

    .note-save-box {
        border: 1px dashed #cdd6e0;
        border-radius: 12px;
        background: #fafcff;
        padding: 10px;
    }

    .step-parent-compact {
        border: 1px solid #e3e8ef;
        border-radius: 14px;
        background: #f8fbff;
        padding: 12px;
    }

    .step-parent-compact .form-label,
    .substep-card .form-label {
        margin-bottom: .35rem;
    }

    .optional-panel {
        border: 1px solid #e3e8ef;
        border-radius: 12px;
        background: #fff;
        padding: 10px 12px;
    }

    .optional-panel + .optional-panel {
        margin-top: .75rem;
    }

    .optional-panel summary {
        cursor: pointer;
        font-weight: 600;
        color: #495057;
        list-style: none;
    }

    .optional-panel summary::-webkit-details-marker {
        display: none;
    }

    .optional-panel[open] summary {
        margin-bottom: .75rem;
    }

    .substep-mode-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
        margin-bottom: .75rem;
    }

    .substep-nav {
        display: none;
    }

    .substep-nav .btn {
        min-height: 42px;
    }

    .substep-counter {
        font-size: .9rem;
        color: #6c757d;
    }

    .substep-card {
        padding: .85rem;
    }

    body.icr-stepper-enhanced .substep-mode[data-substep-mode="1"] .substep-unit {
        display: none;
    }

    body.icr-stepper-enhanced .substep-mode[data-substep-mode="1"] .substep-unit.is-active {
        display: block;
    }

    body.icr-stepper-enhanced .substep-mode[data-substep-mode="1"] .substep-nav {
        display: flex;
    }

    .stepper-toolbar,
    .stepper-nav-group,
    .stepper-message {
        display: none;
    }

    .stepper-progress-meta {
        font-size: .9rem;
        color: #6c757d;
    }

    .stepper-nav-group .btn,
    .stepper-finalize,
    .stepper-draft,
    .stepper-cancel {
        min-height: 48px;
    }

    body.icr-stepper-enhanced .stepper-toolbar {
        display: block;
    }

    body.icr-stepper-enhanced .stepper-step {
        display: none;
    }

    body.icr-stepper-enhanced .stepper-step.is-active {
        display: block;
    }

    body.icr-stepper-enhanced .stepper-nav-group {
        display: flex;
    }

    body.icr-stepper-enhanced .stepper-finalize {
        display: none !important;
    }

    body.icr-stepper-enhanced .stepper-finalize.is-final-step {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
    }

    body.icr-stepper-enhanced .stepper-message.is-visible {
        display: block;
    }

    body.icr-stepper-enhanced .stepper-final-support {
        display: none;
    }

    body.icr-stepper-enhanced .stepper-final-support.is-visible {
        display: block;
    }

    @media (min-width: 992px) {
        .run-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    }
</style>
</head>
<body>
<?php
$title = 'Run ICR';
$back_link = $backUrl;
include __DIR__ . '/partials/top_nav.php';
?>

<div class="run-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="card run-header-card shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h1 class="h3 mb-1">
                                <?= htmlspecialchars($icr['icr_number']) ?> — <?= htmlspecialchars($icr['title']) ?>
                            </h1>
                            <p class="run-meta mb-2">
                                <?php if ($vesselName): ?>
                                    <?= htmlspecialchars($vesselName) ?>
                                <?php else: ?>
                                    Template ICR
                                <?php endif; ?>
                                · Frequency: <?= htmlspecialchars($icr['frequency']) ?>
                                <?php if (!empty($icr['updated_at'])): ?>
                                    · Updated: <?= htmlspecialchars($icr['updated_at']) ?>
                                <?php endif; ?>
                            </p>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($run_id > 0): ?>
                                    <span class="status-chip">Draft Loaded</span>
                                <?php else: ?>
                                    <span class="status-chip">New Run</span>
                                <?php endif; ?>

                                <?php if ($isDrill): ?>
                                    <span class="status-chip">Drill: <?= htmlspecialchars($icr['drill_type']) ?></span>
                                <?php endif; ?>

                                <?php if ($isEquipmentDriven): ?>
                                    <span class="status-chip">Equipment Driven</span>
                                <?php endif; ?>

                                <?php if ($debug): ?>
                                    <span class="status-chip">Source: <?= htmlspecialchars(strtoupper($source)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2">
                            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($backUrl) ?>">Back</a>
                            <a href="print_icr.php?vessel_id=<?= urlencode($vessel_id) ?>&icr_id=<?= urlencode($icr_id) ?>"
                               target="_blank"
                               class="btn btn-outline-dark">
                                Print Blank ICR
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($icr['reference_text'])): ?>
                <div class="alert alert-info">
                    <strong>Authorization / Reference</strong><br>
                    <?= nl2br(htmlspecialchars($icr['reference_text'])) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_GET['saved']) && $_GET['saved'] === 'draft'): ?>
                <div class="alert alert-success">
                    Draft saved successfully. You can continue editing or finalize when ready.
                </div>
            <?php endif; ?>

            <?php if ($isDrill): ?>
                <div class="card run-section-card shadow-sm mb-3">
                    <div class="card-header bg-white">
                        <strong>Drill Template</strong>
                        <span class="text-muted">· <?= htmlspecialchars($icr['drill_type']) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if ($drillTpl): ?>
                            <?php if (!empty($drillTpl['regulatory_references'])): ?>
                                <div class="mb-2"><strong>Regulatory References:</strong> <?= htmlspecialchars($drillTpl['regulatory_references']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['drill_name'])): ?>
                                <div class="mb-2"><strong>Drill Name:</strong> <?= htmlspecialchars($drillTpl['drill_name']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['operating_condition'])): ?>
                                <div class="mb-3"><strong>Operating Condition Options:</strong> <?= htmlspecialchars($drillTpl['operating_condition']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['purpose'])): ?>
                                <div class="mb-3"><strong>Purpose</strong><br><?= nl2br(htmlspecialchars($drillTpl['purpose'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['performance_objective'])): ?>
                                <div class="mb-3"><strong>Performance Objective</strong><br><?= nl2br(htmlspecialchars($drillTpl['performance_objective'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['safety_limitations'])): ?>
                                <div class="mb-3"><strong>Drill Conditions & Safety Limitations</strong><br><?= nl2br(htmlspecialchars($drillTpl['safety_limitations'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($drillTpl['scenario_description'])): ?>
                                <div class="mb-3"><strong>Scenario Description</strong><br><?= nl2br(htmlspecialchars($drillTpl['scenario_description'])) ?></div>
                            <?php endif; ?>

                            <div class="run-two-col">
                                <?php if (!empty($drillTpl['roles_captain'])): ?>
                                    <div class="mb-3"><strong>Roles & Responsibilities – Captain</strong><br><?= nl2br(htmlspecialchars($drillTpl['roles_captain'])) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($drillTpl['roles_crew'])): ?>
                                    <div class="mb-3"><strong>Roles & Responsibilities – Crew</strong><br><?= nl2br(htmlspecialchars($drillTpl['roles_crew'])) ?></div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($drillTpl['evaluation_guidance'])): ?>
                                <div class="mt-2"><strong>Evaluation & Corrective Actions Guidance</strong><br><?= nl2br(htmlspecialchars($drillTpl['evaluation_guidance'])) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                This ICR is marked as a drill, but no drill template header fields were found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$steps): ?>
                <div class="alert alert-warning">No steps found for this ICR.</div>
            <?php else: ?>

            <form id="icrForm" method="post" action="submit_icr_run.php" enctype="multipart/form-data">
                <?php if ($vessel_id > 0): ?>
                    <input type="hidden" name="run_id" value="<?= (int)($run_id ?? 0) ?>">
                <?php endif; ?>
                <input type="hidden" name="save_mode" id="save_mode" value="draft">
                <input type="hidden" name="icr_id" value="<?= (int)$icr['icr_id'] ?>">
                <?php if ($vessel_icr_id > 0): ?>
                    <input type="hidden" name="vessel_icr_id" value="<?= (int)$vessel_icr_id ?>">
                <?php endif; ?>
                <input type="hidden" name="inspector" value="<?= htmlspecialchars($inspector) ?>">
                <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
                <input type="hidden" name="is_equipment_driven" value="<?= $isEquipmentDriven ? 1 : 0 ?>">
                <input type="hidden" name="equipment_scope" value="<?= htmlspecialchars($equipmentScope) ?>">

                <div class="card run-section-card shadow-sm mb-3 stepper-toolbar" aria-live="polite">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="text-muted small text-uppercase">Run Progress</div>
                                <div class="fw-semibold" id="stepperProgressText">
                                    <?= $isEquipmentDriven ? 'Equipment 1 of ' : 'Step 1 of ' ?><?= (int)$wizardStepCount ?>
                                </div>
                            </div>
                            <div class="stepper-progress-meta" id="stepperProgressMeta">
                                <?= $isEquipmentDriven ? 'Equipment Inspection' : 'Inspection Step' ?>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 8px;">
                            <div
                                class="progress-bar"
                                id="stepperProgressBar"
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="<?= (int)$wizardStepCount ?>"
                                aria-valuenow="<?= $wizardStepCount > 0 ? 1 : 0 ?>"
                                style="width: <?= $wizardStepCount > 0 ? (100 / $wizardStepCount) : 0 ?>%;"
                            ></div>
                        </div>
                        <div class="alert alert-danger stepper-message mt-3 mb-0 py-2 px-3" id="stepperMessage" role="alert"></div>
                    </div>
                </div>

                <div class="card run-section-card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="run-two-col">
                            <div>
                                <label for="completed_on" class="form-label mb-1">Completion Date</label>
                                <input type="date" class="form-control" id="completed_on" name="completed_on"
                                       value="<?= htmlspecialchars($completedOnValue) ?>" max="<?= $todayHi ?>" required>
                                <div class="completion-date-help mt-1">
                                    Use the actual date the inspection or drill was completed.
                                </div>
                            </div>

                            <div class="d-flex align-items-end">
                                <div class="w-100 d-grid d-sm-flex gap-2 justify-content-sm-end">
                                    <div class="stepper-nav-group gap-2">
                                        <button type="button" class="btn btn-outline-secondary" data-stepper-prev>
                                            Previous
                                        </button>
                                        <button type="button" class="btn btn-primary" data-stepper-next>
                                            Next
                                        </button>
                                    </div>

                                    <button type="submit" class="btn btn-outline-primary stepper-draft"
                                            onclick="document.getElementById('save_mode').value='draft';">
                                        Save Draft
                                    </button>

                                    <button type="submit" class="btn btn-primary stepper-finalize"
                                            onclick="document.getElementById('save_mode').value='final';">
                                        Finalize Run
                                    </button>

                                    <a class="btn btn-outline-secondary stepper-cancel" href="<?= htmlspecialchars($backUrl) ?>">Cancel</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($vessel_id > 0 && !empty($task_user_list)): ?>
                <div class="card run-section-card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="section-title mb-2">Corrective Action Defaults</div>
                        <div class="text-muted mb-3">
                            If any steps fail and corrective actions are created, use these defaults for assignment and notifications.
                        </div>

                        <div class="run-two-col">
                            <div>
                                <label for="task_assigned_to" class="form-label">Default Assigned To</label>
                                <select name="task_assigned_to" id="task_assigned_to" class="form-select">
                                    <option value="">-- Select Primary Owner --</option>
                                    <?php foreach ($task_user_list as $row):
                                        $label = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                                        if (!empty($row['role'])) $label .= ' (' . $row['role'] . ')';
                                    ?>
                                        <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text mt-1">
                                    Used as the default assignee for ICR-generated corrective actions.
                                </div>
                            </div>

                            <div>
                                <label for="task_notify_users" class="form-label">Default Notify / Keep Informed</label>
                                <select name="task_notify_users[]" id="task_notify_users" class="form-select" multiple size="6">
                                    <?php foreach ($task_user_list as $row):
                                        $label = trim(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? ''));
                                        if (!empty($row['role'])) $label .= ' (' . $row['role'] . ')';
                                    ?>
                                        <option value="<?= (int)$row['id'] ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text mt-1">
                                    Optional. These users will be added as task notification recipients if corrective actions are created.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($isEquipmentDriven): ?>
                    <?php if ($vessel_icr_id <= 0): ?>
                        <div class="alert alert-warning">Equipment-driven ICRs must be opened from a vessel context.</div>
                    <?php elseif (empty($extinguishers)): ?>
                        <div class="alert alert-warning">No matching equipment found for this vessel and equipment scope.</div>
                    <?php else: ?>
                        <div class="card run-section-card shadow-sm mb-3">
                            <div class="card-body">
                                <div class="section-title mb-2">Equipment Inspection</div>
                                <div class="equipment-summary">
                                    Complete the inspection steps for each matching equipment item below.
                                </div>
                            </div>
                        </div>

                        <div class="accordion mb-4" id="equipmentAccordion">
                            <?php foreach ($extinguishers as $idx => $ext): ?>
                                <?php
                                    $eid = (int)$ext['eid'];
                                    $collapseId = 'ext_' . $eid;
                                    $headingId = 'heading_' . $eid;
                                    $label = extinguisherDisplayLabel($ext);
                                    if ($label === '') $label = 'Equipment #' . $eid;
                                ?>
                                <div class="accordion-item border-0 shadow-sm rounded-4 mb-3 overflow-hidden stepper-step"
                                     data-step-index="<?= (int)$idx ?>"
                                     data-step-validation="none">
                                    <h2 class="accordion-header" id="<?= htmlspecialchars($headingId) ?>">
                                        <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>"
                                                aria-expanded="<?= $idx === 0 ? 'true' : 'false' ?>"
                                                aria-controls="<?= htmlspecialchars($collapseId) ?>">
                                            <div>
                                                <strong><?= htmlspecialchars($label) ?></strong><br>
                                                <small class="text-muted">
                                                    Serial: <?= htmlspecialchars($ext['serialNumber'] ?? '—') ?>
                                                    · Manufacturer: <?= htmlspecialchars($ext['manufacturer'] ?? '—') ?>
                                                    · Model: <?= htmlspecialchars($ext['modelNumber'] ?? '—') ?>
                                                </small>
                                            </div>
                                        </button>
                                    </h2>

                                    <div id="<?= htmlspecialchars($collapseId) ?>"
                                         class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>"
                                         aria-labelledby="<?= htmlspecialchars($headingId) ?>"
                                         data-bs-parent="#equipmentAccordion">
                                        <div class="accordion-body">

                                            <div class="run-two-col mb-3">
                                                <div><strong>Location:</strong><br><?= htmlspecialchars($ext['equipmentLocation'] ?? '—') ?></div>
                                                <div><strong>Agent Type:</strong><br><?= htmlspecialchars($ext['agent_type'] ?? '—') ?></div>
                                                <div><strong>Class:</strong><br><?= htmlspecialchars($ext['extinguisher_class'] ?? '—') ?></div>
                                                <div><strong>UL Rating:</strong><br><?= htmlspecialchars($ext['ul_rating'] ?? '—') ?></div>
                                            </div>

                                            <?php foreach ($steps as $step): ?>
                                                <?php
                                                    if ($source !== 'vessel') continue;
                                                    $vessel_step_id = (int)$step['vessel_step_id'];
                                                    $num = (int)$step['step_number'];
                                                    $step_description = $step['step_description'];

                                                    $savedStatus = $existingEqStepStatus[$eid][$vessel_step_id] ?? '';
                                                    $savedNote   = $existingEqStepNotes[$eid][$vessel_step_id] ?? '';
                                                    $savedReg    = $existingEqStepRegs[$eid][$vessel_step_id] ?? '';
                                                ?>
                                                <div class="card border-0 shadow-sm mb-3">
                                                    <div class="card-body">
                                                        <h6 class="mb-3">
                                                            <span class="mono"><?= $num ?></span>
                                                            <?= htmlspecialchars($step_description) ?>
                                                        </h6>

                                                        <div class="choice-group mb-3">
                                                            <?php $eqName = "equipment_status[{$eid}][{$vessel_step_id}]"; ?>
                                                            <div class="choice-pill">
                                                                <input id="eq_pass_<?= $eid ?>_<?= $vessel_step_id ?>" type="radio" name="<?= $eqName ?>" value="pass" <?= $savedStatus === 'pass' ? 'checked' : '' ?>>
                                                                <label for="eq_pass_<?= $eid ?>_<?= $vessel_step_id ?>">Pass</label>
                                                            </div>
                                                            <div class="choice-pill">
                                                                <input id="eq_fail_<?= $eid ?>_<?= $vessel_step_id ?>" type="radio" name="<?= $eqName ?>" value="fail" <?= $savedStatus === 'fail' ? 'checked' : '' ?>>
                                                                <label for="eq_fail_<?= $eid ?>_<?= $vessel_step_id ?>">Fail</label>
                                                            </div>
                                                            <div class="choice-pill">
                                                                <input id="eq_na_<?= $eid ?>_<?= $vessel_step_id ?>" type="radio" name="<?= $eqName ?>" value="n/a" <?= $savedStatus === 'n/a' ? 'checked' : '' ?>>
                                                                <label for="eq_na_<?= $eid ?>_<?= $vessel_step_id ?>">N/A</label>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Notes</label>
                                                            <input type="text" class="form-control"
                                                                   name="equipment_notes[<?= $eid ?>][<?= $vessel_step_id ?>]"
                                                                   value="<?= htmlspecialchars($savedNote) ?>"
                                                                   placeholder="Notes for this equipment item and step">
                                                        </div>

                                                        <div>
                                                            <label class="form-label">Supporting Regulation (optional)</label>
                                                            <input type="text" class="form-control"
                                                                   name="equipment_regulation[<?= $eid ?>][<?= $vessel_step_id ?>]"
                                                                   value="<?= htmlspecialchars($savedReg) ?>"
                                                                   placeholder="e.g. NFPA 10 monthly visual inspection requirement">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>

                    <div class="card run-section-card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="section-title mb-0">Inspection Steps</div>
                        </div>
                    </div>

                    <div class="accordion mb-4" id="stepsAccordion">
                        <?php foreach ($steps as $stepIndex => $step): ?>
                            <?php
                                $linkedRegs = [];

                                if ($source === 'vessel') {
                                    $num = (int)$step['step_number'];
                                    $vessel_step_id = (int)$step['vessel_step_id'];
                                    $mapped_master_step_id = !empty($step['master_step_id']) ? (int)$step['master_step_id'] : 0;

                                    $icr_step_id = $mapped_master_step_id > 0
                                        ? $mapped_master_step_id
                                        : ($icrIdByNumber[$num] ?? 0);

                                    if ($icr_step_id > 0) {
                                        $getStepRegs->execute([$icr_step_id]);
                                        $linkedRegs = $getStepRegs->fetchAll(PDO::FETCH_ASSOC);
                                    }

                                    $subs_to_render = [];
                                    $sub_origin = 'vessel';

                                    if ($hasVesselSubsteps && $vessel_step_id) {
                                        $getVesselSubs->execute([':vessel_step_id' => $vessel_step_id]);
                                        $subs_to_render = $getVesselSubs->fetchAll(PDO::FETCH_ASSOC);
                                    }

                                    if (empty($subs_to_render) && $icr_step_id > 0) {
                                        $getMasterSubs->execute([':icr_step_id' => $icr_step_id]);
                                        $subs_to_render = $getMasterSubs->fetchAll(PDO::FETCH_ASSOC);
                                        $sub_origin = 'master';
                                    }

                                    $step_description = $step['step_description'];
                                    $step_def_action  = '';

                                } else {
                                    $icr_step_id      = (int)$step['icr_step_id'];
                                    $num              = (int)$step['step_number'];
                                    $vessel_step_id   = 0;
                                    $step_description = $step['step_description'];
                                    $step_def_action  = $step['deficiency_action'] ?? '';

                                    $getMasterSubs->execute([':icr_step_id' => $icr_step_id]);
                                    $subs_to_render = $getMasterSubs->fetchAll(PDO::FETCH_ASSOC);
                                    $sub_origin = 'master';

                                    if ($icr_step_id > 0) {
                                        $getStepRegs->execute([$icr_step_id]);
                                        $linkedRegs = $getStepRegs->fetchAll(PDO::FETCH_ASSOC);
                                    }
                                }

                                $savedStepNotes = [];
                                if ($icr_step_id > 0) {
                                    $getSavedStepNotes->execute([$icr_id, $icr_step_id]);
                                    $savedStepNotes = $getSavedStepNotes->fetchAll(PDO::FETCH_ASSOC);
                                }

                                if ($source === 'vessel') {
                                    $stepFieldId = $vessel_step_id;
                                    $savedStepStatus = $existingStepStatus[$icr_step_id] ?? '';
                                    $savedStepNote   = $existingStepNotes[$icr_step_id] ?? '';
                                } else {
                                    $stepFieldId = $icr_step_id;
                                    $savedStepStatus = $existingStepStatus[$icr_step_id] ?? '';
                                    $savedStepNote   = $existingStepNotes[$icr_step_id] ?? '';
                                }

                                $collapseId = "stepCollapse_" . $num;
                                $headingId  = "stepHeading_" . $num;
                                $radioName  = "status[" . (int)$stepFieldId . "]";
                                $usesSubstepWizard = count($subs_to_render) > 1;
                            ?>
                            <div class="accordion-item step-card shadow-sm mb-3 stepper-step"
                                 data-step-index="<?= (int)$stepIndex ?>"
                                 data-step-validation="required"
                                 data-top-level-radio-name="<?= htmlspecialchars($radioName, ENT_QUOTES, 'UTF-8') ?>"
                                 data-substep-mode="<?= $usesSubstepWizard ? '1' : '0' ?>">
                                <h2 class="accordion-header" id="<?= $headingId ?>">
                                    <button class="accordion-button <?= $stepIndex > 0 ? 'collapsed' : '' ?>" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                            aria-expanded="<?= $stepIndex === 0 ? 'true' : 'false' ?>"
                                            aria-controls="<?= $collapseId ?>">
                                        <span class="step-number-badge"><?= $num ?></span>
                                        <span>
                                            <strong><?= htmlspecialchars($step_description) ?></strong>
                                            <?php if (!empty($savedStepStatus)): ?>
                                                <br><small class="text-muted">Saved status: <?= htmlspecialchars(strtoupper($savedStepStatus)) ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </button>
                                </h2>

                                <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $stepIndex === 0 ? 'show' : '' ?>"
                                     aria-labelledby="<?= $headingId ?>" data-bs-parent="#stepsAccordion">
                                    <div class="accordion-body">

                                        <div class="<?= $usesSubstepWizard ? 'step-parent-compact mb-3' : '' ?>">
                                            <?php if ($usesSubstepWizard): ?>
                                                <div class="substep-mode-header">
                                                    <div>
                                                        <div class="small text-uppercase text-muted">Parent Step</div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($step_description) ?></div>
                                                    </div>
                                                    <div class="small text-muted"><?= count($subs_to_render) ?> sub-steps</div>
                                                </div>
                                            <?php endif; ?>

                                        <?php if (!empty($step_def_action)): ?>
                                            <div class="alert alert-warning py-2">
                                                <strong>Deficiency Action</strong><br>
                                                <?= nl2br(htmlspecialchars($step_def_action)) ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <label class="form-label d-block">Result</label>
                                            <div class="choice-group">
                                                <div class="choice-pill">
                                                    <input id="step_pass_<?= $stepFieldId ?>" class="form-check-input" type="radio" name="<?= $radioName ?>" value="pass" <?= $savedStepStatus === 'pass' ? 'checked' : '' ?>>
                                                    <label for="step_pass_<?= $stepFieldId ?>">Pass</label>
                                                </div>
                                                <div class="choice-pill">
                                                    <input id="step_fail_<?= $stepFieldId ?>" class="form-check-input" type="radio" name="<?= $radioName ?>" value="fail" <?= $savedStepStatus === 'fail' ? 'checked' : '' ?>>
                                                    <label for="step_fail_<?= $stepFieldId ?>">Fail</label>
                                                </div>
                                                <div class="choice-pill">
                                                    <input id="step_na_<?= $stepFieldId ?>" class="form-check-input" type="radio" name="<?= $radioName ?>" value="n/a" <?= $savedStepStatus === 'n/a' ? 'checked' : '' ?>>
                                                    <label for="step_na_<?= $stepFieldId ?>">N/A</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Step Notes</label>
                                            <input type="text"
                                                class="form-control step-note-input"
                                                id="step_note_<?= (int)$stepFieldId ?>"
                                                data-save-checkbox-id="save_note_step_<?= (int)$stepFieldId ?>"
                                                name="notes[<?= (int)$stepFieldId ?>]"
                                                value="<?= htmlspecialchars($savedStepNote) ?>"
                                                placeholder="Notes for step <?= $num ?>">
                                        </div>

                                        <details class="optional-panel mb-3">
                                            <summary>More note options</summary>
                                            <?php if (!empty($savedStepNotes)): ?>
                                                <div class="mt-2 note-save-box">
                                                    <div class="small fw-semibold mb-2">Saved Notes for This Step</div>

                                                    <div class="row g-2">
                                                        <div class="col-md-9">
                                                            <select class="form-select form-select-sm saved-note-select"
                                                                    id="saved_step_note_select_<?= (int)$stepFieldId ?>">
                                                                <option value="">-- Select a saved note --</option>
                                                                <?php foreach ($savedStepNotes as $sn): ?>
                                                                    <option value="<?= h($sn['note_text']) ?>">
                                                                        <?= h(ucfirst($sn['note_type'])) ?> | Uses <?= (int)$sn['usage_count'] ?> |
                                                                        <?= h(mb_strimwidth($sn['note_text'], 0, 90, '...')) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-3 d-grid">
                                                            <button type="button"
                                                                    class="btn btn-outline-primary btn-sm"
                                                                    onclick="insertSavedNote(
                                                                        'saved_step_note_select_<?= (int)$stepFieldId ?>',
                                                                        'step_note_<?= (int)$stepFieldId ?>',
                                                                        'save_note_step_<?= (int)$stepFieldId ?>'
                                                                    )">
                                                                Insert Selected Note
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                        <div class="note-save-box mb-3">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox"
                                                       name="save_note_step[<?= (int)$stepFieldId ?>]"
                                                       value="1"
                                                       id="save_note_step_<?= (int)$stepFieldId ?>">
                                                <label class="form-check-label" for="save_note_step_<?= (int)$stepFieldId ?>">
                                                    Save this note for future use
                                                </label>
                                            </div>

                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small">Note Type</label>
                                                    <select class="form-select form-select-sm"
                                                            name="note_type_step[<?= (int)$stepFieldId ?>]">
                                                        <option value="general">General</option>
                                                        <option value="observation">Observation</option>
                                                        <option value="deficiency">Deficiency</option>
                                                        <option value="recommendation">Recommendation</option>
                                                        <option value="disclosure">Disclosure</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <input type="hidden" name="master_step_context[<?= (int)$stepFieldId ?>]" value="<?= (int)$icr_step_id ?>">
                                        </div>
                                        </details>

                                        <details class="optional-panel mb-3">
                                            <summary>Supporting Regulations</summary>
                                            <label class="form-label">Supporting Regulations</label>

                                            <?php if (!empty($linkedRegs)): ?>
                                                <div class="linked-reg-box mb-2">
                                                    <div class="small fw-semibold mb-2">Linked from Master ICR</div>

                                                    <?php foreach ($linkedRegs as $reg):
                                                        $collapseTextId = 'regtext_' . (int)$stepFieldId . '_' . md5(($reg['citation'] ?? '') . '|' . ($reg['paragraph_path'] ?? '') . '|' . ($reg['heading'] ?? ''));
                                                        $displayText = !empty($reg['paragraph_text']) ? $reg['paragraph_text'] : ($reg['section_text'] ?? '');
                                                    ?>
                                                        <div class="linked-reg-item">
                                                            <div>
                                                                <strong><?= htmlspecialchars($reg['citation']) ?></strong>
                                                                <?php if (!empty($reg['paragraph_path'])): ?>
                                                                    <span class="badge bg-info text-dark ms-1">Paragraph <?= htmlspecialchars($reg['paragraph_path']) ?></span>
                                                                <?php endif; ?>
                                                            </div>

                                                            <?php if (!empty($reg['heading'])): ?>
                                                                <div class="small text-muted"><?= htmlspecialchars($reg['heading']) ?></div>
                                                            <?php endif; ?>

                                                            <div class="small text-secondary mb-2">
                                                                Type: <?= htmlspecialchars(ucfirst($reg['reference_type'] ?? 'requirement')) ?>
                                                            </div>

                                                            <?php if (!empty($displayText)): ?>
                                                                <button class="btn btn-sm btn-outline-secondary mb-2" type="button"
                                                                        data-bs-toggle="collapse"
                                                                        data-bs-target="#<?= htmlspecialchars($collapseTextId) ?>"
                                                                        aria-expanded="false"
                                                                        aria-controls="<?= htmlspecialchars($collapseTextId) ?>">
                                                                    Show Text
                                                                </button>

                                                                <div class="collapse" id="<?= htmlspecialchars($collapseTextId) ?>">
                                                                    <div class="linked-reg-text"><?= htmlspecialchars($displayText) ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small mb-2">No linked master regulations for this step.</div>
                                            <?php endif; ?>

                                            <input type="hidden"
                                                   name="linked_regulations_payload[<?= (int)$stepFieldId ?>]"
                                                   value="<?= regulationPayloadForHiddenField($linkedRegs) ?>">

                                            <input type="text" class="form-control"
                                                   name="regulation[<?= (int)$stepFieldId ?>]"
                                                   placeholder="Optional additional citation or note">
                                            <div class="help-text mt-1">
                                                Linked master regulations are shown above. This field can be used for an additional step-specific citation if needed.
                                            </div>
                                        </details>

                                        <details class="optional-panel mb-3">
                                            <summary>Add Photo</summary>
                                            <label class="form-label">Photo (optional)</label>
                                            <input type="file" class="form-control"
                                                   name="photo_step[<?= (int)$stepFieldId ?>]"
                                                   accept="image/*">
                                            <div class="help-text mt-1">Attach a helpful photo for this step.</div>
                                        </details>
                                        </div>

                                        <?php if (!empty($subs_to_render)): ?>
                                            <div class="mt-4 substep-mode" data-substep-mode="<?= $usesSubstepWizard ? '1' : '0' ?>">
                                                <div class="substep-mode-header">
                                                    <div class="section-title mb-0">Sub-steps</div>
                                                    <?php if ($usesSubstepWizard): ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <div class="substep-counter" data-substep-counter>Sub-step 1 of <?= count($subs_to_render) ?></div>
                                                            <div class="substep-nav gap-2">
                                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-substep-prev>Previous Sub-step</button>
                                                                <button type="button" class="btn btn-outline-primary btn-sm" data-substep-next>Next Sub-step</button>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <?php foreach ($subs_to_render as $subIndex => $sub): ?>
                                                    <?php
                                                    if ($sub_origin === 'vessel') {
                                                        $subId = (int)$sub['substep_id'];
                                                        $code  = strtoupper((string)$sub['substep_code']);
                                                        $sname = "sub_status_vessel[$subId]";
                                                        $nname = "sub_notes_vessel[$subId]";
                                                        $desc  = $sub['description'];
                                                        $def   = $hasVesselSubDef ? ($sub['deficiency_action'] ?? null) : null;
                                                        $reg_name  = "regulation_sub_vessel[$subId]";
                                                        $file_name = "photo_sub_vessel[$subId]";

                                                        $savedSubStatus = $existingSubStatusVessel[$subId] ?? '';
                                                        $savedSubNote   = $existingSubNotesVessel[$subId] ?? '';
                                                        $masterSubstepContext = !empty($sub['master_substep_id']) ? (int)$sub['master_substep_id'] : 0;

                                                    } else {
                                                        $subId = (int)$sub['substep_id'];
                                                        $code  = strtoupper((string)$sub['substep_code']);
                                                        $sname = "sub_status[$subId]";
                                                        $nname = "sub_notes[$subId]";
                                                        $desc  = $sub['description'];
                                                        $def   = $sub['deficiency_action'] ?? null;
                                                        $reg_name  = "regulation_sub[$subId]";
                                                        $file_name = "photo_sub[$subId]";

                                                        $savedSubStatus = $existingSubStatusMaster[$subId] ?? '';
                                                        $savedSubNote   = $existingSubNotesMaster[$subId] ?? '';
                                                        $masterSubstepContext = $subId;
                                                    }

                                                    $savedSubstepNotes = [];
                                                        if (!empty($masterSubstepContext)) {
                                                            $getSavedSubstepNotes->execute([$icr_id, (int)$masterSubstepContext]);
                                                            $savedSubstepNotes = $getSavedSubstepNotes->fetchAll(PDO::FETCH_ASSOC);
                                                        }

                                                    $linkedSubRegs = [];
                                                        if (!empty($masterSubstepContext)) {
                                                            $getSubstepRegs->execute([(int)$masterSubstepContext]);
                                                            $linkedSubRegs = $getSubstepRegs->fetchAll(PDO::FETCH_ASSOC);
                                                        }    
                                                    ?>
                                                    <div class="substep-card mb-3 substep-unit" data-substep-index="<?= (int)$subIndex ?>">
                                                        <div class="fw-semibold mb-2">
                                                            <span class="mono"><?= $num . htmlspecialchars($code) ?></span>
                                                            <?= htmlspecialchars($desc) ?>
                                                        </div>

                                                        <?php if (!empty($def)): ?>
                                                            <div class="alert alert-warning py-2 mb-3">
                                                                <strong>Deficiency Action</strong><br>
                                                                <?= nl2br(htmlspecialchars($def)) ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="mb-3">
                                                            <label class="form-label d-block">Result</label>
                                                            <div class="choice-group">
                                                                <div class="choice-pill">
                                                                    <input id="sub_pass_<?= $subId ?>" class="form-check-input" type="radio" name="<?= $sname ?>" value="pass" <?= $savedSubStatus === 'pass' ? 'checked' : '' ?>>
                                                                    <label for="sub_pass_<?= $subId ?>">Pass</label>
                                                                </div>
                                                                <div class="choice-pill">
                                                                    <input id="sub_fail_<?= $subId ?>" class="form-check-input" type="radio" name="<?= $sname ?>" value="fail" <?= $savedSubStatus === 'fail' ? 'checked' : '' ?>>
                                                                    <label for="sub_fail_<?= $subId ?>">Fail</label>
                                                                </div>
                                                                <div class="choice-pill">
                                                                    <input id="sub_na_<?= $subId ?>" class="form-check-input" type="radio" name="<?= $sname ?>" value="na" <?= $savedSubStatus === 'na' ? 'checked' : '' ?>>
                                                                    <label for="sub_na_<?= $subId ?>">N/A</label>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Sub-step Notes</label>
                                                            <input type="text"
                                                                class="form-control substep-note-input"
                                                                id="substep_note_<?= (int)$subId ?>"
                                                                data-save-checkbox-id="save_note_sub_<?= (int)$subId ?>"
                                                                name="<?= $nname ?>"
                                                                value="<?= htmlspecialchars($savedSubNote) ?>"
                                                                placeholder="Notes for sub-step <?= $num . htmlspecialchars($code) ?>">
                                                        </div>

                                                        <details class="optional-panel mb-3">
                                                            <summary>More note options</summary>
                                                            <?php if (!empty($savedSubstepNotes)): ?>
                                                                <div class="mt-2 note-save-box">
                                                                    <div class="small fw-semibold mb-2">Saved Notes for This Sub-step</div>

                                                                    <div class="row g-2">
                                                                        <div class="col-md-9">
                                                                            <select class="form-select form-select-sm saved-note-select"
                                                                                    id="saved_substep_note_select_<?= (int)$subId ?>">
                                                                                <option value="">-- Select a saved note --</option>
                                                                                <?php foreach ($savedSubstepNotes as $sn): ?>
                                                                                    <option value="<?= h($sn['note_text']) ?>">
                                                                                        <?= h(ucfirst($sn['note_type'])) ?> | Uses <?= (int)$sn['usage_count'] ?> |
                                                                                        <?= h(mb_strimwidth($sn['note_text'], 0, 90, '...')) ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-md-3 d-grid">
                                                                            <button type="button"
                                                                                    class="btn btn-outline-primary btn-sm"
                                                                                    onclick="insertSavedNote(
                                                                                        'saved_substep_note_select_<?= (int)$subId ?>',
                                                                                        'substep_note_<?= (int)$subId ?>',
                                                                                        'save_note_sub_<?= (int)$subId ?>'
                                                                                    )">
                                                                                Insert Selected Note
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="note-save-box mb-3">
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="checkbox"
                                                                       name="save_note_sub[<?= (int)$subId ?>]"
                                                                       value="1"
                                                                       id="save_note_sub_<?= (int)$subId ?>">
                                                                <label class="form-check-label" for="save_note_sub_<?= (int)$subId ?>">
                                                                    Save this sub-step note for future use
                                                                </label>
                                                            </div>

                                                            <div class="row g-2">
                                                                <div class="col-md-4">
                                                                    <label class="form-label small">Note Type</label>
                                                                    <select class="form-select form-select-sm"
                                                                            name="note_type_sub[<?= (int)$subId ?>]">
                                                                        <option value="general">General</option>
                                                                        <option value="observation">Observation</option>
                                                                        <option value="deficiency">Deficiency</option>
                                                                        <option value="recommendation">Recommendation</option>
                                                                        <option value="disclosure">Disclosure</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <input type="hidden" name="master_substep_context[<?= (int)$subId ?>]" value="<?= (int)$masterSubstepContext ?>">
                                                            </div>
                                                        </details>

                                                      <details class="optional-panel mb-3">
                                                        <summary>Supporting Regulations</summary>
                                                        <label class="form-label">Supporting Regulations</label>

                                                        <?php if (!empty($linkedSubRegs)): ?>
                                                            <div class="linked-reg-box mb-2">
                                                                <div class="small fw-semibold mb-2">Linked from Master Sub-step</div>

                                                                <?php foreach ($linkedSubRegs as $reg): 
                                                                    $collapseTextId = 'subregtext_' . (int)$subId . '_' . md5(($reg['citation'] ?? '') . '|' . ($reg['paragraph_path'] ?? '') . '|' . ($reg['heading'] ?? ''));
                                                                    $displayText = !empty($reg['paragraph_text']) ? $reg['paragraph_text'] : ($reg['section_text'] ?? '');
                                                                ?>
                                                                    <div class="linked-reg-item">
                                                                        <div>
                                                                            <strong><?= htmlspecialchars($reg['citation']) ?></strong>
                                                                            <?php if (!empty($reg['paragraph_path'])): ?>
                                                                                <span class="badge bg-info text-dark ms-1">
                                                                                    Paragraph <?= htmlspecialchars($reg['paragraph_path']) ?>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <?php if (!empty($reg['heading'])): ?>
                                                                            <div class="small text-muted"><?= htmlspecialchars($reg['heading']) ?></div>
                                                                        <?php endif; ?>

                                                                        <div class="small text-secondary mb-2">
                                                                            Type: <?= htmlspecialchars(ucfirst($reg['reference_type'] ?? 'requirement')) ?>
                                                                        </div>

                                                                        <?php if (!empty($displayText)): ?>
                                                                            <button class="btn btn-sm btn-outline-secondary mb-2"
                                                                                    type="button"
                                                                                    data-bs-toggle="collapse"
                                                                                    data-bs-target="#<?= htmlspecialchars($collapseTextId) ?>"
                                                                                    aria-expanded="false"
                                                                                    aria-controls="<?= htmlspecialchars($collapseTextId) ?>">
                                                                                Show Text
                                                                            </button>

                                                                            <div class="collapse" id="<?= htmlspecialchars($collapseTextId) ?>">
                                                                                <div class="linked-reg-text"><?= htmlspecialchars($displayText) ?></div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-muted small mb-2">No linked master regulations for this sub-step.</div>
                                                        <?php endif; ?>

                                                        <input type="hidden"
                                                            name="<?= $sub_origin === 'vessel'
                                                                    ? 'linked_regulations_payload_sub_vessel[' . (int)$subId . ']'
                                                                    : 'linked_regulations_payload_sub[' . (int)$subId . ']' ?>"
                                                            value="<?= regulationPayloadForHiddenField($linkedSubRegs) ?>">

                                                        <input type="text"
                                                            class="form-control"
                                                            name="<?= $reg_name ?>"
                                                            placeholder="Optional additional citation or note">
                                                        <div class="help-text mt-1">
                                                            Linked master regulations are shown above. This field can be used for an additional sub-step-specific citation if needed.
                                                        </div>
                                                    </details>

                                                    <details class="optional-panel">
                                                        <summary>Add Photo</summary>
                                                        <label class="form-label">Photo (optional)</label>
                                                        <input type="file"
                                                            class="form-control"
                                                            name="<?= $file_name ?>"
                                                            accept="image/*">
                                                    </details>  
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

                <?php if ($isDrill): ?>
                    <div class="card run-section-card shadow-sm mb-4 stepper-final-support">
                        <div class="card-body">
                            <div class="section-title">Crew Present</div>
                            <p class="text-muted mb-3">Check all crew members who participated in this drill.</p>

                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
                                <?php if (!empty($crew_list)): ?>
                                    <?php foreach ($crew_list as $c):
                                        $cid  = (int)$c['id'];
                                        $name = htmlspecialchars(trim(($c['fName'] ?? '') . ' ' . ($c['lName'] ?? '')));
                                        $role = htmlspecialchars($c['role'] ?? '');
                                    ?>
                                    <div class="col">
                                        <div class="form-check p-2 border rounded bg-white">
                                            <input class="form-check-input" type="checkbox" name="crew_present[]" value="<?= $cid ?>" id="crew_<?= $cid ?>">
                                            <label class="form-check-label" for="crew_<?= $cid ?>">
                                                <?= $name ?><?php if ($role !== ''): ?> (<?= $role ?>)<?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col"><em class="text-muted">No crew assigned to this vessel.</em></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if ($steps): ?>
<div class="sticky-run-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <div class="stepper-nav-group gap-2">
                <button type="button" class="btn btn-outline-secondary" data-stepper-prev>
                    Previous
                </button>
                <button type="button" class="btn btn-primary" data-stepper-next>
                    Next
                </button>
            </div>

            <button type="submit" form="icrForm" class="btn btn-outline-primary stepper-draft"
                    onclick="document.getElementById('save_mode').value='draft';">
                Save Draft
            </button>

            <button type="submit" form="icrForm" class="btn btn-primary stepper-finalize"
                    onclick="document.getElementById('save_mode').value='final';">
                Finalize Run
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.getElementById('completed_on')?.addEventListener('change', function () {
    const input = this;
    const today = input.getAttribute('max');
    if (input.value > today) input.value = today;
});
</script>

<script>
document.getElementById('icrForm')?.addEventListener('keydown', function(e) {
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if (e.key === 'Enter' && tag !== 'textarea') {
        e.preventDefault();
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('icrForm');
    if (!form) {
        return;
    }

    const steps = Array.prototype.slice.call(form.querySelectorAll('.stepper-step'));
    if (!steps.length) {
        return;
    }

    document.body.classList.add('icr-stepper-enhanced');

    const progressText = document.getElementById('stepperProgressText');
    const progressMeta = document.getElementById('stepperProgressMeta');
    const progressBar = document.getElementById('stepperProgressBar');
    const messageBox = document.getElementById('stepperMessage');
    const prevButtons = Array.prototype.slice.call(document.querySelectorAll('[data-stepper-prev]'));
    const nextButtons = Array.prototype.slice.call(document.querySelectorAll('[data-stepper-next]'));
    const finalizeButtons = Array.prototype.slice.call(document.querySelectorAll('.stepper-finalize'));
    const finalSupportBlocks = Array.prototype.slice.call(document.querySelectorAll('.stepper-final-support'));
    const totalSteps = steps.length;
    const isEquipmentDriven = form.querySelector('input[name="is_equipment_driven"]')?.value === '1';
    let activeIndex = 0;

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

    function getTopLevelRadioName(step) {
        return step ? String(step.getAttribute('data-top-level-radio-name') || '') : '';
    }

    function hasCheckedRadioForName(name) {
        if (!name) {
            return true;
        }

        const radios = form.querySelectorAll('input[type="radio"]');
        for (let i = 0; i < radios.length; i += 1) {
            if (radios[i].name === name && radios[i].checked) {
                return true;
            }
        }

        return false;
    }

    function isStepComplete(step) {
        if (!step) {
            return true;
        }

        if ((step.getAttribute('data-step-validation') || 'none') !== 'required') {
            return true;
        }

        return hasCheckedRadioForName(getTopLevelRadioName(step));
    }

    function isSubstepMode(step) {
        return step && step.getAttribute('data-substep-mode') === '1';
    }

    function getSubstepUnits(step) {
        return step ? Array.prototype.slice.call(step.querySelectorAll('.substep-unit')) : [];
    }

    function findFirstIncompleteSubstepIndex(step) {
        const units = getSubstepUnits(step);
        for (let i = 0; i < units.length; i += 1) {
            if (!units[i].querySelector('input[type="radio"]:checked')) {
                return i;
            }
        }
        return 0;
    }

    function syncSubstepMode(step) {
        if (!isSubstepMode(step)) {
            return;
        }

        const units = getSubstepUnits(step);
        if (!units.length) {
            return;
        }

        let activeSubstepIndex = parseInt(step.dataset.activeSubstepIndex || '', 10);
        if (Number.isNaN(activeSubstepIndex)) {
            activeSubstepIndex = findFirstIncompleteSubstepIndex(step);
        }

        if (activeSubstepIndex < 0) {
            activeSubstepIndex = 0;
        }
        if (activeSubstepIndex >= units.length) {
            activeSubstepIndex = units.length - 1;
        }

        step.dataset.activeSubstepIndex = String(activeSubstepIndex);

        units.forEach(function (unit, index) {
            unit.classList.toggle('is-active', index === activeSubstepIndex);
        });

        const counter = step.querySelector('[data-substep-counter]');
        if (counter) {
            counter.textContent = 'Sub-step ' + (activeSubstepIndex + 1) + ' of ' + units.length;
        }

        const prev = step.querySelector('[data-substep-prev]');
        const next = step.querySelector('[data-substep-next]');
        if (prev) {
            prev.disabled = activeSubstepIndex === 0;
        }
        if (next) {
            next.disabled = activeSubstepIndex === units.length - 1;
        }
    }

    function syncAccordionState(step, isActive) {
        const collapse = step.querySelector('.accordion-collapse');
        const button = step.querySelector('.accordion-button');

        if (collapse) {
            collapse.classList.toggle('show', isActive);
        }

        if (button) {
            button.classList.toggle('collapsed', !isActive);
            button.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        }
    }

    function updateProgress() {
        const displayIndex = activeIndex + 1;
        const width = (displayIndex / totalSteps) * 100;

        if (progressText) {
            progressText.textContent = (isEquipmentDriven ? 'Equipment ' : 'Step ') + displayIndex + ' of ' + totalSteps;
        }

        if (progressMeta) {
            progressMeta.textContent = isEquipmentDriven ? 'Equipment Inspection' : 'Inspection Step';
        }

        if (progressBar) {
            progressBar.style.width = width + '%';
            progressBar.setAttribute('aria-valuenow', String(displayIndex));
        }

        prevButtons.forEach(function (button) {
            button.disabled = activeIndex === 0;
        });

        nextButtons.forEach(function (button) {
            button.style.display = activeIndex === totalSteps - 1 ? 'none' : '';
        });

        finalizeButtons.forEach(function (button) {
            button.classList.toggle('is-final-step', activeIndex === totalSteps - 1);
        });

        finalSupportBlocks.forEach(function (block) {
            block.classList.toggle('is-visible', activeIndex === totalSteps - 1);
        });
    }

    function focusStep(step) {
        if (!step) {
            return;
        }

        step.scrollIntoView({ behavior: 'auto', block: 'start' });
    }

    function setActiveStep(index) {
        if (index < 0 || index >= totalSteps) {
            return;
        }

        activeIndex = index;

        steps.forEach(function (step, stepIndex) {
            const isActive = stepIndex === activeIndex;
            step.classList.toggle('is-active', isActive);
            syncAccordionState(step, isActive);
            if (isActive) {
                syncSubstepMode(step);
            }
        });

        clearMessage();
        updateProgress();
        focusStep(steps[activeIndex]);
    }

    function validateCurrentStep() {
        if (isStepComplete(steps[activeIndex])) {
            clearMessage();
            return true;
        }

        showMessage('Select a top-level result for this step before continuing.');
        return false;
    }

    function findFirstIncompleteStepIndex() {
        for (let i = 0; i < steps.length; i += 1) {
            if (!isStepComplete(steps[i])) {
                return i;
            }
        }

        return -1;
    }

    prevButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (activeIndex > 0) {
                setActiveStep(activeIndex - 1);
            }
        });
    });

    nextButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!validateCurrentStep()) {
                return;
            }

            if (activeIndex < totalSteps - 1) {
                setActiveStep(activeIndex + 1);
            }
        });
    });

    steps.forEach(function (step) {
        if (isSubstepMode(step)) {
            const prev = step.querySelector('[data-substep-prev]');
            const next = step.querySelector('[data-substep-next]');

            if (prev) {
                prev.addEventListener('click', function () {
                    const current = parseInt(step.dataset.activeSubstepIndex || '0', 10) || 0;
                    step.dataset.activeSubstepIndex = String(Math.max(0, current - 1));
                    syncSubstepMode(step);
                });
            }

            if (next) {
                next.addEventListener('click', function () {
                    const units = getSubstepUnits(step);
                    const current = parseInt(step.dataset.activeSubstepIndex || '0', 10) || 0;
                    step.dataset.activeSubstepIndex = String(Math.min(units.length - 1, current + 1));
                    syncSubstepMode(step);
                });
            }
        }

        const radioName = getTopLevelRadioName(step);
        if (!radioName) {
            return;
        }

        const radios = form.querySelectorAll('input[type="radio"]');
        Array.prototype.forEach.call(radios, function (radio) {
            if (radio.name !== radioName) {
                return;
            }

            radio.addEventListener('change', function () {
                if (steps[activeIndex] === step && isStepComplete(step)) {
                    clearMessage();
                }
            });
        });
    });

    form.addEventListener('submit', function (event) {
        const saveMode = document.getElementById('save_mode')?.value || 'draft';
        if (saveMode !== 'final') {
            return;
        }

        const firstIncomplete = findFirstIncompleteStepIndex();
        if (firstIncomplete !== -1) {
            event.preventDefault();
            setActiveStep(firstIncomplete);
            showMessage('Complete each top-level inspection step before finalizing.');
        }
    });

    setActiveStep(0);
});
</script>

<script>
function insertSavedNote(selectId, inputId, saveCheckboxId = null) {
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    const saveBox = saveCheckboxId ? document.getElementById(saveCheckboxId) : null;

    if (!select || !input) return;
    if (!select.value) return;

    const selectedText = select.value.trim();
    const existingText = input.value.trim();

    if (existingText === '') {
        input.value = selectedText;
    } else {
        input.value = existingText + ' ' + selectedText;
    }

    if (saveBox) {
        // Inserted from library: default to not saving again
        saveBox.checked = false;
        saveBox.disabled = true;
        saveBox.dataset.insertedFromLibrary = '1';
        saveBox.dataset.originalInsertedText = input.value.trim();
    }

    input.focus();
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

document.addEventListener('input', function (e) {
    const input = e.target;
    if (!input || !(input.classList.contains('step-note-input') || input.classList.contains('substep-note-input'))) {
        return;
    }

    const saveCheckboxId = input.dataset.saveCheckboxId;
    if (!saveCheckboxId) return;

    const saveBox = document.getElementById(saveCheckboxId);
    if (!saveBox) return;

    if (saveBox.dataset.insertedFromLibrary === '1') {
        const originalInsertedText = (saveBox.dataset.originalInsertedText || '').trim();
        const currentText = input.value.trim();

        if (currentText !== originalInsertedText) {
            // User changed the inserted note, so allow saving this variant
            saveBox.disabled = false;
            saveBox.dataset.insertedFromLibrary = '0';
        } else {
            // Still identical to inserted library note: keep locked out
            saveBox.checked = false;
            saveBox.disabled = true;
        }
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
