<?php require 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Crew Members</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">

<h2>👨‍✈️ Crew Members</h2>

<a href="add_crew_member.php" class="btn btn-success mb-3">➕ Add New Crew Member</a>

<table class="table table-bordered table-striped">
    <thead class="table-light">
        <tr>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Title</th>
            <th>License #</th>
            <th>Notes</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $query = "SELECT * FROM crew_members ORDER BY last_name, first_name";
        $result = $mysqli->query($query);

        if (!$result) {
            echo "<tr><td colspan='6'>❌ Query Error: " . $mysqli->error . "</td></tr>";
        } else {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                echo "<td>" . htmlspecialchars($row['license_number']) . "</td>";
                echo "<td>" . nl2br(htmlspecialchars($row['notes'])) . "</td>";
                echo "<td>
                        <a href='edit_crew_member.php?id={$row['crew_id']}' class='btn btn-sm btn-primary'>Edit</a>
                        <a href='delete_crew_member.php?id={$row['crew_id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Are you sure?')\">Delete</a>
                      </td>";
                echo "</tr>";
            }
        }
        ?>
    </tbody>
</table>

</body>
</html>

