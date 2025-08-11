<?php
session_start();
require_once 'conn.php';
require_once 'includes/activity_logger.php';

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    logActivity($conn, 'User logged out', 'logout');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
