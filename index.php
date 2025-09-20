<?php
require_once 'conn.php';
require_once 'check_session.php';

// AJAX endpoint for barangay list
if (isset($_GET['ajax']) && $_GET['ajax'] === 'barangays') {
    header('Content-Type: application/json');
    $barangays = [];
    $result = $conn->query("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $barangays[] = $row['barangay_name'];
        }
        echo json_encode($barangays);
    } else {
        echo json_encode(["error" => "Query failed: " . $conn->error]);
    }
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
$barangays = getBarangays($conn);
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
                    <div class="text-2xl font-bold text-agri-green">82</div>
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
                        <div class="flex flex-col md:flex-row md:items-end md:gap-6 mb-4">
                            <div class="flex flex-col w-full md:flex-row md:items-end md:gap-6 mb-4">
                                <!-- Filters and chart type buttons removed to match dashboard card style -->
                        <div class="flex flex-row flex-wrap gap-4 items-end w-full mb-4">
                            <div class="flex flex-row items-end gap-2 w-full">
                                <div id="monthPickerDiv" style="width: 120px;">
                                    <label for="monthPicker" class="block text-xs font-medium text-gray-600 mb-1">Month</label>
                                    <input type="month" id="monthPicker" class="border border-gray-300 rounded px-1 py-1 text-sm h-8 w-full" value="<?php echo date('Y-m'); ?>">
                                </div>
                                <div style="width: 110px;">
                                    <label for="barangayFilter" class="block text-xs font-medium text-gray-600 mb-1">Barangay</label>
                                    <select id="barangayFilter" class="border border-gray-300 rounded px-1 py-1 text-sm h-8 w-full">
                                        <option value="">All</option>
                                        <?php foreach ($barangays as $b): ?>
                                            <option value="<?php echo htmlspecialchars($b['barangay_name']); ?>"><?php echo htmlspecialchars($b['barangay_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex bg-gray-100 rounded-lg p-1 space-x-1 ml-2">
                                    <button onclick="switchChartType('line')" id="btn-line" type="button" class="chart-type-btn px-3 py-2 rounded-md transition-colors flex items-center bg-agri-green text-white text-sm"><i class="fas fa-chart-line mr-2"></i>Line</button>
                                    <button onclick="switchChartType('bar')" id="btn-bar" type="button" class="chart-type-btn px-3 py-2 rounded-md transition-colors flex items-center text-gray-600 hover:bg-gray-200 text-sm"><i class="fas fa-chart-bar mr-2"></i>Bar</button>
                                    <button onclick="switchChartType('pie')" id="btn-pie" type="button" class="chart-type-btn px-3 py-2 rounded-md transition-colors flex items-center text-gray-600 hover:bg-gray-200 text-sm"><i class="fas fa-chart-pie mr-2"></i>Pie</button>
                                    <button onclick="switchChartType('doughnut')" id="btn-doughnut" type="button" class="chart-type-btn px-3 py-2 rounded-md transition-colors flex items-center text-gray-600 hover:bg-gray-200 text-sm"><i class="fas fa-dot-circle mr-2"></i>Doughnut</button>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                        <canvas id="yieldChart" height="120"></canvas>
                        <script>
                        // Chart title and data update logic
                        // Only Yield chart will display
                        let currentChartType = 'line';
                        document.addEventListener('DOMContentLoaded', function() {
                            // Set month picker to current month if not already
                            const monthPicker = document.getElementById('monthPicker');
                            if (monthPicker) {
                                const now = new Date();
                                const monthStr = now.toISOString().slice(0,7);
                                monthPicker.value = monthStr;
                            }
                            // Set default chart type to line and highlight button
                            currentChartType = 'line';
                            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                                btn.classList.remove('bg-agri-green', 'text-white');
                                btn.classList.add('text-gray-600', 'hover:bg-gray-200');
                            });
                            document.getElementById('btn-line').classList.remove('text-gray-600', 'hover:bg-gray-200');
                            document.getElementById('btn-line').classList.add('bg-agri-green', 'text-white');
                            fetchChartData();
                        });

                        function switchChartType(newType) {
                            currentChartType = newType;
                            document.querySelectorAll('.chart-type-btn').forEach(btn => {
                                btn.classList.remove('bg-agri-green', 'text-white');
                                btn.classList.add('text-gray-600', 'hover:bg-gray-200');
                            });
                            document.getElementById('btn-' + newType).classList.remove('text-gray-600', 'hover:bg-gray-200');
                            document.getElementById('btn-' + newType).classList.add('bg-agri-green', 'text-white');
                            fetchChartData();
                        }

                        function fetchChartData() {
                            let month = document.getElementById('monthPicker').value;
                            let barangay = document.getElementById('barangayFilter').value;
                            let params = new URLSearchParams({ type: 'yield_per_barangay', chartType: currentChartType, barangay: barangay, month: month });
                            fetch('get_report_data.php?' + params.toString())
                                .then(res => res.json())
                                .then(data => {
                                    updateYieldChart(data.labels, data.data);
                                });
                        }

                        let yieldChartInstance = null;
                        function updateYieldChart(labels, chartData) {
                            const ctx = document.getElementById('yieldChart').getContext('2d');
                            if (yieldChartInstance) {
                                yieldChartInstance.destroy();
                            }
                            let chartLabel = 'Yield Monitoring (per Barangay)';
                            yieldChartInstance = new Chart(ctx, {
                                type: currentChartType,
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: chartLabel,
                                        data: chartData,
                                        backgroundColor: currentChartType === 'line' ? 'rgba(16,185,129,0.15)' : [
                                            '#10b981', '#3B82F6', '#F59E0B', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316', '#EF4444', '#A21CAF', '#FACC15'
                                        ],
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        pointBackgroundColor: '#10b981',
                                        pointBorderColor: '#10b981',
                                        fill: currentChartType === 'line',
                                        tension: 0.3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { display: false },
                                    },
                                    scales: currentChartType === 'pie' || currentChartType === 'doughnut' ? {} : {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { stepSize: 1 }
                                        }
                                    }
                                }
                            });
                        }

                        document.getElementById('monthPicker').addEventListener('change', fetchChartData);
                        document.getElementById('barangayFilter').addEventListener('change', fetchChartData);
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
                            <button onclick="navigateTo('distribute_input.php')" class="w-full flex items-center p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-300">
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
                            <li class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="flex items-center">
                                    <i class="fas fa-check-circle text-agri-green mr-2"></i>
                                    Distributed 150 bags of Fertilizer to various farmers.
                                </span>
                                <span class="text-xs text-gray-400">2025-09-18</span>
                            </li>
                            <li class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="flex items-center">
                                    <i class="fas fa-check-circle text-agri-green mr-2"></i>
                                    Registered new farmer: Juan Dela Cruz.
                                </span>
                                <span class="text-xs text-gray-400">2025-09-17</span>
                            </li>
                            <li class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="flex items-center">
                                    <i class="fas fa-check-circle text-agri-green mr-2"></i>
                                    Recorded 2,200kg of yield from Field B.
                                </span>
                                <span class="text-xs text-gray-400">2025-09-16</span>
                            </li>
                            <li class="flex items-center justify-between py-2 border-b border-gray-100">
                                <span class="flex items-center">
                                    <i class="fas fa-check-circle text-agri-green mr-2"></i>
                                    Logged new boat for fisherman Mark Santos.
                                </span>
                                <span class="text-xs text-gray-400">2025-09-15</span>
                            </li>
                            <li class="flex items-center justify-between py-2">
                                <span class="flex items-center">
                                    <i class="fas fa-check-circle text-agri-green mr-2"></i>
                                    Updated inventory for Rice Seeds.
                                </span>
                                <span class="text-xs text-gray-400">2025-09-14</span>
                            </li>
                        </ul>
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
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-blue-600 mr-2"></span>RSBSA</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-purple-600 mr-2"></span>NCFRS</div>
                            <div class="flex items-center"><span class="w-3 h-3 rounded-full bg-cyan-600 mr-2"></span>FISH-R</div>
                        </div>
                    </div>
                </div>
            </div>

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
<script src="assets/js/chart.min.js"></script>
<?php include 'includes/notification_complete.php'; ?>