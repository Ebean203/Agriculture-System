    <?php
require_once 'check_session.php';
require_once 'conn.php';

// Only admin can access settings
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle settings management logic here
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Lagonglong FARMS</title>
    <?php include 'includes/assets.php'; ?>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <div class="max-w-6xl mx-auto">
            <!-- Page Header (Uniform White Card Design) -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div class="flex items-start">
                        <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center mr-3">
                            <i class="fas fa-cog text-gray-600 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
                            <p class="text-gray-600">Configure system preferences and options</p>
                            <div class="flex items-center text-gray-500 mt-2">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span><?php echo date('l, F j, Y'); ?></span>
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
                                        <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
                                            <i class="fas fa-cog text-gray-600 mr-3"></i>
                                            System Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="hidden lg:block">
                                <i class="fas fa-cog text-6xl text-white opacity-20"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center py-20">
                    <i class="fas fa-tools text-6xl text-gray-300 mb-4"></i>
                    <h2 class="text-2xl font-bold text-gray-600 mb-2">Settings Configuration</h2>
                    <p class="text-gray-500">System settings functionality coming soon...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Navigation dropdown functionality
        function toggleNavigationDropdown() {
            const dropdown = document.getElementById('navigationDropdown');
            const arrow = document.getElementById('navigationArrow');
            
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                arrow.style.transform = 'rotate(180deg)';
            } else {
                dropdown.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('navigationDropdown');
            const button = event.target.closest('button');
            
            if (!dropdown.contains(event.target) && (!button || !button.getAttribute('onclick')?.includes('toggleNavigationDropdown'))) {
                dropdown.classList.add('hidden');
                document.getElementById('navigationArrow').style.transform = 'rotate(0deg)';
            }
        });
    </script>

    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
