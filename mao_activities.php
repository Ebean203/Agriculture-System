<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';
require_once 'includes/name_helpers.php';

$pageTitle = 'MAO Activities Management - Lagonglong FARMS';

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
                
            case 'mark_done':
                $activity_id = $_POST['activity_id'];
                
                // Update activity status to completed
                $stmt = $conn->prepare("UPDATE mao_activities SET status = 'completed', updated_at = NOW() WHERE activity_id = ?");
                
                if ($stmt) {
                    $stmt->bind_param("i", $activity_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Activity marked as done successfully!';
                        
                        // Get activity title for logging
                        $title_stmt = $conn->prepare("SELECT title FROM mao_activities WHERE activity_id = ?");
                        $title_stmt->bind_param("i", $activity_id);
                        $title_stmt->execute();
                        $title_result = $title_stmt->get_result();
                        $title_row = $title_result->fetch_assoc();
                        
                        // Log the activity
                        logActivity($conn, 'COMPLETE_ACTIVITY', 'MAO_ACTIVITY', 'Marked activity as done: ' . $title_row['title']);
                        $title_stmt->close();
                    } else {
                        $_SESSION['error_message'] = 'Error marking activity as done: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error_message'] = 'Error preparing statement: ' . $conn->error;
                }
                break;
                
            case 'reschedule':
                $activity_id = (int)$_POST['activity_id'];
                $new_date = $_POST['new_date'];
                $reschedule_reason = trim($_POST['reschedule_reason']);

                // Validate inputs
                if (empty($new_date)) {
                    $_SESSION['error_message'] = 'Please select a new date.';
                    break;
                }
                if ($reschedule_reason === '' || strlen($reschedule_reason) < 3) {
                    $_SESSION['error_message'] = 'Please provide a reason for rescheduling (at least 3 characters).';
                    break;
                }

                // Fetch current date and title
                $curr_stmt = $conn->prepare("SELECT activity_date, title FROM mao_activities WHERE activity_id = ?");
                if (!$curr_stmt) { $_SESSION['error_message'] = 'Error preparing statement: ' . $conn->error; break; }
                $curr_stmt->bind_param("i", $activity_id);
                $curr_stmt->execute();
                $curr_res = $curr_stmt->get_result();
                $curr = $curr_res->fetch_assoc();
                $curr_stmt->close();

                if (!$curr) { $_SESSION['error_message'] = 'Activity not found.'; break; }
                $old_date = $curr['activity_date'];
                $title_for_log = $curr['title'];

                if ($old_date === $new_date) {
                    $_SESSION['error_message'] = 'New date must be different from the current scheduled date.';
                    break;
                }

                // Transaction: insert history then update main record
                $conn->begin_transaction();
                try {
                    // Insert into history table if it exists
                    $historyInserted = false;
                    $dbRes = mysqli_query($conn, 'SELECT DATABASE()');
                    $dbRow = $dbRes ? mysqli_fetch_row($dbRes) : null;
                    $dbName = $dbRow ? $dbRow[0] : null;
                    $histCheck = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'mao_activity_reschedules' LIMIT 1");
                    if ($histCheck && $dbName) {
                        $histCheck->bind_param('s', $dbName);
                        $histCheck->execute();
                        $histCheck->store_result();
                        if ($histCheck->num_rows > 0) {
                            // Insert history
                            $staff_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                            $hist_stmt = $conn->prepare("INSERT INTO mao_activity_reschedules (activity_id, old_date, new_date, reason, staff_id) VALUES (?, ?, ?, ?, ?)");
                            if ($hist_stmt) {
                                // bind as i s s s i (last can be null)
                                $hist_stmt->bind_param("isssi", $activity_id, $old_date, $new_date, $reschedule_reason, $staff_id);
                                $historyInserted = $hist_stmt->execute();
                                $hist_stmt->close();
                            }
                        }
                        $histCheck->close();
                    }

                    // Update main activity date
                    $stmt = $conn->prepare("UPDATE mao_activities SET activity_date = ?, updated_at = NOW() WHERE activity_id = ?");
                    if (!$stmt) { throw new Exception('Error preparing update: ' . $conn->error); }
                    $stmt->bind_param("si", $new_date, $activity_id);
                    if (!$stmt->execute()) { throw new Exception('Error rescheduling activity: ' . $stmt->error); }
                    $stmt->close();

                    $conn->commit();
                    $_SESSION['success_message'] = 'Activity rescheduled successfully!' . (!$historyInserted ? ' (History not recorded.)' : '');

                    // Log the activity
                    $log_details = 'Rescheduled activity: ' . $title_for_log . ' to ' . date('F j, Y', strtotime($new_date)) . ' (Reason: ' . $reschedule_reason . ')';
                    logActivity($conn, 'RESCHEDULE_ACTIVITY', 'MAO_ACTIVITY', $log_details);
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error_message'] = $e->getMessage();
                }
                break;

            case 'register_attendance':
                $activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
                $farmer_id = isset($_POST['farmer_id']) ? trim($_POST['farmer_id']) : '';

                if ($activity_id <= 0 || $farmer_id === '') {
                    $_SESSION['error_message'] = 'Please choose an activity and farmer to register.';
                    break;
                }

                // Fetch activity title for messaging/logging
                $activity_title = '';
                $title_stmt = $conn->prepare("SELECT title FROM mao_activities WHERE activity_id = ?");
                if ($title_stmt) {
                    $title_stmt->bind_param('i', $activity_id);
                    $title_stmt->execute();
                    $title_result = $title_stmt->get_result();
                    if ($row = $title_result->fetch_assoc()) {
                        $activity_title = $row['title'];
                    }
                    $title_stmt->close();
                }

                // Fetch farmer name for messaging/logging
                $farmer_name = '';
                $farmer_stmt = $conn->prepare("SELECT first_name, middle_name, last_name, suffix FROM farmers WHERE farmer_id = ?");
                if ($farmer_stmt) {
                    $farmer_stmt->bind_param('s', $farmer_id);
                    $farmer_stmt->execute();
                    $farmer_result = $farmer_stmt->get_result();
                    if ($farmer = $farmer_result->fetch_assoc()) {
                        $farmer_name = formatFarmerName($farmer['first_name'], $farmer['middle_name'], $farmer['last_name'], $farmer['suffix']);
                    }
                    $farmer_stmt->close();
                }

                $insert_stmt = $conn->prepare("INSERT INTO mao_activity_attendance (activity_id, farmer_id) VALUES (?, ?)");
                if (!$insert_stmt) {
                    $_SESSION['error_message'] = 'Error preparing attendance registration: ' . $conn->error;
                    break;
                }

                $insert_stmt->bind_param('is', $activity_id, $farmer_id);

                if ($insert_stmt->execute()) {
                    $_SESSION['success_message'] = 'Farmer successfully registered for this activity!';
                    $log_details = 'Registered farmer ' . ($farmer_name ?: $farmer_id) . ' to activity ' . ($activity_title ?: ('#' . $activity_id));
                    logActivity($conn, 'REGISTER_ATTENDANCE', 'MAO_ACTIVITY', $log_details);
                } else {
                    if ($insert_stmt->errno === 1062) {
                        $_SESSION['error_message'] = 'This farmer is already registered for the selected activity.';
                    } else {
                        $_SESSION['error_message'] = 'Error registering farmer attendance: ' . $insert_stmt->error;
                    }
                }

                $insert_stmt->close();
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
$staff_stmt = $conn->prepare($staff_query);
$staff_stmt->execute();
$staff_result = $staff_stmt->get_result();

