<?php
// Navigation Component - Page Navigation Dropdown
// This component provides a navigation dropdown to different pages
?>
<div class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-200">
    <!-- Navigation Dropdown -->
    <div class="relative">
        <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" onclick="toggleNavigationDropdown()">
            <i class="fas fa-compass mr-2"></i>Go to Page
            <i class="fas fa-chevron-down ml-2 transition-transform" id="navigationArrow"></i>
        </button>
        <div id="navigationDropdown" class="absolute left-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50 hidden">
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
            
            <!-- MAO Management Section -->
            <div class="border-b border-gray-200">
                <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">MAO Management</div>
                <a href="mao_inventory.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                    <i class="fas fa-warehouse text-green-600 mr-3"></i>
                    MAO Inventory
                </a>
                <a href="mao_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                    <i class="fas fa-tasks text-orange-600 mr-3"></i>
                    MAO Activities
                </a>
                <a href="input_distribution_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                    <i class="fas fa-truck text-blue-600 mr-3"></i>
                    Distribution Records
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
                    <i class="fas fa-ship text-navy-600 mr-3"></i>
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
                    <i class="fas fa-user-tie text-purple-600 mr-3"></i>
                    Staff Management
                </a>
                <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                    <i class="fas fa-cog text-gray-600 mr-3"></i>
                    Settings
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Navigation Dropdown Functions
function toggleNavigationDropdown() {
    const dropdown = document.getElementById('navigationDropdown');
    const arrow = document.getElementById('navigationArrow');
    dropdown.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
}

// Close navigation dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('navigationDropdown');
    const button = event.target.closest('[onclick="toggleNavigationDropdown()"]');
    
    if (!button && dropdown && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
        const arrow = document.getElementById('navigationArrow');
        if (arrow) arrow.classList.remove('rotate-180');
    }
});
</script>

<style>
/* Additional styles for navy color */
.text-navy-600 {
    color: #1e40af;
}
</style>
