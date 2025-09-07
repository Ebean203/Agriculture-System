<?php
require_once 'conn.php';
require_once 'check_session.php';

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : '';
$barangay_filter = isset($_GET['barangay_filter']) ? $_GET['barangay_filter'] : '';

// Get barangays for filter dropdown
function getBarangays($conn) {
    $query = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get farmer registrations over time
function getFarmerRegistrations($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';
    $query = "
        SELECT DATE(registration_date) as date, COUNT(*) as count
        FROM farmers f
        WHERE DATE(registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
        GROUP BY DATE(registration_date)
        ORDER BY date
    ";
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get yield monitoring data
function getYieldData($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';
    $query = "
        SELECT DATE(ym.record_date) as date, SUM(ym.yield_amount) as total_yield
        FROM yield_monitoring ym
        JOIN land_parcels lp ON ym.parcel_id = lp.parcel_id
        JOIN farmers f ON lp.farmer_id = f.farmer_id
        WHERE DATE(ym.record_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
        GROUP BY DATE(ym.record_date)
        ORDER BY date
    ";
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get commodity distribution
function getCommodityDistribution($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';
    $query = "
        SELECT c.commodity_name, COUNT(f.farmer_id) as farmer_count
        FROM farmers f
        JOIN commodities c ON f.commodity_id = c.commodity_id
        WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
        GROUP BY c.commodity_name
        ORDER BY farmer_count DESC
    ";
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get registration status comparison
function getRegistrationStatus($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';
    $total_farmers = 0;
    $rsbsa_count = 0;
    $ncfrs_count = 0;
    $fisherfolk_count = 0;
    
    // Get total farmers in date range
    $query = "SELECT COUNT(*) as count FROM farmers f WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $total_farmers = $row['count'];
    }
    
    // Get RSBSA registered farmers
    $query = "
        SELECT COUNT(*) as count 
        FROM farmers f 
        JOIN rsbsa_registered_farmers r ON f.farmer_id = r.farmer_id
        WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $rsbsa_count = $row['count'];
    }
    
    // Get NCFRS registered farmers
    $query = "
        SELECT COUNT(*) as count 
        FROM farmers f 
        JOIN ncfrs_registered_farmers n ON f.farmer_id = n.farmer_id
        WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $ncfrs_count = $row['count'];
    }
    
    // Get Fisherfolk registered farmers (through boats, handle empty table gracefully)
    $fisherfolk_count = 0;
    $query = "
        SELECT COUNT(DISTINCT f.farmer_id) as count 
        FROM farmers f 
        JOIN boats b ON f.farmer_id = b.farmer_id
        JOIN fisherfolk_registered_farmers ff ON b.boat_id = ff.vessel_id
        WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
    ";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $fisherfolk_count = $row['count'] ?? 0;
    }
    
    return [
        ['status' => 'RSBSA Registered', 'count' => $rsbsa_count],
        ['status' => 'NCFRS Registered', 'count' => $ncfrs_count],
        ['status' => 'Fisherfolk Registered', 'count' => $fisherfolk_count],
        ['status' => 'Not Registered', 'count' => $total_farmers - $rsbsa_count - $ncfrs_count - $fisherfolk_count]
    ];
}

// Function to get barangay distribution
function getBarangayDistribution($conn, $start_date, $end_date, $barangay_filter = '') {
    $barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';
    $query = "
        SELECT b.barangay_name, COUNT(f.farmer_id) as farmer_count
        FROM farmers f
        JOIN barangays b ON f.barangay_id = b.barangay_id
        WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
        GROUP BY b.barangay_name
        ORDER BY farmer_count DESC
        LIMIT 10
    ";
    $result = mysqli_query($conn, $query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
    }
    return $data;
}

// Get summary statistics
$total_farmers = 0;
$total_yield = 0;
$total_boats = 0;
$total_commodities = 0;
$barangay_condition = $barangay_filter ? "AND f.barangay_id = '$barangay_filter'" : '';

$query = "SELECT COUNT(*) as count FROM farmers f WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_farmers = $row['count'];
}

$query = "
    SELECT SUM(ym.yield_amount) as total 
    FROM yield_monitoring ym
    JOIN land_parcels lp ON ym.parcel_id = lp.parcel_id
    JOIN farmers f ON lp.farmer_id = f.farmer_id
    WHERE DATE(ym.record_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition
";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_yield = $row['total'] ?? 0;
}

$query = "SELECT COUNT(*) as count FROM boats b JOIN farmers f ON b.farmer_id = f.farmer_id WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_boats = $row['count'];
}

$query = "SELECT COUNT(DISTINCT c.commodity_id) as count FROM commodities c JOIN farmers f ON c.commodity_id = f.commodity_id WHERE DATE(f.registration_date) BETWEEN '$start_date' AND '$end_date' $barangay_condition";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_commodities = $row['count'];
}

