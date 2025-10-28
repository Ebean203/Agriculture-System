<?php
session_start();
require_once 'conn.php';
require_once 'includes/activity_logger.php';

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    $userLabel = isset($_SESSION['full_name']) ? trim($_SESSION['full_name']) : '';
    $roleLabel = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    $activityMsg = $userLabel;
    if ($roleLabel) {
        $activityMsg .= " (" . $roleLabel . ")";
    }
    $activityMsg .= " logged out";
    logActivity($conn, $activityMsg, 'logout');
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
