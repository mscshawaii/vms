<?php
session_start();
require __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/legal_version.php';

$error = '';
$legal_error = '';

// Compute base path so this works both:
// Local: /vessel_management_system/login.php  -> /vessel_management_system/dashboard.php
// DO:    /login.php                           -> /dashboard.php
// Compute base path dynamically
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;

// Correct target
$defaultRedirect = ($basePath !== '' ? $basePath : '') . '/dashboard.php';

// Use redirect if provided, otherwise fallback
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? $defaultRedirect;

// Sanity checks
if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/') {
    $redirect = $defaultRedirect;
}

if (preg_match('/^https?:\/\//i', $redirect)) {
    $redirect = $defaultRedirect;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $acknowledged_legal = isset($_POST['acknowledge_legal']) && $_POST['acknowledge_legal'] === '1';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['pword'])) {
        $userId = (int)$user['id'];
        $alreadyAccepted = vms_user_has_current_legal_ack($pdo, $userId);

        if (!$alreadyAccepted && !$acknowledged_legal) {
            $legal_error = "You must acknowledge the Terms, Privacy Policy, and EULA to continue.";
        } else {
            if (!$alreadyAccepted) {
                vms_record_legal_ack($pdo, $userId);
            }

            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role_id']     = $user['role_id'];
            $_SESSION['company_id']  = $user['company_id'];
            $_SESSION['fName']       = $user['fName'];
            $_SESSION['last_active'] = time();

            if (!empty($_SESSION['post_login_redirect']) && is_string($_SESSION['post_login_redirect'])) {
                $sessionRedirect = $_SESSION['post_login_redirect'];
                unset($_SESSION['post_login_redirect']);

                if ($sessionRedirect !== '' && $sessionRedirect[0] === '/' && !preg_match('/^https?:\/\//i', $sessionRedirect)) {
                    $redirect = $sessionRedirect;
                } else {
                    $redirect = $defaultRedirect;
                }
            } else {
                $redirect = $redirect ?: $defaultRedirect;
            }

            header("Location: " . $redirect);
            exit;
        }
    } else {
        $error = "Invalid username or password.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - VMS</title>

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

        .vms-login-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 12px;
        }

        .vms-login-card {
            width: 100%;
            max-width: 440px;
        }

        .vms-login-brand {
            text-align: center;
            margin-bottom: 18px;
        }

        .vms-login-brand img {
            max-width: 220px;
            width: 100%;
            height: auto;
        }

        .vms-login-eyebrow {
            display: inline-block;
            margin-bottom: 10px;
            padding: 6px 12px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #0d6efd;
            background: #e9f2ff;
            border: 1px solid #cfe2ff;
            border-radius: 999px;
        }

        .vms-login-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 6px;
            color: #1f2a37;
        }

        .vms-login-subtitle {
            text-align: center;
            color: #6b7280;
            font-size: 0.96rem;
            margin-bottom: 20px;
        }

        .vms-login-card .form-label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .vms-login-card .form-control {
            min-height: 50px;
            border-radius: 12px;
            border-color: #dbe4ee;
            padding-left: 14px;
            padding-right: 14px;
        }

        .vms-login-card .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
        }

        .vms-login-submit {
            min-height: 50px;
            border-radius: 12px;
            font-weight: 600;
        }

        .vms-login-links {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 0.95rem;
        }

        .vms-login-links a {
            text-decoration: none;
        }

        .vms-login-links a:hover {
            text-decoration: underline;
        }

        .vms-login-footer {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px solid #e9edf2;
            text-align: center;
            font-size: 0.88rem;
            color: #6b7280;
        }

        @media (max-width: 575.98px) {
            .vms-login-shell {
                align-items: flex-start;
                padding-top: 28px;
            }

            .vms-login-card {
                border-radius: 18px;
            }

            .vms-login-brand img {
                max-width: 190px;
            }

            .vms-login-title {
                font-size: 1.35rem;
            }

            .vms-login-links {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-check-input:focus {
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
            }

            .vms-login-footer a {
                text-decoration: none;
            }

            .vms-login-footer a:hover {
                text-decoration: underline;
            }
        }
    </style>
</head>
<body>

<div class="vms-login-shell">
    <div class="vms-login-card vms-card">
        <div class="vms-login-brand">
            <img src="assets/vms-logo.png" alt="VMS Logo">
        </div>

        <div class="text-center">
            <div class="vms-login-eyebrow">MSCS Hawaii</div>
        </div>

        <h1 class="vms-login-title">Vessel Management System</h1>
        <p class="vms-login-subtitle">
            Sign in to access vessels, compliance records, messaging, and workflow tools.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger mb-3" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($legal_error): ?>
            <div class="alert alert-warning mb-3" role="alert">
                <?= htmlspecialchars($legal_error) ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input
                type="hidden"
                name="redirect"
                value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input
                    id="username"
                    type="text"
                    name="username"
                    class="form-control"
                    required
                    autofocus
                    autocomplete="username"
                >
            </div>

            <div class="mb-2">
                <label class="form-label" for="password">Password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="vms-login-links">
                <a href="forgot_username.php">Forgot Username?</a>
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <div id="legal-ack-container" class="form-check mt-3" style="display: none;">
                <input class="form-check-input" type="checkbox" name="acknowledge_legal" value="1" id="acknowledge_legal">
                <label class="form-check-label small" for="acknowledge_legal">
                    I acknowledge the
                    <a href="terms.php" target="_blank">Terms of Service</a>,
                    <a href="privacy.php" target="_blank">Privacy Policy</a>,
                    and
                    <a href="eula.php" target="_blank">EULA</a>.
                </label>
            </div>

            <div class="d-grid mt-4">
                <button class="btn btn-primary vms-login-submit" type="submit">Login</button>
            </div>
        </form>

            <div class="vms-login-footer">
                <div class="mb-2">
                    Mobile-friendly access for vessel operations, inspections, records, and communication.
                </div>
                <div class="small">
                    <a href="terms.php" target="_blank" rel="noopener noreferrer">Terms</a> ·
                    <a href="privacy.php" target="_blank" rel="noopener noreferrer">Privacy</a> ·
                    <a href="eula.php" target="_blank" rel="noopener noreferrer">EULA</a>
                </div>
            </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const usernameInput = document.getElementById('username');
    const container = document.getElementById('legal-ack-container');

    let timeout = null;

    usernameInput.addEventListener('input', function () {
        clearTimeout(timeout);

        timeout = setTimeout(() => {
            const username = usernameInput.value.trim();

            if (username.length < 2) {
                container.style.display = 'none';
                return;
            }

            fetch('api/check_legal_ack.php?username=' + encodeURIComponent(username))
                .then(res => res.json())
                .then(data => {
                    if (data.needs_ack) {
                        container.style.display = 'block';
                    } else {
                        container.style.display = 'none';
                    }
                })
                .catch(() => {
                    container.style.display = 'none';
                });

        }, 300); // debounce
    });
});
</script>
</body>
</html>
