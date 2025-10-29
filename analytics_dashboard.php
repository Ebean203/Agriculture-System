<?php
require_once 'conn.php';
require_once 'check_session.php';
require_once __DIR__ . '/includes/yield_helpers.php';

// Get date range from request or set defaults
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01')); // First day of current month
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d')); // Today
$report_type = 'yield_monitoring'; // Default report type for simplified chart
$chart_type = '';
$barangay_filter = isset($_POST['barangay_filter']) ? $_POST['barangay_filter'] : (isset($_GET['barangay_filter']) ? $_GET['barangay_filter'] : '');

// Get barangays for filter dropdown
function getBarangays($conn) {
    $query = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $data;
}

// Function to get farmer registrations over time
function getFarmerRegistrations($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $query = "SELECT DATE(registration_date) as date, COUNT(*) as count FROM farmers f WHERE DATE(registration_date) BETWEEN ? AND ? $barangay_condition GROUP BY DATE(registration_date) ORDER BY date";
    if ($stmt = mysqli_prepare($conn, $query)) {
        if ($barangay_filter) {
            mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $data;
    }
    return [];
}

// Function to get yield monitoring data
function getYieldData($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $query = "SELECT DATE(ym.record_date) as date, SUM(ym.yield_amount) as total_yield FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id WHERE DATE(ym.record_date) BETWEEN ? AND ? $barangay_condition GROUP BY DATE(ym.record_date) ORDER BY date";
    if ($stmt = mysqli_prepare($conn, $query)) {
        if ($barangay_filter) {
            mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $data;
    }
    return [];
}

// Function to get commodity distribution
function getCommodityDistribution($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $query = "SELECT c.commodity_name, COUNT(DISTINCT f.farmer_id) as farmer_count FROM farmers f JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id JOIN commodities c ON fc.commodity_id = c.commodity_id WHERE DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition GROUP BY c.commodity_name ORDER BY farmer_count DESC";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $data;
}

// Function to get registration status comparison
function getRegistrationStatus($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $total_farmers = 0;
    $rsbsa_count = 0;
    $ncfrs_count = 0;
    $fisherfolk_count = 0;
    $registered_count = 0;
    // Get total farmers in date range
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "ss" . "s", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $total_farmers = $row['count'];
    }
    mysqli_stmt_close($stmt);
    // Get RSBSA registered farmers
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE f.is_rsbsa = 1 AND DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "ss" . "s", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $rsbsa_count = $row['count'];
    }
    
    // Get NCFRS registered farmers
    $query = "
        SELECT COUNT(*) as count 
        FROM farmers f 
        WHERE f.is_ncfrs = 1 AND DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE f.is_ncfrs = 1 AND DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $ncfrs_count = $row['count'];
    }
    mysqli_stmt_close($stmt);
    
    // Get Fisherfolk registered farmers
    $fisherfolk_count = 0;
    $query = "
        SELECT COUNT(*) as count 
        FROM farmers f 
        WHERE f.is_fisherfolk = 1 AND DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE f.is_fisherfolk = 1 AND DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $fisherfolk_count = $row['count'] ?? 0;
    }
    mysqli_stmt_close($stmt);
    
    // Get count of farmers registered in at least one category (to avoid negative values)
    $query = "
        SELECT COUNT(*) as count 
        FROM farmers f 
        WHERE (f.is_rsbsa = 1 OR f.is_ncfrs = 1 OR f.is_fisherfolk = 1) 
        AND DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE (f.is_rsbsa = 1 OR f.is_ncfrs = 1 OR f.is_fisherfolk = 1) AND DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $registered_count = $row['count'];
    }
    mysqli_stmt_close($stmt);
    
    // Calculate not registered (ensure it's not negative)
    $not_registered_count = max(0, $total_farmers - $registered_count);
    
    return [
        ['status' => 'RSBSA Registered', 'count' => $rsbsa_count],
        ['status' => 'NCFRS Registered', 'count' => $ncfrs_count],
        ['status' => 'Fisherfolk Registered', 'count' => $fisherfolk_count],
        ['status' => 'Not Registered', 'count' => $not_registered_count]
    ];
}

