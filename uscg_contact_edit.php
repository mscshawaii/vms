<?php
// uscg_contact_edit.php — add/edit USCG contact (MSCS-only)
session_start();
require 'session_check.php';
require 'db_connect.php';

$MSCS_OWNER_ID = 1;
if ((int)($_SESSION['company_id'] ?? 0) !== $MSCS_OWNER_ID) {
  http_response_code(403); exit('Forbidden');
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function clean($v, $max=255){
  $v = trim((string)$v);
  if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
  return $v;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
} else {
  $posted = $_POST['csrf_token'] ?? '';
  if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $posted)) {
    http_response_code(400); exit('Invalid request token');
  }
}

$errors = [];
$data = [
  'region_name'   => '',
  'port_name'     => '',
  'email_to'      => '',
  'email_cc'      => '',
  'phone_display' => '',
  'notes'         => '',
  'active'        => 1,
];

// Load existing
if ($id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $stmt = $pdo->prepare("SELECT * FROM uscg_contacts WHERE contact_id = ?");
  $stmt->execute([$id]);
  if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    foreach ($data as $k => $v) {
      if (array_key_exists($k, $row)) $data[$k] = $row[$k];
    }
  } else {
    http_response_code(404); exit('Contact not found');
  }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $data['region_name']   = clean($_POST['region_name'] ?? '', 80);
  $data['port_name']     = clean($_POST['port_name'] ?? '', 80);
  $data['email_to']      = clean($_POST['email_to'] ?? '', 160);
  $data['email_cc']      = clean($_POST['email_cc'] ?? '', 300);
  $data['phone_display'] = clean($_POST['phone_display'] ?? '', 60);
  $data['notes']         = clean($_POST['notes'] ?? '', 255);
  $data['active']        = isset($_POST['active']) ? 1 : 0;

  if ($data['region_name'] === '') $errors[] = 'Region/Island is required.';
  if ($data['email_to'] === '')    $errors[] = 'Email (To) is required.';

  if (!$errors) {
    if ($id > 0) {
      $stmt = $pdo->prepare("
        UPDATE uscg_contacts
           SET region_name=?, port_name=?, email_to=?, email_cc=?, phone_display=?, notes=?, active=?
         WHERE contact_id=?
      ");
      $stmt->execute([
        $data['region_name'], ($data['port_name'] ?: null), $data['email_to'],
        ($data['email_cc'] ?: null), ($data['phone_display'] ?: null), ($data['notes'] ?: null),
        $data['active'], $id
      ]);
    } else {
      $stmt = $pdo->prepare("
        INSERT INTO uscg_contacts (region_name, port_name, email_to, email_cc, phone_display, notes, active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([
        $data['region_name'], ($data['port_name'] ?: null), $data['email_to'],
        ($data['email_cc'] ?: null), ($data['phone_display'] ?: null), ($data['notes'] ?: null),
        $data['active']
      ]);
    }

    header('Location: uscg_contacts.php');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $id ? 'Edit' : 'Add' ?> USCG Contact</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0d6efd">
<link rel="icon" href="/assets/vms-icon-192.png?v=2">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link href="/assets/css/vms-mobile.css" rel="stylesheet">

<style>
    .contact-shell {
        background: var(--vms-bg, #f4f7fb);
        min-height: 100vh;
    }

    .page-header-card,
    .form-card {
        border: 0;
        border-radius: 1rem;
    }

    .page-meta {
        color: #6b7280;
        margin: 0;
    }
</style>
</head>
<body>

<?php
$title = $id ? 'Edit Contact' : 'Add Contact';
$back_link = 'uscg_contacts.php';
include __DIR__ . '/partials/top_nav.php';
?>

<div class="contact-shell">
    <div class="app-page">
        <div class="app-container pb-5">

            <!-- Header -->
            <div class="card shadow-sm page-header-card mb-3">
                <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h1 class="h4 mb-1"><?= $id ? 'Edit' : 'Add' ?> USCG Contact</h1>
                        <p class="page-meta">Manage inspection routing and contact information.</p>
                    </div>

                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="uscg_contacts.php">Back</a>
                    </div>
                </div>
            </div>

            <!-- Errors -->
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= h($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="post" class="card shadow-sm form-card p-3 p-md-4">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <div class="row g-3">

                    <div class="col-12 col-md-6">
                        <label class="form-label">Island / Region *</label>
                        <input type="text" name="region_name" class="form-control"
                               value="<?= h($data['region_name']) ?>" required>
                        <div class="form-text">Oʻahu, Kauaʻi, Maui County, Hawaiʻi Island</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Port (optional)</label>
                        <input type="text" name="port_name" class="form-control"
                               value="<?= h($data['port_name']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email (To) *</label>
                        <input type="email" name="email_to" class="form-control"
                               value="<?= h($data['email_to']) ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email (CC)</label>
                        <input type="text" name="email_cc" class="form-control"
                               value="<?= h($data['email_cc']) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone_display" class="form-control"
                               value="<?= h($data['phone_display']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= h($data['notes']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" id="active"
                                   <?= (int)$data['active'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                    </div>

                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                    <button class="btn btn-primary" type="submit">
                        <?= $id ? 'Save Changes' : 'Create Contact' ?>
                    </button>
                    <a class="btn btn-outline-secondary" href="uscg_contacts.php">Cancel</a>
                </div>

            </form>

        </div>
    </div>
</div>

</body>
</html>