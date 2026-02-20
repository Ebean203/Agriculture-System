<?php
require_once 'conn.php';
require_once 'check_session.php';
require_once __DIR__ . '/includes/yield_helpers.php';

// Get date range from request or set defaults
// Default to current year to show more data in summary cards
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01')); // First day of current year
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
// Fetch inputs for distribution filter
$inputs_for_filter = [];
$stmt_inputs = $conn->prepare("SELECT input_id, input_name FROM input_categories ORDER BY input_name");
if ($stmt_inputs) {
    $stmt_inputs->execute();
    $res_inputs = $stmt_inputs->get_result();
    if ($res_inputs) {
        while ($row = $res_inputs->fetch_assoc()) { $inputs_for_filter[] = $row; }
    }
    $stmt_inputs->close();
}
?>
<?php $pageTitle = 'Visual Analytics Dashboard - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>

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
        .gradient-bg {
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

<div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section (Uniform White Card Design) -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
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
                    // Show 0 for all categories when no data
                    if (empty($parts)) {
                        $cat_lower = strtolower($cat_name);
                        $default_unit = (strpos($cat_lower, 'livestock') !== false || strpos($cat_lower, 'poultry') !== false) ? 'heads' : 'kg';
                        $display = '<span class="text-2xl font-bold">0</span> <span class="text-base">' . $default_unit . '</span>';
                    } else {
                        $display = implode('<br>', $parts);
                    }
                ?>
                <a href="yield_monitoring.php?category_filter=<?php echo $cat_id; ?>" class="block bg-gradient-to-r <?php echo $bg; ?> rounded-lg shadow-md p-6 text-white animate-fade-in cursor-pointer hover:shadow-xl hover:-translate-y-1 transition-all duration-200 group" title="View <?php echo htmlspecialchars($cat_name); ?> yield records">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm"><?php echo htmlspecialchars($cat_name); ?></p>
                            <div><?php echo $display; ?></div>
                        </div>
                        <i class="<?php echo $icon; ?> text-3xl text-blue-200 group-hover:scale-110 transition-transform duration-200"></i>
                    </div>
                    <div class="mt-2 text-xs text-white text-opacity-70 opacity-0 group-hover:opacity-100 transition-opacity duration-200">Click to view records &rarr;</div>
                </a>
                <?php $i++; } if (isset($stmt)) mysqli_stmt_close($stmt); ?>
            </div>

            <!-- Chart Filters: Date Range and Barangay Only -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 mb-2">
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
                    <div class="col-md-2 mb-2">
                        <label for="filterStartDate" class="form-label">Start Date</label>
                        <input type="date" id="filterStartDate" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="filterEndDate" class="form-label">End Date</label>
                        <input type="date" id="filterEndDate" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                    </div>
                    <div class="col-md-2 mb-2 d-grid">
                        <button type="button" id="clearAnalyticsFilters" class="btn btn-outline-secondary d-none">
                            <i class="fas fa-undo me-2"></i>Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chart Display with Commodity Filter -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 animate-fade-in" style="min-height: 540px;">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between mb-6 gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <i class="fas fa-chart-area text-agri-green mr-3"></i>
                            <span id="chartTitle">Analytics Chart</span>
                        </h3>
                    </div>
                    <div class="flex flex-col md:flex-row gap-2 items-end">
                        <div class="relative w-full max-w-xs flex-shrink-0" style="min-width:180px;">
                            <input type="text" id="commoditySearch" autocomplete="off" class="border border-gray-300 rounded-md px-2 py-1 pr-8 text-sm focus:ring-agri-green focus:border-agri-green w-full" placeholder="Search commodity...">
                            <button type="button" id="clearCommoditySearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 hidden" aria-label="Clear commodity filter">
                                <i class="fas fa-times"></i>
                            </button>
                            <ul id="commoditySuggestions" class="absolute left-0 right-0 bg-white border border-gray-200 rounded-md shadow z-10 mt-1 hidden max-h-40 overflow-y-auto"></ul>
                        </div>
                        <!-- Download and fullscreen buttons removed -->
                    </div>
                </div>
                <div class="chart-container" style="height: 600px; min-height: 600px; padding-top: 40px;">
                    <canvas id="analyticsChart"></canvas>
                </div>
                <div class="flex justify-center mt-6">
                    <button type="button" id="btn-line" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center mx-2" style="border:2px solid #15803d; background-color:#16a34a; color:#fff;">
                        <i class="fas fa-chart-line mr-2"></i>Line
                    </button>
                    <button type="button" id="btn-bar" class="chart-type-btn px-4 py-2 rounded-md transition-colors flex items-center mx-2">
                        <i class="fas fa-chart-bar mr-2"></i>Bar
                    </button>
                </div>
                <div id="noDataMessage" class="hidden text-center mt-6 text-gray-600">
                    <i class="fas fa-exclamation-circle text-2xl text-gray-400 block mx-auto"></i>
                    <p class="mt-2">No data available for the selected filters and date range.</p>
                </div>
            </div>

            <!-- SECTION 1: FARMER PRODUCTION PERFORMANCE -->
            <div class="row g-4 mb-4">
                <!-- Top Yield Producers & Input Correlation -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Top Yield Producers & Input Correlation</h5>
                            <form class="row g-2 mb-3 align-items-end" id="topProducersFilterForm" autocomplete="off" onsubmit="return false;">
                                <div class="col-12 col-md-4">
                                    <label for="topProducersFrom" class="form-label mb-1">From Date</label>
                                    <input type="date" class="form-control" id="topProducersFrom" name="from" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="topProducersTo" class="form-label mb-1">To Date</label>
                                    <input type="date" class="form-control" id="topProducersTo" name="to" required>
                                </div>
                                <div class="col-12 col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-success w-100" id="generateTopProducersBtn">Generate</button>
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
                <!-- Input Distribution Volume (Monthly) beside Top Producers -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title mb-0">Input Distribution Volume</h5>
                                <div class="d-flex flex-column align-items-end" style="min-width: 260px;">
                                    <!-- Input combobox with autosuggest -->
                                    <div class="mb-1 position-relative w-100">
                                        <input type="text" id="distributionInputSearch" class="form-control form-control-sm" placeholder="All Inputs" autocomplete="off">
                                        <input type="hidden" id="distributionInputId" value="">
                                        <button type="button" id="clearDistributionInput" class="btn btn-link p-0 text-muted" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); text-decoration:none;">&times;</button>
                                        <div id="distributionInputSuggestions" class="list-group position-absolute w-100" style="z-index: 1050; max-height: 220px; overflow-y: auto; display:none;"></div>
                                    </div>
                                    <!-- Barangay combobox with autosuggest -->
                                    <div class="mb-1 position-relative w-100">
                                        <input type="text" id="distributionBarangaySearch" class="form-control form-control-sm" placeholder="All Barangays" autocomplete="off">
                                        <input type="hidden" id="distributionBarangayId" value="">
                                        <button type="button" id="clearDistributionBarangay" class="btn btn-link p-0 text-muted" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); text-decoration:none;">&times;</button>
                                        <div id="distributionBarangaySuggestions" class="list-group position-absolute w-100" style="z-index: 1050; max-height: 220px; overflow-y: auto; display:none;"></div>
                                    </div>
                                    <div class="dropdown modern-dropdown mb-1">
                                        <button class="btn btn-light btn-sm dropdown-toggle px-3 py-1" type="button" id="distributionRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span id="distributionRangeDropdownLabel">Current Month</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="distributionRangeDropdown">
                                            <li><a class="dropdown-item" href="#" data-value="current_month">Current Month</a></li>
                                            <li><a class="dropdown-item" href="#" data-value="last_6_months">Last 6 Months</a></li>
                                            <li><a class="dropdown-item" href="#" data-value="current_year">Current Year</a></li>
                                            <li><a class="dropdown-item" href="#" data-value="custom_range">Custom Range</a></li>
                                        </ul>
                                    </div>
                                    <div id="distributionCustomRange" style="display: none; width: 100%;">
                                        <input type="date" id="distributionFromDate" class="form-control form-control-sm d-inline-block w-auto mb-1">
                                        <input type="date" id="distributionToDate" class="form-control form-control-sm d-inline-block w-auto">
                                    </div>
                                </div>
                            </div>
                            <canvas id="distributionTrendChart" height="220"></canvas>
