<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    // Store the requested URL in the session
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Optional: Check if session has expired (30 minutes)
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    // Session has expired
    session_unset();     // unset $_SESSION variable for this page
    session_destroy();   // destroy session data
    header("Location: login.php?expired=1");
    exit();
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();
