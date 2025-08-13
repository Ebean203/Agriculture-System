<?php
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/header.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle input distribution logic here

$pageTitle = 'Input Distribution';
outputHeader($pageTitle);
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
    <!-- Add your input distribution form and logic here -->
    <h1>Input Distribution Page</h1>
    <p>Coming soon...</p>
</body>
</html>
