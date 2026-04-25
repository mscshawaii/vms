<?php
declare(strict_types=1);

require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';
$onesignalConfig = __DIR__ . '/private/config_onesignal.php';
if (!file_exists($onesignalConfig)) {
    $onesignalConfig = '/var/www/private/config_onesignal.php';
}
require_once $onesignalConfig;

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo "Access denied.";
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.fName,
        u.lName,
        u.email,
        u.username,
        u.phoneNumber,
        u.receive_notifications,
        u.mmc,
        u.mmc_medical,
        u.fa,
        u.mrop,
        u.mmc_path,
        u.mmc_medical_path,
        u.fa_path,
        u.mrop_path,
        MAX(CASE WHEN ups.is_active = 1 THEN 1 ELSE 0 END) AS has_active_push,
        COUNT(CASE WHEN ups.is_active = 1 THEN 1 END) AS active_push_count
    FROM users u
    LEFT JOIN user_push_subscriptions ups
        ON ups.user_id = u.id
    WHERE u.id = ?
    GROUP BY
        u.id,
        u.fName,
        u.lName,
        u.email,
        u.username,
        u.phoneNumber,
        u.receive_notifications,
        u.mmc,
        u.mmc_medical,
        u.fa,
        u.mrop,
        u.mmc_path,
        u.mmc_medical_path,
        u.fa_path,
        u.mrop_path
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

