<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside id="appSidebar" class="sidebar bg-agri-green text-white shadow-lg" style="height:100vh;overflow:hidden;">
    <div class="sidebar__brand">
        <i id="sidebarLeaf" class="fas fa-seedling sidebar__logo" style="cursor:pointer;"></i>
        <span class="sidebar__title">Lagonglong FARMS</span>
    </div>
    <nav class="sidebar__nav pb-8" style="overflow-y:hidden;max-height:calc(100vh - 32px);">
    <div class="sidebar__section" style="font-size:11.5px;padding:8px 12px 5px;">Main</div>
    <a href="index.php" class="sidebar__link <?php echo $current === 'index.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-home"></i><span>Dashboard</span></a>
    <a href="analytics_dashboard.php" class="sidebar__link <?php echo $current === 'analytics_dashboard.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
    <div class="sidebar__section" style="font-size:11.5px;padding:8px 12px 5px;">Records</div>
    <a href="farmers.php" class="sidebar__link <?php echo $current === 'farmers.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-users"></i><span>Farmers</span></a>
    <a href="rsbsa_records.php" class="sidebar__link <?php echo $current === 'rsbsa_records.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-certificate"></i><span>RSBSA</span></a>
    <a href="ncfrs_records.php" class="sidebar__link <?php echo $current === 'ncfrs_records.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-fish"></i><span>NCFRS</span></a>
    <a href="fishr_records.php" class="sidebar__link <?php echo $current === 'fishr_records.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-water"></i><span>FISHR</span></a>
    <a href="boat_records.php" class="sidebar__link <?php echo $current === 'boat_records.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-ship"></i><span>Boats</span></a>
    <div class="sidebar__section" style="font-size:11.5px;padding:8px 12px 5px;">Operations</div>
    <a href="mao_inventory.php" class="sidebar__link <?php echo $current === 'mao_inventory.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-warehouse"></i><span>Inventory</span></a>
    <a href="input_distribution_records.php" class="sidebar__link <?php echo $current === 'input_distribution_records.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-share-square"></i><span>Distributions</span></a>
    <a href="mao_activities.php" class="sidebar__link <?php echo $current === 'mao_activities.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-calendar-check"></i><span>Activities</span></a>
    <a href="yield_monitoring.php" class="sidebar__link <?php echo $current === 'yield_monitoring.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-chart-line"></i><span>Yield Monitoring</span></a>
    <a href="reports.php" class="sidebar__link <?php echo $current === 'reports.php' ? 'is-active' : ''; ?>" style="padding:9px 13px;font-size:16px;"><i class="fas fa-file-alt"></i><span>Reports</span></a>
    <!-- Admin section removed -->
        <div class="mt-auto"></div>
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
            if (!toggleBtn) return;
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
