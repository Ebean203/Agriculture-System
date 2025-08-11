<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Agricultural Management System';
}
?>
<nav class="bg-gradient-to-r from-agri-green to-agri-dark shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <!-- Logo and Title -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center">
                    <i class="fas fa-seedling text-white text-2xl mr-2"></i>
                    <span class="font-bold text-white">Agriculture Management System</span>
                </a>
            </div>

            <!-- User Info and Dropdown -->
            <div class="flex items-center">
                <span class="text-white text-sm mr-4"><?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</span>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center text-white hover:text-agri-light focus:outline-none">
                        <i class="fas fa-user-circle text-2xl"></i>
                        <i class="fas fa-chevron-down ml-1 text-sm"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1">
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user mr-2"></i>Profile
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </a>
                        <?php endif; ?>
                        <div class="border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
