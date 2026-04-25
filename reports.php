<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/legal_version.php';

// Very light auth guard (replace with your real auth)
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(403); echo "Please sign in."; exit; }

$flash = $_SESSION['flash'] ?? null;
if ($flash) {
  unset($_SESSION['flash']);
}

$companyId = (int)($_SESSION['company_id'] ?? 0);
$isMSCS = ($companyId === 1);

if ($isMSCS) {
    // MSCS: all ACTIVE vessels across companies
    $stmt = $pdo->query("
        SELECT vessel_id, vesselName 
        FROM vessels 
        WHERE is_active = 1
        ORDER BY vesselName ASC
    ");
} else {
    // Non-MSCS: only ACTIVE vessels for their company
    $stmt = $pdo->prepare("
        SELECT vessel_id, vesselName 
        FROM vessels 
        WHERE is_active = 1 AND company_id = ?
        ORDER BY vesselName ASC
    ");
    $stmt->execute([$companyId]);
}
$vessels = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* MSCS-only legal acknowledgement summary */
$currentLegalAckCount = 0;
$missingCurrentLegalAckCount = 0;

if ($isMSCS) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_legal_acknowledgements
        WHERE legal_version = ?
    ");
    $stmt->execute([VMS_LEGAL_VERSION]);
    $currentLegalAckCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users u
        WHERE u.is_active = 1
          AND NOT EXISTS (
              SELECT 1
              FROM user_legal_acknowledgements ula
              WHERE ula.user_id = u.id
                AND ula.legal_version = ?
          )
    ");
    $stmt->execute([VMS_LEGAL_VERSION]);
    $missingCurrentLegalAckCount = (int)$stmt->fetchColumn();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>VMS Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0d6efd">
  <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
  <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="/assets/css/vms-mobile.css" rel="stylesheet">

  <style>
    body { background-color: #f8f9fa; }

    .reports-shell {
      background: var(--vms-bg, #f4f7fb);
      min-height: 100vh;
    }

    .page-card,
    .accordion-item {
      border-radius: 1rem;
      border: 0;
    }

    .page-meta {
      color: #6b7280;
      margin: 0;
    }

    .reports-accordion .accordion-item {
      overflow: hidden;
      box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
      margin-bottom: 1rem;
      background: #fff;
    }

    .reports-accordion .accordion-button {
      font-weight: 600;
      padding: 1rem 1.1rem;
      background: #fff;
      box-shadow: none;
    }

    .reports-accordion .accordion-button:not(.collapsed) {
      background: #f8fbff;
      color: #0d6efd;
    }

    .reports-accordion .accordion-body {
      padding: 1rem 1.1rem 1.1rem;
    }

    .summary-chip {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      border-radius: 999px;
      padding: .4rem .75rem;
      font-size: .85rem;
      font-weight: 600;
      background: #eef5ff;
      color: #0d6efd;
      border: 1px solid #cfe2ff;
    }

    @media (max-width: 768px) {
      .btn-stack-mobile .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
<?php
$title = 'Reports';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="reports-shell">
  <div class="app-page">
    <div class="app-container pb-5">

      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?> alert-dismissible fade show" role="alert">
          <?= htmlspecialchars((string)$flash['msg']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm page-card p-3 p-md-4 mb-3">
        <div class="mb-2">
          <h1 class="h3 mb-1">Reports</h1>
          <p class="page-meta">Generate digests, review logs, and access reporting tools.</p>
        </div>
      </div>

      <div class="accordion reports-accordion" id="reportsAccordion">

        <!-- Report Generator -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingReportGenerator">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseReportGenerator" aria-expanded="true" aria-controls="collapseReportGenerator">
              Report Generator
            </button>
          </h2>
          <div id="collapseReportGenerator" class="accordion-collapse collapse show" aria-labelledby="headingReportGenerator" data-bs-parent="#reportsAccordion">
            <div class="accordion-body">
              <p class="text-muted small mb-3">Generate a digest, preview it, or send it by email.</p>

              <form method="post" action="reports_preview.php">
                <div class="row g-3">

                  <div class="col-12 col-md-2">
                    <label class="form-label">Days Ahead</label>
                    <input type="number" class="form-control" name="days" value="45" min="0">
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="form-label">Vessel (optional)</label>
                    <select class="form-select" name="vessel">
                      <?php if ($isMSCS): ?>
                        <option value="">— All Vessels (MSCS) —</option>
                      <?php else: ?>
                        <option value="">— All My Vessels —</option>
                      <?php endif; ?>
                      <?php foreach ($vessels as $v): ?>
                        <option value="<?= (int)$v['vessel_id'] ?>">
                          <?= htmlspecialchars($v['vesselName'] ?? ('Vessel #'.$v['vessel_id'])) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Sections</label>
                    <div class="row g-2">
                      <?php
                        $sections = [
                          'docs_vessel'           => 'Vessel Documents',
                          'docs_equipment'        => 'Equipment',
                          'crew_credentials'      => 'Crew Credentials',
                          'icr_due'               => 'ICRs',
                          'car_due'               => 'CARs',
                          'crew_drills'           => 'Drills',
                          'upcoming_inspections'  => 'Inspections',
                        ];
                        foreach ($sections as $key => $label):
                      ?>
                        <div class="col-6 col-md-6">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="sections[]" id="sec-<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($key) ?>" checked>
                            <label class="form-check-label" for="sec-<?= htmlspecialchars($key) ?>">
                              <?= htmlspecialchars($label) ?>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="col-12"><hr></div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Recipients</label>
                    <input type="text" class="form-control" name="to" value="info@mschawaii.org">
                    <div class="form-text">Comma-separated emails</div>
                  </div>

                  <div class="col-12 col-md-6">
                    <label class="form-label">Email Subject</label>
                    <input type="text" class="form-control" name="subject" placeholder="VMS Digest">
                  </div>

                  <div class="col-12 mt-2">
                    <div class="d-flex flex-column flex-md-row gap-2 btn-stack-mobile">
                      <button class="btn btn-outline-primary" type="submit">Preview</button>
                      <button class="btn btn-primary" type="submit" formaction="reports_send.php">Send Now</button>
                    </div>
                  </div>

                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Notifications Log -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingNotificationsLog">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNotificationsLog" aria-expanded="false" aria-controls="collapseNotificationsLog">
              Notifications Log
            </button>
          </h2>
          <div id="collapseNotificationsLog" class="accordion-collapse collapse" aria-labelledby="headingNotificationsLog" data-bs-parent="#reportsAccordion">
            <div class="accordion-body">
              <p class="text-muted small mb-3">
                Review document reminder history and recipient activity.
              </p>

              <div class="d-flex flex-column flex-md-row gap-2 btn-stack-mobile">
                <a href="notifications_log.php" class="btn btn-outline-primary">Open Log</a>
              </div>
            </div>
          </div>
        </div>

        <?php if ($isMSCS): ?>
        <!-- Legal Acknowledgements -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingLegalAudit">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLegalAudit" aria-expanded="false" aria-controls="collapseLegalAudit">
              Legal Acknowledgements
            </button>
          </h2>
          <div id="collapseLegalAudit" class="accordion-collapse collapse" aria-labelledby="headingLegalAudit" data-bs-parent="#reportsAccordion">
            <div class="accordion-body">
              <p class="text-muted small mb-3">
                Review user acceptance of the current legal documents and access the full audit log.
              </p>

              <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="summary-chip">Current Version: <?= htmlspecialchars(VMS_LEGAL_VERSION) ?></span>
                <span class="summary-chip">Accepted: <?= (int)$currentLegalAckCount ?></span>
                <span class="summary-chip">Missing Current Ack: <?= (int)$missingCurrentLegalAckCount ?></span>
              </div>

              <div class="d-flex flex-column flex-md-row gap-2 btn-stack-mobile">
                <a href="legal_acknowledgements.php" class="btn btn-outline-primary">Open Audit Log</a>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>