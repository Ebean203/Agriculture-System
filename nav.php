<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Lagonglong FARMS';
}
?>
<nav class="w-full flex items-center justify-end">
    <div class="flex items-center gap-4">
        <!-- Notification Bell -->
        <div class="relative">
            <button onclick="toggleNotificationDropdown()" class="text-agri-green hover:text-agri-dark transition-colors relative">
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
        
        <!-- User Menu -->
        <div class="flex items-center text-agri-green relative z-[1001]" id="userMenu">
            <button class="flex items-center focus:outline-none" onclick="toggleDropdown()" type="button">
                <i class="fas fa-user-circle text-lg mr-2"></i>
                <span><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?></span>
                <i class="fas fa-chevron-down ml-2 text-xs transition-transform duration-200" id="dropdownArrow"></i>
            </button>
            <!-- Dropdown Menu -->
            <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-md shadow-xl py-1 hidden" id="dropdownMenu" style="z-index: 999999;">
                <div class="px-4 py-2 text-sm text-gray-700 border-b">
                    <div class="font-medium"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User'; ?></div>
                    <div class="text-xs text-gray-500"><?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'User'; ?></div>
                </div>
                <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
                    <a href="staff.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <i class="fas fa-user-tie mr-2"></i>Staff
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
// Function to handle dropdown toggle
function toggleDropdown() {
    const dropdownMenu = document.getElementById('dropdownMenu');
    const dropdownArrow = document.getElementById('dropdownArrow');
    if (dropdownMenu.classList.contains('hidden')) {
        dropdownMenu.classList.remove('hidden');
        dropdownMenu.classList.add('show');
        dropdownArrow.classList.add('rotate');
    } else {
        dropdownMenu.classList.add('hidden');
        dropdownMenu.classList.remove('show');
        dropdownArrow.classList.remove('rotate');
    }
}

// Function to handle notification dropdown toggle
function toggleNotificationDropdown() {
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationDropdown.classList.contains('hidden')) {
        notificationDropdown.classList.remove('hidden');
        notificationDropdown.classList.add('show');
    } else {
        notificationDropdown.classList.add('hidden');
        notificationDropdown.classList.remove('show');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.getElementById('userMenu');
    const dropdownMenu = document.getElementById('dropdownMenu');
    const dropdownArrow = document.getElementById('dropdownArrow');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBell = document.querySelector('button[onclick="toggleNotificationDropdown()"]');

    // User menu dropdown
    if (!userMenu.contains(event.target) && !dropdownMenu.classList.contains('hidden')) {
        dropdownMenu.classList.add('hidden');
        dropdownMenu.classList.remove('show');
        dropdownArrow.classList.remove('rotate');
    }
    // Notification dropdown
    if (notificationDropdown && !notificationDropdown.classList.contains('hidden') && (!notificationBell.contains(event.target) && !notificationDropdown.contains(event.target))) {
        notificationDropdown.classList.add('hidden');
        notificationDropdown.classList.remove('show');
    }
});
</script>

<style>
/* Custom dropdown styles */
#dropdownMenu {
    z-index: 999999;
    transition: all 0.5s ease-in-out;
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

#dropdownMenu.hidden {
    display: none !important;
}

#dropdownArrow.rotate {
    transform: rotate(180deg);
}
</style>
