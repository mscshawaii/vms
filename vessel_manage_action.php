<?php
// vessel_manage_action.php (MSCS ONLY / ACTIONS + AUDIT)

session_start();

// ✅ Adjust this path to match your project
require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  exit("Not logged in.");
}

$user_id = (int)$_SESSION['user_id'];

function is_mscs_user(): bool {
  return
    (isset($_SESSION['owner_id']) && (int)$_SESSION['owner_id'] === 1) ||
    (isset($_SESSION['company_id']) && (int)$_SESSION['company_id'] === 1) ||
    (isset($_SESSION['is_mscs']) && (int)$_SESSION['is_mscs'] === 1) ||
    (isset($_SESSION['role']) && in_array($_SESSION['role'], ['mscs','mscs_admin','super_admin','admin'], true));
}

if (!is_mscs_user()) {
  http_response_code(403);
  exit("Access denied.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: manage_vessels.php");
  exit;
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
  http_response_code(403);
  exit("Invalid CSRF token.");
}

$action   = $_POST['action'] ?? '';
$vessel_id = isset($_POST['vessel_id']) ? (int)$_POST['vessel_id'] : 0;
$reason   = isset($_POST['reason']) ? trim($_POST['reason']) : '';
if (strlen($reason) > 255) $reason = substr($reason, 0, 255);

if ($vessel_id <= 0) {
  header("Location: manage_vessels.php");
  exit;
}

// detect is_deleted column
$has_is_deleted = false;
try {
  $has_is_deleted = (bool)$pdo->query("SHOW COLUMNS FROM vessels LIKE 'is_deleted'")->fetch();
} catch (Exception $e) { $has_is_deleted = false; }

// detect audit table
$has_audit = false;
try {
  $has_audit = (bool)$pdo->query("SHOW TABLES LIKE 'vessel_audit_log'")->fetch();
} catch (Exception $e) { $has_audit = false; }

// Helper: write audit row (best-effort)
function audit_log(PDO $pdo, bool $has_audit, int $vessel_id, int $actor_user_id, string $action, ?int $old_company_id, ?int $new_company_id, ?string $reason): void {
  if (!$has_audit) return;
  try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($ua !== null && strlen($ua) > 255) $ua = substr($ua, 0, 255);

    $stmt = $pdo->prepare("
      INSERT INTO vessel_audit_log (vessel_id, actor_user_id, action, old_company_id, new_company_id, reason, ip_address, user_agent)
      VALUES (:vessel_id, :actor_user_id, :action, :old_company_id, :new_company_id, :reason, :ip, :ua)
    ");
    $stmt->execute([
      ':vessel_id' => $vessel_id,
      ':actor_user_id' => $actor_user_id,
      ':action' => $action,
      ':old_company_id' => $old_company_id,
      ':new_company_id' => $new_company_id,
      ':reason' => ($reason !== '' ? $reason : null),
      ':ip' => $ip,
      ':ua' => $ua
    ]);
  } catch (Exception $e) {
    // don't block the main action if logging fails
  }
}

// Get current vessel (for old company id + existence)
$stmt = $pdo->prepare("SELECT vessel_id, company_id, is_active, archived_at, archive_reason " . ($has_is_deleted ? ", is_deleted" : "") . " FROM vessels WHERE vessel_id = :id LIMIT 1");
$stmt->execute([':id' => $vessel_id]);
$vessel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vessel) {
  header("Location: manage_vessels.php");
  exit;
}

$old_company_id = (int)$vessel['company_id'];

