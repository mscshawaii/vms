<?php
require 'db.php';
$crew_id = intval($_GET['id'] ?? 0);

$result = $mysqli->query("SELECT * FROM crew_members WHERE crew_id = $crew_id");
if (!$result || $result->num_rows === 0) {
    die("Crew member not found.");
}
$c = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $title = htmlspecialchars(trim($_POST['title']));
    $license = htmlspecialchars(trim($_POST['license_number']));
    $notes = htmlspecialchars(trim($_POST['notes']));

    $stmt = $mysqli->prepare("UPDATE crew_members SET first_name = ?, last_name = ?, title = ?, license_number = ?, notes = ? WHERE crew_id = ?");
    $stmt->bind_param("sssssi", $first_name, $last_name, $title, $license, $notes, $crew_id);
    $stmt->execute();
    $stmt->close();

    header("Location: crew_members.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Edit Crew</title></head>
<body class="p-4">
<h2>✏️ Edit Crew Member</h2>
<form method="post">
    <label>First Name:</label><br>
    <input type="text" name="first_name" value="<?= htmlspecialchars($c['first_name']) ?>" required><br><br>

    <label>Last Name:</label><br>
    <input type="text" name="last_name" value="<?= htmlspecialchars($c['last_name']) ?>" required><br><br>

    <label>Title:</label><br>
    <input type="text" name="title" value="<?= htmlspecialchars($c['title']) ?>"><br><br>

    <label>License Number:</label><br>
    <input type="text" name="license_number" value="<?= htmlspecialchars($c['license_number']) ?>"><br><br>

    <label>Notes:</label><br>
    <textarea name="notes"><?= htmlspecialchars($c['notes']) ?></textarea><br><br>

    <button type="submit">Update</button>
</form>
</body>
</html>
