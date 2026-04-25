<?php
// partials/logs_body.php

if (!isset($pdo)) {
    require_once __DIR__ . '/../db_connect.php';
}

$vessel_id = (int)($vessel_id ?? ($_GET['vessel_id'] ?? 0));

$existingDraftId = 0;

try {
    $draftStmt = $pdo->prepare("
        SELECT log_id
        FROM vessel_logs
        WHERE vessel_id = ?
          AND status = 'draft'
        ORDER BY log_id DESC
        LIMIT 1
    ");
    $draftStmt->execute([$vessel_id]);
    $existingDraftId = (int)($draftStmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $existingDraftId = 0;
}

// =====================================================
// HELPERS
// =====================================================

$colExists = function(string $table, string $col) use ($pdo): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
};

$tableExists = function(string $table) use ($pdo): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
};

$norm = function(string $s): string {
    return strtolower(preg_replace('/[^a-z0-9]/', '', $s));
};

$getCols = function(string $table) use ($pdo): array {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
    } catch (Throwable $e) {
        return [];
    }
};

$findColumn = function(array $cols, array $candidateNorms) use ($norm): ?string {
    foreach ($candidateNorms as $cand) {
        foreach ($cols as $raw) {
            if ($norm($raw) === $cand) {
                return $raw;
            }
        }
    }
    return null;
};

$findTable = function(array $tables) use ($tableExists): ?string {
    foreach ($tables as $t) {
        if ($tableExists($t)) {
            return $t;
        }
    }
    return null;
};

// =====================================================
// ALERT / BANNER COUNTS
// =====================================================
$todayLocal = date('Y-m-d');
$thirtyDaysOut = date('Y-m-d', strtotime('+30 days'));
$openCorrectiveCount = 0;
$expiredDocCount = 0;
$expiringDocCount = 0;
$expiredEquipCount = 0;
$expiringEquipCount = 0;
$showAttentionBanner = false;

// ---------- Open corrective actions ----------
try {
    if ($tableExists('tasks')) {
        $taskCols = $getCols('tasks');

        $taskVesselCol = $findColumn($taskCols, ['vesselid']);
        $taskStatusCol = $findColumn($taskCols, ['status', 'taskstatus']);

        if ($taskVesselCol && $taskStatusCol) {
            $sql = "
                SELECT COUNT(*)
                FROM `tasks`
                WHERE `$taskVesselCol` = ?
                  AND LOWER(REPLACE(TRIM(`$taskStatusCol`), '_', ' ')) IN ('open','in progress','overdue','deferred')
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$vessel_id]);
            $openCorrectiveCount = (int)$stmt->fetchColumn();
        }
    }
} catch (Throwable $e) {
    $openCorrectiveCount = 0;
}

