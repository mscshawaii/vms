<?php
session_start();
require 'db_connect.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_reset'])) {
    $_SESSION['csrf_reset'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = '';
$showForm = false;

$selector = $_GET['selector'] ?? ($_POST['selector'] ?? '');
$token    = $_GET['token'] ?? ($_POST['token'] ?? '');

function password_ok($p) {
    if (strlen($p) < 10) return false;
    $classes = 0;
    $classes += preg_match('/[a-z]/', $p) ? 1 : 0;
    $classes += preg_match('/[A-Z]/', $p) ? 1 : 0;
    $classes += preg_match('/\d/', $p) ? 1 : 0;
    $classes += preg_match('/[\W_]/', $p) ? 1 : 0;
    return $classes >= 3;
}

$resetRow = null;
if ($selector && $token) {
    $stmt = $pdo->prepare("SELECT *
                           FROM password_resets
                           WHERE selector = ?
                             AND used_at IS NULL
                             AND expires_at > NOW()
                           LIMIT 1");
    $stmt->execute([$selector]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRow || !password_verify($token, $resetRow['token_hash'])) {
        $resetRow = null;
    }
}

if (!$resetRow) {
    $errors[] = 'This password reset link is invalid or has expired.';
} else {
    $showForm = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    if (!hash_equals($_SESSION['csrf_reset'] ?? '', $_POST['csrf'] ?? '')) {
        $errors[] = 'Security token mismatch. Please try again.';
    }

    if (!empty($_POST['website'])) {
        $errors[] = 'Spam detected.';
    }

    $new1 = $_POST['new_password'] ?? '';
    $new2 = $_POST['confirm_password'] ?? '';

    if ($new1 === '' || $new2 === '') {
        $errors[] = 'Enter and confirm your new password.';
    }
    if ($new1 !== $new2) {
        $errors[] = 'Passwords do not match.';
    }
    if (!password_ok($new1)) {
        $errors[] = 'Password must be at least 10 characters and include 3 of: lowercase, uppercase, number, symbol.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($new1, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET pword = ? WHERE id = ?")
                ->execute([$hash, (int)$resetRow['user_id']]);

            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                ->execute([(int)$resetRow['id']]);

            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL")
                ->execute([(int)$resetRow['user_id']]);

            $pdo->commit();

            $success = 'Password updated! You can now log in.';
            $showForm = false;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not update password. Please try again.';
        }
    }

    $_SESSION['csrf_reset'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - VMS</title>

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
                <h1 class="vms-auth-title">Reset Password</h1>
                <p class="vms-auth-subtitle">
                    Create a new password for your VMS account using the secure reset link provided.
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

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?= h($success) ?>
            </div>

            <div class="d-grid mt-3">
                <a class="btn btn-primary vms-auth-submit" href="login.php">Go to Login</a>
            </div>

        <?php elseif ($showForm): ?>
            <div class="alert alert-info small" role="alert">
                Choose a strong password. Minimum 10 characters, using at least 3 of: lowercase, uppercase, number, or symbol.
            </div>

            <form method="post" novalidate>
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_reset']) ?>">
                <div class="hp"><input type="text" name="website" value=""></div>

                <input type="hidden" name="selector" value="<?= h($selector) ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">

                <div class="mb-3">
                    <label class="form-label" for="new_password">New Password</label>
                    <input
                        id="new_password"
                        type="password"
                        name="new_password"
                        class="form-control"
                        required
                        autocomplete="new-password"
                    >
                    <div class="form-text">
                        Minimum 10 characters; include 3 of: lower, UPPER, number, symbol.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input
                        id="confirm_password"
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="d-grid">
                    <button class="btn btn-success vms-auth-submit" type="submit">Update Password</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="vms-auth-footer">
            Reset links are time-limited and can only be used once.
        </div>
    </div>
</div>

</body>
</html>