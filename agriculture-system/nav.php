<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Agricultural Management System';
}
?>
<nav class="bg-agri-green shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo and Title -->
            <div class="flex items-center">
                <a href="index.php" class="flex items-center">
                    <i class="fas fa-seedling text-white text-2xl mr-2"></i>
                    <span class="text-white text-xl font-bold">Agricultural Management System</span>
                </a>
            </div>

            <!-- User actions -->
            <div class="flex items-center space-x-4">
                <button class="text-white hover:text-agri-light transition-colors" title="Notifications">
                    <i class="fas fa-bell text-lg"></i>
                </button>
                <div class="flex items-center text-white relative" x-data="{ open: false }">
                    <button class="flex items-center focus:outline-none" @click="open = !open" type="button">
                        <i class="fas fa-user-circle text-lg mr-2"></i>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <i class="fas fa-chevron-down ml-2 text-xs transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <!-- Dropdown Menu -->
                    <div x-show="open" @click.away="open = false" class="absolute right-0 top-full mt-2 w-48 bg-white rounded-md shadow-lg py-1" style="display: none;">
                        <div class="px-4 py-2 text-sm text-gray-700 border-b">
                            <div class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                        </div>
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-user mr-2"></i>
                            Profile
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-cog mr-2"></i>
                            Settings
                        </a>
                        <?php endif; ?>
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
