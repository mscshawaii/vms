<?php
require 'db_connect.php';
require 'session_check.php';

$crew_id_prefill = intval($_GET['crew_id'] ?? 0);

// Fetch vessels for this company
$vessels = $pdo->prepare("SELECT vessel_id, vesselName FROM vessels WHERE company_id = ?");
$vessels->execute([$_SESSION['company_id']]);

// Fetch crew for this company
$crew_stmt = $pdo->prepare("
SELECT cm.crew_id, u.fName AS first_name, u.lName AS last_name
FROM crew_members cm
JOIN users u ON cm.user_id = u.id
WHERE u.company_cid = ?
ORDER BY u.lName, u.fName

");
$crew_stmt->execute([$_SESSION['company_id']]);
$crew = $crew_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Log Drill</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
  <h3>🛟 Log Drill</h3>

  <form method="post" action="submit_drill.php">
    <div class="mb-3">
      <label class="form-label">Vessel:</label>
      <select name="vessel_id" class="form-select" required>
        <option value="">-- Select Vessel --</option>
        <?php foreach ($vessels as $v): ?>
          <option value="<?= $v['vessel_id'] ?>"><?= htmlspecialchars($v['vesselName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Drill Type:</label>
      <select name="drill_type" class="form-select" required>
        <option value="">-- Select Drill Type --</option>
        <?php
        $types = ['Fire', 'Abandon Ship', 'MOB', 'Flooding', 'Security', 'Other'];
        foreach ($types as $type) {
          echo "<option value='$type'>$type</option>";
        }
        ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Date of Drill:</label>
      <input type="date" name="drill_date" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Notes:</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes..."></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Participating Crew:</label>
      <div class="form-check">
        <?php foreach ($crew as $c): ?>
          <div>
            <input type="checkbox" name="crew_ids[]" value="<?= $c['crew_id'] ?>"
              class="form-check-input" id="crew_<?= $c['crew_id'] ?>"
              <?= $crew_id_prefill === intval($c['crew_id']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="crew_<?= $c['crew_id'] ?>">
              <?= htmlspecialchars($c['last_name'] . ', ' . $c['first_name']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">✅ Log Drill</button>
    <a href="crew_members.php" class="btn btn-secondary">← Cancel</a>
  </form>
</div>
</body>
</html>
