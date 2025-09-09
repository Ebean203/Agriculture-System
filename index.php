<?php
require_once 'conn.php';
require_once 'check_session.php';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] == 'register_farmer') {
        // Redirect to farmers.php to handle registration using the new system
        header("Location: farmers.php");
        exit();
    }
}

// Get session messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Initialize default values
$total_farmers = $total_boats = $total_commodities = $recent_yields = 0;
$recent_activities = [];

// Get dashboard statistics using procedural MySQL
// Count total farmers
$query = "SELECT COUNT(*) as total_farmers FROM farmers WHERE archived = 0";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_farmers = $row['total_farmers'];
}

// Count RSBSA registered farmers
$query = "SELECT COUNT(*) as rsbsa_registered FROM farmers WHERE is_rsbsa = 1 AND archived = 0";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $rsbsa_registered = $row['rsbsa_registered'];
}

// Count NCFRS registered farmers
$query = "SELECT COUNT(*) as ncfrs_registered FROM farmers WHERE is_ncfrs = 1 AND archived = 0";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $ncfrs_registered = $row['ncfrs_registered'];
}

// Count Fisherfolk registered farmers
$query = "SELECT COUNT(*) as fisherfolk_registered FROM farmers WHERE is_fisherfolk = 1 AND archived = 0";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $fisherfolk_registered = $row['fisherfolk_registered'];
}

// Count farmers with boats
$query = "SELECT COUNT(*) as total_boats FROM farmers WHERE is_boat = 1 AND archived = 0";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_boats = $row['total_boats'];
}

// Count total commodities
$query = "SELECT COUNT(*) as total_commodities FROM commodities";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_commodities = $row['total_commodities'];
}

// Count recent yield records (last 30 days)
$query = "SELECT COUNT(*) as recent_yields FROM yield_monitoring WHERE record_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $recent_yields = $row['recent_yields'];
}

