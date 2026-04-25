<?php
require __DIR__ . '/session_check.php';
require __DIR__ . '/db_connect.php';

// Only MSCS Hawaii (owner_id = 1) can access
if (($_SESSION['company_id'] ?? 0) != 1) {
    echo "Access denied.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add New Company</title>
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
$title = 'Add Company';
$back_link = 'view_companies.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="companies-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body">
                    <h1 class="h3 mb-1">Add New Company</h1>
                    <p class="page-meta">Create a company record and basic contact details.</p>
                </div>
            </div>

            <form id="addCompanyForm" method="post" action="submit_company.php" enctype="multipart/form-data">

                <div class="card shadow-sm form-section-card mb-4">
                    <div class="card-body">
                        <div class="section-title">Company Details</div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Company Logo (optional)</label>
                                <input type="file" name="logo" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="contact_name" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="3" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-lg-flex justify-content-between gap-2 mt-4">
                    <a href="view_companies.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Company</button>
                </div>
            </form>

        </div>
    </div>
</div>

<div class="sticky-mobile-actions d-lg-none py-2 px-3">
    <div class="app-container px-0">
        <div class="d-grid gap-2">
            <button type="submit" form="addCompanyForm" class="btn btn-primary">Save Company</button>
            <a href="view_companies.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>
