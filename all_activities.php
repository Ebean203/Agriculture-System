<?php
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_icons.php';

$pageTitle = 'All Activities - Agricultural Management System';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Activities - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
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
    <style>
        /* Remove underlines from links and set text color to black */
        .grid a {
            text-decoration: none !important;
            color: #111827 !important;
        }
        .grid a:hover {
            text-decoration: none !important;
        }

        /* Dropdown menu styles */
        #dropdownMenu {
            z-index: 50;
            transition: all 0.2s ease-in-out;
            transform-origin: top right;
        }

        #dropdownMenu.show {
            display: block;
        }

        #dropdownArrow.rotate {
            transform: rotate(180deg);
        }

        /* Ensure dropdown is above other content */
        .relative {
            z-index: 40;
        }
    </style>
    <script>
        // Function to handle dropdown toggle
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            
            if (!userMenu.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate');
            }
        });
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <?php include 'nav.php'; ?>

        <!-- Success/Error Messages (match index.php) -->
        <?php
        $success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
        $error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
        unset($_SESSION['success_message']);
        unset($_SESSION['error_message']);
        ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header Section (uniform with index.php) -->
            <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-lg shadow-md p-6 mb-8 text-white">
                <h2 class="text-3xl font-bold mb-2">All Activities</h2>
                <p class="text-agri-light">Recent system activities and user actions across the application</p>
            </div>

            <!-- Activities List (match index.php card styles) -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <?php
                // Get current page
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $per_page = 20;
                $offset = ($page - 1) * $per_page;

                // Get total count of activities
                $count_query = "SELECT COUNT(*) as total FROM activity_logs";
                $count_result = mysqli_query($conn, $count_query);
                $total_activities = mysqli_fetch_assoc($count_result)['total'];
                $total_pages = ceil($total_activities / $per_page);

                // Get activities for current page
                $query = "
                    SELECT 
                        al.*,
                        CONCAT(ms.first_name, ' ', ms.last_name) as staff_name,
                        r.role as staff_role
                    FROM activity_logs al
                    JOIN mao_staff ms ON al.user_id = ms.staff_id
                    LEFT JOIN roles r ON ms.role_id = r.role_id
                    ORDER BY al.timestamp DESC
                    LIMIT $per_page OFFSET $offset
                ";
                
                $result = mysqli_query($conn, $query);
                if ($result && mysqli_num_rows($result) > 0):
                    while ($activity = mysqli_fetch_assoc($result)):
                        $icon_info = getActivityIcon($activity['action_type']);
                        ?>
                        <div class="flex items-start space-x-4 mb-4 p-4 rounded-lg hover:bg-gray-50">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full <?php echo $icon_info[1]; ?> flex items-center justify-center">
                                    <i class="<?php echo $icon_info[0]; ?>"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-semibold"><?php echo htmlspecialchars($activity['staff_name']); ?></span>
                                        <span class="text-gray-500"> (<?php echo htmlspecialchars($activity['staff_role']); ?>)</span>
                                    </div>
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                    </span>
                                </div>
                                <p class="text-gray-600"><?php echo htmlspecialchars($activity['action']); ?></p>
                            </div>
                        </div>
                    <?php 
                    endwhile;
                else:
                    ?>
                    <p class="text-gray-500 text-center py-4">No activities found.</p>
                <?php 
                endif;
                ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex justify-center items-center space-x-2 mt-6">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 text-sm bg-gray-100 text-gray-800 rounded hover:bg-gray-200">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <span class="px-4 py-2 text-sm text-gray-600">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 text-sm bg-gray-100 text-gray-800 rounded hover:bg-gray-200">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
