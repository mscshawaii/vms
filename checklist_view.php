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

function checklist_response_badge_class(string $responseValue): string
{
    return match ($responseValue) {
        'complete' => 'text-bg-success',
        'not_complete' => 'text-bg-danger',
        'na' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function checklist_response_label(string $responseValue): string
{
    return match ($responseValue) {
        'complete' => 'Complete',
        'not_complete' => 'Not Complete',
        'na' => 'N/A',
        default => ucfirst(str_replace('_', ' ', $responseValue)),
    };
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

$checklistRunId = (int)($_GET['checklist_run_id'] ?? 0);
$companyId = (int)($_SESSION['company_id'] ?? 0);
$canAccessAllVessels = ($companyId === 1);

if ($checklistRunId <= 0) {
    http_response_code(400);
    exit('Invalid checklist run ID.');
}

$runHeader = checklist_get_run_header($pdo, $checklistRunId);
if (!$runHeader) {
    http_response_code(404);
    exit('Checklist run not found.');
}

$vessel = checklist_get_accessible_vessel($pdo, (int)$runHeader['vessel_id'], $canAccessAllVessels, $companyId);
if (!$vessel) {
    http_response_code(404);
    exit('Access denied or vessel not found.');
}

$runItems = checklist_get_run_items($pdo, $checklistRunId);
$summary = checklist_get_run_response_summary($pdo, $checklistRunId);
$linkedLogId = (int)($runHeader['log_id'] ?? 0);
$backLink = $linkedLogId > 0
    ? 'log_view.php?log_id=' . $linkedLogId
    : 'logs_list.php?vessel_id=' . (int)$vessel['vessel_id'];

$title = ($runHeader['template_name'] ?? 'Checklist') . ' - ' . ($vessel['vesselName'] ?? 'Vessel');
$back_link = $backLink;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($runHeader['template_name'] ?? 'Checklist') ?> - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .checklist-view-shell {
            background: #f4f7fb;
            min-height: 100vh;
        }
        .checklist-view-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
        }
        .checklist-response-item {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
            background: #fbfdff;
        }
        .checklist-item-label {
            font-weight: 600;
            margin-bottom: 10px;
        }
        .checklist-item-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .checklist-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="checklist-view-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="checklist-view-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h1 class="h3 mb-2"><?= h($runHeader['template_name'] ?? 'Checklist') ?></h1>
                        <p class="text-muted mb-0"><?= h($vessel['vesselName'] ?? '') ?></p>
                    </div>

                    <div>
                        <a href="<?= h($backLink) ?>" class="btn btn-outline-secondary">Back</a>
                    </div>
                </div>

                <div class="checklist-meta-grid mt-4">
                    <div>
                        <strong>Checklist Type:</strong><br>
                        <?= h($runHeader['template_name'] ?? $runHeader['run_type'] ?? 'Checklist') ?>
                    </div>
                    <div>
                        <strong>Vessel:</strong><br>
                        <?= h($vessel['vesselName'] ?? '') ?>
                    </div>
                    <div>
                        <strong>Linked Log ID:</strong><br>
                        <?= $linkedLogId > 0 ? (int)$linkedLogId : 'Not linked' ?>
                    </div>
                    <div>
                        <strong>Created:</strong><br>
                        <?= h($runHeader['created_at'] ?? '') ?>
                    </div>
                </div>

                <div class="mt-4 text-muted">
                    Complete: <?= (int)($summary['complete'] ?? 0) ?> |
                    Not Complete: <?= (int)($summary['not_complete'] ?? 0) ?> |
                    N/A: <?= (int)($summary['na'] ?? 0) ?>
                </div>
            </div>

            <div class="checklist-view-card p-4">
                <h2 class="h5 mb-3">Checklist Responses</h2>

                <?php if (empty($runItems)): ?>
                    <div class="text-muted">No checklist responses found.</div>
                <?php else: ?>
                    <div class="d-grid gap-3">
                        <?php foreach ($runItems as $item): ?>
                            <?php
                                $responseValue = (string)($item['response_value'] ?? '');
                                $sourceType = (string)($item['source_type'] ?? 'core');
                                $sourceLabel = $sourceType === 'vessel' ? 'Custom Item' : 'Core Item';
                            ?>
                            <div class="checklist-response-item">
                                <div class="checklist-item-label"><?= h($item['item_label'] ?? '') ?></div>
                                <div class="checklist-item-meta">
                                    <span class="badge text-bg-light"><?= h($sourceLabel) ?></span>
                                    <?php if ($sourceType === 'core' && !empty($item['regulation_ref'])): ?>
                                        <span class="text-muted small"><?= h($item['regulation_ref']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge <?= checklist_response_badge_class($responseValue) ?>">
                                    <?= h(checklist_response_label($responseValue)) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
</body>
</html>
