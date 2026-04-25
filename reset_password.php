<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['role_id'] != 1) {
    echo "Access denied.";
    exit;
}

$user_id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
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
            background: #f4f7fb;
        }

        .vms-admin-shell {
            min-height: 100vh;
            padding: 20px 12px 32px;
        }

        .vms-admin-wrap {
            max-width: 640px;
            margin: 0 auto;
        }

        .vms-admin-card .form-label {
            font-weight: 600;
            margin-bottom: 6px;
        }

        .vms-admin-card .form-control {
            min-height: 50px;
            border-radius: 12px;
            border-color: #dbe4ee;
            padding-left: 14px;
            padding-right: 14px;
        }

        .vms-admin-card .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.12);
        }

        .vms-admin-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 6px;
            color: #1f2a37;
        }

        .vms-admin-subtitle {
            color: #6b7280;
            margin: 0;
        }

        .vms-admin-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .vms-admin-actions .btn {
            min-height: 46px;
            border-radius: 12px;
        }

        .vms-user-chip {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e9f2ff;
            border: 1px solid #cfe2ff;
            color: #0d6efd;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 14px;
        }

        @media (max-width: 575.98px) {
            .vms-admin-actions {
                flex-direction: column;
            }

            .vms-admin-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="vms-admin-shell">
    <div class="vms-admin-wrap">
        <div class="vms-card vms-admin-card">
            <h1 class="vms-admin-title">Reset Password</h1>
            <p class="vms-admin-subtitle">
                Set a new password for the selected user account.
            </p>

            <div class="vms-user-chip">
                User: <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <form action="submit_password_reset.php" method="post">
                <input type="hidden" name="id" value="<?= (int)$user_id ?>">

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
                        Consider using the same password strength rules as the user reset flow.
                    </div>
                </div>

                <div class="vms-admin-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                    <a href="users_list.php" class="btn btn-outline-secondary">Back to User List</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>