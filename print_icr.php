<?php
require 'db_connect.php';
require 'session_check.php';

$vessel_id = intval($_GET['vessel_id'] ?? 0);
$icr_id    = intval($_GET['icr_id'] ?? 0);

if ($vessel_id <= 0 || $icr_id <= 0) {
    die("❌ Missing vessel_id or icr_id.");
}

/* Fetch vessel + ICR info (+ drill_type + drill template fields) */
$hdr = $pdo->prepare("
    SELECT
        v.vesselName,
        i.icr_number,
        i.title,
        i.reference_text,
        i.frequency,
        i.drill_type,
        vi.vessel_icr_id,

        dt.regulatory_references,
        dt.drill_name,
        dt.operating_condition,
        dt.purpose,
        dt.performance_objective,
        dt.safety_limitations,
        dt.scenario_description,
        dt.roles_captain,
        dt.roles_crew,
        dt.evaluation_guidance

    FROM vessel_icrs vi
    JOIN vessels v ON vi.vessel_id = v.vessel_id
    JOIN icrs    i ON vi.icr_id    = i.icr_id
    LEFT JOIN icr_drill_templates dt ON dt.icr_id = i.icr_id
    WHERE vi.vessel_id = ? AND vi.icr_id = ?
    LIMIT 1
");
$hdr->execute([$vessel_id, $icr_id]);
$info = $hdr->fetch(PDO::FETCH_ASSOC);
if (!$info) die("❌ ICR assignment not found for this vessel.");

$vessel_icr_id = (int)$info['vessel_icr_id'];
$isDrill = !empty($info['drill_type']);

/* Get vessel-specific steps */
$stepsStmt = $pdo->prepare("
    SELECT step_id, step_number, step_description, deficiency_action
    FROM vessel_icr_steps
    WHERE vessel_icr_id = ?
    ORDER BY step_number
");
$stepsStmt->execute([$vessel_icr_id]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

/* Gather vessel substeps for all steps */
$subs_by_step = [];
try {
    $subVStmt = $pdo->prepare("
        SELECT substep_id, vessel_step_id, substep_code, description, deficiency_action
        FROM vessel_icr_substeps
        WHERE vessel_step_id IN (
            SELECT step_id FROM vessel_icr_steps WHERE vessel_icr_id = ?
        )
        ORDER BY substep_code
    ");
    $subVStmt->execute([$vessel_icr_id]);
    while ($r = $subVStmt->fetch(PDO::FETCH_ASSOC)) {
        $subs_by_step[(int)$r['vessel_step_id']][] = $r;
    }
} catch (Throwable $e) {
    // vessel substeps table may not exist; ignore
}

/* Fallback to master substeps for any step that has none (match by step_number) */
$needFallback = [];
foreach ($steps as $s) {
    $sid = (int)$s['step_id'];
    if (empty($subs_by_step[$sid])) $needFallback[] = $sid;
}

if ($needFallback) {
    // map vessel step_id -> step_number
    $numMap = [];
    foreach ($steps as $s) $numMap[(int)$s['step_id']] = (int)$s['step_number'];

    // get icr step ids by number
    $mapStmt = $pdo->prepare("SELECT step_id, step_number FROM icr_steps WHERE icr_id = ? ORDER BY step_number");
    $mapStmt->execute([$icr_id]);
    $icrByNum = [];
    while ($r = $mapStmt->fetch(PDO::FETCH_ASSOC)) {
        $icrByNum[(int)$r['step_number']] = (int)$r['step_id'];
    }

    // fetch master substeps per mapped icr step
    $getMS = $pdo->prepare("
        SELECT substep_id, substep_code, description, deficiency_action
        FROM icr_substeps
        WHERE step_id = ?
        ORDER BY substep_code
    ");

    foreach ($needFallback as $vsid) {
        $num = $numMap[$vsid] ?? null;
        if ($num !== null && isset($icrByNum[$num])) {
            $getMS->execute([$icrByNum[$num]]);
            $rows = $getMS->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                // adapt to vessel shape for uniform rendering
                foreach ($rows as $r) {
                    $subs_by_step[$vsid][] = [
                        'substep_id'        => null, // from master, not vessel table
                        'vessel_step_id'    => $vsid,
                        'substep_code'      => $r['substep_code'],
                        'description'       => $r['description'],
                        'deficiency_action' => $r['deficiency_action'] ?? null,
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Print ICR – <?= htmlspecialchars($info['icr_number']) ?> – <?= htmlspecialchars($info['title']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    :root {
        --fg: #111;
        --muted: #6c757d;
        --line: #dee2e6;
    }
    body { color: var(--fg); }
    .no-print { margin-bottom: 12px; }
    .meta small { color: var(--muted); }
    .header-box { border:1px solid var(--line); border-radius:8px; padding:12px; }
    .legend { font-size: 12px; color: var(--muted); }
    .tickrow { white-space:nowrap; font-size: 12px; }
    .tick { display:inline-block; width:12px; height:12px; border:1px solid #000; margin:0 6px 0 10px; vertical-align:middle; }
    table.sheet { width:100%; border-collapse:collapse; }
    .sheet th, .sheet td { border:1px solid var(--line); padding:6px 8px; vertical-align:top; }
    .sheet th { background:#f7f7f7; }
    .step-num { width:7%; font-family: ui-monospace, Menlo, Consolas, monospace; font-weight:600; }
    .desc { width:53%; }
    .status { width:16%; }
    .notes { width:24%; }
    .sub { background:#fafafa; }
    .badge-def { font-size:11px; background:#fff3cd; border:1px solid #ffe69c; color:#8a6d3b; border-radius:6px; padding:2px 6px; }
    .def-body { font-size: 12px; color: #6b5d37; margin-top: 4px; }
    .sign { margin-top:18px; }
    .sign .line { border-bottom:1px solid #000; height:24px; }
    .sign small { color: var(--muted); }

    .drill-box { border:1px solid var(--line); border-radius:8px; padding:12px; margin-top: 12px; }
    .drill-box h5 { margin-bottom: 10px; }
    .drill-label { font-weight: 600; }
    .drill-block { margin-bottom: 10px; }
    .drill-cols { display: flex; gap: 16px; }
    .drill-col { flex: 1; }

    @media print {
        .no-print { display:none !important; }
        body { font-size: 12px; }
        .header-box { border:none; padding:0; }
        .drill-box { border:none; padding:0; }
        .sheet tr { page-break-inside: avoid; }
        .sign { page-break-inside: avoid; }
        @page { margin: 12mm; }
    }
</style>
</head>
<body class="p-4">

<!-- Toolbar (hidden on print) -->
<div class="no-print d-flex gap-2">
  <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#icrs" class="btn btn-secondary">← Back to Vessel Dashboard</a>
  <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
  <button class="btn btn-outline-dark" onclick="window.close()">✖ Close</button>
</div>

<!-- Header -->
<div class="header-box">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h4 class="mb-1">Inspection Criteria Report</h4>
      <div class="meta">
        <div><strong>Vessel:</strong> <?= htmlspecialchars($info['vesselName']) ?></div>
        <div>
          <strong>ICR:</strong>
          <span class="me-2"><?= htmlspecialchars($info['icr_number']) ?></span>
          <?= htmlspecialchars($info['title']) ?>
          <?php if (!empty($info['drill_type'])): ?>
            <span class="badge bg-danger ms-2">DRILL: <?= htmlspecialchars($info['drill_type']) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($info['frequency'])): ?>
          <div><strong>Frequency:</strong> <?= htmlspecialchars($info['frequency']) ?></div>
        <?php endif; ?>
        <?php if (!empty($info['reference_text'])): ?>
          <div><strong>Reference:</strong> <?= nl2br(htmlspecialchars($info['reference_text'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <div style="min-width:280px; margin-left:24px;">
      <div><strong>Date:</strong> ______________________</div>
      <div><strong>Inspector:</strong> __________________</div>
      <div class="legend mt-2">
        Mark one per row:
        <span class="tick"></span> PASS
        <span class="tick"></span> FAIL
        <span class="tick"></span> N/A
      </div>
    </div>
  </div>
</div>

<!-- NEW: Drill Template print block -->
<?php if ($isDrill): ?>
  <div class="drill-box">
    <h5>🚨 Drill Template</h5>

    <?php if (!empty($info['regulatory_references'])): ?>
      <div class="drill-block"><span class="drill-label">Regulatory References:</span> <?= htmlspecialchars($info['regulatory_references']) ?></div>
    <?php endif; ?>

    <?php if (!empty($info['drill_name'])): ?>
      <div class="drill-block"><span class="drill-label">Drill Name / Description:</span> <?= htmlspecialchars($info['drill_name']) ?></div>
    <?php endif; ?>

    <?php if (!empty($info['operating_condition'])): ?>
      <div class="drill-block"><span class="drill-label">Operating Condition Options:</span> <?= htmlspecialchars($info['operating_condition']) ?></div>
    <?php endif; ?>

    <?php if (!empty($info['purpose'])): ?>
      <div class="drill-block">
        <div class="drill-label">Purpose of Drill</div>
        <div><?= nl2br(htmlspecialchars($info['purpose'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($info['performance_objective'])): ?>
      <div class="drill-block">
        <div class="drill-label">Performance Objective</div>
        <div><?= nl2br(htmlspecialchars($info['performance_objective'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($info['safety_limitations'])): ?>
      <div class="drill-block">
        <div class="drill-label">Drill Conditions & Safety Limitations</div>
        <div><?= nl2br(htmlspecialchars($info['safety_limitations'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($info['scenario_description'])): ?>
      <div class="drill-block">
        <div class="drill-label">Scenario Description</div>
        <div><?= nl2br(htmlspecialchars($info['scenario_description'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($info['roles_captain']) || !empty($info['roles_crew'])): ?>
      <div class="drill-block drill-cols">
        <?php if (!empty($info['roles_captain'])): ?>
          <div class="drill-col">
            <div class="drill-label">Roles & Responsibilities – Captain</div>
            <div><?= nl2br(htmlspecialchars($info['roles_captain'])) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!empty($info['roles_crew'])): ?>
          <div class="drill-col">
            <div class="drill-label">Roles & Responsibilities – Crew</div>
            <div><?= nl2br(htmlspecialchars($info['roles_crew'])) ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($info['evaluation_guidance'])): ?>
      <div class="drill-block">
        <div class="drill-label">Evaluation & Corrective Actions Guidance</div>
        <div><?= nl2br(htmlspecialchars($info['evaluation_guidance'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (
      empty($info['regulatory_references']) &&
      empty($info['drill_name']) &&
      empty($info['operating_condition']) &&
      empty($info['purpose']) &&
      empty($info['performance_objective']) &&
      empty($info['safety_limitations']) &&
      empty($info['scenario_description']) &&
      empty($info['roles_captain']) &&
      empty($info['roles_crew']) &&
      empty($info['evaluation_guidance'])
    ): ?>
      <div class="text-muted">
        This ICR is marked as a drill, but no drill template header fields were found.
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Sheet -->
<table class="sheet mt-3">
  <thead>
    <tr>
      <th class="step-num">#</th>
      <th class="desc">Description</th>
      <th class="status">Status</th>
      <th class="notes">Comments / Notes</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($steps as $step): ?>
      <tr>
        <td class="step-num"><?= (int)$step['step_number'] ?></td>
        <td class="desc">
          <div><strong><?= nl2br(htmlspecialchars($step['step_description'])) ?></strong></div>
          <?php if (!empty($step['deficiency_action'])): ?>
            <div class="mt-1">
              <span class="badge-def">Deficiency Action</span>
              <div class="def-body"><?= nl2br(htmlspecialchars($step['deficiency_action'])) ?></div>
            </div>
          <?php endif; ?>
        </td>
        <td class="status">
          <div class="tickrow"><span class="tick"></span> PASS</div>
          <div class="tickrow"><span class="tick"></span> FAIL</div>
          <div class="tickrow"><span class="tick"></span> N/A</div>
        </td>
        <td class="notes"></td>
      </tr>

      <?php
        $vsid = (int)$step['step_id'];
        $subs = $subs_by_step[$vsid] ?? [];
      ?>
      <?php if ($subs): ?>
        <?php foreach ($subs as $sub): ?>
          <tr class="sub">
            <td class="step-num">
              <?= (int)$step['step_number'] . htmlspecialchars(strtoupper($sub['substep_code'])) ?>
            </td>
            <td class="desc">
              <?= nl2br(htmlspecialchars($sub['description'])) ?>
              <?php if (!empty($sub['deficiency_action'])): ?>
                <div class="mt-1">
                  <span class="badge-def">Deficiency Action</span>
                  <div class="def-body"><?= nl2br(htmlspecialchars($sub['deficiency_action'])) ?></div>
                </div>
              <?php endif; ?>
            </td>
            <td class="status">
              <div class="tickrow"><span class="tick"></span> PASS</div>
              <div class="tickrow"><span class="tick"></span> FAIL</div>
              <div class="tickrow"><span class="tick"></span> N/A</div>
            </td>
            <td class="notes"></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!$steps): ?>
      <tr><td colspan="4" class="text-center text-muted">No steps found for this ICR.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- Sign-off -->
<div class="sign row g-4">
  <div class="col-md-6">
    <div class="line"></div>
    <small>Inspector Signature</small>
  </div>
  <div class="col-md-6">
    <div class="line"></div>
    <small>Master/Owner Signature</small>
  </div>
</div>

</body>
</html>