// Get recent activities (last 5 yield records)
$query = "
    SELECT ym.*, f.first_name, f.last_name, c.commodity_name, s.first_name as staff_first_name, s.last_name as staff_last_name
    FROM yield_monitoring ym
    JOIN farmers f ON ym.farmer_id = f.farmer_id
    JOIN commodities c ON ym.commodity_id = c.commodity_id
    JOIN mao_staff s ON ym.recorded_by_staff_id = s.staff_id
    ORDER BY ym.record_date DESC
    LIMIT 5
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagonglong FARMS - Dashboard</title>
    <?php include 'includes/assets.php'; ?>
    
    
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <style>
        /* Remove underlines from links and set text color to black */
        .grid a {
            text-decoration: none !important;
            color: #111827 !important;
        }
        .grid a:hover {
            text-decoration: none !important;
        }

        /* Dropdown menu styles */
        #dropdownMenu {
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

        /* Ensure dropdown is above other content */
        .relative {
            z-index: 40;
        }

        /* Ensure navigation has higher z-index than content */
        nav {
            position: relative;
            z-index: 1000;
        }

        /* User menu container needs highest z-index */
        #userMenu {
            position: relative;
            z-index: 1001;
        }
    </style>
    <script>
        // Function to handle dropdown toggle
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            
            if (!userMenu.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate');
            }
        });
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-agri-green shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-seedling text-white text-2xl mr-3"></i>
                    <h1 class="text-white text-xl font-bold">Lagonglong FARMS</h1>
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
                    
                    <div class="flex items-center text-white relative" id="userMenu">
                        <button class="flex items-center focus:outline-none" onclick="toggleDropdown()" type="button">
                            <i class="fas fa-user-circle text-lg mr-2"></i>
                            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <i class="fas fa-chevron-down ml-2 text-xs transition-transform duration-200" id="dropdownArrow"></i>
                        </button>
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden" id="dropdownMenu">
                            <div class="px-4 py-2 text-sm text-gray-700 border-b">
                                <div class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                            </div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-lg shadow-md p-6 mb-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold mb-2">Welcome to Lagonglong FARMS</h2>
                            <p class="text-lg text-green-100 mb-3">Empowering farmers through modern technology and sustainable practices</p>
                            <div class="flex items-center text-green-200">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span><?php echo date('l, F j, Y'); ?></span>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <i class="fas fa-seedling text-6xl text-white opacity-20"></i>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Farmers -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Farmers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_farmers); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 group-hover:bg-blue-200 transition-colors duration-300">
                        <i class="fas fa-users text-xl text-blue-600"></i>
                    </div>
                </div>
            </div>

            <!-- RSBSA Registered -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">RSBSA Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($rsbsa_registered); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 transition-colors duration-300">
                        <i class="fas fa-certificate text-xl text-green-600"></i>
                    </div>
                </div>
            </div>

            <!-- NCFRS Registered -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">NCFRS Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($ncfrs_registered); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-indigo-100 transition-colors duration-300">
                        <i class="fas fa-id-card text-xl text-indigo-600"></i>
                    </div>
                </div>
            </div>

            <!-- FishR Registered -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">FishR Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($fisherfolk_registered); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-teal-100 transition-colors duration-300">
                        <i class="fas fa-fish text-xl text-teal-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Boats Registered -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Boats Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_boats); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-cyan-100 transition-colors duration-300">
                        <i class="fas fa-ship text-xl text-cyan-600"></i>
                    </div>
                </div>
            </div>

            <!-- Commodities -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Commodities</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_commodities); ?></p>
                    </div>
                    <div class="p-3 rounded-full bg-yellow-100 transition-colors duration-300">
                        <i class="fas fa-wheat-awn text-xl text-yellow-600"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Yields -->
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Recent Yields</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($recent_yields); ?></p>
                        <p class="text-xs text-gray-500">Last 30 days</p>
                    </div>
                    <div class="p-3 rounded-full bg-purple-100 transition-colors duration-300">
                        <i class="fas fa-chart-line text-xl text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions and Recent Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Quick Actions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 h-full">
                    <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center">
                        <i class="fas fa-bolt text-agri-green mr-3"></i>Quick Actions
                    </h3>
                    <div class="space-y-4">
                        <button onclick="openFarmerModal()" class="w-full flex items-center p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg hover:from-blue-100 hover:to-blue-200 transition-all duration-300 group">
                            <div class="p-3 bg-blue-500 rounded-lg mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-user-plus text-white"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-blue-900">Add New Farmer</div>
                                <div class="text-sm text-blue-600">Register a new farmer</div>
                            </div>
                        </button>
                        
                        <button onclick="navigateTo('mao_inventory.php')" class="w-full flex items-center p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg hover:from-green-100 hover:to-green-200 transition-all duration-300 group">
                            <div class="p-3 bg-green-500 rounded-lg mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-boxes text-white"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-green-900">Distribute Inputs</div>
                                <div class="text-sm text-green-600">Manage agricultural inputs</div>
                            </div>
                        </button>
                        
                        <button onclick="openYieldModal()" class="w-full flex items-center p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all duration-300 group">
                            <div class="p-3 bg-purple-500 rounded-lg mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-chart-bar text-white"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-purple-900">Record Yield</div>
                                <div class="text-sm text-purple-600">Track harvest data</div>
                            </div>
                        </button>
                        
                        <button onclick="navigateTo('all_activities.php')" class="w-full flex items-center p-4 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg hover:from-yellow-100 hover:to-yellow-200 transition-all duration-300 group">
                            <div class="p-3 bg-yellow-500 rounded-lg mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-activity text-white"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold text-yellow-900">View Activities</div>
                                <div class="text-sm text-yellow-600">All system activities</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Modules -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 h-full">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-th-large text-agri-green mr-2"></i>System Modules
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-2 h-full pb-4">
                        <a href="farmers.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-users text-blue-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Farmers Management</span>
                        </a>
                        
                        <a href="rsbsa_records.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-certificate text-green-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">RSBSA Records</span>
                        </a>
                        
                        <a href="mao_inventory.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-warehouse text-yellow-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Manage Inventory</span>
                        </a>
                        
                        <a href="input_distribution_records.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-share-square text-orange-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Distribution Records</span>
                        </a>
                        
                        <a href="yield_monitoring.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-chart-bar text-purple-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Yield Monitoring</span>
                        </a>
                        
                        <a href="staff.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-user-tie text-indigo-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">MAO Staff</span>
                        </a>
                        
                        <a href="reports.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-file-alt text-red-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Reports</span>
                        </a>
                        
                        <a href="analytics_dashboard.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-chart-line text-pink-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Visual Analytics</span>
                        </a>
                        
                        <a href="mao_activities.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-calendar-check text-emerald-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">MAO Activities</span>
                        </a>
                        
                        <a href="ncfrs_records.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-fish text-teal-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">NCFRS Records</span>
                        </a>
                        
                        <a href="boat_records.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-ship text-blue-500 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">Boat Records</span>
                        </a>
                        
                        <a href="fishr_records.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all h-20 min-h-[80px]">
                            <i class="fas fa-water text-cyan-600 text-2xl mr-3 flex-shrink-0"></i>
                            <span class="font-medium text-base leading-tight">FISHR Records</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="mt-12 bg-white shadow-md p-6 w-full">
        <div class="text-center text-gray-600">
            <div class="flex items-center justify-center mb-2">
                <i class="fas fa-seedling text-agri-green mr-2"></i>
                <span class="font-semibold">Lagonglong FARMS</span>
            </div>
            <p class="text-sm">&copy; <?php echo date('Y'); ?> All rights reserved.</p>
        </div>
    </footer>

    <!-- Include the farmer registration modal -->
    <?php include 'farmer_regmodal.php'; ?>
    
    <!-- Include the yield record modal -->
    <?php include 'yield_record_modal.php'; ?>

    <script>
        function navigateTo(url) {
            window.location.href = url;
        }

        function openFarmerModal() {
            const modal = new bootstrap.Modal(document.getElementById('farmerRegistrationModal'));
            modal.show();
        }

        // Yield Modal Functions
        function openYieldModal() {
            document.getElementById('addVisitModal').classList.remove('hidden');
            document.getElementById('addVisitModal').classList.add('flex');
            // Load farmers when modal opens
            loadFarmersYield();
        }

        function closeYieldModal() {
            document.getElementById('addVisitModal').classList.add('hidden');
            document.getElementById('addVisitModal').classList.remove('flex');
            // Reset form
            document.querySelector('#addVisitModal form').reset();
            document.getElementById('selected_farmer_id_yield').value = '';
            document.getElementById('farmer_suggestions_yield').classList.add('hidden');
        }

        // Farmer auto-suggestion functionality for yield modal
        let farmersYield = [];

        function loadFarmersYield() {
            fetch('get_farmers.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Ensure data is an array, even if empty
                    farmersYield = Array.isArray(data) ? data : [];
                })
                .catch(error => {
                    console.error('Error loading farmers:', error);
                    farmersYield = []; // Set to empty array on error
                });
        }

        function searchFarmersYield(query) {
            const suggestions = document.getElementById('farmer_suggestions_yield');
            const selectedFarmerField = document.getElementById('selected_farmer_id_yield');
            
            if (!suggestions) return; // Exit if element doesn't exist
            
            if (!query || query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                if (selectedFarmerField) selectedFarmerField.value = '';
                return;
            }

            // Ensure farmers array exists and is not empty
            if (!Array.isArray(farmersYield) || farmersYield.length === 0) {
                suggestions.innerHTML = '<div class="px-3 py-2 text-orange-500">No farmers registered yet. Please register farmers first.</div>';
                suggestions.classList.remove('hidden');
                return;
            }

            const filteredFarmers = farmersYield.filter(farmer => {
                if (!farmer || !farmer.first_name || !farmer.last_name) return false;
                const fullName = `${farmer.first_name} ${farmer.last_name}`.toLowerCase();
                return fullName.includes(query.toLowerCase());
            });

            if (filteredFarmers.length > 0) {
                let html = '';
                filteredFarmers.forEach((farmer, index) => {
                    const fullName = `${farmer.first_name || ''} ${farmer.last_name || ''}`.trim();
                    const farmerId = farmer.farmer_id || '';
                    html += `<div class="suggestion-item px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100" 
                                  onclick="selectFarmerYield('${farmerId}', '${fullName}')" 
                                  data-index="${index}">
                                <div class="font-medium">${fullName}</div>
                                <div class="text-sm text-gray-600">ID: ${farmerId}</div>
                             </div>`;
                });
                suggestions.innerHTML = html;
                suggestions.classList.remove('hidden');
            } else {
                suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No farmers found matching your search</div>';
                suggestions.classList.remove('hidden');
            }
        }

        function selectFarmerYield(farmerId, farmerName) {
            const farmerNameField = document.getElementById('farmer_name_yield');
            const selectedFarmerField = document.getElementById('selected_farmer_id_yield');
            const suggestions = document.getElementById('farmer_suggestions_yield');
            
            if (farmerNameField) farmerNameField.value = farmerName || '';
            if (selectedFarmerField) selectedFarmerField.value = farmerId || '';
            if (suggestions) suggestions.classList.add('hidden');
        }

        function showSuggestionsYield() {
            const query = document.getElementById('farmer_name_yield').value;
            if (query.length > 0) {
                searchFarmersYield(query);
            }
        }

        function hideSuggestionsYield() {
            // Delay hiding to allow click events on suggestions
            setTimeout(() => {
                const suggestions = document.getElementById('farmer_suggestions_yield');
                if (suggestions) suggestions.classList.add('hidden');
            }, 200);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('addVisitModal');
            if (event.target === modal) {
                closeYieldModal();
            }
        });

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics on load
            const stats = document.querySelectorAll('.text-2xl.font-bold');
            stats.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                let currentValue = 0;
                const increment = finalValue / 30;
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(currentValue).toLocaleString();
                }, 50);
            });

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
    
    <!-- Include Notification System -->
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>