// Function to get barangay distribution
function getBarangayDistribution($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $query = "SELECT b.barangay_name, COUNT(f.farmer_id) as farmer_count FROM farmers f JOIN barangays b ON f.barangay_id = b.barangay_id WHERE DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition GROUP BY b.barangay_name ORDER BY farmer_count DESC LIMIT 10";
    $stmt = mysqli_prepare($conn, $query);
    if ($barangay_filter) {
        mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $data;
}

// Get summary statistics

$total_farmers = 0;
$total_yield = 0;
$total_boats = 0;
$total_commodities = 0;
$barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';

$query = "SELECT COUNT(*) as count FROM farmers f WHERE DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
$stmt = mysqli_prepare($conn, $query);
if ($barangay_filter) {
    mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_farmers = $row['count'];
}
mysqli_stmt_close($stmt);

$query = "SELECT SUM(ym.yield_amount) as total FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id WHERE DATE(ym.record_date) BETWEEN ? AND ? $barangay_condition";
$stmt = mysqli_prepare($conn, $query);
if ($barangay_filter) {
    mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_yield = $row['total'] ?? 0;
}
mysqli_stmt_close($stmt);

$query = "SELECT COUNT(*) as count FROM farmers f WHERE f.is_boat = 1 AND DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
$stmt = mysqli_prepare($conn, $query);
if ($barangay_filter) {
    mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_boats = $row['count'];
}
mysqli_stmt_close($stmt);

$query = "SELECT COUNT(DISTINCT c.commodity_id) as count FROM commodities c JOIN farmer_commodities fc ON c.commodity_id = fc.commodity_id JOIN farmers f ON fc.farmer_id = f.farmer_id WHERE DATE(f.registration_date) BETWEEN ? AND ? $barangay_condition";
$stmt = mysqli_prepare($conn, $query);
if ($barangay_filter) {
    mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
} else {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_commodities = $row['count'];
}
mysqli_stmt_close($stmt);

// Get barangays for dropdown
$barangays = getBarangays($conn);
?>
<?php $pageTitle = 'Visual Analytics Dashboard - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
            <div class="max-w-7xl mx-auto py-0 px-4 sm:px-6 lg:px-8" style="margin-top:-32px;padding-top:0;">
                <!-- Local Chart.js files for offline use -->
                <script src="assets/js/chart.min.js"></script>
                <script src="assets/js/chartjs-plugin-datalabels.min.js"></script>
    
    <style>
        /* Custom dropdown styles for user menu */
        #dropdownMenu {
            display: none;
            z-index: 50;
            transition: all 0.2s ease-in-out;
            transform-origin: top right;
        }
        #dropdownMenu.show {
            display: block !important;
            z-index: 999999 !important;
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            margin-top: 0.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
        }
        #dropdownArrow.rotate {
            transform: rotate(180deg);
        }