<script>
// --- Distribution Range Dropdown Logic ---
if (document.getElementById('distributionTrendChart')) {
    const distributionDropdown = document.getElementById('distributionRangeDropdown');
    const distributionDropdownLabel = document.getElementById('distributionRangeDropdownLabel');
    const distributionDropdownItems = document.querySelectorAll('#distributionRangeDropdown ~ .dropdown-menu .dropdown-item');
    const distributionFromInput = document.getElementById('distributionFromDate');
    const distributionToInput = document.getElementById('distributionToDate');
    const distributionInputSearch = document.getElementById('distributionInputSearch');
    const distributionInputId = document.getElementById('distributionInputId');
    const distributionInputSuggestions = document.getElementById('distributionInputSuggestions');
    const distributionBarangaySearch = document.getElementById('distributionBarangaySearch');
    const distributionBarangayId = document.getElementById('distributionBarangayId');
    const distributionBarangaySuggestions = document.getElementById('distributionBarangaySuggestions');
    const allInputs = <?php echo json_encode($inputs_for_filter ?? []); ?>;
    const allBarangays = <?php echo json_encode($barangays ?? []); ?>;
    let distributionSelectedValue = 'current_month';
    distributionDropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            distributionSelectedValue = this.getAttribute('data-value');
            distributionDropdownLabel.textContent = this.textContent;
            handleDistributionRangeChange();
        });
    });
    if (distributionFromInput) distributionFromInput.addEventListener('change', handleDistributionRangeChange);
    if (distributionToInput) distributionToInput.addEventListener('change', handleDistributionRangeChange);

    // --- Autosuggest helpers ---
    function bindCombo(inputEl, hiddenIdEl, suggestionsEl, dataList, labelKey, idKey) {
        let currentIndex = -1; // keyboard highlight index
        let lastItems = [];
        function clearActive() {
            Array.from(suggestionsEl.querySelectorAll('.list-group-item')).forEach(el => el.classList.remove('active'));
        }
        function setActive(index) {
            clearActive();
            const children = suggestionsEl.querySelectorAll('.list-group-item');
            if (index >= 0 && index < children.length) {
                children[index].classList.add('active');
                children[index].scrollIntoView({ block: 'nearest' });
            }
        }
        function filterList(term) {
            const t = term.trim().toLowerCase();
            if (!t) return dataList.slice(0, 10);
            return dataList.filter(it => String(it[labelKey]).toLowerCase().includes(t)).slice(0, 10);
        }
        function render(list) {
            suggestionsEl.innerHTML = '';
            currentIndex = -1;
            lastItems = list || [];
            if (!list.length) { suggestionsEl.style.display = 'none'; return; }
            list.forEach((item, idx) => {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.textContent = item[labelKey];
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    inputEl.value = item[labelKey];
                    hiddenIdEl.value = item[idKey];
                    suggestionsEl.style.display = 'none';
                    handleDistributionRangeChange();
                });
                suggestionsEl.appendChild(a);
            });
            suggestionsEl.style.display = 'block';
        }
        inputEl.addEventListener('input', () => {
            const list = filterList(inputEl.value);
            render(list);
            // Clear selection if user edits text
            if (hiddenIdEl.value) hiddenIdEl.value = '';
        });
        inputEl.addEventListener('focus', () => {
            if (inputEl.value.trim() === '') render(filterList(''));
        });
        inputEl.addEventListener('blur', () => setTimeout(() => suggestionsEl.style.display = 'none', 120));
        // Keyboard navigation (ArrowUp/Down, Enter, Tab, Escape)
        inputEl.addEventListener('keydown', (e) => {
            const visible = suggestionsEl.style.display !== 'none' && suggestionsEl.childElementCount > 0;
            if (e.key === 'Escape') { suggestionsEl.style.display = 'none'; return; }
            if (!visible) return; 
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentIndex = Math.min(currentIndex + 1, suggestionsEl.childElementCount - 1);
                setActive(currentIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentIndex = Math.max(currentIndex - 1, 0);
                setActive(currentIndex);
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                if (currentIndex >= 0 && currentIndex < lastItems.length) {
                    const it = lastItems[currentIndex];
                    inputEl.value = it[labelKey];
                    hiddenIdEl.value = it[idKey];
                    suggestionsEl.style.display = 'none';
                    handleDistributionRangeChange();
                }
            }
        });

        // Clear button handlers if present
        const clearBtnId = inputEl.id === 'distributionInputSearch' ? 'clearDistributionInput' : (inputEl.id === 'distributionBarangaySearch' ? 'clearDistributionBarangay' : null);
        if (clearBtnId) {
            const clearBtn = document.getElementById(clearBtnId);
            const updateClear = () => {
                if (!clearBtn) return;
                if (inputEl.value && inputEl.value.trim() !== '') clearBtn.style.visibility = 'visible';
                else clearBtn.style.visibility = 'hidden';
            };
            if (clearBtn) {
                updateClear();
                inputEl.addEventListener('input', updateClear);
                clearBtn.addEventListener('click', function(){
                    inputEl.value = '';
                    hiddenIdEl.value = '';
                    suggestionsEl.style.display = 'none';
                    updateClear();
                    handleDistributionRangeChange();
                });
            }
        }
    }

    bindCombo(distributionInputSearch, distributionInputId, distributionInputSuggestions, allInputs, 'input_name', 'input_id');
    bindCombo(distributionBarangaySearch, distributionBarangayId, distributionBarangaySuggestions, allBarangays, 'barangay_name', 'barangay_id');
    // Safe invoker to avoid calling before function is defined
    function callInitDistributionWhenReady(range, startDate, endDate) {
        const tryCall = () => {
            if (typeof initDistributionTrendChart === 'function') {
                initDistributionTrendChart(range, startDate, endDate);
            } else {
                setTimeout(tryCall, 50);
            }
        };
        tryCall();
    }

    function handleDistributionRangeChange() {
        const customRangeDiv = document.getElementById('distributionCustomRange');
        let today = new Date();
        let startDate = null, endDate = null;
        if (distributionSelectedValue === 'current_month') {
            startDate = today.toISOString().slice(0,8) + '01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (distributionSelectedValue === 'last_6_months') {
            let past = new Date(today.getFullYear(), today.getMonth() - 5, 1);
            startDate = past.toISOString().slice(0,10);
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (distributionSelectedValue === 'current_year') {
            startDate = today.getFullYear() + '-01-01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (distributionSelectedValue === 'custom_range') {
            customRangeDiv.style.display = 'flex';
            customRangeDiv.style.flexDirection = 'column';
            startDate = distributionFromInput.value;
            endDate = distributionToInput.value;
        }
        if (distributionSelectedValue !== 'custom_range' || (distributionFromInput.value && distributionToInput.value)) {
            callInitDistributionWhenReady('custom', startDate, endDate);
        }
    }
    // Initial load
    handleDistributionRangeChange();
}
</script>
                        </div>
                    </div>
                </div>
            </div>
            

            <!-- SECTION 2: INVENTORY RISK & DISTRIBUTION -->
            <div class="row g-4 mb-4">
                <!-- Inventory Risk: Low Stock & Expiring Items (Full Width) -->
                <div class="col-12">
                    <div class="card text-white bg-danger shadow-sm">
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
            </div>

            <!-- SECTION 3: COMPLIANCE & PROGRAM EFFICIENCY -->
            <div class="row g-4 mb-4">
                <!-- Yield Reporting Compliance Score -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-agri-green text-white d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-clipboard-check me-2"></i>Yield Reporting Compliance</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="dropdown modern-dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle px-3 py-1" type="button" id="complianceRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span id="complianceRangeDropdownLabel">Current Year</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="complianceRangeDropdown">
                                        <li><a class="dropdown-item" href="#" data-value="current_month">Current Month</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="last_6_months">Last 6 Months</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="current_year">Current Year</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="custom_range">Custom Range</a></li>
                                    </ul>
                                </div>
                                <div id="complianceCustomRange" style="display: none;">
                                    <input type="date" id="complianceFromDate" class="form-control form-control-sm d-inline-block w-auto me-1">
                                    <input type="date" id="complianceToDate" class="form-control form-control-sm d-inline-block w-auto">
                                </div>
                            </div>
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <canvas id="complianceBarChart" height="200"></canvas>
                        </div>
                    </div>
