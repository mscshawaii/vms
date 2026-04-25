<?php
require_once __DIR__ . '/includes/legal_version.php';
$last_updated = VMS_LEGAL_LAST_UPDATED;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>End User License Agreement - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        body { background: #f4f7fb; }
        .legal-shell { padding: 24px 12px 40px; }
        .legal-card { max-width: 900px; margin: 0 auto; }
        .legal-title { font-size: 1.85rem; font-weight: 700; margin-bottom: 6px; }
        .legal-meta { color: #6b7280; margin-bottom: 20px; }
        .legal-card h2 { font-size: 1.1rem; margin-top: 24px; margin-bottom: 10px; font-weight: 700; }
        .legal-card p, .legal-card li { color: #1f2937; line-height: 1.65; }
    </style>
</head>
<body>
<div class="legal-shell">
    <div class="vms-card legal-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h1 class="legal-title">End User License Agreement (EULA)</h1>
               <div class="legal-meta">
                    Vessel Management System (VMS)<br>
                    Last Updated: <?= htmlspecialchars($last_updated) ?><br>
                    Version: <?= htmlspecialchars(VMS_LEGAL_VERSION) ?>
                </div>
            </div>
            <div>
                <a href="login.php" class="btn btn-outline-secondary btn-sm">Back to Login</a>
            </div>
        </div>

        <p>This End User License Agreement (“EULA”) governs the authorized use of the Vessel Management System (“VMS”) software and platform provided by MSCS Hawaii.</p>

        <h2>1. License Grant</h2>
        <p>Subject to compliance with this EULA and any related agreement, MSCS Hawaii grants you a limited, non-exclusive, non-transferable, revocable license to access and use VMS for your internal business purposes.</p>

        <h2>2. Restrictions</h2>
        <p>You may not copy, distribute, sublicense, resell, lease, modify, reverse engineer, decompile, or otherwise attempt to derive the source code of VMS, except to the extent such restriction is prohibited by applicable law.</p>

        <h2>3. Ownership</h2>
        <p>VMS and all associated software, design, structure, workflows, and system materials remain the property of MSCS Hawaii or its licensors. No ownership rights are transferred to you under this EULA.</p>

        <h2>4. User Data</h2>
        <p>You retain rights to the data your organization submits into VMS, subject to the permissions, functionality, and operational rules of the platform.</p>

        <h2>5. Termination</h2>
        <p>This license may be suspended or terminated if you violate this EULA, misuse the system, or lose authorization to access the platform.</p>

        <h2>6. Disclaimer</h2>
        <p>VMS is licensed “as is” and “as available,” without warranties of any kind, to the fullest extent permitted by law.</p>

        <h2>7. Limitation of Liability</h2>
        <p>MSCS Hawaii is not liable for indirect, incidental, consequential, or special damages arising from use of or inability to use the platform, to the fullest extent permitted by law.</p>

        <h2>8. Relationship to Other Terms</h2>
        <p>This EULA supplements the Terms of Service and related platform policies. If there is a conflict, the Terms of Service and any controlling service agreement will govern to the extent applicable.</p>

        <h2>9. Contact</h2>
        <p>MSCS Hawaii<br>
        Email: info@mschawaii.org</p>
    </div>
</div>
</body>
</html>