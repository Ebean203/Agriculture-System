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
    <title>Settings - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-light': '#dcfce7',
                        'agri-dark': '#15803d'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Add your settings form and logic here -->
    <h1>Settings Page</h1>
    <p>Coming soon...</p>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