<script>
// --- Compliance Range Dropdown Logic ---
if (document.getElementById('complianceBarChart')) {
    // Dropdown logic for compliance card only
    const complianceDropdown = document.getElementById('complianceRangeDropdown');
    const complianceDropdownLabel = document.getElementById('complianceRangeDropdownLabel');
    const complianceDropdownItems = document.querySelectorAll('#complianceRangeDropdown ~ .dropdown-menu .dropdown-item');
    const complianceFromInput = document.getElementById('complianceFromDate');
    const complianceToInput = document.getElementById('complianceToDate');
    let complianceSelectedValue = 'current_year';
    complianceDropdownItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            complianceSelectedValue = this.getAttribute('data-value');
            complianceDropdownLabel.textContent = this.textContent;
            handleComplianceRangeChange();
        });
    });
    if (complianceFromInput) complianceFromInput.addEventListener('change', handleComplianceRangeChange);
    if (complianceToInput) complianceToInput.addEventListener('change', handleComplianceRangeChange);
    // Safe invoker to avoid calling before function is defined
    function callInitComplianceWhenReady(startDate, endDate) {
        const tryCall = () => {
            if (typeof initComplianceBarChart === 'function') {
                initComplianceBarChart(startDate, endDate);
            } else {
                setTimeout(tryCall, 50);
            }
        };
        tryCall();
    }

    function handleComplianceRangeChange() {
        const customRangeDiv = document.getElementById('complianceCustomRange');
        let today = new Date();
        let startDate = null, endDate = null;
        if (complianceSelectedValue === 'current_month') {
            startDate = today.toISOString().slice(0,8) + '01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (complianceSelectedValue === 'last_6_months') {
            let past = new Date(today.getFullYear(), today.getMonth() - 5, 1);
            startDate = past.toISOString().slice(0,10);
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (complianceSelectedValue === 'current_year') {
            startDate = today.getFullYear() + '-01-01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (complianceSelectedValue === 'custom_range') {
            customRangeDiv.style.display = '';
            startDate = complianceFromInput.value;
            endDate = complianceToInput.value;
        }
        if (complianceSelectedValue !== 'custom_range' || (complianceFromInput.value && complianceToInput.value)) {
            callInitComplianceWhenReady(startDate, endDate);
        }
    }
    // Initial load
    handleComplianceRangeChange();
}
</script>
                </div>
                <!-- Commodity Yield Breakdown -->
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-agri-green text-white d-flex align-items-center justify-content-between">
                            <span><i class="fas fa-seedling me-2"></i>Total Yield Breakdown by Commodity</span>
                            <div class="d-flex align-items-center gap-2">
                                <div class="dropdown modern-dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle px-3 py-1" type="button" id="commodityRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span id="commodityRangeDropdownLabel">Current Year</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="commodityRangeDropdown">
                                        <li><a class="dropdown-item" href="#" data-value="current_month">Current Month</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="last_6_months">Last 6 Months</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="current_year">Current Year</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="custom_range">Custom Range</a></li>
                                    </ul>
                                </div>
                                <div id="commodityCustomRange" style="display: none;">
                                    <input type="date" id="commodityFromDate" class="form-control form-control-sm d-inline-block w-auto me-1">
                                    <input type="date" id="commodityToDate" class="form-control form-control-sm d-inline-block w-auto">
                                </div>
                            </div>
    <style>
    .modern-dropdown .dropdown-toggle {
        border-radius: 6px;
        background: #fff;
        color: #222;
        border: 1px solid #d1d5db;
        font-weight: 500;
        transition: box-shadow 0.2s;
    }
    .modern-dropdown .dropdown-toggle:focus, .modern-dropdown .dropdown-toggle:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        border-color: #16a34a;
        color: #16a34a;
    }
    .modern-dropdown .dropdown-menu {
        min-width: 180px;
        border-radius: 8px;
        font-size: 15px;
        padding: 0.25rem 0;
    }
    .modern-dropdown .dropdown-item {
        padding: 8px 18px;
        transition: background 0.15s, color 0.15s;
    }
    .modern-dropdown .dropdown-item.active, .modern-dropdown .dropdown-item:active, .modern-dropdown .dropdown-item:hover {
        background: #16a34a;
        color: #fff;
    }
    </style>
                        </div>
                        <div class="card-body">
                            <canvas id="commodityYieldPie" height="180"></canvas>
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
    // Chart type button highlight logic
    document.addEventListener('DOMContentLoaded', function() {
        const btnLine = document.getElementById('btn-line');
        const btnBar = document.getElementById('btn-bar');
        if (btnLine && btnBar) {
            // Set default active to Line
            btnLine.classList.add('active');
            btnLine.style.backgroundColor = '#16a34a';
            btnLine.style.color = '#fff';
            btnLine.style.border = '2px solid #15803d';
            btnBar.style.backgroundColor = '';
            btnBar.style.color = '';
            btnBar.style.border = '';
            btnLine.addEventListener('click', function() {
                btnLine.classList.add('active');
                btnBar.classList.remove('active');
                btnLine.style.backgroundColor = '#16a34a';
                btnLine.style.color = '#fff';
                btnLine.style.border = '2px solid #15803d';
                btnBar.style.backgroundColor = '';
                btnBar.style.color = '';
                btnBar.style.border = '';
            });
            btnBar.addEventListener('click', function() {
                btnBar.classList.add('active');
                btnLine.classList.remove('active');
                btnBar.style.backgroundColor = '#16a34a';
                btnBar.style.color = '#fff';
                btnBar.style.border = '2px solid #15803d';
                btnLine.style.backgroundColor = '';
                btnLine.style.color = '';
                btnLine.style.border = '';
            });
        }
    });
    </script>

    <?php
    // Prepare all commodities for JS search/autocomplete
    $commodities = [];
    $stmt_com = $conn->prepare("SELECT c.commodity_id, c.commodity_name, c.category_id, cc.category_name FROM commodities c LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id ORDER BY cc.category_name, c.commodity_name");
    if ($stmt_com) {
        $stmt_com->execute();
        $res_com = $stmt_com->get_result();
        if ($res_com) {
            while ($r = $res_com->fetch_assoc()) {
                $commodities[] = $r;
            }
        }
        $stmt_com->close();
    }
    ?>

    <?php
    // Prepare commodities for JS
    $commodities = [];
    $stmt_com = $conn->prepare("SELECT c.commodity_id, c.commodity_name, c.category_id, cc.category_name FROM commodities c LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id ORDER BY cc.category_name, c.commodity_name");
    if ($stmt_com) {
        $stmt_com->execute();
        $res_com = $stmt_com->get_result();
        if ($res_com) {
            while ($r = $res_com->fetch_assoc()) {
                $commodities[] = $r;
            }
        }
        $stmt_com->close();
    }
    ?>
    <script>
    let analyticsChart = null;
    let currentChartType = 'bar';
    let currentCommodity = '';
    const allCommodities = <?php echo json_encode($commodities); ?>;
    const defaultStartDate = '<?php echo date('Y-01-01'); ?>';
    const defaultEndDate = '<?php echo date('Y-m-d'); ?>';

    function fetchAndUpdateAnalyticsChart() {
        let url = 'get_report_data.php?type=yield_by_commodity';
        const barangay = document.getElementById('filterBarangay').value;
        const startDate = document.getElementById('filterStartDate').value;
        const endDate = document.getElementById('filterEndDate').value;
        if (barangay) url += '&barangay=' + encodeURIComponent(barangay);
        if (startDate) url += '&start_date=' + encodeURIComponent(startDate);
        if (endDate) url += '&end_date=' + encodeURIComponent(endDate);
        if (currentCommodity) url += '&commodity=' + encodeURIComponent(currentCommodity);
        fetch(url)
            .then(res => res.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    document.getElementById('noDataMessage').classList.remove('hidden');
                    document.getElementById('analyticsChart').classList.add('hidden');
                    console.error('Invalid JSON from get_report_data.php:', text);
                    return;
                }
                window.lastApiData = data;
                updateAnalyticsChart(data.labels, data.data, data.units, data.commodities);
            });
    }

    async function updateAnalyticsChart(labels, chartData, units, commodities) {
        const ctx = document.getElementById('analyticsChart').getContext('2d');
        if (analyticsChart) analyticsChart.destroy();
        const labelIsCommodity = Array.isArray(commodities)
            && Array.isArray(labels)
            && commodities.length === labels.length
            && commodities.every((c, i) => c === labels[i]);
        let unit = 'kg';
        if (Array.isArray(units) && units.length > 0) {
            unit = units[0];
        }
        let chartLabel = 'Yield Monitoring';
        const isBarChart = currentChartType === 'bar';
        const datalabelFormatter = function(value, context) {
            let idx = context.dataIndex;
            let unitLabel = (Array.isArray(units) && units.length > 0) ? (units[idx] || unit) : unit;
            let comm = (Array.isArray(commodities) && commodities[idx]) ? commodities[idx] : '';
            let baseLabel = value + ' ' + unitLabel;
            if (isBarChart) {
                // If labels are already commodities, don't repeat commodity name
                if (labelIsCommodity) return baseLabel;
                return comm ? baseLabel + '\n' + comm : baseLabel;
            }
            if (comm) {
                return [baseLabel, comm];
            }
            return baseLabel;
        };
        const datalabelOptions = {
            display: true,
            color: function() {
                return isBarChart ? '#ffffff' : '#222';
            },
            anchor: isBarChart ? 'center' : 'end',
            align: isBarChart ? 'center' : 'top',
            clamp: !isBarChart,
            offset: isBarChart ? 0 : 18,
            font: function(context) {
                if (isBarChart) {
                    return { weight: 'bold', size: 12 };
                }
                const hasCommodity = Array.isArray(commodities) && commodities[context.dataIndex];
                return { weight: 'bold', size: hasCommodity ? 13 : 14 };
            },
            formatter: datalabelFormatter,
            lineHeight: isBarChart ? 1.1 : 1.2,
            padding: { top: isBarChart ? 0 : 6, bottom: isBarChart ? 0 : 0 }
        };
        // Use datalabels plugin to show correct unit per data point
        analyticsChart = new Chart(ctx, {
            type: currentChartType,
            data: {
                labels: labels,
                datasets: [{
                    label: chartLabel,
                    data: chartData,
                    backgroundColor: currentChartType === 'line' ? 'rgba(59, 130, 246, 0.1)' : '#10b981',
                    borderColor: '#10b981',
                    borderWidth: 2,
                    fill: currentChartType === 'line',
                    tension: 0.4,
                    datalabels: Object.assign({}, datalabelOptions)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true },
                    datalabels: datalabelOptions,
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                let idx = context[0].dataIndex;
                                let date = context[0].label;
                                let comm = (Array.isArray(commodities) && commodities[idx]) ? commodities[idx] : '';
                                if (comm && comm !== date) {
                                    return comm + ' (' + date + ')';
                                } else {
                                    return date;
                                }
                            },
                            label: function(context) {
                                let value = context.parsed.y !== undefined ? context.parsed.y : context.parsed;
                                let idx = context.dataIndex;
                                let unitLabel = (Array.isArray(units) && units.length > 0) ? (units[idx] || unit) : unit;
                                let comm = (Array.isArray(commodities) && commodities[idx]) ? commodities[idx] : '';
                                if (comm) {
                                    return value + ' ' + unitLabel + ' of ' + comm;
                                } else {
                                    return value + ' ' + unitLabel;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    // Commodity search and autosuggest logic
    document.addEventListener('DOMContentLoaded', function() {
        const commoditySearch = document.getElementById('commoditySearch');
        const suggestionsBox = document.getElementById('commoditySuggestions');
        const clearCommodityBtn = document.getElementById('clearCommoditySearch');
        const clearAllBtn = document.getElementById('clearAnalyticsFilters');

        const btnLine = document.getElementById('btn-line');
        const btnBar = document.getElementById('btn-bar');

        function setChartTypeUI(type) {
            if (!btnLine || !btnBar) return;
            if (type === 'bar') {
                btnBar.classList.add('active');
                btnBar.style.backgroundColor = '#16a34a';
                btnBar.style.color = '#fff';
                btnBar.style.border = '2px solid #15803d';
                btnLine.classList.remove('active');
                btnLine.style.backgroundColor = '';
                btnLine.style.color = '';
                btnLine.style.border = '';
            } else {
                btnLine.classList.add('active');
                btnLine.style.backgroundColor = '#16a34a';
                btnLine.style.color = '#fff';
                btnLine.style.border = '2px solid #15803d';
                btnBar.classList.remove('active');
                btnBar.style.backgroundColor = '';
                btnBar.style.color = '';
                btnBar.style.border = '';
            }
        }

        function syncCommodityClearButton() {
            if (!clearCommodityBtn) return;
            if (commoditySearch && commoditySearch.value && commoditySearch.value.trim() !== '') {
                clearCommodityBtn.classList.remove('hidden');
            } else {
                clearCommodityBtn.classList.add('hidden');
            }
        }

        function updateClearAllVisibility() {
            if (!clearAllBtn) return;
            const barangayVal = document.getElementById('filterBarangay')?.value || '';
            const startVal = document.getElementById('filterStartDate')?.value || '';
            const endVal = document.getElementById('filterEndDate')?.value || '';

            const hasBarangay = barangayVal !== '';
            const hasCommodity = !!(currentCommodity && currentCommodity.trim() !== '');
            const hasDate = (startVal !== defaultStartDate) || (endVal !== defaultEndDate);

            if (hasBarangay || hasCommodity || hasDate) {
                clearAllBtn.classList.remove('d-none');
            } else {
                clearAllBtn.classList.add('d-none');
            }
        }
        function getFilteredCommodities(searchTerm = '') {
            searchTerm = searchTerm.trim().toLowerCase();
            return allCommodities.filter(com => !searchTerm || com.commodity_name.toLowerCase().includes(searchTerm));
        }
        function showSuggestions(list) {
            suggestionsBox.innerHTML = '';
            if (list.length === 0 || !commoditySearch.value.trim()) {
                suggestionsBox.classList.add('hidden');
                return;
            }
            list.forEach(com => {
                const li = document.createElement('li');
                li.textContent = com.commodity_name;
                li.className = 'px-3 py-1 cursor-pointer hover:bg-agri-light';
                li.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    commoditySearch.value = com.commodity_name;
                    suggestionsBox.classList.add('hidden');
                    currentCommodity = com.commodity_name;
                    syncCommodityClearButton();
                    updateClearAllVisibility();
                    fetchAndUpdateAnalyticsChart();
                });
                suggestionsBox.appendChild(li);
            });
            suggestionsBox.classList.remove('hidden');
        }
        commoditySearch.addEventListener('input', function() {
            const filtered = getFilteredCommodities(commoditySearch.value);
            showSuggestions(filtered);
            // Only show/hide the clear button; don't change applied filter until user selects/enters
            syncCommodityClearButton();
        });
        commoditySearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const filtered = getFilteredCommodities(commoditySearch.value);
                if (filtered.length > 0) {
                    currentCommodity = filtered[0].commodity_name;
                    fetchAndUpdateAnalyticsChart();
                } else {
                    currentCommodity = '';
                    fetchAndUpdateAnalyticsChart();
                }
                suggestionsBox.classList.add('hidden');
                syncCommodityClearButton();
                updateClearAllVisibility();
            }
        });
        commoditySearch.addEventListener('blur', function() {
            setTimeout(() => suggestionsBox.classList.add('hidden'), 100);
        });

        document.getElementById('filterBarangay').addEventListener('change', function() {
            updateClearAllVisibility();
            fetchAndUpdateAnalyticsChart();
        });
        document.getElementById('filterStartDate').addEventListener('change', function() {
            updateClearAllVisibility();
            fetchAndUpdateAnalyticsChart();
        });
        document.getElementById('filterEndDate').addEventListener('change', function() {
            updateClearAllVisibility();
            fetchAndUpdateAnalyticsChart();
        });

        // Clear commodity (X button)
        if (clearCommodityBtn) {
            clearCommodityBtn.addEventListener('click', function() {
                if (commoditySearch) commoditySearch.value = '';
                currentCommodity = '';
                suggestionsBox.classList.add('hidden');
                syncCommodityClearButton();
                updateClearAllVisibility();
                fetchAndUpdateAnalyticsChart();
            });
        }

        // Clear all filters (barangay/date/commodity) without refresh
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function() {
                const barangayEl = document.getElementById('filterBarangay');
                const startEl = document.getElementById('filterStartDate');
                const endEl = document.getElementById('filterEndDate');

                if (barangayEl) barangayEl.value = '';
                if (startEl) startEl.value = defaultStartDate;
                if (endEl) endEl.value = defaultEndDate;

                if (commoditySearch) commoditySearch.value = '';
                currentCommodity = '';
                suggestionsBox.classList.add('hidden');
                syncCommodityClearButton();

                // Keep bar chart as default
                currentChartType = 'bar';
                setChartTypeUI('bar');
                updateClearAllVisibility();
                fetchAndUpdateAnalyticsChart();
            });
        }

        // Default UI state: Bar
        setChartTypeUI('bar');
        syncCommodityClearButton();
        updateClearAllVisibility();

        btnLine.addEventListener('click', function() {
            currentChartType = 'line';
            setChartTypeUI('line');
            fetchAndUpdateAnalyticsChart();
        });
        btnBar.addEventListener('click', function() {
            currentChartType = 'bar';
            setChartTypeUI('bar');
            fetchAndUpdateAnalyticsChart();
        });
        // Initial chart load
        fetchAndUpdateAnalyticsChart();
    });
    </script>
    <!-- Deep-dive Analytics JS (new sections) -->
    <script>
    // SECTION 1: Farmer Production Performance
    // Commodity filter removed
    function loadTopProducers() {
        const from = document.getElementById('topProducersFrom').value;
        const to = document.getElementById('topProducersTo').value;
        const tableBody = document.querySelector('#topProducersTable tbody');
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>';
        let url = 'get_top_producers.php?type=top_producers&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
        // Remove any lingering commodity param from the URL if present
        if (window.location.search.includes('commodity=')) {
            const urlObj = new URL(window.location.href);
            urlObj.searchParams.delete('commodity');
            history.replaceState(null, '', urlObj.pathname + urlObj.search);
        }
        fetch(url)
            .then(res => res.json())
            .then(res => {
                if (Array.isArray(res) && res.length) {
                    tableBody.innerHTML = res.map(row =>
                        `<tr><td>${row.farmer_name}</td><td>${row.total_yield}</td><td>${row.inputs_received}</td></tr>`
                    ).join('');
                } else {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data found for selected range.</td></tr>';
                }
            })
            .catch(() => {
                tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>';
            });
    }

    // Commodity filter/autosuggest removed

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

    let distributionTrendChartInstance = null;
    function initDistributionTrendChart(range = 'monthly', startDate = null, endDate = null) {
        const ctx = document.getElementById('distributionTrendChart').getContext('2d');
        let params = { type: 'input_distribution', range: range };
        if (range === 'custom' && startDate && endDate) {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        const inputId = distributionInputId ? distributionInputId.value : '';
        const inputName = distributionInputSearch ? distributionInputSearch.value : '';
        if (inputId) params.input_id = inputId;
        const barangayId = distributionBarangayId ? distributionBarangayId.value : '';
        if (barangayId) params.barangay = barangayId;
        $.getJSON('get_report_data.php', params, function(res) {
            if (distributionTrendChartInstance) distributionTrendChartInstance.destroy();
            distributionTrendChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        label: inputId ? ('Inputs Distributed (' + inputName + ')') : 'Inputs Distributed',
                        data: res.data || [],
                        backgroundColor: '#f59e42'
                    }]
                },
                options: {responsive:true, plugins:{legend:{display:false}}}
            });
        });
    }

    // SECTION 3: Compliance & Program Efficiency
    function initComplianceBarChart(startDate = null, endDate = null) {
        let params = { type: 'compliance_rate' };
        if (startDate && endDate) {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        $.getJSON('get_report_data.php', params, function(res) {
            const ctx = document.getElementById('complianceBarChart').getContext('2d');
            if (window.complianceBarChartInstance) window.complianceBarChartInstance.destroy();
            const labels = res.map(r => r.input_name);
            const data = res.map(r => r.rate);
            window.complianceBarChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Compliance Rate (%)',
                        data: data,
                        backgroundColor: '#16a34a'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: ctx => ctx.parsed.y + '%' } }
                    },
                    scales: {
                        y: { beginAtZero: true, max: 100, title: { display: true, text: 'Compliance (%)' } }
                    }
                }
            });
        });
    }

    function initCommodityYieldPie(startDate = null, endDate = null) {
        const ctx = document.getElementById('commodityYieldPie').getContext('2d');
        let params = { type: 'yield_breakdown' };
        if (startDate && endDate) {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        $.getJSON('get_report_data.php', params, function(res) {
            const labels = res.labels || [];
            const dataPoints = (res.data || []).map(value => {
                if (typeof value === 'number') return value;
                const parsed = parseFloat(value);
                return Number.isFinite(parsed) ? parsed : 0;
            });
            const totalValue = dataPoints.reduce((sum, val) => sum + (Number.isFinite(val) ? val : 0), 0);
            if (window.commodityYieldPieChart) window.commodityYieldPieChart.destroy();
            window.commodityYieldPieChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataPoints,
                        backgroundColor: ['#16a34a','#f59e42','#3b82f6','#e11d48','#fbbf24','#6366f1','#10b981']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        datalabels: {
                            color: function(context) {
                                const bgColors = context.dataset.backgroundColor || [];
                                const baseColor = Array.isArray(bgColors) ? bgColors[context.dataIndex] : bgColors;
                                if (typeof baseColor !== 'string') return '#1f2937';
                                let hex = baseColor.replace('#','');
                                if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
                                const r = parseInt(hex.slice(0,2), 16);
                                const g = parseInt(hex.slice(2,4), 16);
                                const b = parseInt(hex.slice(4,6), 16);
                                if ([r,g,b].some(isNaN)) return '#1f2937';
                                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                                return luminance > 0.55 ? '#1f2937' : '#ffffff';
                            },
                            font: function(context) {
                                const percentage = totalValue ? (dataPoints[context.dataIndex] / totalValue) * 100 : 0;
                                const baseSize = percentage >= 15 ? 14 : 12;
                                return { weight: '600', size: baseSize };
                            },
                            anchor: 'center',
                            align: 'center',
                            offset: 0,
                            clamp: false,
                            padding: 4,
                            formatter: function(value, context) {
                                if (!Number.isFinite(value) || value <= 0) return '';
                                const label = labels[context.dataIndex] || '';
                                if (!totalValue) {
                                    return label ? `${label}` : '';
                                }
                                const percentage = (value / totalValue) * 100;
                                if (percentage < 2) return ''; // avoid clutter on very thin slices
                                const rounded = percentage >= 10 ? Math.round(percentage) : parseFloat(percentage.toFixed(1));
                                if (label) {
                                    return `${label}\n${rounded}%`;
                                }
                                return `${rounded}%`;
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        });
    }

    function handleCommodityRangeChange() {
        const selector = document.getElementById('commodityRangeSelector');
        const customRangeDiv = document.getElementById('commodityCustomRange');
        const fromInput = document.getElementById('commodityFromDate');
        const toInput = document.getElementById('commodityToDate');
        let today = new Date();
        let startDate = null, endDate = null;
        if (selector.value === 'current_month') {
            startDate = today.toISOString().slice(0,8) + '01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (selector.value === 'last_6_months') {
            let past = new Date(today.getFullYear(), today.getMonth() - 5, 1);
            startDate = past.toISOString().slice(0,10);
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (selector.value === 'current_year') {
            startDate = today.getFullYear() + '-01-01';
            endDate = today.toISOString().slice(0,10);
            customRangeDiv.style.display = 'none';
        } else if (selector.value === 'custom_range') {
            customRangeDiv.style.display = '';
            startDate = fromInput.value;
            endDate = toInput.value;
        }
        if (selector.value !== 'custom_range' || (fromInput.value && toInput.value)) {
            initCommodityYieldPie(startDate, endDate);
        }
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
            document.getElementById('generateTopProducersBtn').addEventListener('click', loadTopProducers);
            loadTopProducers();
        }
        if (document.getElementById('historicalYieldChart')) initHistoricalYieldChart();
        loadInventoryRisk();
        if (document.getElementById('distributionTrendChart')) initDistributionTrendChart('monthly');
        // Input Distribution filter buttons
        const filterBtns = [
            {id: 'filterMonthly', range: 'monthly'},
            {id: 'filter3Months', range: '3months'},
            {id: 'filter6Months', range: '6months'},
            {id: 'filterAnnual', range: 'annual'}
        ];
        filterBtns.forEach(btn => {
            const el = document.getElementById(btn.id);
            if (el) {
                el.addEventListener('click', function() {
                    filterBtns.forEach(b => {
                        const e = document.getElementById(b.id);
                        if (e) e.classList.remove('active');
                    });
                    el.classList.add('active');
                    initDistributionTrendChart(btn.range);
                });
            }
        });
    initComplianceBarChart();
        if (document.getElementById('commodityYieldPie')) {
            initCommodityYieldPie();
            // Dropdown logic for commodity card only
            const commodityDropdown = document.getElementById('commodityRangeDropdown');
            const commodityDropdownLabel = document.getElementById('commodityRangeDropdownLabel');
            const commodityDropdownItems = document.querySelectorAll('#commodityRangeDropdown ~ .dropdown-menu .dropdown-item');
            const commodityFromInput = document.getElementById('commodityFromDate');
            const commodityToInput = document.getElementById('commodityToDate');
            let commoditySelectedValue = 'current_year';
            commodityDropdownItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    commoditySelectedValue = this.getAttribute('data-value');
                    commodityDropdownLabel.textContent = this.textContent;
                    handleCommodityRangeChangeLocal();
                });
            });
            if (commodityFromInput) commodityFromInput.addEventListener('change', handleCommodityRangeChangeLocal);
            if (commodityToInput) commodityToInput.addEventListener('change', handleCommodityRangeChangeLocal);
            function handleCommodityRangeChangeLocal() {
                const customRangeDiv = document.getElementById('commodityCustomRange');
                let today = new Date();
                let startDate = null, endDate = null;
                if (commoditySelectedValue === 'current_month') {
                    startDate = today.toISOString().slice(0,8) + '01';
                    endDate = today.toISOString().slice(0,10);
                    customRangeDiv.style.display = 'none';
                } else if (commoditySelectedValue === 'last_6_months') {
                    let past = new Date(today.getFullYear(), today.getMonth() - 5, 1);
                    startDate = past.toISOString().slice(0,10);
                    endDate = today.toISOString().slice(0,10);
                    customRangeDiv.style.display = 'none';
                } else if (commoditySelectedValue === 'current_year') {
                    startDate = today.getFullYear() + '-01-01';
                    endDate = today.toISOString().slice(0,10);
                    customRangeDiv.style.display = 'none';
                } else if (commoditySelectedValue === 'custom_range') {
                    customRangeDiv.style.display = '';
                    startDate = commodityFromInput.value;
                    endDate = commodityToInput.value;
                }
                if (commoditySelectedValue !== 'custom_range' || (commodityFromInput.value && commodityToInput.value)) {
                    initCommodityYieldPie(startDate, endDate);
                }
            }
            // Initial load
            handleCommodityRangeChangeLocal();
        }
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

