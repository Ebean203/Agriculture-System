<?php
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle PDF export
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Build search condition for export
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
    $search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
    $search_params = [];
    
    if (!empty($search)) {
        $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
        $search_term = "%$search%";
        $search_params = [$search_term, $search_term, $search_term];
    }
    
    if (!empty($barangay_filter)) {
        $search_condition .= " AND f.barangay_id = ?";
        $search_params[] = $barangay_filter;
    }
    
    // Get FishR records for PDF export (using actual database structure)
    $export_sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
                   f.contact_number, f.address_details, f.land_area_hectares,
                   b.barangay_name, c.commodity_name, boats.boat_name, 
                   boats.boat_type, boats.registration_number, f.registration_date,
                   ff.fisherfolk_registration_number
                   FROM farmers f
                   INNER JOIN fisherfolk_registered_farmers ff ON f.farmer_id = ff.fisherfolk_id
                   INNER JOIN boats ON ff.boat_id = boats.boat_id
                   LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                   LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
                   $search_condition
                   ORDER BY f.registration_date DESC";
    
    if (!empty($search_params)) {
        $stmt = $conn->prepare($export_sql);
        $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
        $stmt->execute();
        $export_result = $stmt->get_result();
    } else {
        $export_result = $conn->query($export_sql);
    }
    
    // Create PDF content
    $html = '<div class="header">
        <div class="title">FishR Records Report</div>
        <div class="subtitle">Fisheries Registry System</div>
        <div class="subtitle">Generated on: ' . date('F d, Y h:i A') . '</div>
        <div class="subtitle">Total Records: ' . $export_result->num_rows . '</div>
    </div>';
    
    if ($export_result->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Farmer ID</th>
                    <th>Full Name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>FishR Reg. No.</th>
                    <th>Boat Name</th>
                    <th>Boat Type</th>
                    <th>Boat Reg. No.</th>
                    <th>Commodity</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $export_result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            $html .= '<tr>
                <td>' . htmlspecialchars($row['farmer_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['fisherfolk_registration_number']) . '</td>
                <td>' . htmlspecialchars($row['boat_name']) . '</td>
                <td>' . htmlspecialchars($row['boat_type']) . '</td>
                <td>' . htmlspecialchars($row['registration_number']) . '</td>
                <td>' . htmlspecialchars($row['commodity_name']) . '</td>
                <td>' . htmlspecialchars($row['registration_date']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div style="text-align: center; padding: 50px;">
            <h3>No FishR Records Found</h3>
            <p>No fishers are currently registered in FishR.</p>
        </div>';
    }
    
    // Output PDF-ready HTML
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>FishR Records Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .subtitle { font-size: 14px; color: #7f8c8d; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #16a34a; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #f5f5f5; }
    </style>
</head>
<body>' . $html . '</body>
</html>';
    exit();
}

// Get barangays for filter dropdown
$barangays_query = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
$barangays_result = $conn->query($barangays_query);

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search condition
$search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