// Get barangays for dropdown
$barangays = getBarangays($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Analytics Dashboard - Lagonglong FARMS</title>
    <?php include 'includes/assets.php'; ?>
    
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    
    <style>
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
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-agri-green shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-seedling text-white text-2xl mr-3"></i>
                        <h1 class="text-white text-xl font-bold">Lagonglong FARMS</h1>
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

    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="gradient-bg rounded-lg shadow-xl p-6 mb-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold mb-2 flex items-center">
                                <i class="fas fa-chart-line mr-3"></i>Visual Analytics Dashboard
                            </h2>
                            <p class="text-lg opacity-90 mb-3">Interactive data visualization and insights</p>
                            <div class="flex items-center opacity-80">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Data Range: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></span>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <i class="fas fa-chart-pie text-6xl opacity-20"></i>
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
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg shadow-md p-6 text-white animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Boats Registered</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_boats); ?></p>
                        </div>
                        <i class="fas fa-ship text-3xl text-purple-200"></i>
                    </div>
                </div>
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
                <form method="GET" action="" class="space-y-4">
                    <!-- Report Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Choose a report type...</label>
                        <select name="report_type" id="reportType" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent" onchange="updateChartOptions()">
                            <option value="">Select Report Type...</option>
                            <option value="farmer_registrations" <?php echo $report_type === 'farmer_registrations' ? 'selected' : ''; ?>>👥 Farmer Registrations Over Time</option>
                            <option value="yield_monitoring" <?php echo $report_type === 'yield_monitoring' ? 'selected' : ''; ?>>🌾 Yield Monitoring Report</option>
                            <option value="commodity_distribution" <?php echo $report_type === 'commodity_distribution' ? 'selected' : ''; ?>>🌽 Commodity Production Report</option>
                            <option value="barangay_analytics" <?php echo $report_type === 'barangay_analytics' ? 'selected' : ''; ?>>🏘️ Barangay Analytics Report</option>
                            <option value="registration_status" <?php echo $report_type === 'registration_status' ? 'selected' : ''; ?>>📋 Registration Analytics Report</option>
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
        // Chart instance
        let analyticsChart = null;

        // Chart data from PHP - load data if report_type is selected
        <?php if($report_type): ?>
        const chartData = {
            <?php
            // Map report_type to the appropriate function
            switch($report_type) {
                case 'farmer_registrations':
                    $data = getFarmerRegistrations($conn, $start_date, $end_date, $barangay_filter);
                    echo "labels: " . json_encode(array_column($data, 'date')) . ",";
                    echo "data: " . json_encode(array_column($data, 'count')) . ",";
                    echo "reportType: 'farmer_registrations',";
                    echo "label: 'Farmers Registered'";
                    break;
                    
                case 'yield_monitoring':
                    $data = getYieldData($conn, $start_date, $end_date, $barangay_filter);
                    echo "labels: " . json_encode(array_column($data, 'date')) . ",";
                    echo "data: " . json_encode(array_column($data, 'total_yield')) . ",";
                    echo "reportType: 'yield_monitoring',";
                    echo "label: 'Total Yield (kg)'";
                    break;
                    
                case 'commodity_distribution':
                    $data = getCommodityDistribution($conn, $start_date, $end_date, $barangay_filter);
                    echo "labels: " . json_encode(array_column($data, 'commodity_name')) . ",";
                    echo "data: " . json_encode(array_column($data, 'farmer_count')) . ",";
                    echo "reportType: 'commodity_distribution',";
                    echo "label: 'Farmers per Commodity'";
                    break;
                    
                case 'barangay_analytics':
                    $data = getBarangayDistribution($conn, $start_date, $end_date, $barangay_filter);
                    echo "labels: " . json_encode(array_column($data, 'barangay_name')) . ",";
                    echo "data: " . json_encode(array_column($data, 'farmer_count')) . ",";
                    echo "reportType: 'barangay_analytics',";
                    echo "label: 'Farmers per Barangay'";
                    break;
                    
                case 'registration_status':
                    $data = getRegistrationStatus($conn, $start_date, $end_date, $barangay_filter);
                    echo "labels: " . json_encode(array_column($data, 'status')) . ",";
                    echo "data: " . json_encode(array_column($data, 'count')) . ",";
                    echo "reportType: 'registration_status',";
                    echo "label: 'Registration Status'";
                    break;
            }
            ?>
        };
        
        // Current chart type (default to line)
        let currentChartType = 'line';
        <?php else: ?>
        const chartData = null;
        let currentChartType = 'line';
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
            if (!chartData) {
                return; // No data to display
            }
            
            const ctx = document.getElementById('analyticsChart').getContext('2d');
            
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
                        }
                    },
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

            analyticsChart = new Chart(ctx, config);
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
            if (chartData) {
                // Set default active button
                switchChartType('line');
            }
            
            // Add animation to cards
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Download chart function
        function downloadChart() {
            if (analyticsChart) {
                const link = document.createElement('a');
                link.download = 'analytics-chart.png';
                link.href = analyticsChart.toBase64Image();
                link.click();
            }
        }

        // Toggle fullscreen function
        function toggleFullscreen() {
            const chartContainer = document.querySelector('.chart-container');
            if (chartContainer.requestFullscreen) {
                chartContainer.requestFullscreen();
            }
        }

        // Quick date range buttons
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - days);
            
            document.querySelector('input[name="start_date"]').value = startDate.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = endDate.toISOString().split('T')[0];
        }
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
</body>
</html>