$statusMessage = '';
$statusType = $_GET['status'] ?? '';
if ($statusType === 'saved') {
    $statusMessage = 'Settings updated successfully.';
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$basePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : $scriptDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Settings - VMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="<?= $basePath ?>/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= $basePath ?>/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="<?= $basePath ?>/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $basePath ?>/assets/css/vms-mobile.css">

    <style>
        .settings-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .settings-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .settings-subtitle {
            color: #6b7280;
            margin: 0;
        }

        .settings-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .settings-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .settings-section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .settings-section-desc {
            font-size: 0.92rem;
            color: #6b7280;
            margin-bottom: 14px;
        }

        .settings-meta-grid {
            display: grid;
            gap: 12px;
        }

        .settings-meta-item {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px;
            background: #fff;
        }

        .settings-meta-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .settings-meta-value {
            font-size: 1rem;
            font-weight: 600;
        }

        .settings-inline-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sticky-settings-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(244,247,251,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dbe4ee;
            padding-top: 12px;
            margin-top: 16px;
        }

        @media (min-width: 768px) {
            .settings-meta-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<?php
$title = 'User Settings';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="settings-shell">
    <div class="app-page">
        <div class="app-container">

            <?php if ($statusMessage !== ''): ?>
                <div class="alert alert-success"><?= h($statusMessage) ?></div>
            <?php endif; ?>

            <div class="vms-card">
                <div class="settings-header">
                    <div>
                        <h1 class="settings-title">User Settings</h1>
                        <p class="settings-subtitle">
                            Manage your profile, login details, reminder preferences, and push notifications.
                        </p>
                    </div>

                    <div class="settings-actions">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-danger">Log Out</a>
                    </div>
                </div>
            </div>

            <form action="update_user_settings.php" method="post" enctype="multipart/form-data">
                <div class="vms-card mb-3">
                    <div class="settings-section-title">Profile Settings</div>
                    <div class="settings-section-desc">
                        Update your basic account information. Leave the password field blank to keep your current password.
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">First Name</label>
                            <input
                                type="text"
                                name="fName"
                                class="form-control"
                                value="<?= h($user['fName'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Last Name</label>
                            <input
                                type="text"
                                name="lName"
                                class="form-control"
                                value="<?= h($user['lName'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= h($user['email'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input
                                type="text"
                                name="phoneNumber"
                                class="form-control"
                                value="<?= h($user['phoneNumber'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Username</label>
                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                value="<?= h($user['username'] ?? '') ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">New Password</label>
                            <input
                                type="password"
                                name="new_password"
                                class="form-control"
                                placeholder="Leave blank to keep current password"
                            >
                        </div>
                    </div>
                </div>

                <div class="vms-card mb-3">
                    <div class="settings-section-title">Email Reminders</div>
                    <div class="settings-section-desc">
                        Controls reminder emails sent by VMS for your account.
                    </div>

                    <div class="vms-card mb-3">
                    <div class="settings-section-title">Credentials / Expirations</div>
                    <div class="settings-section-desc">
                        Keep your credential dates and supporting documents current.
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label">MMC Expiration</label>
                            <input
                                type="date"
                                name="mmc"
                                class="form-control"
                                value="<?= h($user['mmc'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">MMC Medical</label>
                            <input
                                type="date"
                                name="mmc_medical"
                                class="form-control"
                                value="<?= h($user['mmc_medical'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">First Aid Exp.</label>
                            <input
                                type="date"
                                name="fa"
                                class="form-control"
                                value="<?= h($user['fa'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label">MROP Issued</label>
                            <input
                                type="date"
                                name="mrop"
                                class="form-control"
                                value="<?= h($user['mrop'] ?? '') ?>"
                            >
                        </div>
                    </div>
                </div>

                <div class="vms-card mb-3">
                    <div class="settings-section-title">Credential Documents</div>
                    <div class="settings-section-desc">
                        Upload replacements only when you need to update the current file.
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">MMC Document</label>
                            <input type="file" name="mmc_path" class="form-control">
                            <?php if (!empty($user['mmc_path'])): ?>
                                <div class="mt-2 small text-muted">
                                    Current: <a href="<?= h($user['mmc_path']) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">MMC Medical Document</label>
                            <input type="file" name="mmc_medical_path" class="form-control">
                            <?php if (!empty($user['mmc_medical_path'])): ?>
                                <div class="mt-2 small text-muted">
                                    Current: <a href="<?= h($user['mmc_medical_path']) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">First Aid Document</label>
                            <input type="file" name="fa_path" class="form-control">
                            <?php if (!empty($user['fa_path'])): ?>
                                <div class="mt-2 small text-muted">
                                    Current: <a href="<?= h($user['fa_path']) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">MROP Document</label>
                            <input type="file" name="mrop_path" class="form-control">
                            <?php if (!empty($user['mrop_path'])): ?>
                                <div class="mt-2 small text-muted">
                                    Current: <a href="<?= h($user['mrop_path']) ?>" target="_blank">View</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="receive_notifications"
                            id="receive_notifications"
                            value="1"
                            <?= !empty($user['receive_notifications']) ? 'checked' : '' ?>
                        >
                        <label class="form-check-label" for="receive_notifications">
                            Receive email reminders
                        </label>
                    </div>
                </div>

                <div class="sticky-settings-actions">
                    <div class="vms-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div class="text-muted">
                                Changes apply to your current account only.
                            </div>

                            <div class="settings-inline-buttons">
                                <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="vms-card mb-3">
                <div class="settings-section-title">Push Notifications</div>
                <div class="settings-section-desc">
                    Enable push notifications on this device to receive company, vessel, and task message alerts.
                </div>

                <div class="settings-meta-grid mb-3">
                    <div class="settings-meta-item">
                        <div class="settings-meta-label">Status</div>
                        <div class="settings-meta-value">
                            <?php if ((int)($user['has_active_push'] ?? 0) === 1): ?>
                                <span class="badge bg-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Enabled</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="settings-meta-item">
                        <div class="settings-meta-label">Active Device Records</div>
                        <div class="settings-meta-value">
                            <span class="badge bg-info text-dark"><?= (int)($user['active_push_count'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>

                <div class="settings-inline-buttons">
                    <button type="button" class="btn btn-success" id="enablePushBtn">
                        Enable Notifications on This Device
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="disablePushBtn">
                        Disable Notifications on This Device
                    </button>
                </div>

                <div id="pushStatusMsg" class="mt-3 small text-muted"></div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function(OneSignal) {
    const vmsUserId = <?= json_encode((string)($_SESSION['user_id'] ?? '')) ?>;

    async function syncPushStatus() {
        try {
            const payload = {
                external_id: String(vmsUserId || ''),
                onesignal_id: OneSignal.User.onesignalId || '',
                subscription_id: OneSignal.User.PushSubscription.id || '',
                platform: 'web',
                is_active: !!OneSignal.User.PushSubscription.optedIn
            };

            if (!payload.subscription_id) {
                const msgEl = document.getElementById('pushStatusMsg');
                if (msgEl) {
                    msgEl.textContent = 'No active subscription detected on this device yet.';
                }
                return;
            }

            const res = await fetch('api/sync_push_subscription.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            console.log('User settings push sync:', data);
        } catch (err) {
            console.error('User settings push sync failed', err);
        }
    }

    try {
        await OneSignal.init({
            appId: "<?= htmlspecialchars(ONESIGNAL_APP_ID, ENT_QUOTES, 'UTF-8') ?>"
        });

        if (vmsUserId) {
            await OneSignal.login(vmsUserId);
        }

        await syncPushStatus();

        OneSignal.User.PushSubscription.addEventListener('change', async function() {
            await syncPushStatus();
        });

        document.getElementById('enablePushBtn')?.addEventListener('click', async function() {
            try {
                await OneSignal.Notifications.requestPermission();
                await syncPushStatus();
                window.location.reload();
            } catch (e) {
                console.error(e);
            }
        });

        document.getElementById('disablePushBtn')?.addEventListener('click', async function() {
            try {
                await OneSignal.User.PushSubscription.optOut();
                await syncPushStatus();
                window.location.reload();
            } catch (e) {
                console.error(e);
            }
        });

    } catch (err) {
        console.error('User settings OneSignal init/login failed', err);
    }
});
</script>
</body>
</html>