if (!empty($barangay_filter)) {
    $search_condition .= " AND f.barangay_id = ?";
    $search_params[] = $barangay_filter;
    $param_types .= 'i';
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total 
              FROM farmers f
              INNER JOIN fisherfolk_registered_farmers ff ON f.farmer_id = ff.fisherfolk_id
              INNER JOIN boats ON ff.boat_id = boats.boat_id
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
              LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
              $search_condition";

if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($param_types)) {
        $count_stmt->bind_param($param_types, ...$search_params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);

// Get FishR registered farmers with pagination
$sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, f.address_details, f.land_area_hectares,
        b.barangay_name, c.commodity_name, boats.boat_name, 
        boats.boat_type, boats.registration_number, f.registration_date,
        ff.fisherfolk_registration_number
        FROM farmers f
        INNER JOIN fisherfolk_registered_farmers ff ON f.farmer_id = ff.fisherfolk_id
        INNER JOIN boats ON ff.boat_id = boats.boat_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
        $search_condition
        ORDER BY f.registration_date DESC
        LIMIT ? OFFSET ?";

$search_params[] = $limit;
$search_params[] = $offset;
$param_types .= 'ii';

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$search_params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $limit_sql = $sql;
    $result = $conn->query($limit_sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FishR Records - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-dark': '#15803d',
                        'agri-light': '#22c55e'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-agri-green shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-seedling text-white text-2xl mr-3"></i>
                        <h1 class="text-white text-xl font-bold">Agricultural Management System</h1>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Notification Bell -->
                    <div class="relative">
                        <button onclick="toggleNotificationDropdown()" class="text-white hover:text-agri-light transition-colors relative">
                            <i class="fas fa-bell text-lg"></i>
                            <span id="notificationBadge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">0</span>
                        </button>
                        
                        <!-- Notification Dropdown positioned to occupy bottom part -->
                        <div id="notificationDropdown" class="hidden fixed bg-white rounded-lg shadow-xl border border-gray-200 z-[9999] overflow-hidden" style="top: 70px; right: 20px; bottom: 20px; width: 400px;">
                            <div class="p-4 border-b border-gray-200">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        Notifications
                                    </h3>
                                    <span id="notificationCount" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">0</span>
                                </div>
                            </div>
                            
                            <div id="notificationList" style="height: calc(100% - 80px); overflow-y: auto;">
                                <!-- Notifications will be loaded here -->
                                <div class="p-4 text-center text-gray-500">
                                    <i class="fas fa-spinner fa-spin mb-2"></i>
                                    <p class="text-sm">Loading notifications...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center text-white">
                        <i class="fas fa-user-circle text-lg mr-2"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-fish text-blue-600 mr-3"></i>
                        FishR Records Management
                    </h1>
                    <p class="text-gray-600 mt-1">Fisheries Registry System - Manage fisher registrations</p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="exportToPDF()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-file-pdf mr-2"></i>
                        Export PDF
                    </button>
                    <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Fishers</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or contact number..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                </div>
                <div class="min-w-48">
                    <label for="barangay" class="block text-sm font-medium text-gray-700 mb-2">Filter by Barangay</label>
                    <select name="barangay" id="barangay" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                        <option value="">All Barangays</option>
                        <?php while ($barangay = $barangays_result->fetch_assoc()): ?>
                            <option value="<?php echo $barangay['barangay_id']; ?>" 
                                    <?php echo ($barangay_filter == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-agri-green hover:bg-agri-dark text-white px-6 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-search mr-2"></i>
                        Search
                    </button>
                    <a href="fishr_records.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                        <i class="fas fa-refresh mr-2"></i>
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Results Summary -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                    <span class="text-blue-800">
                        Found <strong><?php echo number_format($total_records); ?></strong> FishR registered fisher(s)
                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                            matching your search criteria
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($total_records > 0): ?>
                    <span class="text-sm text-blue-600">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- FishR Records Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <?php if ($total_records > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-blue-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fisher Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact & Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">FishR Registration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boat Information</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commodity</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i class="fas fa-fish text-blue-600"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php 
                                                    $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
                                                    echo htmlspecialchars($full_name); 
                                                    ?>
                                                </div>
                                                <div class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($row['farmer_id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['contact_number']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['barangay_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['fisherfolk_registration_number'] ?: 'Not specified'); ?></div>
                                        <div class="text-sm text-gray-500">
                                            Registered: <?php echo date('M d, Y', strtotime($row['registration_date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['boat_name'] ?: 'Not specified'); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <strong>Type:</strong> <?php echo htmlspecialchars($row['boat_type'] ?: 'Not specified'); ?><br>
                                            <strong>Reg:</strong> <?php echo htmlspecialchars($row['registration_number'] ?: 'Not specified'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo htmlspecialchars($row['commodity_name'] ?: 'Not specified'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&barangay=<?php echo urlencode($barangay_filter); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&barangay=<?php echo urlencode($barangay_filter); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?php echo (($page - 1) * $limit) + 1; ?></span> to 
                                    <span class="font-medium"><?php echo min($page * $limit, $total_records); ?></span> of 
                                    <span class="font-medium"><?php echo $total_records; ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&barangay=<?php echo urlencode($barangay_filter); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&barangay=<?php echo urlencode($barangay_filter); ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo ($i == $page) ? 'text-agri-green bg-green-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&barangay=<?php echo urlencode($barangay_filter); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-fish text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No FishR Records Found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                            No fishers match your search criteria. Try adjusting your filters.
                        <?php else: ?>
                            No fishers are currently registered in the FishR system.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search) || !empty($barangay_filter)): ?>
                        <a href="fishr_records.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-agri-green hover:bg-agri-dark">
                            <i class="fas fa-refresh mr-2"></i>
                            Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function exportToPDF() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('action', 'export_pdf');
            window.open('fishr_records.php?' + urlParams.toString(), '_blank');
        }
    </script>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
