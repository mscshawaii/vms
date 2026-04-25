<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   SESSION TIMEOUT (30 min)
   ========================= */
if (
    isset($_SESSION['last_active']) &&
    (time() - $_SESSION['last_active'] > 1800)
) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION['last_active'] = time();

/* =========================
   AUTH CHECK
   ========================= */
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   ROLE CHECK (NO OUTPUT)
   ========================= */
function requireRole(int $required_role_id): void
{
    if (
        !isset($_SESSION['role_id']) ||
        $_SESSION['role_id'] > $required_role_id
    ) {
        http_response_code(403);
        exit;
    }
}