try {

  if ($action === 'archive') {
    $stmt = $pdo->prepare("
      UPDATE vessels
      SET is_active = 0,
          archived_at = NOW(),
          archive_reason = :reason
      WHERE vessel_id = :id
      LIMIT 1
    ");
    $stmt->execute([':reason' => ($reason !== '' ? $reason : null), ':id' => $vessel_id]);

    audit_log($pdo, $has_audit, $vessel_id, $user_id, 'archive', $old_company_id, $old_company_id, $reason);

  } elseif ($action === 'unarchive') {
    $stmt = $pdo->prepare("
      UPDATE vessels
      SET is_active = 1,
          archived_at = NULL,
          archive_reason = NULL
      WHERE vessel_id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $vessel_id]);

    audit_log($pdo, $has_audit, $vessel_id, $user_id, 'unarchive', $old_company_id, $old_company_id, $reason);

  } elseif ($action === 'delete') {
    $confirm_text = strtoupper(trim($_POST['confirm_text'] ?? ''));
    if ($confirm_text !== 'DELETE') {
      http_response_code(400);
      exit("Delete not confirmed.");
    }

    if ($has_is_deleted) {
      $stmt = $pdo->prepare("
        UPDATE vessels
        SET is_deleted = 1,
            deleted_at = NOW(),
            deleted_reason = :reason,
            is_active = 0
        WHERE vessel_id = :id
        LIMIT 1
      ");
      $stmt->execute([':reason' => ($reason !== '' ? $reason : null), ':id' => $vessel_id]);
    } else {
      // fallback: mark deleted using archive_reason prefix
      $fallback_reason = "DELETED:" . ($reason !== '' ? " ".$reason : "");
      $stmt = $pdo->prepare("
        UPDATE vessels
        SET is_active = 0,
            archived_at = NOW(),
            archive_reason = :reason
        WHERE vessel_id = :id
        LIMIT 1
      ");
      $stmt->execute([':reason' => $fallback_reason, ':id' => $vessel_id]);
    }

    audit_log($pdo, $has_audit, $vessel_id, $user_id, 'delete', $old_company_id, $old_company_id, $reason);

  } elseif ($action === 'restore') {
    if ($has_is_deleted) {
      $stmt = $pdo->prepare("
        UPDATE vessels
        SET is_deleted = 0,
            deleted_at = NULL,
            deleted_reason = NULL,
            is_active = 1
        WHERE vessel_id = :id
        LIMIT 1
      ");
      $stmt->execute([':id' => $vessel_id]);
    } else {
      // fallback: remove "DELETED:" style archive
      $stmt = $pdo->prepare("
        UPDATE vessels
        SET is_active = 1,
            archived_at = NULL,
            archive_reason = NULL
        WHERE vessel_id = :id
        LIMIT 1
      ");
      $stmt->execute([':id' => $vessel_id]);
    }

    audit_log($pdo, $has_audit, $vessel_id, $user_id, 'restore', $old_company_id, $old_company_id, $reason);

  } elseif ($action === 'transfer') {
    $new_company_id = isset($_POST['new_company_id']) ? (int)$_POST['new_company_id'] : 0;
    if ($new_company_id <= 0) {
      http_response_code(400);
      exit("Invalid destination company.");
    }

    // Validate destination exists
    $chk = $pdo->prepare("SELECT owner_id FROM owners WHERE owner_id = :oid LIMIT 1");
    $chk->execute([':oid' => $new_company_id]);
    if (!$chk->fetch()) {
      http_response_code(400);
      exit("Destination company not found.");
    }

    // No-op guard
    if ($new_company_id === $old_company_id) {
      header("Location: manage_vessels.php");
      exit;
    }

    $stmt = $pdo->prepare("UPDATE vessels SET company_id = :cid WHERE vessel_id = :id LIMIT 1");
    $stmt->execute([':cid' => $new_company_id, ':id' => $vessel_id]);

    audit_log($pdo, $has_audit, $vessel_id, $user_id, 'transfer', $old_company_id, $new_company_id, $reason);

  } else {
    http_response_code(400);
    exit("Unknown action.");
  }

} catch (Exception $e) {
  http_response_code(500);
  exit("Server error: " . htmlspecialchars($e->getMessage()));
}

header("Location: manage_vessels.php");
exit;