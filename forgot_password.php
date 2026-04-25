<?php
// forgot_password.php
session_start();
require __DIR__ . '/db_connect.php';
require __DIR__ . '/lib/sendgrid_api_mail.php';

if (empty($_SESSION['csrf_fp'])) {
    $_SESSION['csrf_fp'] = bin2hex(random_bytes(32));
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$errors  = [];
$stage   = 'verify'; // verify | done
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_fp'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    if (!empty($_POST['website'])) {
        $errors[] = 'Spam detected.';
    }

    $action = $_POST['action'] ?? 'verify';

    if ($action === 'verify' && !$errors) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }

        $genericMsg = 'If the account details are correct, we emailed a password reset link. Please check your inbox.';

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, email
                     FROM users
                     WHERE username = ? AND email = ?
                     LIMIT 1"
                );
                $stmt->execute([$username, $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $pdo->prepare(
                        "DELETE FROM password_resets
                         WHERE user_id = ? AND used_at IS NULL"
                    )->execute([(int)$user['id']]);

                    $selector   = bin2hex(random_bytes(12));
                    $token      = bin2hex(random_bytes(32));
                    $tokenHash  = password_hash($token, PASSWORD_DEFAULT);
                    $expiresAt  = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

                    $pdo->prepare(
                        "INSERT INTO password_resets
                         (user_id, selector, token_hash, expires_at, request_ip, user_agent)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    )->execute([
                        (int)$user['id'],
                        $selector,
                        $tokenHash,
                        $expiresAt,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    ]);

                    $baseUrl = 'https://vms.mschawaii.org';

                    $resetLink = $baseUrl . '/reset_password_user.php?selector='
                               . urlencode($selector) . '&token=' . urlencode($token);

                    $subject = 'VMS Password Reset';
                    $resetLinkEsc = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

                    $htmlBody =
                        "<p>A password reset was requested for your VMS account.</p>" .
                        "<p><strong>This link is valid for 60 minutes:</strong></p>" .
                        "<p><a href=\"{$resetLinkEsc}\">Reset your password</a></p>" .
                        "<p>If you did not request this, you can safely ignore this email.</p>" .
                        "<hr>" .
                        "<p style=\"font-size:12px;color:#666\">" .
                        "If the button does not work, copy and paste this URL into your browser:<br>" .
                        $resetLinkEsc .
                        "</p>";

                    $textBody =
                        "A password reset was requested for your VMS account.\n\n" .
                        "Reset your password (valid for 60 minutes):\n" .
                        $resetLink . "\n\n" .
                        "If you did not request this, you can safely ignore this email.";

                    $res = send_via_sendgrid_api(
                        $user['email'],
                        $subject,
                        $htmlBody,
                        'info@mschawaii.org',
                        'MSCS Hawaii VMS',
                        $textBody
                    );

                    if (!$res['ok']) {
                        error_log(
                            "SendGrid password reset failed for user_id={$user['id']}: " .
                            ($res['error'] ?? 'unknown error')
                        );
                    }
                }

                $success = $genericMsg;
                $stage   = 'done';

            } catch (Throwable $e) {
                $success = $genericMsg;
                $stage   = 'done';
            }
        }
    }

    $_SESSION['csrf_fp'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - VMS</title>

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
            background:
                radial-gradient(circle at top, #eef5ff 0%, #f4f7fb 38%, #f4f7fb 100%);
        }

        .vms-auth-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 12px;
        }

        .vms-auth-card {
            width: 100%;
            max-width: 500px;
        }

        .vms-auth-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .vms-auth-back {
            white-space: nowrap;
            border-radius: 12px;
        }

        .vms-auth-title {
            font-size: 1.45rem;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 6px;
            color: #1f2a37;
        }

        .vms-auth-subtitle {
            color: #6b7280;
            font-size: 0.96rem;
            margin: 0 0 18px;
        }

        .vms-auth-card .form-label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .vms-auth-card .form-control {
            min-height: 50px;
            border-radius: 12px;
            border-color: #dbe4ee;
            padding-left: 14px;
            padding-right: 14px;
        }

        .vms-auth-card .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
        }

        .vms-auth-submit {
            min-height: 50px;
            border-radius: 12px;
            font-weight: 600;
        }

        .vms-auth-footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e9edf2;
            text-align: center;
            font-size: 0.88rem;
            color: #6b7280;
        }

        .hp {
            display: none !important;
            visibility: hidden !important;
            height: 0;
        }

        @media (max-width: 575.98px) {
            .vms-auth-shell {
                align-items: flex-start;
                padding-top: 28px;
            }

            .vms-auth-top {
                flex-direction: column;
                align-items: stretch;
            }

            .vms-auth-back {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="vms-auth-shell">
    <div class="vms-auth-card vms-card">
        <div class="vms-auth-top">
            <div>
                <h1 class="vms-auth-title">Forgot Password</h1>
                <p class="vms-auth-subtitle">
                    Enter your username and registered email. If the details match, a reset link will be sent to your inbox.
                </p>
            </div>
            <a class="btn btn-outline-secondary btn-sm vms-auth-back" href="login.php">Back to Login</a>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($stage === 'done' && $success): ?>
            <div class="alert alert-success" role="alert">
                <?= h($success) ?>
            </div>

            <div class="d-grid mt-3">
                <a class="btn btn-primary vms-auth-submit" href="login.php">Go to Login</a>
            </div>
        <?php else: ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_fp']) ?>">
                <div class="hp"><input type="text" name="website" value=""></div>
                <input type="hidden" name="action" value="verify">

                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        class="form-control"
                        required
                        autocomplete="username"
                        value="<?= h($_POST['username'] ?? '') ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Registered Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="form-control"
                        required
                        autocomplete="email"
                        value="<?= h($_POST['email'] ?? '') ?>"
                    >
                </div>

                <div class="d-grid">
                    <button class="btn btn-primary vms-auth-submit" type="submit">Send Reset Link</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="vms-auth-footer">
            For security, password reset requests use a time-limited email link.
        </div>
    </div>
</div>

</body>
</html>