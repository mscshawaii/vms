<?php
require_once __DIR__ . '/includes/legal_version.php';
$last_updated = VMS_LEGAL_LAST_UPDATED;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
        }
        .legal-shell {
            padding: 24px 12px 40px;
        }
        .legal-card {
            max-width: 900px;
            margin: 0 auto;
        }
        .legal-title {
            font-size: 1.85rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .legal-meta {
            color: #6b7280;
            margin-bottom: 20px;
        }
        .legal-card h2 {
            font-size: 1.1rem;
            margin-top: 24px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .legal-card p, .legal-card li {
            color: #1f2937;
            line-height: 1.65;
        }
    </style>
</head>
<body>
<div class="legal-shell">
    <div class="vms-card legal-card">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h1 class="legal-title">Privacy Policy</h1>
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

        <p>MSCS Hawaii (“we,” “our,” or “us”) operates the Vessel Management System (“VMS”), a web-based platform designed to support vessel management, compliance tracking, recordkeeping, crew coordination, and corrective action workflows.</p>

        <h2>1. Information We Collect</h2>
        <p>We may collect and store the following categories of information:</p>
        <ul>
            <li>Account information such as names, usernames, passwords, email addresses, and company affiliation.</li>
            <li>Vessel information such as vessel names, vessel specifications, inspection details, and ownership-related records.</li>
            <li>Crew and personnel information such as names, roles, assignments, credential dates, and training or drill participation.</li>
            <li>Operational and compliance records such as inspection criteria reports (ICRs), corrective actions, notes, discussions, uploaded documents, and task history.</li>
            <li>System usage information such as login activity, user preferences, notification preferences, and device/browser interactions needed to operate the system.</li>
        </ul>

        <h2>2. How We Use Information</h2>
        <p>We use information collected through VMS to provide, operate, maintain, and improve the platform. This includes supporting vessel and crew recordkeeping, compliance workflows, task management, reminders, messaging, and user support.</p>

        <h2>3. Notifications</h2>
        <p>VMS may send emails, in-system messages, and push notifications related to account activity, compliance reminders, task discussions, and corrective action workflows. Users may be able to manage certain notification settings within their account.</p>

        <h2>4. How Information Is Shared</h2>
        <p>We do not sell personal information. Information within VMS may be shared with authorized users within the same organization, with MSCS Hawaii administrators for support and platform administration, and with service providers who help us operate the platform. Information may also be disclosed where required by law, legal process, or regulatory request.</p>

        <h2>5. Data Storage and Security</h2>
        <p>We use reasonable administrative, technical, and operational safeguards to help protect information stored within VMS. However, no method of transmission or storage is completely secure, and we cannot guarantee absolute security.</p>

        <h2>6. Data Retention</h2>
        <p>We retain information for as long as reasonably necessary to provide the service, maintain operational and compliance records, resolve disputes, enforce agreements, and satisfy business or legal obligations.</p>

        <h2>7. User Responsibilities</h2>
        <p>Users are responsible for ensuring that information they enter into VMS is accurate and appropriate for their operational and compliance needs.</p>

        <h2>8. Your Choices</h2>
        <p>Users may request correction of inaccurate account information or request account-related assistance by contacting MSCS Hawaii. Some information may need to be retained for operational, administrative, or compliance reasons.</p>

        <h2>9. Third-Party Services</h2>
        <p>VMS may rely on third-party providers for hosting, email delivery, messaging, and notification services. These providers may process limited information as necessary to support platform functionality.</p>

        <h2>10. Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. Updates will be posted within VMS or on the applicable policy page, and the “Last Updated” date will be revised accordingly.</p>

        <h2>11. Contact</h2>
        <p>MSCS Hawaii<br>
        Email: info@mschawaii.org</p>
    </div>
</div>
</body>
</html>