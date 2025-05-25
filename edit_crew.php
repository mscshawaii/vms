<?php
require 'db.php';
$id = intval($_GET['id'] ?? 0);

$result = $mysqli->query("SELECT * FROM crew_members WHERE id = $id");
if (!$result || $result->num_rows === 0) {
    die("Crew member not found.");
}
$c = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $title = htmlspecialchars(trim($_POST['title']));
    $license = htmlspecialchars(trim($_POST['license_number']));
    $notes = htmlspecialchars(trim($_POST['notes']));

    $stmt = $mysqli->prepare("UPDATE crew_members SET name=?, title=?, license_number=?, notes=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $title, $license, $notes, $id);
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
    <label>Name:</label><br>
    <input type="text" name="name" value="<?= $c['name'] ?>" required><br><br>

    <label>Title:</label><br>
    <input type="text" name="title" value="<?= $c['title'] ?>"><br><br>

    <label>License Number:</label><br>
    <input type="text" name="license_number" value="<?= $c['license_number'] ?>"><br><br>

    <label>Notes:</label><br>
    <textarea name="notes"><?= $c['notes'] ?></textarea><br><br>

    <button type="submit">Update</button>
</form>
</body>
</html>
