<?php
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_icons.php';

$pageTitle = 'All Activities - Lagonglong FARMS';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$activity_type = isset($_GET['activity_type']) ? trim($_GET['activity_type']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Get distinct activity types for filter dropdown
$activity_types_query = "SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type";
$stmt = mysqli_prepare($conn, $activity_types_query);
mysqli_stmt_execute($stmt);
$activity_types_result = mysqli_stmt_get_result($stmt);
$activity_types = [];
while ($row = mysqli_fetch_assoc($activity_types_result)) {
    $activity_types[] = $row['action_type'];
}
mysqli_stmt_close($stmt);

// Function to build URL with current filters
function buildFilterUrl($new_params = []) {
    global $search, $activity_type, $date_from, $date_to;
    
    $params = [
        'search' => $search,
        'activity_type' => $activity_type,
        'date_from' => $date_from,
        'date_to' => $date_to
    ];
    
    $params = array_merge($params, $new_params);
    $params = array_filter($params, function($value) {
        return $value !== '';
    });
    
    return '?' . http_build_query($params);
}
?>

<?php include 'includes/layout_start.php'; ?>
    
    <style>


        /* Smaller activity card styles */
        .activities-list-compact {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            border-left: 3px solid #16a34a;
        }
        
        .activity-item:hover {
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            border-left-color: #059669;
            background-color: #f8fafc;
        }
        
        .activity-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.875rem;
            flex-shrink: 0;
            font-size: 0.875rem;
        }
        
        .activity-content {
            flex: 1;
            min-width: 0;
        }
        
        .activity-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.375rem;
        }
        
        .activity-type {
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
            text-transform: capitalize;
            margin-right: auto;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #6b7280;
            margin-left: 0.5rem;
        }
        
        .activity-details {
            font-size: 0.8rem;
            color: #4b5563;
            line-height: 1.4;
            margin-bottom: 0.375rem;
        }
        
        .activity-user {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            align-items: center;
        }
        
        .activity-user .badge {
            font-size: 0.65rem;
            padding: 0.125rem 0.375rem;
        }
        
        .filter-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 1px solid #e2e8f0;
        }
    </style>


    <!-- Success/Error Messages -->
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

    <div class="px-6 py-4">
            <!-- Header Section (Uniform White Card Design) -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div class="flex items-start">
                        <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center mr-3">
                            <i class="fas fa-list text-gray-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold text-gray-900">All Activities</h2>
                            <p class="text-gray-600">Recent system activities and user actions across the application</p>
                            <div class="flex items-center text-gray-500 mt-2">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span><?php echo date('l, F j, Y'); ?></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search activities...">
                    </div>
                    <div class="col-md-3">
                        <label for="activity_type" class="form-label">Activity Type</label>
                        <select class="form-select" id="activity_type" name="activity_type">
                            <option value="">All Activities</option>
                            <?php foreach ($activity_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"
                                        <?php echo $activity_type === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <?php 
                        // Check if any filters are applied
                        $has_filters = !empty($search) || !empty($activity_type) || !empty($date_from) || !empty($date_to);
                        if ($has_filters): 
                        ?>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Activities List (match index.php card styles) -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <?php
                // Get current page
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $per_page = 10; // Changed to show 10 activities per page
                $offset = ($page - 1) * $per_page;

                // Build WHERE clause for filters
                $where_conditions = [];
                $params = [];

                if (!empty($search)) {
                    $where_conditions[] = "(al.action_type LIKE ? OR al.details LIKE ? OR CONCAT(ms.first_name, ' ', ms.last_name) LIKE ?)";
                    $search_param = "%$search%";
                    $params[] = $search_param;
                    $params[] = $search_param;
                    $params[] = $search_param;
                }

                if (!empty($activity_type)) {
                    $where_conditions[] = "al.action_type = ?";
                    $params[] = $activity_type;
                }

                if (!empty($date_from)) {
                    $where_conditions[] = "DATE(al.timestamp) >= ?";
                    $params[] = $date_from;
                }

                if (!empty($date_to)) {
                    $where_conditions[] = "DATE(al.timestamp) <= ?";
                    $params[] = $date_to;
                }

                $where_clause = "";
                if (!empty($where_conditions)) {
                    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                }

                // Get total count of filtered activities
                $count_query = "
                    SELECT COUNT(*) as total 
                    FROM activity_logs al
                    JOIN mao_staff ms ON al.staff_id = ms.staff_id
                    LEFT JOIN roles r ON ms.role_id = r.role_id
                    $where_clause
                ";
                
                if (!empty($params)) {
                    $count_stmt = mysqli_prepare($conn, $count_query);
                    if (!empty($params)) {
                        $types = str_repeat('s', count($params));
                        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
                    }
                    mysqli_stmt_execute($count_stmt);
                    $count_result = mysqli_stmt_get_result($count_stmt);
                } else {
                    $count_stmt = mysqli_prepare($conn, $count_query);
                    mysqli_stmt_execute($count_stmt);
                    $count_result = mysqli_stmt_get_result($count_stmt);
                }
                
                $total_activities = mysqli_fetch_assoc($count_result)['total'];
                $total_pages = ceil($total_activities / $per_page);

                // Get activities for current page
                $query = "
                    SELECT 
                        al.*,
                        CONCAT(ms.first_name, ' ', ms.last_name) as staff_name,
                        r.role as staff_role
                    FROM activity_logs al
                    JOIN mao_staff ms ON al.staff_id = ms.staff_id
                    LEFT JOIN roles r ON ms.role_id = r.role_id
                    $where_clause
                    ORDER BY al.timestamp DESC
                    LIMIT $per_page OFFSET $offset
                ";
                
                if (!empty($params)) {
                    $stmt = mysqli_prepare($conn, $query);
                    if (!empty($params)) {
                        $types = str_repeat('s', count($params));
                        mysqli_stmt_bind_param($stmt, $types, ...$params);
                    }
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                } else {
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                }
                
                if ($result && mysqli_num_rows($result) > 0):
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0">
                            <i class="fas fa-clock text-success me-2"></i>
                            Recent Activities
                            <span class="badge bg-success ms-2"><?php echo $total_activities; ?></span>
                        </h5>
                    </div>
                    
                    <div class="activities-list-compact">
                    <?php
                    while ($activity = mysqli_fetch_assoc($result)):
                        $icon_info = getActivityIcon($activity['action_type']);
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="<?php echo $icon_info[0]; ?> text-white"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-header">
                                    <span class="activity-type"><?php echo htmlspecialchars($activity['action_type']); ?></span>
                                    <small class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></small>
                                </div>
                                <div class="activity-details">
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                </div>
                                <div class="activity-user">
                                    <i class="fas fa-user text-muted me-1"></i>
                                    <?php echo htmlspecialchars($activity['staff_name']); ?>
                                    <?php if ($activity['staff_role']): ?>
                                        <span class="badge bg-light text-dark ms-1"><?php echo htmlspecialchars($activity['staff_role']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    endwhile;
                    ?>
                    </div>
                    
                    <!-- Enhanced Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top">
                        <div class="text-muted">
                            Showing <?php echo (($page - 1) * $per_page) + 1; ?>-<?php echo min($page * $per_page, $total_activities); ?> 
                            of <?php echo $total_activities; ?> activities
                        </div>
                        <nav aria-label="Activities pagination">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildFilterUrl(['page' => $page - 1]); ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                // Calculate page range to show
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo buildFilterUrl(['page' => $i]); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildFilterUrl(['page' => $page + 1]); ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">No Activities Found</h5>
                        <p class="text-muted">No activities match your current filters.</p>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-success">
                            <i class="fas fa-refresh"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<?php include 'includes/notification_complete.php'; ?>
