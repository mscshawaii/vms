<?php
declare(strict_types=1);

require 'session_check.php';
require 'db_connect.php';

$session_company_id = (int)($_SESSION['company_id'] ?? 0);
$session_user_id    = (int)($_SESSION['user_id'] ?? 0);
$session_role_id    = (int)($_SESSION['role_id'] ?? 0);

/**
 * Access rule:
 * - MSCS (company_id = 1) always allowed
 * - Non-MSCS must be company admin (role_id = 2)
 */
$allowed_non_mscs_roles = [2];

if ($session_company_id !== 1 && !in_array($session_role_id, $allowed_non_mscs_roles, true)) {
    echo "Access denied.";
    exit;
}

$is_mscs = ($session_company_id === 1);

$role_labels = [
    1 => 'MSCS Admin',
    2 => 'Company Admin',
    3 => 'User',
];

// Filters
$search = trim($_GET['q'] ?? '');
$filter_company_id = (int)($_GET['company_id'] ?? 0);
$filter_role_id = (int)($_GET['role_id'] ?? 0);
$filter_notifications = $_GET['notifications'] ?? '';
$filter_status = $_GET['status'] ?? 'active';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$allowed_per_page = [10, 25, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 25);
if (!in_array($per_page, $allowed_per_page, true)) {
    $per_page = 25;
}

// Load filter options
$roles = $pdo->query("
    SELECT role_id, role_name
    FROM roles
    ORDER BY role_name
")->fetchAll(PDO::FETCH_ASSOC);

$companies = [];
if ($is_mscs) {
    $companies = $pdo->query("
        SELECT owner_id, company_name
        FROM owners
        ORDER BY company_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

$where  = [];
$params = [];

$base_from = "
    FROM users u
    LEFT JOIN owners o
        ON o.owner_id = u.company_id
    LEFT JOIN user_push_subscriptions ups
        ON ups.user_id = u.id
";

if (!$is_mscs) {
    $where[] = 'u.company_id = :session_company_id';
    $params['session_company_id'] = $session_company_id;
}

if ($is_mscs && $filter_company_id > 0) {
    $where[] = 'u.company_id = :filter_company_id';
    $params['filter_company_id'] = $filter_company_id;
}

if ($search !== '') {
    $where[] = '(
        u.fName LIKE :search
        OR u.lName LIKE :search
        OR u.email LIKE :search
        OR u.username LIKE :search
    )';
    $params['search'] = '%' . $search . '%';
}

if ($filter_role_id > 0) {
    $where[] = 'u.role_id = :filter_role_id';
    $params['filter_role_id'] = $filter_role_id;
}

if ($filter_notifications === 'yes') {
    $where[] = 'u.receive_notifications = 1';
} elseif ($filter_notifications === 'no') {
    $where[] = 'u.receive_notifications = 0';
}

if ($filter_status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'u.is_active = 0';
} elseif ($filter_status === 'login_disabled') {
    $where[] = 'u.login_enabled = 0';
}

$where_sql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

// Count total matching users
$count_sql = "
    SELECT COUNT(DISTINCT u.id)
    $base_from
    $where_sql
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = (int)$count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_results / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// Main query
$sql = "
    SELECT
        u.id AS user_id,
        u.fName AS first_name,
        u.lName AS last_name,
        u.email,
        u.username,
        u.role_id,
        u.company_id,
        u.receive_notifications,
        u.is_active,
        u.login_enabled,
        o.company_name,
        MAX(CASE WHEN ups.is_active = 1 THEN 1 ELSE 0 END) AS has_active_push
    $base_from
    $where_sql
    GROUP BY
        u.id,
        u.fName,
        u.lName,
        u.email,
        u.username,
        u.role_id,
        u.company_id,
        u.receive_notifications,
        u.is_active,
        u.login_enabled,
        o.company_name
    ORDER BY o.company_name, u.lName, u.fName
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    if (is_int($value)) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }
}

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$visible_start = $total_results > 0 ? ($offset + 1) : 0;
$visible_end = min($offset + $per_page, $total_results);

