<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside id="appSidebar" class="sidebar bg-agri-green text-white shadow-lg" style="height:100vh;overflow:hidden;display:flex;flex-direction:column;position:sticky;top:0;">
    <div class="sidebar__brand" style="flex-shrink:0;justify-content:center;">
        <img src="assets/Logo/559589567_122111810120996957_6008080270013910283_n.jpg" id="sidebarLogo" class="sidebar__logo-img" alt="MAO Seal">
        <span class="sidebar__title">Lagonglong FARMS</span>
    </div>
    <nav class="sidebar__nav" style="overflow-y:auto;flex:1;min-height:0;padding-bottom:4px;">
    <a href="index.php" class="sidebar__link <?php echo $current === 'index.php' ? 'is-active' : ''; ?>" data-tooltip="Dashboard" style="padding:9px 13px;font-size:16px;"><i class="fas fa-home"></i><span>Dashboard</span></a>
    <a href="analytics_dashboard.php" class="sidebar__link <?php echo $current === 'analytics_dashboard.php' ? 'is-active' : ''; ?>" data-tooltip="Analytics" style="padding:9px 13px;font-size:16px;"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
    <a href="farmers.php" class="sidebar__link <?php echo $current === 'farmers.php' ? 'is-active' : ''; ?>" data-tooltip="Farmers" style="padding:9px 13px;font-size:16px;"><i class="fas fa-users"></i><span>Farmers</span></a>
    <a href="rsbsa_records.php" class="sidebar__link <?php echo $current === 'rsbsa_records.php' ? 'is-active' : ''; ?>" data-tooltip="RSBSA" style="padding:9px 13px;font-size:16px;"><i class="fas fa-certificate"></i><span>RSBSA</span></a>
    <a href="ncfrs_records.php" class="sidebar__link <?php echo $current === 'ncfrs_records.php' ? 'is-active' : ''; ?>" data-tooltip="NCFRS" style="padding:9px 13px;font-size:16px;"><i class="fas fa-fish"></i><span>NCFRS</span></a>
    <a href="fishr_records.php" class="sidebar__link <?php echo $current === 'fishr_records.php' ? 'is-active' : ''; ?>" data-tooltip="FISHR" style="padding:9px 13px;font-size:16px;"><i class="fas fa-water"></i><span>FISHR</span></a>
    <a href="boat_records.php" class="sidebar__link <?php echo $current === 'boat_records.php' ? 'is-active' : ''; ?>" data-tooltip="Boats" style="padding:9px 13px;font-size:16px;"><i class="fas fa-ship"></i><span>Boats</span></a>
    <a href="mao_inventory.php" class="sidebar__link <?php echo $current === 'mao_inventory.php' ? 'is-active' : ''; ?>" data-tooltip="Inventory" style="padding:9px 13px;font-size:16px;"><i class="fas fa-warehouse"></i><span>Inventory</span></a>
    <a href="input_distribution_records.php" class="sidebar__link <?php echo $current === 'input_distribution_records.php' ? 'is-active' : ''; ?>" data-tooltip="Distributions" style="padding:9px 13px;font-size:16px;"><i class="fas fa-share-square"></i><span>Distributions</span></a>
    <a href="mao_activities.php" class="sidebar__link <?php echo $current === 'mao_activities.php' ? 'is-active' : ''; ?>" data-tooltip="Activities" style="padding:9px 13px;font-size:16px;"><i class="fas fa-calendar-check"></i><span>Activities</span></a>
    <a href="yield_monitoring.php" class="sidebar__link <?php echo $current === 'yield_monitoring.php' ? 'is-active' : ''; ?>" data-tooltip="Yield Monitoring" style="padding:9px 13px;font-size:16px;"><i class="fas fa-chart-line"></i><span>Yield Monitoring</span></a>
    <a href="reports.php" class="sidebar__link <?php echo $current === 'reports.php' ? 'is-active' : ''; ?>" data-tooltip="Reports" style="padding:9px 13px;font-size:16px;"><i class="fas fa-file-alt"></i><span>Reports</span></a>
    <!-- Admin section removed -->
    </nav>
    <!-- Collapse toggle button at the bottom -->
    <div class="sidebar__collapse-wrap" style="flex-shrink:0;padding:8px 8px 16px;background:inherit;border-top:1px solid rgba(255,255,255,0.1);">
        <button id="sidebarCollapseBtn" class="sidebar__collapse-btn" title="Collapse sidebar">
            <span class="collapse-label"><i class="fas fa-chevron-left mr-1 text-xs"></i>Collapse</span>
            <span class="collapse-icon" style="display:none;"><i class="fas fa-times"></i></span>
        </button>
    </div>
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
        // Make collapse button toggle sidebar
        const collapseBtn = document.getElementById('sidebarCollapseBtn');
        function updateCollapseBtn() {
            if (!collapseBtn) return;
            const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
            collapseBtn.querySelector('.collapse-label').style.display = isCollapsed ? 'none' : '';
            collapseBtn.querySelector('.collapse-icon').style.display = isCollapsed ? '' : 'none';
        }
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function() {
                setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
                updateBurgerVisibility();
                updateCollapseBtn();
            });
        }
        // Initial state
        updateBurgerVisibility();
        updateCollapseBtn();
        // Expose a global toggle so topbar button can trigger it
        window.toggleSidebar = function() {
            setCollapsed(!document.documentElement.classList.contains('sidebar-collapsed'));
            updateBurgerVisibility();
            updateCollapseBtn();
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
