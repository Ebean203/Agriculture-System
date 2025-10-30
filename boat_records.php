<?php
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/name_helpers.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle PDF export
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Build search condition for export
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $barangay_filter = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
    $search_condition = 'WHERE f.archived = 0 AND f.is_boat = 1';
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
    
    // Get Boat records for PDF export
    $export_sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
                   f.contact_number, f.address_details, b.barangay_name,
                   GROUP_CONCAT(DISTINCT c.commodity_name SEPARATOR ', ') as commodities_info,
                   f.registration_date as boat_registration_date
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
        <div class="title">Boat Owner Records Report</div>
        <div class="subtitle">Farmers with Boat Registration Status</div>
        <div class="subtitle">Generated on: ' . date('F d, Y h:i A') . '</div>
        <div class="subtitle">Total Records: ' . $export_result->num_rows . '</div>
    </div>';
    
    if ($export_result->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Farmer ID</th>
                    <th>Farmer Name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Commodities</th>
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
                <td>' . htmlspecialchars(date('M d, Y', strtotime($row['boat_registration_date']))) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div style="text-align: center; padding: 50px;">
            <h3>No Boat Owner Records Found</h3>
            <p>No farmers with boat registration status are currently in the system.</p>
        </div>';
    }
    
    // Output PDF-ready HTML
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Boat Records Report</title>';
    include 'includes/assets.php';
    echo '<style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #7c3aed;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 14px;
            color: #6d28d9;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        th {
            background-color: #7c3aed;
            color: white;
            padding: 8px 4px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
            max-width: 80px;
        }
        tr:nth-child(even) {
            background-color: #faf5ff;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    ' . $html . '
    <div class="footer">
        <p>Agriculture Management System - Boat Records</p>
        <p>This report contains ' . $export_result->num_rows . ' boat registration records</p>
    </div>
</body>
</html>';
    exit;
}

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Search and filter variables
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$barangay_filter = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';

// Handle clear all - reset search and filter
if (isset($_POST['clear_all'])) {
    $search = '';
    $barangay_filter = '';
}

// Build search condition
$search_condition = 'WHERE f.archived = 0 AND f.is_boat = 1';
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

// Count total records
$count_sql = "SELECT COUNT(DISTINCT f.farmer_id) as total 
              FROM farmers f 
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
              LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
              LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id 
              $search_condition";

if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}

$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch Boat records with pagination
$sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, f.gender, f.birth_date, f.address_details, f.registration_date,
        GROUP_CONCAT(DISTINCT c.commodity_name SEPARATOR ', ') as commodities_info,
        b.barangay_name, h.household_size,
        f.registration_date as boat_registration_date
        FROM farmers f
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
        LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
        LEFT JOIN household_info h ON f.farmer_id = h.farmer_id
        $search_condition
        GROUP BY f.farmer_id
        ORDER BY f.registration_date DESC, f.farmer_id DESC
        LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $all_params = array_merge($search_params, [$records_per_page, $offset]);
    $stmt->bind_param(str_repeat('s', count($search_params)) . 'ii', ...$all_params);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $records_per_page, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

// Get barangays for filter dropdown
$barangays_result = $conn->query("SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
    $barangays[] = $row;
}

