<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])) {
    log_audit($_SESSION['company_id'], $_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
