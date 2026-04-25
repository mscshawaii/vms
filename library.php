<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/session_check.php';

$title = 'Training & Library';
$back_link = 'dashboard.php';

$user_role_id = $_SESSION['role_id'] ?? null;
$company_id = (int)($_SESSION['company_id'] ?? 0);
$is_mscs_admin = ($company_id === 1 || (int)$user_role_id === 1);

$cfr_count = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM regulation_sections");
    $cfr_count = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
    $cfr_count = 0;
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> - VMS</title>

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $basePath ?>/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .dash-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .library-top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .library-top-actions .btn {
            min-height: 40px;
            border-radius: 12px;
        }

        .library-hero {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .library-title {
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.15;
            margin: 0;
        }

        .library-subtitle {
            margin: 0;
            color: var(--vms-muted, #6b7280);
        }

        .library-stat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            background: #f8fbff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 999px;
            font-size: 0.92rem;
            color: var(--vms-text, #1f2a37);
            font-weight: 600;
        }

        .library-section {
            margin-top: 18px;
        }

        .library-section-label {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--vms-muted, #6b7280);
            margin-bottom: 10px;
        }

        .library-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .library-card {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 6px;
            min-height: 96px;
            padding: 14px;
            background: #fff;
            border: 1px solid var(--vms-border, #dbe4ee);
            border-radius: 16px;
            box-shadow: var(--vms-shadow, 0 8px 24px rgba(16,24,40,.08));
            text-decoration: none;
            color: var(--vms-text, #1f2a37);
            transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.12s ease;
        }

        .library-card:hover,
        .library-card:focus {
            background: #f8fbff;
            color: var(--vms-text, #1f2a37);
            text-decoration: none;
            transform: translateY(-1px);
        }

        .library-card.disabled-card {
            opacity: 0.65;
            pointer-events: none;
            background: #f8fafc;
        }

        .library-card-title {
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }

        .library-card-meta {
            font-size: 0.88rem;
            color: var(--vms-muted, #6b7280);
            line-height: 1.25;
            margin: 0;
        }

        .library-card-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .library-note {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            color: #4b5563;
            font-size: 0.92rem;
        }

        @media (min-width: 768px) {
            .library-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 992px) {
            .library-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .library-grid {
                grid-template-columns: 1fr;
            }

            .library-title {
                font-size: 1.45rem;
            }

            .library-top-actions {
                align-items: stretch;
            }

            .library-top-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/partials/top_nav.php'; ?>

<div class="dash-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="library-top-actions">
                <a href="<?= htmlspecialchars($back_link) ?>" class="btn btn-outline-secondary btn-sm">
                    ← Back to Dashboard
                </a>
            </div>

            <div class="vms-card">
                <div class="library-hero">
                    <h1 class="library-title">Training & Library</h1>
                    <p class="library-subtitle">
                        Central access point for CFR references, training resources, user guidance, and future compliance tools.
                    </p>

                    <?php if ($cfr_count > 0): ?>
                        <div class="library-stat">
                            <?= number_format($cfr_count) ?> CFR sections currently available
                        </div>
                    <?php else: ?>
                        <div class="library-stat">
                            CFR library not loaded yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="library-section">
                <div class="library-section-label">Library</div>

                <div class="library-grid">
                    <a href="admin/regulation_library.php" class="library-card">
                        <div class="library-card-title">CFR Library</div>
                        <p class="library-card-meta">
                            Search and browse regulations by citation, keyword, part, or subchapter.
                        </p>
                    </a>

                    <a href="equipment_manual_library.php" class="library-card">
                        <span class="badge bg-success library-card-badge">Live</span>
                        <div class="library-card-title">Equipment Source Library</div>
                        <p class="library-card-meta">
                            Review approved manuals and saved maintenance source references for reuse across VMS.
                        </p>
                    </a>

                    <a href="predefined_notes.php" class="library-card">
                        <div class="library-card-title">Predefined Notes</div>
                        <p class="library-card-meta">
                            Browse reusable discrepancy, disclosure, and recommendation notes saved from inspection runs.
                        </p>
                    </a>

                    <a href="#" class="library-card disabled-card">
                        <div class="library-card-title">Training Resources</div>
                        <p class="library-card-meta">
                            Guides, procedures, onboarding materials, and support resources.
                        </p>
                    </a>

                    <a href="#" class="library-card disabled-card">
                        <div class="library-card-title">User Guide</div>
                        <p class="library-card-meta">
                            Quick-start instructions for VMS workflows, modules, and field use.
                        </p>
                    </a>

                    <a href="#" class="library-card disabled-card">
                        <div class="library-card-title">QR / Field Tools</div>
                        <p class="library-card-meta">
                            Reference material for QR workflows, mobile usage, and field tools.
                        </p>
                    </a>
                </div>
            </div>

            <?php if ($is_mscs_admin): ?>
                <div class="library-section">
                    <div class="library-section-label">Admin</div>

                    <div class="library-grid">
                        <a href="admin/regulation_library.php" class="library-card">
                            <span class="badge bg-primary library-card-badge">Live</span>
                            <div class="library-card-title">Manage CFR Access</div>
                            <p class="library-card-meta">
                                Open the regulation library and continue building the compliance resource layer.
                            </p>
                        </a>

                        <a href="manage_predefined_notes.php" class="library-card">
                            <div class="library-card-title">Manage Notes</div>
                            <p class="library-card-meta">
                                Review, edit, deactivate, and maintain the predefined notes library.
                            </p>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="library-note">
                This page is intended to become the central knowledge hub for VMS. The CFR Library is live now, while notes, user guide content, training material, and Lighthouse-related tools can be added here as the system grows.
            </div>

        </div>
    </div>
</div>

<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
