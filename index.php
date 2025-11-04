<?php
require_once 'conn.php';
require_once 'check_session.php';

// Provide commodity data for the yield record modal and dashboard chart
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

// Flash toasts will be emitted by includes/toast_flash.php included in layout_start

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

        <div class="max-w-7xl mx-auto py-2">
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-xl card-shadow p-6 mb-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="heading-xl mb-2 text-3xl lg:text-4xl font-bold">Welcome to Lagonglong FARMS</h1>
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
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='farmers.php'" title="View Farmers">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-user-friends text-agri-green text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">FARMERS</span>
                    </div>
                    <div class="text-2xl font-bold text-agri-green"><?php echo number_format($total_farmers); ?></div>
                </div>
                <!-- RSBSA -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='rsbsa_records.php'" title="View RSBSA Records">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-shield-alt text-blue-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">RSBSA</span>
                    </div>
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($rsbsa_registered); ?></div>
                </div>
                <!-- NCFRS -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='ncfrs_records.php'" title="View NCFRS Records">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-file-alt text-purple-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">NCFRS</span>
                    </div>
                    <div class="text-2xl font-bold text-purple-600"><?php echo number_format($ncfrs_registered); ?></div>
                </div>
                    <!-- Calendar (replaces Weather) -->
                    <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center xl:row-span-2 xl:h-[352px] h-[352px] xl:col-span-1 xl:w-full" style="min-width:0;">
                        <div class="w-full flex flex-col items-center">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-calendar-alt text-agri-green mr-2"></i>Calendar
                            </h3>
                            <div id="dashboardCalendar" class="w-full"></div>
                        </div>
                        <script>
                        // Simple calendar generator for current month
                        function renderDashboardCalendar() {
                            const calendarEl = document.getElementById('dashboardCalendar');
                            if (!calendarEl) return;
                            const today = new Date();
                            const year = today.getFullYear();
                            const month = today.getMonth();
                            const monthNames = [
                                'January', 'February', 'March', 'April', 'May', 'June',
                                'July', 'August', 'September', 'October', 'November', 'December'
                            ];
                            // First day of the month
                            const firstDay = new Date(year, month, 1);
                            // Last day of the month
                            const lastDay = new Date(year, month + 1, 0);
                            // Day of week for first day (0=Sun, 6=Sat)
                            const startDay = firstDay.getDay();
                            // Number of days in month
                            const daysInMonth = lastDay.getDate();
                            let html = `<div class="text-center font-semibold text-lg mb-2">${monthNames[month]} ${year}</div>`;
                            html += '<div class="grid grid-cols-7 gap-1 text-xs text-gray-500 mb-1">';
                            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d => {
                                html += `<div class="text-center font-bold">${d}</div>`;
                            });
                            html += '</div>';
                            html += '<div class="grid grid-cols-7 gap-1">';
                            // Empty cells for days before the 1st
                            for (let i = 0; i < startDay; i++) {
                                html += '<div></div>';
                            }
                            for (let d = 1; d <= daysInMonth; d++) {
                                const isToday = d === today.getDate();
                                html += `<div class="text-center py-1 rounded ${isToday ? 'bg-agri-green text-white font-bold' : 'hover:bg-gray-100'} cursor-pointer">${d}</div>`;
                            }
                            html += '</div>';
                            calendarEl.innerHTML = html;
                        }
                        document.addEventListener('DOMContentLoaded', renderDashboardCalendar);
                        </script>
                    </div>
                <!-- Commodities -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='commodities.php'" title="View Commodities">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-box-open text-orange-500 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">COMMODITIES</span>
                    </div>
                    <div class="text-2xl font-bold text-orange-500"><?php echo number_format($total_commodities); ?></div>
                </div>
                <!-- Registered Boats -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='boat_records.php'" title="View Registered Boats">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-ship text-yellow-600 text-2xl mr-2"></i>
                        <span class="font-semibold text-gray-700">REGISTERED BOATS</span>
                    </div>
                    <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($total_boats); ?></div>
                </div>
                <!-- Inventory -->
                <div class="bg-white rounded-xl card-shadow p-6 flex flex-col justify-center items-center h-40 cursor-pointer hover:shadow-lg transition-shadow duration-200" onclick="window.location.href='mao_inventory.php'" title="View Inventory">
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
                    <div class="bg-white rounded-xl card-shadow p-6 h-full flex flex-col justify-between" style="min-height:480px;">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                                <i class="fas fa-chart-line text-agri-green mr-2"></i><span id="chartTitle">Yield Monitoring</span>
                            </h3>
                            <div class="flex flex-row justify-end items-center mb-2 gap-2">
                                <div class="relative w-full max-w-xs flex-shrink-0" style="min-width:180px;">
                                    <input type="text" id="commoditySearch" autocomplete="off" class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:ring-agri-green focus:border-agri-green w-full" placeholder="Search commodity...">
                                    <ul id="commoditySuggestions" class="absolute left-0 right-0 bg-white border border-gray-200 rounded-md shadow z-10 mt-1 hidden max-h-40 overflow-y-auto"></ul>
                                </div>
                                <div>
                                    <select id="timeRange" class="border border-gray-300 rounded-md px-2 py-1 text-sm">
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Per 3 Months</option>
                                        <option value="semiannual">Per 6 Months</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </div>
                            </div>
                            <canvas id="yieldChart" height="120"></canvas>
                        </div>
                        <div class="flex flex-col items-center mt-4">
                            <div id="categoryButtons" class="flex flex-wrap gap-2 mb-2 justify-center">
                                <?php foreach ($commodity_categories as $cat): ?>
                                    <button type="button" class="category-btn px-3 py-1 rounded-md border border-gray-200 bg-white text-gray-700 flex items-center gap-1 text-sm shadow-sm hover:bg-agri-green hover:text-white transition" data-category="<?php echo htmlspecialchars($cat['category_id']); ?>">
                                        <i class="fas fa-leaf"></i> <span class="category-btn-label"><?php echo htmlspecialchars($cat['category_name']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <script>
                        let yieldChartInstance = null;
                        let currentCategory = '';
                        let currentCommodity = '';
                        // Add style for active category button to keep text visible
                        const style = document.createElement('style');
                        style.innerHTML = `
                            .category-btn.active, .category-btn.active:focus {
                                background-color: #10b981 !important;
                                color: #fff !important;
                            }
                            .category-btn.active .category-btn-label {
                                color: #fff !important;
                            }
                            .category-btn .fas {
                                color: inherit;
                            }
                        `;
                        document.head.appendChild(style);
                        // Prepare commodity data for search (from PHP)
                        const allCommodities = <?php echo json_encode($commodities); ?>;
                        // Fetch and update chart for a specific commodity
                        function fetchAndUpdateYieldChart(commodity) {
                            let url = 'get_report_data.php?type=yield';
                            const timeRange = document.getElementById('timeRange').value;
                            if (commodity) {
                                url += '&commodity=' + encodeURIComponent(commodity);
                            } else if (currentCategory) {
                                url = 'get_report_data.php?type=yield_by_category&category_id=' + encodeURIComponent(currentCategory);
                            }
                            url += '&time_range=' + encodeURIComponent(timeRange);
                            fetch(url)
                                .then(res => res.json())
                                .then(data => {
                                    window.lastApiData = data;
                                    updateYieldChart(data.labels, data.data, commodity, currentCategory);
                                });
                        }
                        async function updateYieldChart(labels, chartData, commodity) {
                            const ctx = document.getElementById('yieldChart').getContext('2d');
                            if (yieldChartInstance) {
                                yieldChartInstance.destroy();
                            }
                            // Fetch the yield unit (default to 'kg' if request fails)
                            let units = [];
                            let unit = 'kg';
                            // If viewing by category, fetch units from the API response
                            if (currentCategory && !commodity && window.lastApiData && Array.isArray(window.lastApiData.units)) {
                                units = window.lastApiData.units;
                            } else if (commodity) {
                                try {
                                    let url = 'get_yield_unit.php?commodity=' + encodeURIComponent(commodity);
                                    const res = await fetch(url);
                                    if (res.ok) {
                                        const json = await res.json();
                                        if (json.unit) unit = json.unit;
                                    }
                                } catch (e) {}
                            }
                            let chartLabel = '';
                            if (commodity) {
                                chartLabel = 'Yield for ' + commodity + ' (' + unit + ')';
                            } else if (currentCategory) {
                                chartLabel = 'Total Yield per Commodity';
                            } else {
                                chartLabel = 'Yearly Yield per Barangay (' + unit + ')';
                            }
                            // Dynamically set y-axis max based on highest yield per commodity being displayed
                            let maxValue = 0;
                            if (Array.isArray(chartData) && chartData.length > 0) {
                                maxValue = Math.max(...chartData);
                            }
                            // Add 10% buffer, then round up to nearest 50 for a clean axis
                            let yAxisMax = maxValue > 0 ? Math.ceil((maxValue * 1.1) / 50) * 50 : 10;
                            yieldChartInstance = new Chart(ctx, {
                                type: (commodity || currentCategory) ? 'bar' : 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: chartLabel,
                                        data: chartData,
                                        backgroundColor: 'rgba(16,185,129,0.35)',
                                        borderColor: '#10b981',
                                        borderWidth: 2,
                                        pointBackgroundColor: '#10b981',
                                        pointBorderColor: '#10b981',
                                        fill: true,
                                        tension: 0.3,
                                        datalabels: {
                                            display: true,
                                            color: '#222',
                                            anchor: 'end',
                                            align: 'top',
                                            font: { weight: 'bold', size: 14 },
                                            formatter: function(value, context) {
                                                if (currentCategory && units.length > 0) {
                                                    return value + ' ' + units[context.dataIndex];
                                                }
                                                return value + ' ' + unit;
                                            }
                                        }
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { display: false },
                                        datalabels: {
                                            display: true,
                                            color: '#222',
                                            anchor: 'end',
                                            align: 'top',
                                            font: { weight: 'bold', size: 14 },
                                            formatter: function(value, context) {
                                                if (currentCategory && units.length > 0) {
                                                    return value + ' ' + units[context.dataIndex];
                                                }
                                                return value + ' ' + unit;
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: yAxisMax,
                                            ticks: { display: false }
                                        }
                                    }
                                },
                                plugins: [ChartDataLabels]
                            });
                        }
                        // Category button and search logic with autosuggest
                        document.addEventListener('DOMContentLoaded', function() {
                            const categoryBtns = document.querySelectorAll('.category-btn');
                            const commoditySearch = document.getElementById('commoditySearch');
                            const suggestionsBox = document.getElementById('commoditySuggestions');

                            function getFilteredCommodities(searchTerm = '') {
                                searchTerm = searchTerm.trim().toLowerCase();
                                return allCommodities.filter(com => {
                                    let matchCat = currentCategory ? com.category_id == currentCategory : true;
                                    let matchSearch = !searchTerm || com.commodity_name.toLowerCase().includes(searchTerm);
                                    return matchCat && matchSearch;
                                });
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
                                        fetchAndUpdateYieldChart(currentCommodity);
                                    });
                                    suggestionsBox.appendChild(li);
                                });
                                suggestionsBox.classList.remove('hidden');
                            }

                            // On category button click
                            categoryBtns.forEach(btn => {
                                btn.addEventListener('click', function() {
                                    categoryBtns.forEach(b => b.classList.remove('active', 'bg-agri-green', 'text-white'));
                                    btn.classList.add('active', 'bg-agri-green', 'text-white');
                                    currentCategory = btn.getAttribute('data-category');
                                    currentCommodity = '';
                                    commoditySearch.value = '';
                                    suggestionsBox.classList.add('hidden');
                                    // Fetch yield by category (not by commodity)
                                    fetchAndUpdateYieldChart(null);
                                });
                            });

                            // On search input
                            commoditySearch.addEventListener('input', function() {
                                const filtered = getFilteredCommodities(commoditySearch.value);
                                showSuggestions(filtered);
                            });

                            // On Enter in search
                            commoditySearch.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter') {
                                    const filtered = getFilteredCommodities(commoditySearch.value);
                                    if (filtered.length > 0) {
                                        currentCommodity = filtered[0].commodity_name;
                                        fetchAndUpdateYieldChart(currentCommodity);
                                    } else {
                                        currentCommodity = '';
                                        if (yieldChartInstance) {
                                            yieldChartInstance.destroy();
                                            yieldChartInstance = null;
                                        }
                                        const ctx = document.getElementById('yieldChart').getContext('2d');
                                        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                                        document.getElementById('chartTitle').textContent = 'Yield Monitoring';
                                    }
                                    suggestionsBox.classList.add('hidden');
                                }
                            });

                            // Hide suggestions on blur
                            commoditySearch.addEventListener('blur', function() {
                                setTimeout(() => suggestionsBox.classList.add('hidden'), 100);
                            });

                            // Time range dropdown event
                            document.getElementById('timeRange').addEventListener('change', function() {
                                fetchAndUpdateYieldChart(currentCommodity);
                            });
                            // On load, do not display any data; wait for user to select a commodity or category
                            currentCommodity = '';
                            if (yieldChartInstance) {
                                yieldChartInstance.destroy();
                                yieldChartInstance = null;
                            }
                            const ctx = document.getElementById('yieldChart').getContext('2d');
                            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
                            document.getElementById('chartTitle').textContent = 'Yield Monitoring';
                        });
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
                                                case 'reschedule':
                                                    $icon = 'fas fa-calendar-alt';
                                                    $icon_color = 'text-pink-600';
                                                    break;
                                                default:
                                                    $icon = 'fas fa-check-circle';
                                                    $icon_color = 'text-agri-green';
                                            }
                                            ?>
                                            <i class="<?php echo $icon . ' ' . $icon_color; ?> mr-2"></i>
                                            <span class="text-sm leading-tight">
                                                <?php
                                                // Build friendlier message; some older logs used "+1"/numeric
                                                $rawAction = trim((string)($activity['action'] ?? ''));
                                                $type = strtolower((string)($activity['action_type'] ?? ''));
                                                $actionText = $rawAction;
                                                if ($type === 'farmer' && ($rawAction === '' || preg_match('/^\+?\d+$/', $rawAction))) {
                                                    $actionText = 'Added new farmer';
                                                } elseif ($rawAction !== '' && preg_match('/^[A-Z_]+$/', $rawAction)) {
                                                    // Turn legacy ACTION_TOKENS into 'Action Tokens'
                                                    $actionText = ucwords(strtolower(str_replace('_', ' ', $rawAction)));
                                                }
                                                echo htmlspecialchars($actionText);
                                                ?>
                                                <?php if (!empty($activity['details'])): ?>
                                                    <br><span class="text-xs text-gray-600"><?php echo htmlspecialchars($activity['details']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($activity['first_name']) || !empty($activity['last_name'])): ?>
                                                    <br><span class="text-xs text-gray-400">by <?php echo htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? ''))); ?></span>
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
    <?php include 'yield_record_modal.php'; ?>

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

        // Commodity filtering function used by the modal's category <select> onchange
        window.filterCommodities = function() {
            const categoryFilter = document.getElementById('commodity_category_filter');
            const commoditySelect = document.getElementById('commodity_id');
            if (!categoryFilter || !commoditySelect) return;

            const selectedCategory = categoryFilter.value;
            const farmerCommodities = categoryFilter.farmerCommodities || [];

            // Reset commodity dropdown and add default
            let html = '<option value="">Select Commodity</option>';

            farmerCommodities.forEach(function(c) {
                // Normalize possible category fields
                const cCatId = (c.category_id !== undefined) ? String(c.category_id) : '';
                const cCatName = (c.category_name !== undefined) ? String(c.category_name) : '';
                const cCatOther = (c.category !== undefined) ? String(c.category) : '';

                const matches = (selectedCategory === '') ||
                                (cCatId && String(selectedCategory) === cCatId) ||
                                (cCatName && String(selectedCategory) === cCatName) ||
                                (cCatOther && String(selectedCategory) === cCatOther);

                if (matches) {
                    const dataCategory = c.category || c.category_id || c.category_name || '';
                    html += `<option value="${escapeHtml(c.id)}" data-category="${escapeHtml(dataCategory)}">${escapeHtml(c.name)}</option>`;
                }
            });

            commoditySelect.innerHTML = html;
            commoditySelect.value = '';
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
<script src="assets/js/chartjs-plugin-datalabels.min.js"></script>
<?php include 'includes/notification_complete.php'; ?>