function buildPageUrl(array $overrides = []): string {
    $query = $_GET;
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    return 'manage_users.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users - VMS</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">

    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/vms-icon-512.png?v=2">
    <link rel="shortcut icon" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/vms-mobile.css">

    <style>
        .users-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }

        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .users-title {
            font-size: 1.65rem;
            font-weight: 700;
            margin: 0 0 4px;
            line-height: 1.15;
        }

        .users-subtitle {
            color: var(--vms-muted, #6b7280);
            margin: 0;
        }

        .users-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .users-actions .btn {
            min-height: 42px;
            border-radius: 12px;
        }

        .users-filter-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .users-filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .users-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .users-table-wrap table {
            min-width: 980px;
            margin-bottom: 0;
        }

        .users-result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .users-name-cell {
            font-weight: 600;
        }

        .users-pagination {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            margin-top: 14px;
        }

        .users-page-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        @media (min-width: 768px) {
            .users-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1100px) {
            .users-filter-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
<?php
$title = 'Manage Users';
$back_link = 'dashboard.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="users-shell">
    <div class="app-page">
        <div class="app-container">

            <div class="vms-card">
                <div class="users-header">
                    <div>
                        <h1 class="users-title">Manage Users</h1>
                        <p class="users-subtitle">
                            Search, filter, and manage system users, access, and notification settings.
                        </p>
                    </div>

                    <div class="users-actions">
                        <a class="btn btn-outline-secondary" href="dashboard.php">Back to Dashboard</a>
                        <a href="add_user.php" class="btn btn-primary">Add User</a>
                    </div>
                </div>
            </div>

            <div class="vms-card">
                <form method="get">
                    <div class="users-filter-grid mb-3">
                        <div>
                            <label class="form-label">Search</label>
                            <input
                                type="text"
                                name="q"
                                class="form-control"
                                placeholder="Name, email, or username"
                                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <?php if ($is_mscs): ?>
                            <div>
                                <label class="form-label">Company</label>
                                <select name="company_id" class="form-select">
                                    <option value="0">All Companies</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= (int)$company['owner_id'] ?>" <?= $filter_company_id === (int)$company['owner_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="form-label">System Role</label>
                            <select name="role_id" class="form-select">
                                <option value="0">All Roles</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int)$role['role_id'] ?>" <?= $filter_role_id === (int)$role['role_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Email Reminders</label>
                            <select name="notifications" class="form-select">
                                <option value="" <?= $filter_notifications === '' ? 'selected' : '' ?>>All</option>
                                <option value="yes" <?= $filter_notifications === 'yes' ? 'selected' : '' ?>>Notifications On</option>
                                <option value="no" <?= $filter_notifications === 'no' ? 'selected' : '' ?>>Notifications Off</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Only</option>
                                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                                <option value="login_disabled" <?= $filter_status === 'login_disabled' ? 'selected' : '' ?>>Login Disabled</option>
                                <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Per Page</label>
                            <select name="per_page" class="form-select">
                                <?php foreach ($allowed_per_page as $pp): ?>
                                    <option value="<?= $pp ?>" <?= $per_page === $pp ? 'selected' : '' ?>>
                                        <?= $pp ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <input type="hidden" name="page" value="1">

                    <div class="users-filter-actions">
                        <button class="btn btn-primary" type="submit">Apply Filters</button>
                        <a href="manage_users.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <div class="users-result-row">
                <div class="text-muted">
                    Showing <strong><?= (int)$visible_start ?>–<?= (int)$visible_end ?></strong>
                    of <strong><?= (int)$total_results ?></strong> user<?= $total_results === 1 ? '' : 's' ?>
                </div>
            </div>

            <div class="vms-card">
                <div class="users-table-wrap">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>System Role</th>
                                <?php if ($is_mscs): ?>
                                    <th>Company</th>
                                <?php endif; ?>
                                <th>Email Reminders</th>
                                <th>Message Notifications</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$users): ?>
                                <tr>
                                    <td colspan="<?= $is_mscs ? 8 : 7 ?>" class="text-center text-muted">
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($users as $u): ?>
                                <?php
                                $is_self = ((int)$u['user_id'] === $session_user_id);
                                $is_mscs_user = ((int)$u['company_id'] === 1);

                                if ((int)$u['is_active'] === 0) {
                                    $status_badge = '<span class="badge bg-danger">Inactive</span>';
                                } elseif ((int)$u['login_enabled'] === 0) {
                                    $status_badge = '<span class="badge bg-warning text-dark">Login Disabled</span>';
                                } else {
                                    $status_badge = '<span class="badge bg-success">Active</span>';
                                }

                                $notification_badge = ((int)$u['receive_notifications'] === 1)
                                    ? '<span class="badge bg-primary">On</span>'
                                    : '<span class="badge bg-secondary">Off</span>';

                                $message_notification_badge = ((int)($u['has_active_push'] ?? 0) === 1)
                                    ? '<span class="badge bg-success">Enabled</span>'
                                    : '<span class="badge bg-secondary">Not Enabled</span>';
                                ?>
                                <tr>
                                    <td class="users-name-cell">
                                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($is_self): ?>
                                            <span class="badge bg-secondary ms-1">You</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>

                                    <td><?= htmlspecialchars($role_labels[(int)$u['role_id']] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>

                                    <?php if ($is_mscs): ?>
                                        <td><?= htmlspecialchars($u['company_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endif; ?>

                                    <td><?= $notification_badge ?></td>
                                    <td><?= $message_notification_badge ?></td>
                                    <td><?= $status_badge ?></td>

                                    <td class="text-end">
                                        <a href="edit_user.php?id=<?= (int)$u['user_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            Edit
                                        </a>

                                        <?php if (!$is_self && ($is_mscs || !$is_mscs_user)): ?>
                                            <a href="delete_user.php?id=<?= (int)$u['user_id'] ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete this user?');">
                                                Delete
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="users-pagination">
                        <div class="text-muted small">
                            Page <?= (int)$page ?> of <?= (int)$total_pages ?>
                        </div>

                        <div class="users-page-links">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(buildPageUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>">
                                    Prev
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            if ($start_page > 1): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(buildPageUrl(['page' => 1]), ENT_QUOTES, 'UTF-8') ?>">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="btn btn-sm btn-light disabled">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
                                   href="<?= htmlspecialchars(buildPageUrl(['page' => $i]), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="btn btn-sm btn-light disabled">…</span>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(buildPageUrl(['page' => $total_pages]), ENT_QUOTES, 'UTF-8') ?>">
                                    <?= $total_pages ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(buildPageUrl(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-3">
                <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>