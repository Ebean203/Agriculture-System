<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

$pageTitle = 'MAO Activities Management';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_activity':
                $staff_id = $_POST['staff_id'];
                $activity_type = trim($_POST['activity_type']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $activity_date = $_POST['activity_date'];
                $location = trim($_POST['location']);
                
                // Validate inputs
                if (empty($activity_type) || empty($title) || empty($activity_date) || empty($location)) {
                    $_SESSION['error_message'] = 'Please fill in all required fields.';
                } else {
                    // Insert new activity
                    $stmt = $conn->prepare("INSERT INTO mao_activities (staff_id, activity_type, title, description, activity_date, location) VALUES (?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt) {
                        $stmt->bind_param("isssss", $staff_id, $activity_type, $title, $description, $activity_date, $location);
                        
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = 'Activity added successfully!';
                            
                            // Log the activity
                            logActivity($conn, 'ADD_ACTIVITY', 'MAO_ACTIVITY', 'Added new MAO activity: ' . $title);
                        } else {
                            $_SESSION['error_message'] = 'Error adding activity: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = 'Error preparing statement: ' . $conn->error;
                    }
                }
                break;
                
            case 'edit_activity':
                $activity_id = $_POST['activity_id'];
                $staff_id = $_POST['staff_id'];
                $activity_type = trim($_POST['activity_type']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $activity_date = $_POST['activity_date'];
                $location = trim($_POST['location']);
                
                // Validate inputs
                if (empty($activity_type) || empty($title) || empty($activity_date) || empty($location)) {
                    $_SESSION['error_message'] = 'Please fill in all required fields.';
                } else {
                    // Update activity
                    $stmt = $conn->prepare("UPDATE mao_activities SET staff_id = ?, activity_type = ?, title = ?, description = ?, activity_date = ?, location = ? WHERE activity_id = ?");
                    
                    if ($stmt) {
                        $stmt->bind_param("isssssi", $staff_id, $activity_type, $title, $description, $activity_date, $location, $activity_id);
                        
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = 'Activity updated successfully!';
                            
                            // Log the activity
                            logActivity($conn, 'UPDATE_ACTIVITY', 'MAO_ACTIVITY', 'Updated MAO activity: ' . $title);
                        } else {
                            $_SESSION['error_message'] = 'Error updating activity: ' . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = 'Error preparing statement: ' . $conn->error;
                    }
                }
                break;
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: mao_activities.php");
    exit();
}

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$activity_type_filter = isset($_GET['activity_type']) ? trim($_GET['activity_type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build the query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(ma.title LIKE ? OR ma.description LIKE ? OR ma.location LIKE ? OR CONCAT(ms.first_name, ' ', ms.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $param_types .= 'ssss';
}

if (!empty($activity_type_filter)) {
    $where_conditions[] = "ma.activity_type = ?";
    $params[] = $activity_type_filter;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "ma.activity_date >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "ma.activity_date <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get activities with staff information
$activities_query = "
    SELECT ma.*, CONCAT(ms.first_name, ' ', ms.last_name) as staff_name 
    FROM mao_activities ma
    LEFT JOIN mao_staff ms ON ma.staff_id = ms.staff_id
    $where_clause
    ORDER BY ma.activity_date DESC, ma.created_at DESC
";

$activities_stmt = $conn->prepare($activities_query);
if (!empty($params)) {
    $activities_stmt->bind_param($param_types, ...$params);
}
$activities_stmt->execute();
$activities_result = $activities_stmt->get_result();

// Get all staff for dropdowns
$staff_query = "SELECT staff_id, CONCAT(first_name, ' ', last_name) as full_name FROM mao_staff ORDER BY first_name, last_name";
$staff_result = mysqli_query($conn, $staff_query);

// Get distinct activity types for filter
$types_query = "SELECT DISTINCT activity_type FROM mao_activities ORDER BY activity_type";
$types_result = mysqli_query($conn, $types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Lagonglong FARMS</title>
    <?php include 'includes/assets.php'; ?>
    
    <style>
        .activity-card {
            transition: all 0.3s ease;
            border-left: 4px solid #16a34a;
        }
        
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .activity-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .activity-type-training {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .activity-type-meeting {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .activity-type-inspection {
            background-color: #dcfce7;
            color: #16a34a;
        }
        
        .activity-type-seminar {
            background-color: #fce7f3;
            color: #be185d;
        }
        
        .activity-type-default {
            background-color: #f3f4f6;
            color: #374151;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>
    
    <!-- Success/Error Messages -->
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

    <!-- Main Content -->
    <div class="min-h-screen">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-check text-agri-green mr-3"></i>
                            MAO Activities Management
                        </h1>
                        <p class="text-gray-600 mt-2">Manage and track Municipal Agriculture Office activities</p>
                    </div>
                    <button onclick="openAddModal()" class="bg-agri-green text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-600 transition-colors flex items-center">
                        <i class="fas fa-plus mr-2"></i>Add New Activity
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search activities..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                            <select name="activity_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-agri-green focus:border-transparent">
                                <option value="">All Types</option>
                                <?php
                                mysqli_data_seek($types_result, 0);
                                while ($type = mysqli_fetch_assoc($types_result)):
                                ?>
                                    <option value="<?php echo htmlspecialchars($type['activity_type']); ?>" 
                                            <?php echo $activity_type_filter === $type['activity_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['activity_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="bg-agri-green text-white px-4 py-2 rounded-md hover:bg-green-600 transition-colors flex items-center">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search) || !empty($activity_type_filter) || !empty($date_from) || !empty($date_to)): ?>
                        <a href="mao_activities.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 transition-colors flex items-center">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Activities List -->
            <div class="space-y-6">
                <?php if ($activities_result->num_rows > 0): ?>
                    <?php while ($activity = $activities_result->fetch_assoc()): ?>
                        <div class="activity-card bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <h3 class="text-xl font-bold text-gray-900 mr-4"><?php echo htmlspecialchars($activity['title']); ?></h3>
                                        <?php
                                        $type_class = 'activity-type-default';
                                        switch (strtolower($activity['activity_type'])) {
                                            case 'training':
                                                $type_class = 'activity-type-training';
                                                break;
                                            case 'meeting':
                                                $type_class = 'activity-type-meeting';
                                                break;
                                            case 'inspection':
                                                $type_class = 'activity-type-inspection';
                                                break;
                                            case 'seminar':
                                                $type_class = 'activity-type-seminar';
                                                break;
                                        }
                                        ?>
                                        <span class="activity-type-badge <?php echo $type_class; ?>">
                                            <?php echo htmlspecialchars($activity['activity_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($activity['description'])): ?>
                                        <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                        <div class="flex items-center">
                                            <i class="fas fa-calendar text-agri-green mr-2"></i>
                                            <span><?php echo date('F j, Y', strtotime($activity['activity_date'])); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt text-agri-green mr-2"></i>
                                            <span><?php echo htmlspecialchars($activity['location']); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-agri-green mr-2"></i>
                                            <span><?php echo htmlspecialchars($activity['staff_name'] ?? 'Unassigned'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($activity)); ?>)" 
                                            class="bg-green-500 text-white px-3 py-2 rounded-md hover:bg-green-600 transition-colors">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($activity)); ?>)" 
                                            class="bg-blue-500 text-white px-3 py-2 rounded-md hover:bg-blue-600 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white rounded-lg shadow-md p-12 text-center">
                        <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-500 mb-2">No Activities Found</h3>
                        <p class="text-gray-400">There are no activities matching your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include Modals -->
    <?php include 'includes/mao_activities_modals.php'; ?>
    
    <!-- Include Notification System -->
    <?php include 'includes/notification_complete.php'; ?>
    
    <script>
        // Auto-hide success messages after 2 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    const alert = new bootstrap.Alert(successAlert);
                    alert.close();
                }, 2000); // 2 seconds
            }
        });
    </script>
</body>
</html>
