<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Agricultural Management System';
}
?>
<nav class="bg-agri-green shadow-lg relative z-[1000]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <a href="index.php" class="flex items-center">
                    <i class="fas fa-seedling text-white text-2xl mr-3"></i>
                    <h1 class="text-white text-xl font-bold">Agricultural Management System</h1>
                </a>
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-white hover:text-agri-light transition-colors">
                    <i class="fas fa-bell text-lg"></i>
                </button>
                <div class="flex items-center text-white relative z-[1001]" id="userMenu">
                    <button class="flex items-center focus:outline-none" onclick="toggleDropdown()" type="button">
                        <i class="fas fa-user-circle text-lg mr-2"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <i class="fas fa-chevron-down ml-2 text-xs transition-transform duration-200" id="dropdownArrow"></i>
                    </button>
                    <!-- Dropdown Menu -->
                    <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-md shadow-xl py-1 hidden" id="dropdownMenu" style="z-index: 999999;">
                        <div class="px-4 py-2 text-sm text-gray-700 border-b">
                            <div class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                        </div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

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

<style>
/* Custom dropdown styles */
#dropdownMenu {
    display: none;
    z-index: 999999;
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
</style>
