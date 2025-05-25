<?php
require 'session_check.php';
require 'db_connect.php';

if ($_SESSION['company_id'] != 1) {
    echo "Access denied.";
    exit;
}

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM owners WHERE owner_id = ?");
$stmt->execute([$id]);
$company = $stmt->fetch();

if (!$company) {
    echo "Company not found.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Edit Company</title></head>
<body>
<h2>✏️ Edit Company</h2>
<form method="post" action="update_company.php">
    <input type="hidden" name="owner_id" value="<?= $company['owner_id'] ?>">
    <label>Company Name:</label><br>
    <input type="text" name="company_name" value="<?= htmlspecialchars($company['company_name']) ?>" required><br><br>

    <label>Contact Name:</label><br>
    <input type="text" name="contact_name" value="<?= htmlspecialchars($company['contact_name']) ?>"><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" value="<?= htmlspecialchars($company['email']) ?>"><br><br>

    <label>Phone:</label><br>
    <input type="text" name="phone" value="<?= htmlspecialchars($company['phone']) ?>"><br><br>

    <label>Address:</label><br>
    <textarea name="address" rows="3" cols="40"><?= htmlspecialchars($company['address']) ?></textarea><br><br>

    <button type="submit">💾 Update</button>
</form>
</body>
</html>