// Get distinct activity types for filter
$types_query = "SELECT DISTINCT activity_type FROM mao_activities ORDER BY activity_type";
$types_stmt = $conn->prepare($types_query);
$types_stmt->execute();
$types_result = $types_stmt->get_result();
?>
<?php $pageTitle = 'MAO Activities Management - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-calendar-check text-agri-green mr-3"></i>
                            MAO Activities Management
                        </h1>
                        <p class="text-gray-600 mt-2">Manage and track Municipal Agriculture Office activities</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 min-w-fit">
                        <!-- Add New Activity (now left of Go to Page) -->
                        <button onclick="openAddModal()" class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>Add New Activity
                        </button>

                        <div class="relative">
                            <button type="button" onclick="toggleNavigationDropdown()" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors flex items-center shadow-sm">
                                <i class="fas fa-map-signs text-agri-green mr-2"></i>
                                Go to Page
                                <i id="navigationArrow" class="fas fa-chevron-down ml-2 text-sm transition-transform duration-200"></i>
                            </button>

                            <div id="navigationDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-40">
                                <div class="py-2">
                                    <!-- Operations Section -->
                                    <div class="border-b border-gray-200 pb-2 mb-2">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Operations</div>
                                        <a href="mao_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-tasks text-orange-600 mr-3"></i>
                                            MAO Activities
                                        </a>
                                        <a href="input_distribution_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-truck text-blue-600 mr-3"></i>
                                            Distribution Records
                                        </a>
                                    </div>

                                    <!-- Records Management Section -->
                                    <div class="border-b border-gray-200 pb-2 mb-2">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Records Management</div>
                                        <a href="farmers.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-users text-green-600 mr-3"></i>
                                            Farmers Registry
                                        </a>
                                        <a href="rsbsa_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-id-card text-blue-600 mr-3"></i>
                                            RSBSA Records
                                        </a>
                                        <a href="ncfrs_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-fish text-cyan-600 mr-3"></i>
                                            NCFRS Records
                                        </a>
                                        <a href="fishr_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-anchor text-blue-600 mr-3"></i>
                                            FishR Records
                                        </a>
                                        <a href="boat_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-ship text-navy-600 mr-3"></i>
                                            Boat Records
                                        </a>
                                    </div>

                                    <!-- Monitoring & Reports Section -->
                                    <div class="border-b border-gray-200 pb-2 mb-2">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Monitoring & Reports</div>
                                        <a href="yield_monitoring.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-seedling text-green-600 mr-3"></i>
                                            Yield Monitoring
                                        </a>
                                        <a href="reports.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-file-alt text-red-600 mr-3"></i>
                                            Reports
                                        </a>
                                        <a href="all_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-list text-gray-600 mr-3"></i>
                                            All Activities
                                        </a>
                                    </div>

                                    <!-- Settings Section -->
                                    <div>
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Settings</div>
                                        <a href="staff.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-user-tie text-purple-600 mr-3"></i>
                                            Staff Management
                                        </a>
                                        <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-cog text-gray-600 mr-3"></i>
                                            Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
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
                                        
                                        <!-- Menu Button moved here -->
                                        <div class="relative">
                                            <button onclick="toggleActivityMenu(<?php echo $activity['activity_id']; ?>)" 
                                                    class="text-gray-500 hover:text-gray-700 p-2 rounded-md hover:bg-gray-100 transition-colors">
                                                <i class="fas fa-ellipsis-v text-xl"></i>
                                            </button>
                                            
                                            <!-- Dropdown Menu (appears above the button) -->
                                            <div id="activityMenu<?php echo $activity['activity_id']; ?>" 
                                                 class="absolute right-0 bottom-full mb-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 z-50 hidden">
                                                <div class="py-1">
                                                    <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($activity)); ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)" 
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-eye text-green-600 mr-3 w-5"></i>
                                                        View Activity
                                                    </button>
                                                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($activity)); ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)" 
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-edit text-blue-600 mr-3 w-5"></i>
                                                        Edit Activity
                                                    </button>
                                                    <button onclick="openMarkDoneModal(<?php echo htmlspecialchars(json_encode($activity)); ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)" 
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-check-circle text-green-600 mr-3 w-5"></i>
                                                        Mark as Done
                                                    </button>
                                                    <button onclick="openRegisterAttendanceModal(<?php echo htmlspecialchars(json_encode($activity)); ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-user-plus text-purple-600 mr-3 w-5"></i>
                                                        Register Farmer
                                                    </button>
                                                    <button onclick="openViewAttendanceModal(<?php echo $activity['activity_id']; ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)"
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-users text-indigo-600 mr-3 w-5"></i>
                                                        View Attendance
                                                    </button>
                                                    <button onclick="openRescheduleModal(<?php echo htmlspecialchars(json_encode($activity)); ?>); closeActivityMenu(<?php echo $activity['activity_id']; ?>)" 
                                                            class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 transition-colors">
                                                        <i class="fas fa-calendar-alt text-orange-600 mr-3 w-5"></i>
                                                        Reschedule Activity
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
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
    
    <script>
        // Activity Menu Dropdown Functions
        function toggleActivityMenu(activityId) {
            const menu = document.getElementById('activityMenu' + activityId);
            const allMenus = document.querySelectorAll('[id^="activityMenu"]');
            const wrapper = menu.parentElement; // relative container
            const card = wrapper.closest('.activity-card');

            // Close all other menus
            allMenus.forEach(m => {
                if (m.id !== 'activityMenu' + activityId) m.classList.add('hidden');
            });

            // Toggle visibility
            const willShow = menu.classList.contains('hidden');
            menu.classList.toggle('hidden');
            if (!willShow) return; // just closed

            // Default placement: above the button
            menu.style.transform = '';
            menu.classList.remove('top-full', 'mt-2');
            menu.classList.add('bottom-full', 'mb-2');

            // Allow layout to paint, then measure and if the menu crosses the card's top,
            // pin it to the card top (instead of flipping below)
            requestAnimationFrame(() => {
                const menuRect = menu.getBoundingClientRect();
                const cardRect = card ? card.getBoundingClientRect() : { top: 0 };

                const desiredTop = cardRect.top + 8; // small padding from the red line/top edge
                if (menuRect.top < desiredTop) {
                    const pushDown = desiredTop - menuRect.top;
                    // Keep it above the button but slide it down so its top aligns with the card top
                    menu.style.transform = `translateY(${pushDown}px)`;
                }
            });
        }
        
        function closeActivityMenu(activityId) {
            const menu = document.getElementById('activityMenu' + activityId);
            menu.classList.add('hidden');
        }
        
        // Close activity menus when clicking outside
        document.addEventListener('click', function(event) {
            const isMenuButton = event.target.closest('[onclick^="toggleActivityMenu"]');
            const isMenuContent = event.target.closest('[id^="activityMenu"]');
            
            if (!isMenuButton && !isMenuContent) {
                const allMenus = document.querySelectorAll('[id^="activityMenu"]');
                allMenus.forEach(menu => menu.classList.add('hidden'));
            }
        });
        
        // Open Mark as Done confirmation modal
        function openMarkDoneModal(activity) {
            document.getElementById('markdone_activity_id').value = activity.activity_id;
            document.getElementById('markdone_title').textContent = activity.title;
            document.getElementById('markdone_date').textContent = new Date(activity.activity_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

            const modal = new bootstrap.Modal(document.getElementById('markDoneModal'));
            modal.show();
        }
        
        // Open Reschedule Modal
        function openRescheduleModal(activity) {
            document.getElementById('reschedule_activity_id').value = activity.activity_id;
            document.getElementById('reschedule_current_date').value = activity.activity_date;
            document.getElementById('reschedule_new_date').value = activity.activity_date;
            document.getElementById('reschedule_title').textContent = activity.title;
            document.getElementById('reschedule_reason').value = '';
            
            // Open modal using Bootstrap
            const modal = new bootstrap.Modal(document.getElementById('rescheduleModal'));
            modal.show();
        }

        // Open Register Attendance Modal
        function openRegisterAttendanceModal(activity) {
            document.getElementById('register_activity_id').value = activity.activity_id;
            document.getElementById('register_activity_title').textContent = activity.title;
            document.getElementById('register_activity_date').textContent = new Date(activity.activity_date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            });

            const searchInput = document.getElementById('register_farmer_search');
            const hiddenInput = document.getElementById('register_farmer_id');
            const suggestionBox = document.getElementById('register_farmer_suggestions');
            const selectedBox = document.getElementById('register_selected_farmer');

            if (searchInput) {
                searchInput.value = '';
                searchInput.setCustomValidity('');
            }
            if (hiddenInput) hiddenInput.value = '';
            if (suggestionBox) {
                suggestionBox.innerHTML = '';
                suggestionBox.style.display = 'none';
            }
            if (selectedBox) selectedBox.classList.add('d-none');

            const modal = new bootstrap.Modal(document.getElementById('registerAttendanceModal'));
            modal.show();
        }

        const attendanceState = {
            items: [],
            currentPage: 1,
            pageSize: 10
        };

        function renderAttendanceTable() {
            const tbody = document.getElementById('attendance_table_body');
            if (!tbody) {
                return;
            }

            const listContainer = document.getElementById('attendance_list');
            const emptyState = document.getElementById('attendance_empty');
            const pagination = document.getElementById('attendance_pagination');
            const paginationInfo = document.getElementById('attendance_pagination_info');
            const prevBtn = document.getElementById('attendance_prev_btn');
            const nextBtn = document.getElementById('attendance_next_btn');

            const totalItems = attendanceState.items.length;

            tbody.innerHTML = '';

            if (totalItems === 0) {
                if (listContainer) listContainer.classList.add('d-none');
                if (emptyState) emptyState.classList.remove('d-none');
                if (pagination) pagination.classList.add('d-none');
                return;
            }

            if (emptyState) emptyState.classList.add('d-none');
            if (listContainer) listContainer.classList.remove('d-none');

            const totalPages = Math.ceil(totalItems / attendanceState.pageSize) || 1;
            if (attendanceState.currentPage > totalPages) {
                attendanceState.currentPage = totalPages;
            }

            const startIndex = (attendanceState.currentPage - 1) * attendanceState.pageSize;
            const endIndex = Math.min(startIndex + attendanceState.pageSize, totalItems);
            const pageItems = attendanceState.items.slice(startIndex, endIndex);

            pageItems.forEach((attendee) => {
                const row = document.createElement('tr');

                const nameCell = document.createElement('td');
                nameCell.classList.add('fw-semibold');
                nameCell.textContent = attendee.full_name;

                const dateCell = document.createElement('td');
                dateCell.classList.add('text-center', 'text-muted', 'small');

                if (attendee.registered_at) {
                    const registeredDate = new Date(attendee.registered_at);
                    if (!isNaN(registeredDate.getTime())) {
                        dateCell.textContent = registeredDate.toLocaleDateString('en-US', {
                            month: 'short', day: 'numeric', year: 'numeric'
                        });
                    } else {
                        dateCell.textContent = 'Not recorded';
                    }
                } else {
                    dateCell.textContent = 'Not recorded';
                }

                row.appendChild(nameCell);
                row.appendChild(dateCell);
                tbody.appendChild(row);
            });

            if (pagination && paginationInfo && prevBtn && nextBtn) {
                paginationInfo.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalItems}`;
                pagination.classList.toggle('d-none', totalPages <= 1);
                prevBtn.disabled = attendanceState.currentPage <= 1;
                nextBtn.disabled = attendanceState.currentPage >= totalPages;
            }
        }

        function changeAttendancePage(delta) {
            const totalPages = Math.ceil(attendanceState.items.length / attendanceState.pageSize) || 1;
            const targetPage = attendanceState.currentPage + delta;
            if (targetPage < 1 || targetPage > totalPages) {
                return;
            }
            attendanceState.currentPage = targetPage;
            renderAttendanceTable();
        }

        // Open View Attendance Modal
        function openViewAttendanceModal(activityId) {
            attendanceState.items = [];
            attendanceState.currentPage = 1;

            const loadingEl = document.getElementById('attendance_loading');
            const errorEl = document.getElementById('attendance_error');
            const errorMessageEl = document.getElementById('attendance_error_message');
            const emptyEl = document.getElementById('attendance_empty');
            const listEl = document.getElementById('attendance_list');
            const paginationEl = document.getElementById('attendance_pagination');
            const tbody = document.getElementById('attendance_table_body');

            if (loadingEl) loadingEl.classList.remove('d-none');
            if (errorEl) errorEl.classList.add('d-none');
            if (emptyEl) emptyEl.classList.add('d-none');
            if (listEl) listEl.classList.add('d-none');
            if (paginationEl) paginationEl.classList.add('d-none');
            if (tbody) tbody.innerHTML = '';

            const modal = new bootstrap.Modal(document.getElementById('viewAttendanceModal'));
            modal.show();

            fetch('get_activity_attendance.php?activity_id=' + activityId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (loadingEl) loadingEl.classList.add('d-none');

                    if (!data.success) {
                        if (errorEl) errorEl.classList.remove('d-none');
                        if (errorMessageEl) {
                            errorMessageEl.textContent = data.message || 'Failed to load attendance data';
                        }
                        return;
                    }

                    const activityDetails = data.activity || {};
                    const titleEl = document.getElementById('attendance_activity_title');
                    const dateEl = document.getElementById('attendance_activity_date');
                    const locationEl = document.getElementById('attendance_activity_location');

                    if (titleEl) {
                        titleEl.textContent = activityDetails.title || 'Activity Details';
                    }

                    if (dateEl) {
                        if (activityDetails.activity_date) {
                            const parsedDate = new Date(activityDetails.activity_date);
                            dateEl.textContent = !isNaN(parsedDate.getTime())
                                ? parsedDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
                                : activityDetails.activity_date;
                        } else {
                            dateEl.textContent = 'Date not available';
                        }
                    }

                    if (locationEl) {
                        locationEl.textContent = activityDetails.location || 'Location not available';
                    }

                    attendanceState.items = Array.isArray(data.attendees) ? data.attendees.slice() : [];
                    attendanceState.currentPage = 1;

                    const totalCount = attendanceState.items.length;
                    const countEl = document.getElementById('attendance_count');
                    if (countEl) {
                        countEl.textContent = totalCount + ' ' + (totalCount === 1 ? 'attendee' : 'attendees');
                    }

                    if (totalCount === 0) {
                        if (emptyEl) emptyEl.classList.remove('d-none');
                        return;
                    }

                    renderAttendanceTable();
                })
                .catch(error => {
                    console.error('Error fetching attendance:', error);
                    if (loadingEl) loadingEl.classList.add('d-none');
                    if (errorEl) errorEl.classList.remove('d-none');
                    if (errorMessageEl) {
                        errorMessageEl.textContent = 'An error occurred while loading attendance data: ' + error.message;
                    }
                });
        }

        
        document.addEventListener('DOMContentLoaded', function() {
            const prevBtn = document.getElementById('attendance_prev_btn');
            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    changeAttendancePage(-1);
                });
            }

            const nextBtn = document.getElementById('attendance_next_btn');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    changeAttendancePage(1);
                });
            }
        });

        // Navigation Dropdown Functions
        function toggleNavigationDropdown() {
            const dropdown = document.getElementById('navigationDropdown');
            const arrow = document.getElementById('navigationArrow');
            dropdown.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }

        // Close navigation dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('navigationDropdown');
            const button = event.target.closest('[onclick="toggleNavigationDropdown()"]');
            
            if (!button && dropdown && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
                const arrow = document.getElementById('navigationArrow');
                if (arrow) arrow.classList.remove('rotate-180');
            }
        });
    </script>
    
    <!-- Additional styles for navigation -->
    <style>
        .text-navy-600 {
            color: #1e40af;
        }
    </style>
    
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

