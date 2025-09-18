<?php
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Helper function to format farmer name properly (exclude N/A suffixes)
function formatFarmerName($first_name, $middle_name, $last_name, $suffix) {
    $name_parts = [];
    
    if (!empty($first_name)) $name_parts[] = $first_name;
    if (!empty($middle_name)) $name_parts[] = $middle_name;
    if (!empty($last_name)) $name_parts[] = $last_name;
    
    // Only add suffix if it's not N/A (case insensitive)
    if (!empty($suffix) && !in_array(strtolower($suffix), ['n/a', 'na'])) {
        $name_parts[] = $suffix;
    }
    
    return trim(implode(' ', $name_parts));
}

// Handle PDF export
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Build search condition for export
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
    $search_condition = 'WHERE f.archived = 0 AND f.is_fisherfolk = 1';
    $search_params = [];
    
    if (!empty($search)) {
        $search_condition .= " AND (f.first_name LIKE ? OR f.middle_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ?)";
        $search_term = "%$search%";
        $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term];
    }
    
    if (!empty($barangay_filter)) {
        $search_condition .= " AND f.barangay_id = ?";
        $search_params[] = $barangay_filter;
    }
    
    // Get FishR records for PDF export (using actual database structure)
    $export_sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
                   f.contact_number, f.address_details,
                   GROUP_CONCAT(DISTINCT CONCAT(c.commodity_name, ' (', fc.land_area_hectares, ' ha)') SEPARATOR ', ') as commodities_info,
                   b.barangay_name, f.registration_date as fisherfolk_registration_date
                   FROM farmers f
                   LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                   LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
                   LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
                   $search_condition
                   GROUP BY f.farmer_id
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
                    <th>Commodities & Land Area</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $export_result->fetch_assoc()) {
            $full_name = formatFarmerName($row['first_name'], $row['middle_name'], $row['last_name'], $row['suffix']);
            $html .= '<tr>
                <td>' . htmlspecialchars($row['farmer_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['commodities_info'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars(date('M d, Y', strtotime($row['fisherfolk_registration_date']))) . '</td>
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
    <title>FishR Records Report</title>';
    include 'includes/assets.php';
    echo '<style>
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
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$barangay_filter = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';

// Handle clear all - reset search and filter
if (isset($_POST['clear_all'])) {
    $search = '';
    $barangay_filter = '';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build search condition
$search_condition = 'WHERE f.archived = 0 AND f.is_fisherfolk = 1';
$search_params = [];
$param_types = '';

if (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.middle_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $param_types .= 'sssss';
}

if (!empty($barangay_filter)) {
    $search_condition .= " AND f.barangay_id = ?";
    $search_params[] = $barangay_filter;
    $param_types .= 'i';
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT f.farmer_id) as total 
              FROM farmers f
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
              LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
              LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
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
        f.contact_number, f.address_details,
        GROUP_CONCAT(DISTINCT CONCAT(c.commodity_name, ' (', fc.land_area_hectares, ' ha)') SEPARATOR ', ') as commodities_info,
        b.barangay_name, f.registration_date as fisherfolk_registration_date
        FROM farmers f
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
        LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
        $search_condition
        GROUP BY f.farmer_id
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
<?php $pageTitle = 'FishR Records - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
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
                            <!-- Go to Page (rightmost) -->
                            <div class="relative">
                                <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center font-medium" onclick="toggleNavigationDropdown()">
                                    <i class="fas fa-compass mr-2"></i>Go to Page
                                    <i class="fas fa-chevron-down ml-2 transition-transform" id="navigationArrow"></i>
                                </button>
                                <div id="navigationDropdown" class="absolute left-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-[60] hidden overflow-y-auto" style="max-height: 500px;">
                                    <!-- Dashboard Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Dashboard</div>
                                        <a href="index.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-home text-blue-600 mr-3"></i>
                                            Dashboard
                                        </a>
                                        <a href="analytics_dashboard.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-chart-bar text-purple-600 mr-3"></i>
                                            Analytics Dashboard
                                        </a>
                                    </div>
                                    
                                    <!-- Inventory Management Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Inventory Management</div>
                                        <a href="mao_inventory.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-warehouse text-green-600 mr-3"></i>
                                            MAO Inventory
                                        </a>
                                        <a href="input_distribution_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-truck text-blue-600 mr-3"></i>
                                            Distribution Records
                                        </a>
                                        <a href="mao_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-calendar-check text-green-600 mr-3"></i>
                                            MAO Activities
                                        </a>
                                    </div>
                                    
                                    <!-- Records Management Section -->
                                    <div class="border-b border-gray-200">
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
                                        <a href="fishr_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
                                            <i class="fas fa-anchor text-blue-600 mr-3"></i>
                                            FishR Records
                                        </a>
                                        <a href="boat_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-ship text-indigo-600 mr-3"></i>
                                            Boat Records
                                        </a>
                                    </div>
                                    
                                    <!-- Monitoring & Reports Section -->
                                    <div class="border-b border-gray-200">
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
                                            <i class="fas fa-user-tie text-gray-600 mr-3"></i>
                                            Staff Management
                                        </a>
                                        <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-cog text-gray-600 mr-3"></i>
                                            System Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                    <form method="POST" class="flex flex-wrap gap-4 items-end">
                        <div class="flex-1 min-w-64">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Fishers</label>
                            <div class="relative">
                                <input type="text" name="search" id="fisher_search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or contact number..." 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green"
                                       onkeyup="searchFisherAutoSuggest(this.value)"
                                       onfocus="showFisherSuggestions()"
                                       onblur="hideFisherSuggestions()">
                                
                                <!-- Auto-suggest dropdown -->
                                <div id="fisher_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <!-- Suggestions will be populated here -->
                                </div>
                            </div>
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
                            <?php 
                            // Check if any filters are applied
                            $has_filters = !empty($search) || !empty($barangay_filter);
                            if ($has_filters): 
                            ?>
                                <a href="fishr_records.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                                    <i class="fas fa-refresh mr-2"></i>
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- FishR Records Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <?php if ($total_records > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-agri-green text-white">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Fisher Details</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Contact & Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Registration Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Commodity</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
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
                                                            <?php echo htmlspecialchars(formatFarmerName($row['first_name'], $row['middle_name'], $row['last_name'], $row['suffix'])); ?>
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
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($row['fisherfolk_registration_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Fisherfolk Registration
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <?php echo htmlspecialchars($row['commodities_info'] ?: 'Not specified'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-fish mr-1"></i>Fisherfolk Registered
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

                // Auto-suggest functionality for fisher search
                function searchFisherAutoSuggest(query) {
                    const suggestions = document.getElementById('fisher_suggestions');
                    
                    if (!suggestions) return; // Exit if element doesn't exist

                    if (query.length < 1) {
                        suggestions.innerHTML = '';
                        suggestions.classList.add('hidden');
                        return;
                    }

                    // Show loading indicator
                    suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
                    suggestions.classList.remove('hidden');

                    // Make AJAX request to get farmer suggestions
                    fetch('get_farmers.php?action=search&include_archived=false&filter_type=fisherfolk&query=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.farmers && data.farmers.length > 0) {
                                let html = '';
                                data.farmers.forEach(farmer => {
                                    html += `
                                        <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0" 
                                             onclick="selectFisherSuggestion('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}')">
                                            <div class="font-medium text-gray-900">${farmer.full_name}</div>
                                            <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                                            <div class="text-xs text-gray-500">${farmer.barangay_name}</div>
                                        </div>
                                    `;
                                });
                                suggestions.innerHTML = html;
                                suggestions.classList.remove('hidden');
                            } else {
                                suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No fishers found matching your search</div>';
                                suggestions.classList.remove('hidden');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
                            suggestions.classList.remove('hidden');
                        });
                }

                function selectFisherSuggestion(farmerId, farmerName, contactNumber) {
                    const searchInput = document.getElementById('fisher_search');
                    searchInput.value = farmerName;
                    hideFisherSuggestions();
                    
                    // Trigger form submission to filter results
                    const form = searchInput.closest('form');
                    if (form) {
                        form.submit();
                    }
                }

                function showFisherSuggestions() {
                    const searchInput = document.getElementById('fisher_search');
                    if (searchInput.value.length >= 1) {
                        searchFisherAutoSuggest(searchInput.value);
                    }
                }

                function hideFisherSuggestions() {
                    const suggestions = document.getElementById('fisher_suggestions');
                    if (suggestions) {
                        setTimeout(() => {
                            suggestions.classList.add('hidden');
                        }, 200); // Delay to allow click events on suggestions
                    }
                }

                // Navigation dropdown functionality
                function toggleNavigationDropdown() {
                    const dropdown = document.getElementById('navigationDropdown');
                    const arrow = document.getElementById('navigationArrow');
                    
                    if (dropdown.classList.contains('hidden')) {
                        dropdown.classList.remove('hidden');
                        arrow.classList.add('rotate-180');
                    } else {
                        dropdown.classList.add('hidden');
                        arrow.classList.remove('rotate-180');
                    }
                }

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    const navigationButton = event.target.closest('button');
                    const isNavigationButton = navigationButton && navigationButton.onclick && navigationButton.onclick.toString().includes('toggleNavigationDropdown');
                    const navigationDropdown = document.getElementById('navigationDropdown');
                    
                    if (!isNavigationButton && navigationDropdown && !navigationDropdown.contains(event.target)) {
                        navigationDropdown.classList.add('hidden');
                        const arrow = document.getElementById('navigationArrow');
                        if (arrow) arrow.classList.remove('rotate-180');
                    }
                });
            </script>
            
            <?php include 'includes/notification_complete.php'; ?>
