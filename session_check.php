<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Session timeout: 30 mins
if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION['last_active'] = time(); // update last activity

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Role-based restrictions (optional)
function requireRole($required_role_id) {
    if ($_SESSION['role_id'] > $required_role_id) {
        echo "Unauthorized.";
        exit;
    }
}
