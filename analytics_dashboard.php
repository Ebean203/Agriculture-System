<?php
require_once 'conn.php';
require_once 'check_session.php';
require_once __DIR__ . '/includes/yield_helpers.php';

// Get date range from request or set defaults
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01')); // First day of current month
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d')); // Today
$report_type = isset($_POST['report_type']) ? $_POST['report_type'] : (isset($_GET['report_type']) ? $_GET['report_type'] : '');
$chart_type = isset($_POST['chart_type']) ? $_POST['chart_type'] : (isset($_GET['chart_type']) ? $_GET['chart_type'] : '');
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

// Function to get input distribution totals (by input name) from distribution log
function getInputDistribution($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $query = "SELECT i.input_name, SUM(md.quantity_distributed) as total_distributed
              FROM mao_distribution_log md
              JOIN input_categories i ON md.input_id = i.input_id
              LEFT JOIN farmers f ON md.farmer_id = f.farmer_id
              WHERE DATE(md.date_given) BETWEEN ? AND ? $barangay_condition
              GROUP BY i.input_name ORDER BY total_distributed DESC";
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

// Function to get current inventory status (snapshot)
function getInventoryStatus($conn) {
    // Inventory quantities are stored in `mao_inventory` and the canonical input names live
    // in `input_categories`. Join them to return readable names with quantities.
    $query = "SELECT ic.input_name, COALESCE(mi.quantity_on_hand, 0) as quantity_on_hand
              FROM input_categories ic
              LEFT JOIN mao_inventory mi ON ic.input_id = mi.input_id
              ORDER BY ic.input_name";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
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
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Farmers (Period)</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_farmers); ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-200"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Yield (kg)</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_yield, 1); ?></p>
                        </div>
                        <i class="fas fa-weight-hanging text-3xl text-green-200"></i>
                    </div>
                </div>
                <?php
                    // Replace Boats card with dynamic unit totals aggregated across all commodities
                    $agg_all = aggregate_totals_by_commodity_db($conn, $start_date, $end_date, $barangay_filter);
                    // Sum across commodities per unit
                    $unit_totals = [];
                    foreach ($agg_all as $com => $units) {
                        foreach ($units as $unit => $amt) {
                            if (!isset($unit_totals[$unit])) $unit_totals[$unit] = 0.0;
                            $unit_totals[$unit] += $amt;
                        }
                    }
                    // Prepare cards for top N units by amount
                    arsort($unit_totals);
                    $maxCards = 4;
                    $i = 0;
                    foreach ($unit_totals as $unit => $amt) {
                        if ($i >= $maxCards) break;
                        $i++;
                        $displayUnit = $unit ? htmlspecialchars($unit) : 'Units';
                        // Choose color set based on index
                        $bgClasses = ['from-purple-500 to-purple-600', 'from-pink-500 to-pink-600', 'from-teal-500 to-teal-600', 'from-indigo-500 to-indigo-600'];
                        $iconClasses = ['fas fa-boxes','fas fa-balance-scale','fas fa-seedling','fas fa-box'];
                        $bg = $bgClasses[$i-1] ?? 'from-gray-500 to-gray-600';
                        $icon = $iconClasses[$i-1] ?? 'fas fa-box';
                        ?>
                        <div class="bg-gradient-to-r <?php echo $bg; ?> rounded-lg shadow-md p-6 text-white animate-fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-100 text-sm"><?php echo 'Total ' . $displayUnit; ?></p>
                                    <p class="text-2xl font-bold"><?php echo number_format($amt, 2); ?></p>
                                </div>
                                <i class="<?php echo $icon; ?> text-3xl text-purple-200"></i>
                            </div>
                        </div>
                        <?php
                    }
                    // If no unit totals found, render an empty placeholder (previous Boats card)
                    if ($i === 0) {
                ?>
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Boats Registered</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_boats); ?></p>
                        </div>
                        <i class="fas fa-ship text-3xl text-purple-200"></i>
                    </div>
                </div>
                <?php } ?>
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Active Commodities</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_commodities); ?></p>
                        </div>
                        <i class="fas fa-wheat-awn text-3xl text-orange-200"></i>
                    </div>
                </div>
            </div>

            <!-- Controls Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-sliders-h text-agri-green mr-3"></i>Analytics Controls
                </h3>
                <form method="POST" action="" class="space-y-4">
                    <!-- Report Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Choose a report type...</label>
                        <select name="report_type" id="reportType" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent" onchange="updateChartOptions()">
                            <option value="">Select Report Type...</option>
                            <!-- Keep analytics-specific time-series option -->
                            <option value="farmer_registrations" <?php echo $report_type === 'farmer_registrations' ? 'selected' : ''; ?>>👥 Farmer Registrations Over Time</option>

                            <!-- Mirror reports available on reports.php -->
                            <option value="farmers_summary" <?php echo $report_type === 'farmers_summary' ? 'selected' : ''; ?>>👥 Farmers Summary Report</option>
                            <option value="input_distribution" <?php echo $report_type === 'input_distribution' ? 'selected' : ''; ?>>📦 Input Distribution Report</option>
                            <option value="yield_monitoring" <?php echo $report_type === 'yield_monitoring' ? 'selected' : ''; ?>>🌾 Yield Monitoring Report</option>
                            <option value="inventory_status" <?php echo $report_type === 'inventory_status' ? 'selected' : ''; ?>>📋 Current Inventory Status</option>
                            <option value="barangay_analytics" <?php echo $report_type === 'barangay_analytics' ? 'selected' : ''; ?>>🗺️ Barangay Analytics Report</option>
                            <option value="commodity_production" <?php echo $report_type === 'commodity_production' ? 'selected' : ''; ?>>🌱 Commodity Production Report</option>
                            <option value="registration_analytics" <?php echo $report_type === 'registration_analytics' ? 'selected' : ''; ?>>📋 Registration Analytics Report</option>
                            <option value="comprehensive_overview" <?php echo $report_type === 'comprehensive_overview' ? 'selected' : ''; ?>>📈 Comprehensive Overview Report</option>
                            <option value="consolidated_yield" <?php echo $report_type === 'consolidated_yield' ? 'selected' : ''; ?>>🧮 Consolidated Yield of All Commodities</option>
                        </select>
                    </div>

                    <!-- Filters Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Barangay Filter</label>
                            <select name="barangay_filter" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                                <option value="">All Barangays</option>
                                <?php foreach($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo $barangay_filter == $barangay['barangay_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-center">
                        <button type="submit" class="bg-agri-green text-white px-6 py-3 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>Generate Analytics
                        </button>
                    </div>
                </form>

                <!-- Loading indicator -->
                <div id="loadingIndicator" class="hidden text-center py-4">
                    <div class="inline-flex items-center">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-agri-green mr-3"></div>
                        <span class="text-gray-600">Generating analytics...</span>
                    </div>
                </div>
            </div>

            <!-- Chart Display -->
            <?php if($report_type): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <i class="fas fa-chart-area text-agri-green mr-3"></i>
                        <span id="chartTitle">
                            <?php 
                            $titles = [
                                'farmer_registrations' => 'Farmer Registrations Over Time',
                                'yield_monitoring' => 'Yield Production Over Time',
                                'commodity_distribution' => 'Commodity Distribution',
                                'barangay_analytics' => 'Farmers by Barangay',
                                'registration_status' => 'Registration Status Comparison'
                            ];
                            echo $titles[$report_type] ?? 'Analytics Chart';
                            ?>
                        </span>
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

                <!-- Chart Type Switcher Buttons -->
                <div class="flex justify-center mb-4">
                    <div class="bg-gray-100 rounded-lg p-1 flex space-x-1">
                        <button onclick="switchChartType('line')" id="btn-line" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-line mr-2"></i>Line
                        </button>
                        <button onclick="switchChartType('bar')" id="btn-bar" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-bar mr-2"></i>Bar
                        </button>
                        <button onclick="switchChartType('pie')" id="btn-pie" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-pie mr-2"></i>Pie
                        </button>
                        <button onclick="switchChartType('doughnut')" id="btn-doughnut" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center">
                            <i class="fas fa-chart-donut mr-2"></i>Doughnut
                        </button>
                    </div>
                </div>

                <div class="chart-container">
                    <canvas id="analyticsChart"></canvas>
                </div>
                <div id="noDataMessage" class="hidden text-center mt-6 text-gray-600">
                    <i class="fas fa-exclamation-circle text-2xl text-gray-400 block mx-auto"></i>
                    <p class="mt-2">No data available for the selected report and date range.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                    <p class="text-blue-800">Please select a report type to begin generating analytics.</p>
                </div>
            </div>
            <?php endif; ?>
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

        // Chart data from PHP - load data if report_type is selected
        <?php if($report_type):
            // Build a PHP payload and json_encode it. If the selected report_type is not supported for analytics,
            // $chart_payload will remain null and JS will receive `null` which prevents rendering undefined labels.
            $chart_payload = null;
            switch($report_type) {
                case 'farmer_registrations':
                    $data = getFarmerRegistrations($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'date'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'count'))),
                        'reportType' => 'farmer_registrations',
                        'label' => 'Farmers Registered'
                    ];
                    break;
                case 'yield_monitoring':
                    $data = getYieldData($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'date'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'total_yield'))),
                        'reportType' => 'yield_monitoring',
                        'label' => 'Total Yield (kg)'
                    ];
                    break;
                case 'commodity_distribution':
                    $data = getCommodityDistribution($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'commodity_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'farmer_count'))),
                        'reportType' => 'commodity_distribution',
                        'label' => 'Farmers per Commodity'
                    ];
                    break;
                case 'barangay_analytics':
                    $data = getBarangayDistribution($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'barangay_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'farmer_count'))),
                        'reportType' => 'barangay_analytics',
                        'label' => 'Farmers per Barangay'
                    ];
                    break;
                case 'registration_status':
                    $data = getRegistrationStatus($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'status'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'count'))),
                        'reportType' => 'registration_status',
                        'label' => 'Registration Status'
                    ];
                    break;
                // Aliases / additional reports present on reports.php
                case 'registration_analytics':
                    // alias for registration_status
                    $data = getRegistrationStatus($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'status'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'count'))),
                        'reportType' => 'registration_status',
                        'label' => 'Registration Status'
                    ];
                    break;
                case 'commodity_production':
                case 'commodity_distribution':
                    // alias: commodity production / distribution
                    $data = getCommodityDistribution($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'commodity_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'farmer_count'))),
                        'reportType' => 'commodity_distribution',
                        'label' => 'Farmers per Commodity'
                    ];
                    break;
                case 'consolidated_yield':
                    // Use helper to aggregate totals grouped by commodity+unit and prepare labels/data
                    $agg = aggregate_totals_by_commodity_db($conn, $start_date, $end_date, $barangay_filter);
                    $flat = flatten_aggregated_totals_for_chart($agg, 30, false);
                    $chart_payload = [
                        'labels' => $flat['labels'],
                        'data' => $flat['data'],
                        'reportType' => 'consolidated_yield',
                        'label' => 'Total Yield by Commodity'
                    ];
                    break;
                case 'farmers_summary':
                    // Use barangay distribution as a reasonable farmers summary visualization
                    $data = getBarangayDistribution($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'barangay_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'farmer_count'))),
                        'reportType' => 'farmers_summary',
                        'label' => 'Farmers per Barangay'
                    ];
                    break;
                case 'input_distribution':
                    $data = getInputDistribution($conn, $start_date, $end_date, $barangay_filter);
                    $chart_payload = [
                        'labels' => array_column($data, 'input_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):$v; }, array_column($data, 'total_distributed'))),
                        'reportType' => 'input_distribution',
                        'label' => 'Distributed Quantity by Input'
                    ];
                    break;
                case 'inventory_status':
                    // Inventory is a snapshot; ignore date range and show current quantities
                    $data = getInventoryStatus($conn);
                    $chart_payload = [
                        'labels' => array_column($data, 'input_name'),
                        'data' => array_values(array_map(function($v){ return is_numeric($v)?(0+$v):0; }, array_column($data, 'quantity_on_hand'))),
                        'reportType' => 'inventory_status',
                        'label' => 'Quantity on Hand'
                    ];
                    break;
                case 'comprehensive_overview':
                    // Small summary: Farmers, Total Yield, Total Distributed Inputs (sum), Active Commodities
                    // Total farmers
                    $q = "SELECT COUNT(*) as cnt FROM farmers WHERE DATE(registration_date) BETWEEN ? AND ?";
                    $totals = [];
                    if ($s = mysqli_prepare($conn, $q)) {
                        mysqli_stmt_bind_param($s, 'ss', $start_date, $end_date);
                        mysqli_stmt_execute($s);
                        $r = mysqli_stmt_get_result($s);
                        $row = $r ? mysqli_fetch_assoc($r) : null;
                        $totals['farmers'] = $row['cnt'] ?? 0;
                        mysqli_stmt_close($s);
                    }
                    // Total yield
                    $q = "SELECT SUM(yield_amount) as total_yield FROM yield_monitoring WHERE DATE(record_date) BETWEEN ? AND ?";
                    if ($s = mysqli_prepare($conn, $q)) {
                        mysqli_stmt_bind_param($s, 'ss', $start_date, $end_date);
                        mysqli_stmt_execute($s);
                        $r = mysqli_stmt_get_result($s);
                        $row = $r ? mysqli_fetch_assoc($r) : null;
                        $totals['total_yield'] = $row['total_yield'] ?? 0;
                        mysqli_stmt_close($s);
                    }
                    // Total distributed quantity
                    $q = "SELECT SUM(quantity_distributed) as total_dist FROM mao_distribution_log WHERE DATE(date_given) BETWEEN ? AND ?";
                    if ($s = mysqli_prepare($conn, $q)) {
                        mysqli_stmt_bind_param($s, 'ss', $start_date, $end_date);
                        mysqli_stmt_execute($s);
                        $r = mysqli_stmt_get_result($s);
                        $row = $r ? mysqli_fetch_assoc($r) : null;
                        $totals['total_distributed'] = $row['total_dist'] ?? 0;
                        mysqli_stmt_close($s);
                    }
                    // Active commodities count
                    $q = "SELECT COUNT(DISTINCT c.commodity_id) as cnt FROM commodities c JOIN farmer_commodities fc ON c.commodity_id = fc.commodity_id";
                    if ($s = mysqli_prepare($conn, $q)) {
                        mysqli_stmt_execute($s);
                        $r = mysqli_stmt_get_result($s);
                        $row = $r ? mysqli_fetch_assoc($r) : null;
                        $totals['active_commodities'] = $row['cnt'] ?? 0;
                        mysqli_stmt_close($s);
                    }

                    $chart_payload = [
                        'labels' => ['Farmers', 'Total Yield', 'Total Distributed', 'Active Commodities'],
                        'data' => [ (0+$totals['farmers']), (0+$totals['total_yield']), (0+$totals['total_distributed']), (0+$totals['active_commodities']) ],
                        'reportType' => 'comprehensive_overview',
                        'label' => 'Comprehensive Overview'
                    ];
                    break;
                // Unsupported report types will result in $chart_payload = null
            }

            // Encode payload and expose whether chart data is available for the selected report
            $encoded = $chart_payload ? json_encode($chart_payload) : 'null';
            $chartAvailable = ($chart_payload !== null) ? 'true' : 'false';
            echo "const chartData = {$encoded};\n";
            echo "const chartAvailable = {$chartAvailable};\n";
            echo "let currentChartType = 'line';\n";
            echo "if (window.APP_DEBUG) console.log('Chart data from PHP:', chartData, 'chartAvailable=', chartAvailable);\n";
        else: ?>
        const chartData = null;
        const chartAvailable = false;
        let currentChartType = 'line';
    if (window.APP_DEBUG) console.log('No chart data - report type not selected or not chartable');
        <?php endif; ?>

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
            
            // If the selected report type is not chartable, show a clear message and disable chart controls
            if (typeof chartAvailable !== 'undefined' && !chartAvailable) {
                if (window.APP_DEBUG) console.log('Selected report type is not chartable');
                const noDataEl = document.getElementById('noDataMessage');
                if (noDataEl) {
                    noDataEl.classList.remove('hidden');
                    noDataEl.innerHTML = '<i class="fas fa-exclamation-circle text-2xl text-gray-400 block mx-auto"></i>' +
                        '<p class="mt-2">The selected report type does not produce chartable data. Please choose one of the chartable report types: Farmer Registrations, Yield Monitoring, Commodity Distribution, Barangay Analytics, Registration Status, or Consolidated Yield.</p>';
                }
                const canvasEl = document.getElementById('analyticsChart');
                if (canvasEl) canvasEl.classList.add('hidden');
                // Disable chart type buttons
                document.querySelectorAll('.chart-type-btn').forEach(btn => btn.disabled = true);
                return;
            }

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