r        .gradient-bg {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">
    

<div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section (Uniform White Card Design) -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div class="flex items-start">
                        <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mr-3">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Analytics Dashboard</h1>
                            <p class="text-gray-600">Interactive data visualization and insights</p>
                            <div class="flex items-center text-gray-500 mt-2">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Data Range: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
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
                                    <a href="analytics_dashboard.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
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

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <?php
                // Always show all commodity categories as cards, even if no yield data
                $cat_sql = "SELECT category_id, category_name FROM commodity_categories ORDER BY category_id ASC LIMIT 4";
                $cat_result = mysqli_query($conn, $cat_sql);
                $categories = [];
                while ($cat_row = mysqli_fetch_assoc($cat_result)) {
                    $categories[$cat_row['category_id']] = $cat_row['category_name'];
                }

                // Get yield and unit per category (may be missing some categories)
                // Use LEFT JOIN and put date filter in ON clause
                $sql = "SELECT cat.category_id, cat.category_name, ym.unit, SUM(ym.yield_amount) as total_yield
                        FROM commodity_categories cat
                        LEFT JOIN commodities c ON c.category_id = cat.category_id
                        LEFT JOIN farmer_commodities fc ON fc.commodity_id = c.commodity_id
                        LEFT JOIN farmers f ON f.farmer_id = fc.farmer_id
                        LEFT JOIN yield_monitoring ym ON ym.farmer_id = fc.farmer_id AND ym.commodity_id = fc.commodity_id AND DATE(ym.record_date) BETWEEN ? AND ?
                        ";
                $params = [$start_date, $end_date];
                $types = "ss";
                if ($barangay_filter) {
                    $sql .= " AND f.barangay_id = ?";
                    $params[] = $barangay_filter;
                    $types .= "s";
                }
                $sql .= " GROUP BY cat.category_id, cat.category_name, ym.unit ORDER BY cat.category_id ASC LIMIT 40";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                // Build associative array: [category_id][unit] = total_yield
                $yield_data = [];
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $cat_id = $row['category_id'];
                        $unit = $row['unit'];
                        $total = $row['total_yield'];
                        if (!isset($yield_data[$cat_id])) $yield_data[$cat_id] = [];
                        $yield_data[$cat_id][$unit] = $total;
                    }
                }
                $bgClasses = ['from-blue-500 to-blue-600', 'from-green-500 to-green-600', 'from-purple-500 to-purple-600', 'from-orange-500 to-orange-600'];
                $iconClasses = ['fas fa-wheat-awn','fas fa-seedling','fas fa-leaf','fas fa-apple-alt'];
                require_once __DIR__ . '/includes/yield_helpers.php';
                $i = 0;
                foreach ($categories as $cat_id => $cat_name) {
                    $bg = $bgClasses[$i] ?? 'from-gray-500 to-gray-600';
                    $icon = $iconClasses[$i] ?? 'fas fa-box';
                    $units = isset($yield_data[$cat_id]) ? $yield_data[$cat_id] : [];
                    $parts = [];
                    if (!empty($units)) {
                        foreach ($units as $unit => $amt) {
                            if ($amt > 0) {
                                $labelUnit = normalize_unit_label($unit);
                                $parts[] = '<span class="text-2xl font-bold">' . number_format($amt, 2) . '</span> <span class="text-base">' . htmlspecialchars($labelUnit) . '</span>';
                            }
                        }
                    }
                    // Only show 0 for Agronomic Crops and Livestock if no data, otherwise leave blank for High Value Crops and Poultry if no data
                    if (empty($parts)) {
                        if ($cat_id == 1 || $cat_id == 3) {
                            $display = '<span class="text-2xl font-bold">0</span> <span class="text-base">kg</span>';
                        } else {
                            $display = '';
                        }
                    } else {
                        $display = implode('<br>', $parts);
                    }
                ?>
                <div class="bg-gradient-to-r <?php echo $bg; ?> rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm"><?php echo htmlspecialchars($cat_name); ?></p>
                            <div><?php echo $display; ?></div>
                        </div>
                        <i class="<?php echo $icon; ?> text-3xl text-blue-200"></i>
                    </div>
                </div>
                <?php $i++; } if (isset($stmt)) mysqli_stmt_close($stmt); ?>
            </div>

            <!-- Controls Section -->
            <!-- Chart Filters Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <form id="chartFilterForm" class="row g-3 align-items-end" autocomplete="off">
                    <div class="col-md-4">
                        <label for="filterBarangay" class="form-label">Barangay</label>
                        <select id="filterBarangay" name="barangay_filter" class="form-select">
                            <option value="">All Barangays</option>
                            <?php foreach($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo $barangay_filter == $barangay['barangay_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filterStartDate" class="form-label">Start Date</label>
                        <input type="date" id="filterStartDate" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="filterEndDate" class="form-label">End Date</label>
                        <input type="date" id="filterEndDate" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync-alt me-2"></i>Update</button>
                    </div>
                </form>
            </div>

            <!-- Chart Display -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-area text-agri-green mr-3"></i>
                        <span id="chartTitle">Analytics Chart</span>
                    </h3>
                    <div class="flex space-x-2">
                        <button onclick="downloadChart()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center">
                            <i class="fas fa-download mr-2"></i>Download
                        </button>
                        <button onclick="toggleFullscreen()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <!-- Chart Type Switcher Buttons (only line and bar) -->
                <div class="flex justify-center mb-4">
                    <div class="bg-gray-100 rounded-lg p-1 flex space-x-1">
                        <button onclick="switchChartType('line')" id="btn-line" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-line mr-2"></i>Line
                        </button>
                        <button onclick="switchChartType('bar')" id="btn-bar" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>Bar
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="analyticsChart"></canvas>
                </div>
                <div id="noDataMessage" class="hidden text-center mt-6 text-gray-600">
                    <i class="fas fa-exclamation-circle text-2xl text-gray-400 block mx-auto"></i>
                    <p class="mt-2">No data available for the selected filters and date range.</p>
                </div>
            </div>

            <!-- SECTION 1: FARMER PRODUCTION PERFORMANCE -->
            <div class="row g-4 mb-4">
            <!-- SECTION 3: COMPLIANCE & PROGRAM EFFICIENCY -->
            <div class="row g-4 mb-4">
                <!-- Yield Reporting Compliance Score -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <h5 class="card-title mb-3">Yield Reporting Compliance</h5>
                            <h1 id="complianceRateDisplay" class="display-3 fw-bold text-success mb-0">--%</h1>
                            <div class="text-muted mt-2">% of input recipients reporting yield</div>
                        </div>
                    </div>
                </div>
                <!-- Commodity Yield Breakdown -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Commodity Share of Total Yield</h5>
                            <canvas id="commodityYieldPie" height="180"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Program Efficiency Comparison -->
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Avg. Yield by Program (RSBSA vs. General)</h5>
                            <canvas id="programEfficiencyChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- SECTION 2: INVENTORY RISK & DISTRIBUTION -->
            <div class="row g-4 mb-4">
                <!-- Inventory Risk: Low Stock & Expiring Items -->
                <div class="col-md-6">
                    <div class="card text-white bg-danger h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">⚠️ Inventory Risk: Low Stock & Expiring</h5>
                            <div class="row">
                                <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                                    <h6 class="fw-bold">Low Stock Items</h6>
                                    <ul class="list-group list-group-flush bg-transparent" id="lowStockList">
                                        <!-- Data loaded via JS -->
                                    </ul>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <h6 class="fw-bold">Expiring Soon (60 days)</h6>
                                    <ul class="list-group list-group-flush bg-transparent" id="expiringSoonList">
                                        <!-- Data loaded via JS -->
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Input Distribution Trends -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Input Distribution Volume (Monthly)</h5>
                            <canvas id="distributionTrendChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
                <!-- Top Yield Producers & Input Correlation -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Top Yield Producers & Input Correlation</h5>
                            <form class="row g-2 mb-3" id="topProducersFilterForm" autocomplete="off">
                                <div class="col-6">
                                    <label for="topProducersFrom" class="form-label mb-1">From Date</label>
                                    <input type="date" class="form-control" id="topProducersFrom" name="from" required>
                                </div>
                                <div class="col-6">
                                    <label for="topProducersTo" class="form-label mb-1">To Date</label>
                                    <input type="date" class="form-control" id="topProducersTo" name="to" required>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0" id="topProducersTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Farmer Name</th>
                                            <th>Total Yield (kg)</th>
                                            <th>Key Inputs Received</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Data loaded via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Historical Yield Trends -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Historical Yield Trends (Kg/Month)</h5>
                            <canvas id="historicalYieldChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dropdown toggle for user menu (Farmers.php style)
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
        }

        // Close dropdown when clicking outside (Farmers.php style)
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            if (!userMenu.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate');
            }
            
        });
        // Chart instance
        let analyticsChart = null;

        // Chart data from PHP - always use yield_monitoring as default
        <?php
            $data = getYieldData($conn, $start_date, $end_date, $barangay_filter);
            $chart_payload = [
                'labels' => array_column($data, 'date'),
                'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'total_yield'))),
                'reportType' => 'yield_monitoring',
                'label' => 'Total Yield (kg)'
            ];
            echo "const chartData = " . ($chart_payload ? json_encode($chart_payload) : 'null') . ";\n";
            echo "let currentChartType = 'line';\n";
        ?>

        // Function to switch chart type dynamically
        function switchChartType(newType) {
            if (!chartData) return;
            
            currentChartType = newType;
            
            // Update button states
            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                btn.classList.remove('bg-agri-green', 'text-white');
                btn.classList.add('text-gray-600', 'hover:bg-gray-200');
            });
            
            document.getElementById('btn-' + newType).classList.remove('text-gray-600', 'hover:bg-gray-200');
            document.getElementById('btn-' + newType).classList.add('bg-agri-green', 'text-white');
            
            // Recreate chart with new type
            initChart();
        }

        // Color schemes
        const colorSchemes = {
            blue: ['#3B82F6', '#1E40AF', '#1D4ED8', '#2563EB', '#3730A3'],
            green: ['#10B981', '#059669', '#047857', '#065F46', '#064E3B'],
            purple: ['#8B5CF6', '#7C3AED', '#6D28D9', '#5B21B6', '#4C1D95'],
            mixed: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316']
        };

        function initChart() {
            if (window.APP_DEBUG) console.log('initChart called');
            
            if (!chartData) {
                if (window.APP_DEBUG) console.log('No chart data available');
                document.getElementById('noDataMessage')?.classList.remove('hidden');
                document.getElementById('analyticsChart')?.classList.add('hidden');
                return;
            }
            // If chartData exists but has empty labels or data, treat as no-data
            if (!Array.isArray(chartData.labels) || chartData.labels.length === 0 || !Array.isArray(chartData.data) || chartData.data.length === 0) {
                if (window.APP_DEBUG) console.log('Chart data empty');
                document.getElementById('noDataMessage')?.classList.remove('hidden');
                document.getElementById('analyticsChart')?.classList.add('hidden');
                return;
            }

            if (window.APP_DEBUG) console.log('Chart data:', chartData);
            
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }
            
            const canvas = document.getElementById('analyticsChart');
            document.getElementById('noDataMessage')?.classList.add('hidden');
            canvas.classList.remove('hidden');
            if (!canvas) {
                console.error('Chart canvas element not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            if (analyticsChart) {
                analyticsChart.destroy();
            }

            const config = {
                type: currentChartType,
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: chartData.label,
                        data: chartData.data,
                        backgroundColor: currentChartType === 'line' ? 'rgba(59, 130, 246, 0.1)' : colorSchemes.mixed,
                        borderColor: currentChartType === 'line' ? '#3B82F6' : colorSchemes.mixed,
                        borderWidth: 2,
                        fill: currentChartType === 'line',
                        tension: 0.4
                    }]
                },
                plugins: [ChartDataLabels],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: '#3B82F6',
                            borderWidth: 1,
                            callbacks: {
                                afterLabel: function(context) {
                                    if (currentChartType === 'pie' || currentChartType === 'doughnut') {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.raw / total) * 100).toFixed(1);
                                        return `Percentage: ${percentage}%`;
                                    }
                                    return '';
                                }
                            }
                        },
                        datalabels: {
                            display: function(context) {
                                // Only show for pie and doughnut charts
                                return currentChartType === 'pie' || currentChartType === 'doughnut';
                            },
                            color: 'white',
                            font: {
                                weight: 'bold',
                                size: 14
                            },
                            formatter: function(value, context) {
                                // For pie/doughnut charts, value IS the actual data value
                                if (currentChartType !== 'pie' && currentChartType !== 'doughnut') {
                                    return null;
                                }
                                
                                // Get the dataset and calculate total
                                const dataset = context.dataset;
                                const dataArray = dataset.data;
                                const total = dataArray.reduce((sum, val) => sum + Number(val), 0);
                                
                                // Calculate percentage using the raw value
                                const numValue = Number(value);
                                const percentage = total > 0 ? ((numValue / total) * 100).toFixed(1) : 0;
                                
                                // Only hide labels for 0% (but show all others)
                                if (parseFloat(percentage) === 0) {
                                    return null;
                                }
                                
                                return percentage + '%';
                            },
                            anchor: 'center',
                            align: 'center'
                        }
                    },
                    layout: currentChartType === 'pie' || currentChartType === 'doughnut' ? {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 20,
                            right: 20
                        }
                    } : {},
                    scales: currentChartType === 'line' || currentChartType === 'bar' ? {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    } : {},
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            };

            if (window.APP_DEBUG) console.log('Creating chart with config:', config);
            analyticsChart = new Chart(ctx, config);
            if (window.APP_DEBUG) console.log('Chart created successfully');
        }

        function downloadChart() {
            if (analyticsChart) {
                const link = document.createElement('a');
                link.download = 'analytics-chart.png';
                link.href = analyticsChart.toBase64Image();
                link.click();
            }
        }

        function toggleFullscreen() {
            const chartContainer = document.querySelector('.chart-container').parentElement;
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                chartContainer.requestFullscreen();
            }
        }

        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (window.APP_DEBUG) console.log('DOM Content Loaded');
            if (window.APP_DEBUG) console.log('Chart available?', typeof Chart !== 'undefined');
            if (window.APP_DEBUG) console.log('Chart data?', chartData);
            
            // Register the datalabels plugin
            if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
                Chart.register(ChartDataLabels);
                if (window.APP_DEBUG) console.log('ChartDataLabels plugin registered successfully');
            } else {
                console.error('ChartDataLabels plugin not available');
            }
            
            // Wait a bit for Chart.js to fully load
            setTimeout(function() {
                if (typeof Chart !== 'undefined') {
                    if (window.APP_DEBUG) console.log('Chart.js loaded successfully');
                    if (chartData) {
                        if (window.APP_DEBUG) console.log('Chart data exists, initializing chart');
                        // Set default active button
                        switchChartType('line');
                    } else {
                        if (window.APP_DEBUG) console.log('No chart data available');
                    }
                } else {
                    console.error('Chart.js failed to load');
                }
                
                // Add animation to cards
                const cards = document.querySelectorAll('.animate-fade-in');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }, 100);
        });

        // Quick date range buttons
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            document.querySelector('input[name="start_date"]').value = startDate.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = endDate.toISOString().split('T')[0];
        }

        // AJAX form submission disabled for debugging - use normal form submission
        /*
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const loadingIndicator = document.getElementById('loadingIndicator');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent normal form submission
                    
                    // Show loading indicator
                    loadingIndicator.classList.remove('hidden');
                    
                    // Get form data
                    const formData = new FormData(form);
                    
                    // Submit via AJAX
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Parse the response and update the page content
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Update the main content area
                        const newContent = doc.querySelector('.container');
                        const currentContent = document.querySelector('.container');
                        
                        if (newContent && currentContent) {
                            currentContent.innerHTML = newContent.innerHTML;
                            
                            // Re-initialize any scripts if needed
                            const scripts = newContent.querySelectorAll('script');
                            scripts.forEach(script => {
                                const newScript = document.createElement('script');
                                newScript.textContent = script.textContent;
                                document.head.appendChild(newScript);
                            });
                        }
                        
                        // Hide loading indicator
                        loadingIndicator.classList.add('hidden');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        loadingIndicator.classList.add('hidden');
                        alert('An error occurred while generating the report. Please try again.');
                    });
                });
            }
        });
        */
        
        // Function to handle navigation dropdown toggle
        function toggleNavigationDropdown() {
            const dropdown = document.getElementById('navigationDropdown');
            const arrow = document.getElementById('navigationArrow');
            
            dropdown.classList.toggle('hidden');
            arrow.classList.toggle('rotate-180');
        }

        // Close navigation dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const navigationButton = event.target.closest('button');
            const isNavigationButton = navigationButton && navigationButton.onclick && navigationButton.onclick.toString().includes('toggleNavigationDropdown');
            const navigationDropdown = document.getElementById('navigationDropdown');
            
            if (!isNavigationButton && navigationDropdown && !navigationDropdown.contains(event.target)) {
                navigationDropdown.classList.add('hidden');
                const navigationArrow = document.getElementById('navigationArrow');
                if (navigationArrow) {
                    navigationArrow.classList.remove('rotate-180');
                }
            }
        });
    </script>
    <!-- Deep-dive Analytics JS (new sections) -->
    <script>
    // SECTION 1: Farmer Production Performance
    function loadTopProducers() {
        const from = document.getElementById('topProducersFrom').value;
        const to = document.getElementById('topProducersTo').value;
        const tableBody = document.querySelector('#topProducersTable tbody');
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>';
        $.getJSON('get_report_data.php', { type: 'top_producers', from, to }, function(res) {
            if (Array.isArray(res) && res.length) {
                tableBody.innerHTML = res.map(row =>
                    `<tr><td>${row.farmer_name}</td><td>${row.total_yield}</td><td>${row.inputs_received}</td></tr>`
                ).join('');
            } else {
                tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data found for selected range.</td></tr>';
            }
        }).fail(function() {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>';
        });
    }

    function initHistoricalYieldChart() {
        const ctx = document.getElementById('historicalYieldChart').getContext('2d');
        $.getJSON('get_report_data.php', { type: 'yield_trend' }, function(res) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        label: 'Yield (kg)',
                        data: res.data || [],
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}}
            });
        });
    }

    // SECTION 2: Inventory Risk & Distribution
    function loadInventoryRisk() {
        // Low Stock
        $.getJSON('get_report_data.php', { type: 'low_stock' }, function(res) {
            const list = document.getElementById('lowStockList');
            if (Array.isArray(res) && res.length) {
                list.innerHTML = res.map(item => `<li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">${item.name}<span class="badge bg-warning text-dark">${item.qty}</span></li>`).join('');
            } else {
                list.innerHTML = '<li class="list-group-item bg-transparent text-muted">No low stock items.</li>';
            }
        });
        // Expiring Soon
        $.getJSON('get_report_data.php', { type: 'expiring_soon' }, function(res) {
            const list = document.getElementById('expiringSoonList');
            if (Array.isArray(res) && res.length) {
                list.innerHTML = res.map(item => `<li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">${item.name}<span class="badge bg-danger">${item.days_left}d</span></li>`).join('');
            } else {
                list.innerHTML = '<li class="list-group-item bg-transparent text-muted">No items expiring soon.</li>';
            }
        });
    }

    function initDistributionTrendChart() {
        const ctx = document.getElementById('distributionTrendChart').getContext('2d');
        $.getJSON('get_report_data.php', { type: 'input_distribution' }, function(res) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        label: 'Inputs Distributed',
                        data: res.data || [],
                        backgroundColor: '#f59e42'
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}}
            });
        });
    }

    // SECTION 3: Compliance & Program Efficiency
    function loadComplianceRate() {
        $.getJSON('get_report_data.php', { type: 'compliance_rate' }, function(res) {
            document.getElementById('complianceRateDisplay').textContent = (res && res.rate ? res.rate : '--') + '%';
        });
    }

    function initCommodityYieldPie() {
        const ctx = document.getElementById('commodityYieldPie').getContext('2d');
        $.getJSON('get_report_data.php', { type: 'yield_breakdown' }, function(res) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        data: res.data || [],
                        backgroundColor: ['#16a34a','#f59e42','#3b82f6','#e11d48','#fbbf24','#6366f1','#10b981']
                    }]
                },
                options: {responsive:true, plugins:{legend:{position:'bottom'}}}
            });
        });
    }

    function initProgramEfficiencyChart() {
        const ctx = document.getElementById('programEfficiencyChart').getContext('2d');
        $.getJSON('get_report_data.php', { type: 'program_comparison' }, function(res) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        label: 'Avg. Yield (kg)',
                        data: res.data || [],
                        backgroundColor: ['#6366f1','#f59e42']
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}}
            });
        });
    }

    // Event bindings and initial load
    document.addEventListener('DOMContentLoaded', function() {
        // Top Producers date filter
        const fromInput = document.getElementById('topProducersFrom');
        const toInput = document.getElementById('topProducersTo');
        if (fromInput && toInput) {
            const today = new Date().toISOString().split('T')[0];
            fromInput.value = today.slice(0,8) + '01';
            toInput.value = today;
            fromInput.addEventListener('change', loadTopProducers);
            toInput.addEventListener('change', loadTopProducers);
            loadTopProducers();
        }
        if (document.getElementById('historicalYieldChart')) initHistoricalYieldChart();
        loadInventoryRisk();
        if (document.getElementById('distributionTrendChart')) initDistributionTrendChart();
        loadComplianceRate();
        if (document.getElementById('commodityYieldPie')) initCommodityYieldPie();
        if (document.getElementById('programEfficiencyChart')) initProgramEfficiencyChart();
    });
    </script>

    <style>
        .chart-type-btn {
            transition: all 0.3s ease;
        }
        .chart-type-btn:hover {
            transform: translateY(-1px);
        }
        .chart-container {
            position: relative;
            height: 400px;
        }
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <?php include 'includes/notification_complete.php'; ?>