// Function to build URL parameters
function buildUrlParams($page, $search = '', $barangay = '') {
    $params = "?page=$page";
    if (!empty($search)) {
        $params .= "&search=" . urlencode($search);
    }
    if (!empty($barangay)) {
        $params .= "&barangay=" . urlencode($barangay);
    }
    return $params;
}
?>
<?php $pageTitle = 'Boat Records - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-ship text-agri-green mr-3"></i>
                            Boat Records
                        </h1>
                        <p class="text-gray-600 mt-2">Fishing Boat Registration and Management System</p>
                        <div class="mt-2 text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Total Boat Registered Farmers: <span class="font-bold text-agri-green"><?php echo $total_records; ?></span>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="exportToPDF()" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i>Export to PDF
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
                                    <a href="fishr_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                        <i class="fas fa-anchor text-blue-600 mr-3"></i>
                                        FishR Records
                                    </a>
                                    <a href="boat_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
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

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="POST" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1"></i>Search Boat Records
                            </label>
                            <div class="relative">
                                <input type="text" name="search" id="boat_search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by owner name, contact, or registration number..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green"
                                       onkeyup="searchBoatAutoSuggest(this.value)"
                                       onfocus="showBoatSuggestions()"
                                       onblur="hideBoatSuggestions()">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                
                                <!-- Auto-suggest dropdown -->
                                <div id="boat_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <!-- Suggestions will be populated here -->
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>Filter by Barangay
                            </label>
                            <select name="barangay" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" 
                                            <?php echo ($barangay_filter == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="bg-agri-green text-white px-6 py-2 rounded-lg hover:bg-agri-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                            <button type="submit" name="clear_all" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Boat Records Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-alt mr-2 text-agri-green"></i>
                        Registered Fishing Boats
                    <?php if (!empty($search) || !empty($barangay_filter)): ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            - Filtered by: 
                            <?php if (!empty($search)): ?>
                                Search "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                            <?php if (!empty($search) && !empty($barangay_filter)): ?> & <?php endif; ?>
                            <?php if (!empty($barangay_filter)): ?>
                                <?php 
                                $selected_barangay = '';
                                foreach ($barangays as $barangay) {
                                    if ($barangay['barangay_id'] == $barangay_filter) {
                                        $selected_barangay = $barangay['barangay_name'];
                                        break;
                                    }
                                }
                                ?>
                                Barangay "<?php echo htmlspecialchars($selected_barangay); ?>"
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </h3>
            </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-agri-green text-white">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-id-card mr-1"></i>Farmer ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-user mr-1"></i>Farmer Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-phone mr-1"></i>Contact
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Barangay
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-seedling mr-1"></i>Commodities
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-calendar mr-1"></i>Registration Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-ship mr-1"></i>Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($farmer = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-agri-light transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <span class="bg-agri-green text-white px-2 py-1 rounded text-xs">
                                                #<?php echo $farmer['farmer_id']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(formatFarmerName($farmer['first_name'], $farmer['middle_name'], $farmer['last_name'], $farmer['suffix'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-birthday-cake mr-1"></i>
                                                <?php echo $farmer['birth_date'] ? date('M d, Y', strtotime($farmer['birth_date'])) : 'N/A'; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <i class="fas fa-phone mr-1 text-green-600"></i>
                                            <?php echo htmlspecialchars($farmer['contact_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <i class="fas fa-map-pin mr-1 text-red-600"></i>
                                            <?php echo htmlspecialchars($farmer['barangay_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php 
                                                if (!empty($farmer['commodities_info'])) {
                                                    $commodities = explode(', ', $farmer['commodities_info']);
                                                    echo '<div class="flex flex-col gap-1">';
                                                    foreach ($commodities as $commodity) {
                                                        echo '<div class="bg-green-100 text-green-800 rounded px-2 py-1 text-xs flex items-center"><i class="fas fa-leaf mr-1"></i>' . htmlspecialchars($commodity) . '</div>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('M d, Y', strtotime($farmer['boat_registration_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium">
                                                <i class="fas fa-ship mr-1"></i>Boat Owner
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-ship text-6xl text-gray-300 mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Boat Records Found</h3>
                                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                                            <p class="text-sm text-gray-600 mb-2">No boats match your search criteria</p>
                                            <p class="text-xs text-gray-500">Try adjusting your search terms or filters</p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-600 mb-2">No boats are currently registered in the system</p>
                                            <p class="text-xs text-gray-500">The system is ready to display boat records when boats are registered</p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Showing <?php echo min(($page - 1) * $records_per_page + 1, $total_records); ?> to 
                            <?php echo min($page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> results
                        </div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildUrlParams($page - 1, $search, $barangay_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="<?php echo buildUrlParams($i, $search, $barangay_filter); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                                          <?php echo $i == $page ? 'bg-agri-green text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo buildUrlParams($page + 1, $search, $barangay_filter); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Function to export Boat records to PDF
        function exportToPDF() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const barangay = new URLSearchParams(window.location.search).get('barangay') || '';
            let exportUrl = 'pdf_export.php?action=export_pdf&is_boat=1';
            if (search) exportUrl += `&search=${encodeURIComponent(search)}`;
            if (barangay) exportUrl += `&barangay=${encodeURIComponent(barangay)}`;
            // Show loading indicator
            const exportBtn = document.querySelector('button[onclick="exportToPDF()"]');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating PDF...';
            exportBtn.disabled = true;
            // Open in new window for PDF download
            window.open(exportUrl, '_blank');
            // Reset button after delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        }

        // Auto-suggest functionality for boat search
        function searchBoatAutoSuggest(query) {
            const suggestions = document.getElementById('boat_suggestions');
            
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
            fetch('get_farmers.php?action=search&include_archived=false&filter_type=boat&query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.farmers && data.farmers.length > 0) {
                        let html = '';
                        data.farmers.forEach(farmer => {
                            html += `
                                <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0" 
                                     onclick="selectBoatSuggestion('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}')">
                                    <div class="font-medium text-gray-900">${farmer.full_name}</div>
                                    <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                                    <div class="text-xs text-gray-500">${farmer.barangay_name}</div>
                                </div>
                            `;
                        });
                        suggestions.innerHTML = html;
                        suggestions.classList.remove('hidden');
                    } else {
                        suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No boat owners found matching your search</div>';
                        suggestions.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
                    suggestions.classList.remove('hidden');
                });
        }

        function selectBoatSuggestion(farmerId, farmerName, contactNumber) {
            const searchInput = document.getElementById('boat_search');
            searchInput.value = farmerName;
            hideBoatSuggestions();
            
            // Trigger form submission to filter results
            const form = searchInput.closest('form');
            if (form) {
                form.submit();
            }
        }

        function showBoatSuggestions() {
            const searchInput = document.getElementById('boat_search');
            if (searchInput.value.length >= 1) {
                searchBoatAutoSuggest(searchInput.value);
            }
        }

        function hideBoatSuggestions() {
            const suggestions = document.getElementById('boat_suggestions');
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