// ---------- Documents ----------
try {
    $expiredDocCount = 0;
    $expiringDocCount = 0;

    $todayLocal = date('Y-m-d');
    $thirtyDaysOut = date('Y-m-d', strtotime('+30 days'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM documents
        WHERE vessel_id = ?
          AND archived_at IS NULL
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate < ?
    ");
    $stmt->execute([$vessel_id, $todayLocal]);
    $expiredDocCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM documents
        WHERE vessel_id = ?
          AND archived_at IS NULL
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate BETWEEN ? AND ?
    ");
    $stmt->execute([$vessel_id, $todayLocal, $thirtyDaysOut]);
    $expiringDocCount = (int)$stmt->fetchColumn();

} catch (Throwable $e) {
    $expiredDocCount = 0;
    $expiringDocCount = 0;
}

// ---------- Equipment ----------
try {
    $expiredEquipCount = 0;
    $expiringEquipCount = 0;

    $todayLocal = date('Y-m-d');
    $thirtyDaysOut = date('Y-m-d', strtotime('+30 days'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE vessel_id = ?
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate < ?
    ");
    $stmt->execute([$vessel_id, $todayLocal]);
    $expiredEquipCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM equipment
        WHERE vessel_id = ?
          AND expDate IS NOT NULL
          AND expDate >= '1000-01-01'
          AND expDate BETWEEN ? AND ?
    ");
    $stmt->execute([$vessel_id, $todayLocal, $thirtyDaysOut]);
    $expiringEquipCount = (int)$stmt->fetchColumn();

} catch (Throwable $e) {
    $expiredEquipCount = 0;
    $expiringEquipCount = 0;
}

$showAttentionBanner =
    $openCorrectiveCount > 0 ||
    $expiredDocCount > 0 ||
    $expiringDocCount > 0 ||
    $expiredEquipCount > 0 ||
    $expiringEquipCount > 0;


// =====================================================
// CREW LOADER
// =====================================================

$crewOptions = [];

// =====================================================
// CREW LOADER
// =====================================================

$crewOptions = [];

try {
    $crewStmt = $pdo->prepare("
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
          AND vc.counts_for_voyage_logs = 1
          AND vc.role IN ('Master', 'Deckhand')
        ORDER BY
            FIELD(vc.role, 'Master', 'Deckhand'),
            u.lName,
            u.fName
    ");
    $crewStmt->execute([$vessel_id]);
    $crewOptions = $crewStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<div class="alert alert-warning">Crew list not loaded due to a schema mismatch.</div>';
}
?>

<!-- Top-right: View existing logs -->
<div class="d-flex justify-content-end mb-3">
  <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" target="_blank" class="btn btn-outline-dark">
    View Log Entries
  </a>
</div>

<?php if ($showAttentionBanner): ?>
  <div class="alert alert-warning border border-warning shadow-sm mb-4" id="complianceAttentionBanner">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h5 class="alert-heading mb-2">Attention Required Before Saving This Log</h5>
        <p class="mb-2">This vessel currently has compliance items requiring attention:</p>

        <ul class="mb-2">
  <li>
    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>&task_filter=open#tasksModal"
       target="_blank"
       class="fw-bold text-decoration-none">
      <?= (int)$openCorrectiveCount ?>
    </a>
    open corrective action item<?= $openCorrectiveCount === 1 ? '' : 's' ?>
  </li>

  <li>
    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#documentsModal"
       target="_blank"
       class="fw-bold text-decoration-none">
      <?= (int)$expiredDocCount ?>
    </a>
    expired document<?= $expiredDocCount === 1 ? '' : 's' ?>
  </li>

  <li>
    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#documentsModal"
       target="_blank"
       class="fw-bold text-decoration-none">
      <?= (int)$expiringDocCount ?>
    </a>
    document<?= $expiringDocCount === 1 ? ' is' : 's are' ?> expiring within 30 days
  </li>

  <li>
    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#equipmentModal"
       target="_blank"
       class="fw-bold text-decoration-none">
      <?= (int)$expiredEquipCount ?>
    </a>
    expired equipment/service item<?= $expiredEquipCount === 1 ? '' : 's' ?>
  </li>

  <li>
    <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#equipmentModal"
       target="_blank"
       class="fw-bold text-decoration-none">
      <?= (int)$expiringEquipCount ?>
    </a>
    equipment/service item<?= $expiringEquipCount === 1 ? ' is' : 's are' ?> expiring within 30 days
  </li>
</ul>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" value="1" id="alertAckCheckbox">
          <label class="form-check-label" for="alertAckCheckbox">
            I acknowledge these open or expiring items and want to continue.
          </label>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>&task_filter=open#tasksModal"
           target="_blank"
           class="btn btn-outline-primary btn-sm">
          View Corrective Actions
        </a>

        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#documentsModal"
           target="_blank"
           class="btn btn-outline-secondary btn-sm">
          View Documents
        </a>

        <a href="vessel_dashboard.php?vessel_id=<?= (int)$vessel_id ?>#equipmentModal"
           target="_blank"
           class="btn btn-outline-secondary btn-sm">
          View Equipment
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($existingDraftId > 0): ?>
  <div class="alert alert-warning border border-warning shadow-sm mb-4">
    <h5 class="alert-heading mb-2">Draft Log In Progress</h5>
    <p class="mb-3">
      A draft vessel log already exists for this vessel. Please resume and finalize that draft before starting a new log.
    </p>

    <div class="d-flex gap-2 flex-wrap">
      <a href="log_edit.php?log_id=<?= (int)$existingDraftId ?>" class="btn btn-primary">
        Resume Draft
      </a>

      <a href="logs_list.php?vessel_id=<?= (int)$vessel_id ?>" target="_blank" class="btn btn-outline-dark">
        View Log Entries
      </a>
    </div>
  </div>

  <?php return; ?>
<?php endif; ?>

<form id="logForm" method="post" action="log_create.php" enctype="multipart/form-data">
  <input type="hidden" name="vessel_id" value="<?= (int)$vessel_id ?>">
  <input type="hidden" name="signature_png" id="signature_png">
  <input type="hidden" name="casualty_flag" id="casualty_flag" value="0">
  <input type="hidden" name="alert_acknowledged" id="alert_acknowledged" value="0">

  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label">Departure (local)</label>
      <input type="datetime-local" class="form-control" name="depart_dt">
    </div>

    <div class="col-md-3">
      <label class="form-label">Origin Port</label>
      <input type="text" class="form-control" name="origin_port" maxlength="120">
    </div>

    <div class="col-md-3 d-flex align-items-end">
      <span class="d-grid"
            data-bs-toggle="tooltip"
            data-bs-trigger="hover focus click"
            data-bs-placement="top"
            title="Under development">
        <button type="button" class="btn btn-outline-secondary w-100" disabled>
          Pre-Underway Checklist
        </button>
      </span>
    </div>

    <div class="col-md-3">
      <label class="form-label">Passengers (qty)</label>
      <input type="number" min="0" class="form-control" name="passenger_count" placeholder="0">
    </div>

    <div class="col-12">
      <label class="form-label">Crew Onboard</label>
      <select name="crew_ids[]" class="form-select" multiple size="6">
        <?php foreach ($crewOptions as $c): ?>
          <option value="<?= (int)$c['id'] ?>">
            <?= htmlspecialchars(trim(($c['lName'] ?? '') . ', ' . ($c['fName'] ?? ''))) ?>
            <?php if (!empty($c['role'])): ?> (<?= htmlspecialchars($c['role']) ?>)<?php endif; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Hold Ctrl/Cmd (or drag) to select multiple crew.</div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Return (local)</label>
      <input type="datetime-local" class="form-control" name="return_dt">
    </div>

    <div class="col-md-3">
      <label class="form-label">Arrival Port</label>
      <input type="text" class="form-control" name="arrival_port" maxlength="120">
    </div>

    <div class="col-md-3 d-flex align-items-end">
      <span class="d-grid"
            data-bs-toggle="tooltip"
            data-bs-trigger="hover focus click"
            data-bs-placement="top"
            title="Under development">
        <button type="button" class="btn btn-outline-secondary w-100" disabled>
          Post-Underway Checklist
        </button>
      </span>
    </div>

    <div class="col-md-6">
      <label class="form-label">Engine Hours (Port)</label>
      <input type="number" step="0.1" min="0" class="form-control" name="engine_hours_port" placeholder="e.g., 1234.5">
    </div>

    <div class="col-md-6">
      <label class="form-label">Engine Hours (Starboard)</label>
      <input type="number" step="0.1" min="0" class="form-control" name="engine_hours_stbd" placeholder="e.g., 1234.5">
    </div>

    <div class="col-12">
      <label class="form-label">Trip Summary / Notes</label>
      <textarea class="form-control" name="trip_summary" rows="4"
                placeholder="Weather, incidents, ops limits, sightings, maintenance notes, etc."></textarea>
    </div>

    <div class="col-12">
      <div class="row g-3">
        <div class="col-md-6 d-grid">
          <span class="d-grid"
                data-bs-toggle="tooltip"
                data-bs-trigger="hover focus click"
                data-bs-placement="top"
                title="Under development">
            <button type="button"
                    class="btn btn-outline-secondary btn-lg w-100"
                    disabled>
              Report a Marine Casualty
            </button>
          </span>
        </div>

        <div class="col-md-6 d-grid">
          <a href="add_task.php?vessel_id=<?= (int)$vessel_id ?>&origin=log"
             class="btn btn-outline-primary btn-lg w-100"
             title="Create a Corrective Action and report equipment failure">
            Report a Corrective Action Requirement
          </a>
        </div>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Photos / Videos</label>
      <input class="form-control" type="file" name="media_files[]" accept="image/*,video/*" multiple>
      <div class="form-text">Select multiple files (images & common video formats).</div>
    </div>

    <div class="col-12">
      <label class="form-label d-block">Signature</label>
      <div class="border rounded p-2 bg-light">
        <canvas id="sigCanvas" width="800" height="140" class="w-100" style="background:#fff; border:1px solid #ccc;"></canvas>
        <div class="mt-2 d-flex gap-2">
          <input type="text" class="form-control" name="signed_by_name" placeholder="Printed name (optional)">
          <button type="button" class="btn btn-outline-secondary" id="btnClearSig">Clear</button>
        </div>
        <div class="form-text">Sign with mouse or touch. Signature will be timestamped on save.</div>
      </div>
    </div>
  </div>
</form>

<script>
(function () {
  const form = document.getElementById('logForm');
  if (!form) return;

  const canvas = document.getElementById('sigCanvas');
  const alertAckCheckbox = document.getElementById('alertAckCheckbox');
  const alertAckHidden = document.getElementById('alert_acknowledged');

  if (canvas) {
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let last = null;

    function relPos(e) {
      const r = canvas.getBoundingClientRect();
      if (e.touches && e.touches.length) {
        return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
      }
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function start(e) {
      drawing = true;
      last = relPos(e);
      e.preventDefault();
    }

    function move(e) {
      if (!drawing) return;
      const p = relPos(e);
      ctx.lineWidth = 2;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(last.x, last.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      last = p;
      e.preventDefault();
    }

    function end() {
      drawing = false;
      last = null;
    }

    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end);

    const clearBtn = document.getElementById('btnClearSig');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
      });
    }

    form.addEventListener('submit', function () {
      const blank = document.createElement('canvas');
      blank.width = canvas.width;
      blank.height = canvas.height;
      if (canvas.toDataURL() !== blank.toDataURL()) {
        document.getElementById('signature_png').value = canvas.toDataURL('image/png');
      }
    });
  }

  form.addEventListener('submit', function (e) {
    if (alertAckCheckbox) {
      if (!alertAckCheckbox.checked) {
        e.preventDefault();
        alert('Please acknowledge the open or expiring compliance items before saving this log.');
        alertAckCheckbox.focus();
        return false;
      }

      if (alertAckHidden) {
        alertAckHidden.value = '1';
      }
    }
  });

  const KEY = 'vms_log_draft_vessel_<?= (int)$vessel_id ?>';

  try {
    const data = JSON.parse(localStorage.getItem(KEY) || '{}');
    for (const [k, v] of Object.entries(data)) {
      const el = form.elements[k];
      if (!el) continue;
      if (el.type === 'file') continue;

      if (el instanceof HTMLSelectElement && el.multiple && Array.isArray(v)) {
        for (const opt of el.options) {
          opt.selected = v.includes(parseInt(opt.value, 10));
        }
      } else {
        el.value = v;
      }
    }

    if (alertAckCheckbox && data.alertAckCheckbox) {
      alertAckCheckbox.checked = !!data.alertAckCheckbox;
    }
  } catch (e) {}

  setInterval(function () {
    const payload = {};
    const f = form.elements;

    [
      'depart_dt',
      'origin_port',
      'return_dt',
      'arrival_port',
      'passenger_count',
      'trip_summary',
      'engine_hours_port',
      'engine_hours_stbd',
      'signed_by_name'
    ].forEach(function (name) {
      if (f[name]) payload[name] = f[name].value;
    });

    if (f['crew_ids[]']) {
      payload['crew_ids[]'] = Array.from(f['crew_ids[]'].options)
        .filter(function (o) { return o.selected; })
        .map(function (o) { return parseInt(o.value, 10); });
    }

    if (alertAckCheckbox) {
      payload.alertAckCheckbox = alertAckCheckbox.checked ? 1 : 0;
    }

    localStorage.setItem(KEY, JSON.stringify(payload));
  }, 60000);

  form.addEventListener('submit', function () {
    localStorage.removeItem(KEY);
  });

  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
})();
</script>