<?php
// forgot_username.php
session_start();
require 'db_connect.php';

if (empty($_SESSION['csrf_fu'])) {
    $_SESSION['csrf_fu'] = bin2hex(random_bytes(32));
}

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_fu'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    if (!empty($_POST['website'])) {
        $errors[] = 'Spam detected.';
    }

    $email = trim($_POST['email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $result = $user['username'];
            } else {
                $errors[] = 'No account found for that email.';
            }
        } catch (Throwable $e) {
            $errors[] = 'An error occurred. Please try again.';
        }

        $_SESSION['csrf_fu'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Username - VMS</title>

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
            max-width: 460px;
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
                <h1 class="vms-auth-title">Forgot Username</h1>
                <p class="vms-auth-subtitle">
                    Enter the email associated with your account and we’ll display your username here.
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

        <?php if ($result): ?>
            <div class="alert alert-success" role="alert">
                <strong>Your username:</strong> <code><?= h($result) ?></code>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_fu']) ?>">
            <div class="hp"><input type="text" name="website" value=""></div>

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
                <button class="btn btn-primary vms-auth-submit" type="submit">Find Username</button>
            </div>
        </form>

        <div class="vms-auth-footer">
            Return to login once you’ve confirmed your username.
        </div>
    </div>
</div>

</body>
</html>