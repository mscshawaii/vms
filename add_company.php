<?php
require 'session_check.php';
require 'db_connect.php';

// Only MSCS Hawaii (owner_id = 1) can access
if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Company</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2 class="mb-4">Add New Company</h2>
    <form method="post" action="submit_company.php" enctype="multipart/form-data" class="row g-3">

        <div class="col-md-6">
            <label class="form-label">Company Name</label>
            <input type="text" name="company_name" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Company Logo (optional)</label>
            <input type="file" name="logo" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Contact Name</label>
            <input type="text" name="contact_name" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <div class="col-md-12">
            <label class="form-label">Address</label>
            <textarea name="address" rows="3" class="form-control"></textarea>
        </div>

        <div class="col-12 text-end mt-3">
            <button type="submit" class="btn btn-primary">Save Company</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>

    </form>
</div>
</body>
</html>
