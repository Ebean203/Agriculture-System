<?php
require_once 'conn.php';
require_once 'check_session.php';

// AJAX endpoint for barangay list
if (isset($_GET['ajax']) && $_GET['ajax'] === 'barangays') {
    header('Content-Type: application/json');
    $barangays = [];
    // Use prepared statement for AJAX endpoint
    $stmt = $conn->prepare("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $barangays[] = $row['barangay_name'];
        }
        echo json_encode($barangays);
    } else {
        echo json_encode(["error" => "Query failed: " . $conn->error]);
    }
    $stmt->close();
    exit;
}

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
$total_farmers = $total_boats = $total_commodities = $total_inventory = $recent_yields = 0;
$recent_activities = [];

// Get dashboard statistics using procedural MySQL
// Count total farmers
// Count total farmers
$query = "SELECT COUNT(*) as total_farmers FROM farmers WHERE archived = 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $total_farmers = $row['total_farmers'];
}
$stmt->close();

// Count RSBSA registered farmers
// Count RSBSA registered farmers
// Count RSBSA registered farmers
$query = "SELECT COUNT(*) as rsbsa_registered FROM farmers WHERE is_rsbsa = 1 AND archived = 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $rsbsa_registered = $row['rsbsa_registered'];
}
$stmt->close();

// Count NCFRS registered farmers
// Count NCFRS registered farmers
// Count NCFRS registered farmers
$query = "SELECT COUNT(*) as ncfrs_registered FROM farmers WHERE is_ncfrs = 1 AND archived = 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $ncfrs_registered = $row['ncfrs_registered'];
}
$stmt->close();

// Count Fisherfolk registered farmers
// Count Fisherfolk registered farmers
$query = "SELECT COUNT(*) as fisherfolk_registered FROM farmers WHERE is_fisherfolk = 1 AND archived = 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $fisherfolk_registered = $row['fisherfolk_registered'];
}
$stmt->close();

// Count farmers with boats
// Count farmers with boats
$query = "SELECT COUNT(*) as total_boats FROM farmers WHERE is_boat = 1 AND archived = 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $total_boats = $row['total_boats'];
}
$stmt->close();

// Count total commodities
// Count total commodities
$query = "SELECT COUNT(*) as total_commodities FROM commodities";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $total_commodities = $row['total_commodities'];
}
$stmt->close();

// Count total inventory items in stock
// Count total inventory items in stock
$query = "SELECT SUM(quantity_on_hand) as total_inventory FROM mao_inventory WHERE quantity_on_hand > 0";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$total_inventory = 0;
if ($result && ($row = $result->fetch_assoc())) {
    $total_inventory = $row['total_inventory'] ? $row['total_inventory'] : 0;
}
$stmt->close();

// Count recent yield records (last 30 days)
// Count recent yield records (last 30 days)
$query = "SELECT COUNT(*) as recent_yields FROM yield_monitoring WHERE record_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result && ($row = $result->fetch_assoc())) {
    $recent_yields = $row['recent_yields'];
}
$stmt->close();

// Get recent activities from activity logs
// Get recent activities from activity logs
$query = "SELECT al.*, s.first_name, s.last_name, al.timestamp FROM activity_logs al LEFT JOIN mao_staff s ON al.staff_id = s.staff_id ORDER BY al.timestamp DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
$stmt->close();

// Get barangays for filter dropdown
function getBarangays($conn) {
    $query = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    $stmt->close();
    return $data;
}
// Get barangays for filter dropdown
$barangays = getBarangays($conn);

