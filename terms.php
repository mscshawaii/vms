<?php
require_once __DIR__ . '/includes/legal_version.php';
$last_updated = VMS_LEGAL_LAST_UPDATED;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - VMS</title>

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
                <h1 class="legal-title">Terms of Service</h1>
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

        <p>These Terms of Service govern access to and use of the Vessel Management System (“VMS”), operated by MSCS Hawaii (“MSCS Hawaii,” “we,” “our,” or “us”). By accessing or using VMS, you agree to these Terms.</p>

        <h2>1. Use of the Platform</h2>
        <p>VMS is intended to support vessel management, inspection workflows, compliance tracking, crew administration, document storage, corrective actions, and related operational functions. You may use VMS only for authorized and lawful business purposes.</p>

        <h2>2. Accounts and Access</h2>
        <p>You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. You must not share access credentials in a manner inconsistent with your organization’s authorized use of the platform.</p>

        <h2>3. Acceptable Use</h2>
        <p>You agree not to misuse VMS, attempt unauthorized access, interfere with system performance, upload malicious material, or use the platform for unlawful, deceptive, or harmful purposes.</p>

        <h2>4. Data and Content</h2>
        <p>You are responsible for the accuracy, completeness, and appropriateness of the information entered into VMS, including vessel records, crew records, inspection results, tasks, and uploaded documents.</p>

        <h2>5. No Regulatory Replacement</h2>
        <p>VMS is a management and recordkeeping tool. It does not replace regulatory review, professional judgment, required inspections, or independent compliance obligations.</p>

        <h2>6. Availability</h2>
        <p>We aim to provide reliable access to VMS but do not guarantee uninterrupted or error-free availability at all times.</p>

        <h2>7. Intellectual Property</h2>
        <p>The VMS software, interface, structure, and related materials are owned by or licensed to MSCS Hawaii. Except as expressly permitted, you may not copy, distribute, modify, or reverse engineer the platform.</p>

        <h2>8. Suspension or Termination</h2>
        <p>We may suspend or terminate access to VMS if we believe these Terms have been violated, if misuse occurs, or if continued access would create operational, legal, or security concerns.</p>

        <h2>9. Disclaimer</h2>
        <p>VMS is provided on an “as is” and “as available” basis without warranties of any kind, whether express or implied, to the fullest extent permitted by law.</p>

        <h2>10. Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, MSCS Hawaii is not liable for indirect, incidental, special, consequential, or business-interruption damages arising from or related to use of VMS.</p>

        <h2>11. Changes to These Terms</h2>
        <p>We may revise these Terms from time to time. Updated terms will be posted and the “Last Updated” date will be changed. Continued use of VMS after updates constitutes acceptance of the revised Terms.</p>

        <h2>12. Governing Law</h2>
        <p>These Terms are governed by the laws of the State of Hawaii, without regard to conflict-of-law principles.</p>

        <h2>13. Contact</h2>
        <p>MSCS Hawaii<br>
        Email: info@mschawaii.org</p>
    </div>
</div>
</body>
</html>