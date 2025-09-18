<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside id="appSidebar" class="sidebar bg-agri-green text-white shadow-lg">
    <div class="sidebar__brand">
        <i id="sidebarLeaf" class="fas fa-seedling sidebar__logo" style="cursor:pointer;"></i>
        <span class="sidebar__title">Lagonglong FARMS</span>
    </div>
    <nav class="sidebar__nav">
        <div class="sidebar__section">Main</div>
        <a href="index.php" class="sidebar__link <?php echo $current === 'index.php' ? 'is-active' : ''; ?>"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="analytics_dashboard.php" class="sidebar__link <?php echo $current === 'analytics_dashboard.php' ? 'is-active' : ''; ?>"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
        <div class="sidebar__section">Records</div>
        <a href="farmers.php" class="sidebar__link <?php echo $current === 'farmers.php' ? 'is-active' : ''; ?>"><i class="fas fa-users"></i><span>Farmers</span></a>
        <a href="rsbsa_records.php" class="sidebar__link <?php echo $current === 'rsbsa_records.php' ? 'is-active' : ''; ?>"><i class="fas fa-certificate"></i><span>RSBSA</span></a>
        <a href="ncfrs_records.php" class="sidebar__link <?php echo $current === 'ncfrs_records.php' ? 'is-active' : ''; ?>"><i class="fas fa-fish"></i><span>NCFRS</span></a>
        <a href="fishr_records.php" class="sidebar__link <?php echo $current === 'fishr_records.php' ? 'is-active' : ''; ?>"><i class="fas fa-water"></i><span>FISHR</span></a>
        <a href="boat_records.php" class="sidebar__link <?php echo $current === 'boat_records.php' ? 'is-active' : ''; ?>"><i class="fas fa-ship"></i><span>Boats</span></a>
        <div class="sidebar__section">Operations</div>
        <a href="mao_inventory.php" class="sidebar__link <?php echo $current === 'mao_inventory.php' ? 'is-active' : ''; ?>"><i class="fas fa-warehouse"></i><span>Inventory</span></a>
        <a href="input_distribution_records.php" class="sidebar__link <?php echo $current === 'input_distribution_records.php' ? 'is-active' : ''; ?>"><i class="fas fa-share-square"></i><span>Distributions</span></a>
        <a href="mao_activities.php" class="sidebar__link <?php echo $current === 'mao_activities.php' ? 'is-active' : ''; ?>"><i class="fas fa-calendar-check"></i><span>Activities</span></a>
        <a href="reports.php" class="sidebar__link <?php echo $current === 'reports.php' ? 'is-active' : ''; ?>"><i class="fas fa-file-alt"></i><span>Reports</span></a>
        <div class="sidebar__section">Admin</div>
        <a href="staff.php" class="sidebar__link <?php echo $current === 'staff.php' ? 'is-active' : ''; ?>"><i class="fas fa-user-tie"></i><span>Staff</span></a>
        <a href="settings.php" class="sidebar__link <?php echo $current === 'settings.php' ? 'is-active' : ''; ?>"><i class="fas fa-cog"></i><span>Settings</span></a>
    </nav>
</aside>
<script>
    (function() {
        const root = document.documentElement;
        const toggleBtn = document.getElementById('sidebarToggle');
        const STORAGE_KEY = 'llfarms.sidebar.collapsed';
        function setCollapsed(collapsed) {
            if (collapsed) {
                root.classList.add('sidebar-collapsed');
            } else {
                root.classList.remove('sidebar-collapsed');
            }
            try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch(e) {}
        }
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved === '1') setCollapsed(true);
        } catch(e) {}
        function updateBurgerVisibility() {
            // Show burger only if sidebar is expanded (full sidebar)
            if (!document.documentElement.classList.contains('sidebar-collapsed')) {
                toggleBtn.style.display = 'inline-flex';
            } else {
                toggleBtn.style.display = 'none';
            }
        }
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
                updateBurgerVisibility();
            });
        }
        // Make leaf icon toggle sidebar
        const leafIcon = document.getElementById('sidebarLeaf');
        if (leafIcon) {
            leafIcon.addEventListener('click', function() {
                setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
                updateBurgerVisibility();
            });
        }
        // Initial burger visibility
        updateBurgerVisibility();
        // Expose a global toggle so topbar button can trigger it
        window.toggleSidebar = function() {
            setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
            updateBurgerVisibility();
        };
        // Wire up topbar hamburger if present
        const topbarBtn = document.getElementById('topbarSidebarToggle');
        if (topbarBtn) {
            topbarBtn.addEventListener('click', function() {
                window.toggleSidebar();
            });
        }
    })();
</script>