// Get number of yield_monitoring records per barangay
$yield_records_per_barangay = [];
$query = "SELECT b.barangay_name, COUNT(ym.yield_id) AS record_count
          FROM yield_monitoring ym
          JOIN farmers f ON ym.farmer_id = f.farmer_id
          JOIN barangays b ON f.barangay_id = b.barangay_id
          GROUP BY b.barangay_name
          ORDER BY b.barangay_name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $yield_records_per_barangay[] = $row;
    }
}
$stmt->close();
?>
<?php $pageTitle = 'Lagonglong FARMS - Dashboard'; include 'includes/layout_start.php'; ?>
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

        <div class="max-w-7xl mx-auto py-2">
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-xl card-shadow p-6 mb-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="heading-xl mb-2">Welcome to Lagonglong FARMS</h2>
                            <p class="text-base text-green-100 mb-3">Empowering the agriculture office of Lagonglong to better serve its farmers through comprehensive agricultural services and resources</p>
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
            <!-- Dashboard Main Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
                <!-- Farmers -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-user-friends text-agri-green text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">FARMERS</span>
                    </div>
                    <div class="text-2xl font-bold text-agri-green"><?php echo number_format($total_farmers); ?></div>
                </div>
                <!-- RSBSA -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-shield-alt text-blue-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">RSBSA</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($rsbsa_registered); ?></div>
                </div>
                <!-- NCFRS -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-file-alt text-purple-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">NCFRS</span>
                    </div>
                    <div class="text-2xl font-bold text-purple-600"><?php echo number_format($ncfrs_registered); ?></div>
                </div>
                <!-- Weather (wider card) -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center xl:row-span-2 xl:h-[352px] h-[352px] xl:col-span-1 xl:w-full" style="min-width:0;">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-cloud-sun text-gray-500 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">Weather Today <span class="text-xs text-gray-400">Lagonglong</span></span>
                    </div>
                    <div class="text-3xl font-bold text-gray-700">31°C</div>
                    <div class="text-sm text-gray-500 mb-2">Partly Cloudy</div>
                    <div class="flex space-x-2 text-xs text-gray-400">
                        <div>Mon<br>32°/25°</div>
                        <div>Tue<br>30°/24°</div>
                        <div>Wed<br>28°/23°</div>
                        <div>Thu<br>31°/25°</div>
                        <div>Fri<br>33°/26°</div>
                    </div>
                </div>
                <!-- Commodities -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-box-open text-orange-500 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">COMMODITIES</span>
                    </div>
                    <div class="text-2xl font-bold text-orange-500"><?php echo number_format($total_commodities); ?></div>
                </div>
                <!-- Registered Boats -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-ship text-yellow-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">REGISTERED BOATS</span>
                    </div>
                    <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($total_boats); ?></div>
                </div>
                <!-- Inventory -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-cube text-agri-green text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">INVENTORY</span>
                    </div>
                    <div class="text-2xl font-bold text-agri-green"><?php echo number_format($total_inventory); ?></div>
                    <div class="text-xs text-gray-400">items in stock</div>
                </div>
            </div>

            <!-- Yield Monitoring, Quick Actions, Farmers by Program -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                <!-- Yield Monitoring Chart -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-xl card-shadow p-6 h-full">
                        <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                            <i class="fas fa-chart-line text-agri-green mr-2"></i><span id="chartTitle">Yield Monitoring</span>
                        </h3>
                        <canvas id="yieldChart" height="120"></canvas>
                        <script>
                        // Show yearly yield per barangay using a line graph
                        document.addEventListener('DOMContentLoaded', function() {
                            fetch('get_report_data.php?type=yield_per_barangay')
                                .then(res => res.json())
                                .then(data => {
                                    updateYieldChart(data.labels, data.data);
                                });
                        });

                        let yieldChartInstance = null;
                        function updateYieldChart(labels, chartData) {
                            const ctx = document.getElementById('yieldChart').getContext('2d');
                            if (yieldChartInstance) {
                                yieldChartInstance.destroy();
                            }
                            let chartLabel = 'Yearly Yield per Barangay';
                            yieldChartInstance = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: chartLabel,
                                        data: chartData,
                                        backgroundColor: 'rgba(16,185,129,0.15)',
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        pointBackgroundColor: '#10b981',
                                        pointBorderColor: '#10b981',
                                        fill: true,
                                        tension: 0.3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { display: false },
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { stepSize: 1 }
                                        }
                                    }
                                }
                            });
                        }
                        </script>
                    </div>
                </div>
                <!-- Quick Actions -->
                <div>
                    <div class="bg-white rounded-xl card-shadow p-6 h-full">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-bolt text-yellow-500 mr-2"></i>Quick Actions
                        </h3>
                        <div class="space-y-4">
                            <button onclick="openFarmerModal()" class="w-full flex items-center p-4 bg-agri-green text-white rounded-lg hover:bg-green-700 transition-all duration-300">
                                <i class="fas fa-user-plus text-white text-2xl mr-3"></i>
                                <span class="font-medium text-base leading-tight">Add New Farmer</span>
                            </button>
                            <button onclick="navigateTo('mao_inventory.php')" class="w-full flex items-center p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-300">
                                <i class="fas fa-truck text-white text-2xl mr-3"></i>
                                <span class="font-medium text-base leading-tight">Distribute Inputs</span>
                            </button>
                            <button onclick="openYieldModal()" class="w-full flex items-center p-4 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-all duration-300">
                                <i class="fas fa-clipboard-list text-white text-2xl mr-3"></i>
                                <span class="font-medium text-base leading-tight">Record Yield</span>
                            </button>
                            <button onclick="navigateTo('all_activities.php')" class="w-full flex items-center p-4 bg-yellow-400 text-white rounded-lg hover:bg-yellow-500 transition-all duration-300">
                                <i class="fas fa-list-alt text-white text-2xl mr-3"></i>
                                <span class="font-medium text-base leading-tight">All Activities</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities & Farmers by Program -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
                <!-- Recent Activities -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-xl card-shadow p-6 h-full">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-history text-agri-green mr-2"></i>Recent Activities
                        </h3>
                        <ul class="space-y-2">
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <li class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="flex items-center">
                                            <?php
                                            // Determine icon based on activity type
                                            $icon = 'fas fa-info-circle';
                                            $icon_color = 'text-agri-green';
                                            
                                            switch (strtolower($activity['action_type'])) {
                                                case 'farmer':
                                                    $icon = 'fas fa-user-plus';
                                                    $icon_color = 'text-green-600';
                                                    break;
                                                case 'inventory':
                                                    $icon = 'fas fa-boxes';
                                                    $icon_color = 'text-blue-600';
                                                    break;
                                                case 'distribution':
                                                    $icon = 'fas fa-truck';
                                                    $icon_color = 'text-orange-600';
                                                    break;
                                                case 'yield':
                                                    $icon = 'fas fa-seedling';
                                                    $icon_color = 'text-green-500';
                                                    break;
                                                case 'staff':
                                                    $icon = 'fas fa-user-tie';
                                                    $icon_color = 'text-purple-600';
                                                    break;
                                                case 'commodity':
                                                    $icon = 'fas fa-leaf';
                                                    $icon_color = 'text-yellow-600';
                                                    break;
                                                default:
                                                    $icon = 'fas fa-check-circle';
                                                    $icon_color = 'text-agri-green';
                                            }
                                            ?>
                                            <i class="<?php echo $icon . ' ' . $icon_color; ?> mr-2"></i>
                                            <span class="text-sm">
                                                <?php echo htmlspecialchars($activity['action']); ?>
                                                <?php if (!empty($activity['first_name'])): ?>
                                                    <span class="text-gray-600 text-xs">
                                                        by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <?php 
                                            $date = isset($activity['timestamp']) ? $activity['timestamp'] : date('Y-m-d H:i:s');
                                            echo date('M j, Y', strtotime($date)); 
                                            ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="flex items-center justify-center py-8 text-gray-500">
                                    <div class="text-center">
                                        <i class="fas fa-history text-2xl mb-2"></i>
                                        <p class="text-sm">No recent activities found</p>
                                        <p class="text-xs">Activities will appear here as they are logged</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <?php if (!empty($recent_activities)): ?>
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <a href="all_activities.php" class="text-agri-green hover:text-agri-dark font-medium text-sm flex items-center justify-center transition-colors">
                                    <span>View All Activities</span>
                                    <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Farmers by Program Pie Chart -->
                <div>
                    <div class="bg-white rounded-xl card-shadow p-6 h-full flex flex-col items-center justify-center">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-users text-agri-green mr-2"></i>Farmers by Program
                        </h3>
                        <canvas id="farmersPieChart" width="180" height="180"></canvas>
                        <div class="flex justify-center mt-4 space-x-4">
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-600 mr-2"></span>RSBSA <span class="ml-1 font-bold text-blue-600"><?php echo number_format($rsbsa_registered); ?></span></div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-purple-600 mr-2"></span>NCFRS <span class="ml-1 font-bold text-purple-600"><?php echo number_format($ncfrs_registered); ?></span></div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-cyan-600 mr-2"></span>FISH-R <span class="ml-1 font-bold text-cyan-600"><?php echo number_format($fisherfolk_registered); ?></span></div>
                        </div>
                        <div class="w-full mt-6">
                            <h4 class="text-md font-semibold text-gray-700 mb-2 text-center">Yield Records per Barangay</h4>
                            <ul class="text-sm text-gray-600 divide-y divide-gray-100">
                                <?php if (!empty($yield_records_per_barangay)): ?>
                                    <?php foreach ($yield_records_per_barangay as $row): ?>
                                        <li class="flex justify-between py-1 px-2">
                                            <span><?php echo htmlspecialchars($row['barangay_name']); ?></span>
                                            <span class="font-bold text-agri-green"><?php echo number_format($row['record_count']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="py-1 px-2 text-gray-400">No yield records found.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var ctx = document.getElementById('farmersPieChart').getContext('2d');
                            var farmersPieChart = new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: ['RSBSA', 'NCFRS', 'FISH-R'],
                                    datasets: [{
                                        data: [
                                            <?php echo isset($rsbsa_registered) ? $rsbsa_registered : 0; ?>,
                                            <?php echo isset($ncfrs_registered) ? $ncfrs_registered : 0; ?>,
                                            <?php echo isset($fisherfolk_registered) ? $fisherfolk_registered : 0; ?>
                                        ],
                                        backgroundColor: [
                                            '#3B82F6', // RSBSA (blue)
                                            '#8B5CF6', // NCFRS (purple)
                                            '#06B6D4'  // FISH-R (cyan)
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                    </div>
                </div>
            </div>

    <!-- Include the farmer registration modal -->
    <?php include 'farmer_regmodal.php'; ?>
    
    <!-- Include the yield record modal -->
    <?php
    // Provide commodity data for the yield record modal when included on the dashboard
    $commodity_categories = [];
    $stmt_cat = $conn->prepare("SELECT category_id, category_name FROM commodity_categories ORDER BY category_name");
    if ($stmt_cat) {
        $stmt_cat->execute();
        $res_cat = $stmt_cat->get_result();
        if ($res_cat) {
            while ($r = $res_cat->fetch_assoc()) {
                $commodity_categories[] = $r;
            }
        }
        $stmt_cat->close();
    }

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

    include 'yield_record_modal.php';
    ?>

    <script>
        function navigateTo(url) {
            window.location.href = url;
        }

        function openFarmerModal() {
            const modal = new bootstrap.Modal(document.getElementById('farmerRegistrationModal'));
            modal.show();
        }

            // Modal functions (Tailwind logic, same as mao_inventory.php)
            function openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            // Close modal when clicking outside
            document.addEventListener('click', function(event) {
                const modals = ['distributeModal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (event.target === modal) {
                        closeModal(modalId);
                    }
                });
            });

        // Yield Modal Functions
        function openYieldModal() {
            const modal = new bootstrap.Modal(document.getElementById('addVisitModal'));
            modal.show();
            // Load farmers when modal opens
            loadFarmersYield();
        }

        function closeYieldModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addVisitModal'));
            if (modal) modal.hide();
            // Reset form
            const form = document.querySelector('#addVisitModal form');
            if (form) form.reset();
            const farmerIdField = document.getElementById('selected_farmer_id_yield');
            if (farmerIdField) farmerIdField.value = '';
            const farmerSuggestions = document.getElementById('farmer_suggestions_yield');
            if (farmerSuggestions) farmerSuggestions.classList.add('hidden');
            const farmerNameField = document.getElementById('farmer_name_yield');
            if (farmerNameField) farmerNameField.value = '';
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

            // Populate commodities for the selected farmer so the modal matches yield_monitoring behavior
            if (farmerId) {
                populateModalCommodities(farmerId);
            }
        }

        // Populate commodity <select> inside the yield modal for a given farmer
        function populateModalCommodities(farmerId) {
            const commoditySelect = document.getElementById('commodity_id');
            if (!commoditySelect) return;

            // Loading placeholder
            commoditySelect.innerHTML = '<option value="">Loading...</option>';

            fetch('get_farmer_commodities.php?farmer_id=' + encodeURIComponent(farmerId))
                .then(response => response.text())
                .then(text => {
                    let data = { success: false, commodities: [] };
                    try { data = JSON.parse(text); } catch (e) { console.error('Invalid JSON from get_farmer_commodities.php', text); }
                    // Build list of farmer commodities and the set of categories used by this farmer
                    const farmerCommodities = [];
                    const categories = {}; // category_id -> category_name

                    if (data && data.success && Array.isArray(data.commodities)) {
                        data.commodities.forEach(item => {
                            if (!item) return;
                            const id = item.commodity_id ?? item.id ?? '';
                            const name = item.commodity_name ?? item.name ?? '';
                            const catId = item.category_id ?? item.cat_id ?? '';
                            const catName = item.category_name ?? item.cat_name ?? '';
                            if (typeof name !== 'string') return;
                            if (name.indexOf('\\') !== -1) return; // skip malformed values
                            farmerCommodities.push({ id: id, name: name, category_id: catId, category_name: catName });
                            if (catId) categories[catId] = catName || catId;
                        });
                    }

                    // Populate category filter with only the categories this farmer has
                    const categoryFilter = document.getElementById('commodity_category_filter');
                    if (categoryFilter) {
                        // Clear existing options and add a default 'All Categories'
                        categoryFilter.innerHTML = '<option value="">All Categories</option>';
                        Object.keys(categories).forEach(function(cid) {
                            const opt = document.createElement('option');
                            opt.value = cid;
                            opt.textContent = categories[cid];
                            categoryFilter.appendChild(opt);
                        });
                        // Attach farmerCommodities to the categoryFilter for later filtering
                        categoryFilter.farmerCommodities = farmerCommodities;
                    }

                    // If there are categories, select the first and show only commodities in that category
                    if (categoryFilter && categoryFilter.options.length > 1) {
                        // choose first non-empty option
                        for (let i = 0; i < categoryFilter.options.length; i++) {
                            if (categoryFilter.options[i].value !== '') {
                                categoryFilter.selectedIndex = i;
                                break;
                            }
                        }
                        // Filter commodities by selected category
                        const selCat = categoryFilter.value;
                        let html = '<option value="">Select Commodity</option>';
                        farmerCommodities.forEach(fc => {
                            if (!selCat || String(fc.category_id) === String(selCat)) {
                                html += `<option value="${escapeHtml(fc.id)}">${escapeHtml(fc.name)}</option>`;
                            }
                        });
                        commoditySelect.innerHTML = html;
                    } else {
                        // No categories found for farmer — show all farmer commodities
                        let html = '<option value="">Select Commodity</option>';
                        farmerCommodities.forEach(fc => {
                            html += `<option value="${escapeHtml(fc.id)}">${escapeHtml(fc.name)}</option>`;
                        });
                        commoditySelect.innerHTML = html;
                    }
                })
                .catch(err => {
                    console.error('Error fetching farmer commodities:', err);
                    commoditySelect.innerHTML = '<option value="">Select Commodity</option>';
                });
        }

        // Minimal HTML-escape helper
        function escapeHtml(str) {
            return String(str === undefined || str === null ? '' : str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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

            // Wire up quick-action modal farmer name field to show/hide suggestions
            const quickFarmerName = document.getElementById('farmer_name_yield');
            if (quickFarmerName) {
                quickFarmerName.addEventListener('input', function() {
                    if (this.value && this.value.length > 0) showSuggestionsYield();
                });
                quickFarmerName.addEventListener('focus', function() {
                    if (this.value && this.value.length > 0) showSuggestionsYield();
                });
                quickFarmerName.addEventListener('blur', function() {
                    hideSuggestionsYield();
                });
            }
        });
    </script>
<script src="assets/js/chart.min.js"></script>
<?php include 'includes/notification_complete.php'; ?>