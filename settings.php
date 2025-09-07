<?php
require_once 'check_session.php';
require_once 'conn.php';

// Only admin can access settings
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle settings management logic here
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Lagonglong FARMS</title>
    <?php include 'includes/assets.php'; ?>
    
    
    
    
</head>
<body class="bg-gray-50">
    <!-- Add your settings form and logic here -->
    <h1>Settings Page</h1>
    <p>Coming soon...</p>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
