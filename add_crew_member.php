<?php require 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Crew Member</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>👨‍✈️ Add New Crew Member</h2>

    <form method="post" action="submit_crew_member.php">
        <div class="mb-3">
            <label>First Name:</label>
            <input type="text" name="first_name" required class="form-control">
        </div>
        <div class="mb-3">
            <label>Last Name:</label>
            <input type="text" name="last_name" required class="form-control">
        </div>
        <div class="mb-3">
            <label>Title:</label>
            <input type="text" name="title" class="form-control">
        </div>
        <div class="mb-3">
            <label>License Number:</label>
            <input type="text" name="license_number" class="form-control">
        </div>
        <div class="mb-3">
            <label>Notes:</label>
            <textarea name="notes" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-success">➕ Add Crew Member</button>
    </form>
</div>
</body>
</html>
