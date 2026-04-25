<?php
require 'session_check.php';
require 'db_connect.php';

if (($_SESSION['company_id'] ?? 0) != 1) {
    echo "Access denied.";
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "Invalid company id."; exit; }

// Load company
$stmt = $pdo->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->execute([$id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$company) { echo "Company not found."; exit; }

// Load users scoped to this company
$usersStmt = $pdo->prepare("
    SELECT id, fName, lName, email 
    FROM users 
    WHERE company_id = ? 
    ORDER BY fName, lName
");
$usersStmt->execute([$id]);
$companyUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function userOption($u, $selectedId) {
    $label = trim(($u['fName'] ?? '').' '.($u['lName'] ?? ''));
    if ($label === '') $label = $u['email'] ?? ('User '.$u['id']);
    $sel = ((int)$selectedId === (int)$u['id']) ? ' selected' : '';
    return '<option value="'.(int)$u['id'].'"'.$sel.'>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</option>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Company</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0d6efd">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/vms-icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/assets/vms-icon-192.png?v=2">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="/assets/css/vms-mobile.css" rel="stylesheet">

    <style>
        .companies-shell {
            background: var(--vms-bg, #f4f7fb);
            min-height: 100vh;
        }
        .page-header-card,
        .form-section-card {
            border: 0;
            border-radius: 1rem;
        }
        .page-meta {
            color: #6b7280;
            margin: 0;
        }
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .logo-preview {
            height: 56px;
            width: auto;
            object-fit: contain;
            border: 1px solid #ddd;
            padding: 4px;
            background: #fff;
            border-radius: .5rem;
        }
        .sticky-mobile-actions {
            position: sticky;
            bottom: 0;
            z-index: 1020;
            background: rgba(248,249,250,.96);
            backdrop-filter: blur(6px);
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
<?php
$title = 'Edit Company';
$back_link = 'view_companies.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="companies-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <h1 class="h3 mb-1">Edit Company</h1>
                    <p class="page-meta"><?= h($company['company_name'] ?? '') ?></p>
                </div>
            </div>

            <form id="editCompanyForm" method="post" action="update_company.php" enctype="multipart/form-data">
                <input type="hidden" name="owner_id" value="<?= (int)$company['owner_id'] ?>">

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Company Details</div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control" required
                                       value="<?= h($company['company_name'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="contact_name" class="form-control"
                                       value="<?= h($company['contact_name'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= h($company['email'] ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?= h($company['phone'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="3" class="form-control"><?= h($company['address'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Primary Contact (User)</label>
                                <select name="primary_contact_user_id" class="form-select">
                                    <option value="">— None —</option>
                                    <?php foreach ($companyUsers as $u) { echo userOption($u, $company['primary_contact_user_id'] ?? null); } ?>
                                </select>
                                <div class="form-text">Users scoped to this company.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Alternate Contact (User)</label>
                                <select name="alt_contact_user_id" class="form-select">
                                    <option value="">— None —</option>
                                    <?php foreach ($companyUsers as $u) { echo userOption($u, $company['alt_contact_user_id'] ?? null); } ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label d-block">Logo</label>
                                <?php if (!empty($company['logo_path'])): ?>
                                    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                                        <img src="<?= h($company['logo_path']) ?>" alt="Logo" class="logo-preview">
                                        <span class="text-muted small"><?= h($company['logo_path']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="row g-2">
                                    <div class="col-12 col-md-8">
                                        <input type="file" name="logo_file" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp,.svg">
                                        <div class="form-text">Upload to replace the current logo (optional).</div>
                                    </div>
                                    <div class="col-12 col-md-4 d-flex align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="clearLogo" name="clear_logo">
                                            <label class="form-check-label" for="clearLogo">Clear logo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2">
                    <a href="view_companies.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Company</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="editCompanyForm" class="btn btn-primary">Update Company</button>
            <a href="view_companies.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>