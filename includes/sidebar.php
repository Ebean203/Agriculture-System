<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside id="appSidebar" class="sidebar bg-agri-green text-white shadow-lg" style="height:100vh;overflow:hidden;display:flex;flex-direction:column;">
    <style>
        /* Slightly larger sidebar icons without changing text size */
        .sidebar .sidebar-icon { width: 1.3em; height: 1.3em; vertical-align: middle; }
        .sidebar__link span { margin-left: 0.5rem; }
    </style>
    <div class="sidebar__brand" style="flex-shrink:0;justify-content:center;">
        <img src="assets/Logo/559589567_122111810120996957_6008080270013910283_n.jpg" id="sidebarLogo" class="sidebar__logo-img" alt="MAO Seal">
        <span class="sidebar__title">Lagonglong FARMS</span>
    </div>
    <nav class="sidebar__nav" style="overflow-y:auto;flex:1;min-height:0;padding-bottom:4px;">
    <a href="index.php" class="sidebar__link <?php echo $current === 'index.php' ? 'is-active' : ''; ?>" data-tooltip="Dashboard" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M12 3.1L2 10v10a1 1 0 0 0 1 1h6v-7h6v7h6a1 1 0 0 0 1-1V10L12 3.1z"/></svg>
        <span>Dashboard</span>
    </a>
    <a href="analytics_dashboard.php" class="sidebar__link <?php echo $current === 'analytics_dashboard.php' ? 'is-active' : ''; ?>" data-tooltip="Analytics" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M3 3v18h18v-2H5V3H3zm6 12l4-5 4 6 2-3 1 1-3 5-5-7-4 5V15z"/></svg>
        <span>Analytics</span>
    </a>
    <a href="farmers.php" class="sidebar__link <?php echo $current === 'farmers.php' ? 'is-active' : ''; ?>" data-tooltip="Farmers" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zM8 11c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm8 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zM3 17v2h4v-2c0-1.5-2-2-4-2z"/></svg>
        <span>Farmers</span>
    </a>
    <a href="rsbsa_records.php" class="sidebar__link <?php echo $current === 'rsbsa_records.php' ? 'is-active' : ''; ?>" data-tooltip="RSBSA" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M12 2l2.09 4.26L18.6 7l-3.3 3.22.78 4.54L12 12.77 7.92 14.9l.78-4.54L5.4 7l4.51-.74L12 2z"/></svg>
        <span>RSBSA</span>
    </a>
    <a href="ncfrs_records.php" class="sidebar__link <?php echo $current === 'ncfrs_records.php' ? 'is-active' : ''; ?>" data-tooltip="NCFRS" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M21 12c0-1.1-.9-2-2-2-1.1 0-2 .9-2 2 0 .4.1.8.4 1.2L12 19 7 12l-5 3V6l5 3 5-7 7 3.8c.3.4.6.8.6 1.2 0 1.1.9 2 2 2z"/></svg>
        <span>NCFRS</span>
    </a>
    <a href="fishr_records.php" class="sidebar__link <?php echo $current === 'fishr_records.php' ? 'is-active' : ''; ?>" data-tooltip="FISHR" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M12 3.1L2 12h20L12 3.1zM4 14v6h16v-6H4z"/></svg>
        <span>FISHR</span>
    </a>
    <a href="boat_records.php" class="sidebar__link <?php echo $current === 'boat_records.php' ? 'is-active' : ''; ?>" data-tooltip="Boats" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M20 21H4l1-4h14l1 4zM22 17H2v-2l2-5h16l2 5v2zM6 6h12v2H6z"/></svg>
        <span>Boats</span>
    </a>
    <a href="mao_inventory.php" class="sidebar__link <?php echo $current === 'mao_inventory.php' ? 'is-active' : ''; ?>" data-tooltip="Inventory" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M3 9v11h18V9L12 3 3 9zm7 8H8v-5h2v5zm6 0h-2v-5h2v5z"/></svg>
        <span>Inventory</span>
    </a>
    <a href="input_distribution_records.php" class="sidebar__link <?php echo $current === 'input_distribution_records.php' ? 'is-active' : ''; ?>" data-tooltip="Distributions" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M15 8V5l6 6-6 6v-3H9v-6h6zM3 5v14h8v-2H5V5H3z"/></svg>
        <span>Distributions</span>
    </a>
    <a href="mao_activities.php" class="sidebar__link <?php echo $current === 'mao_activities.php' ? 'is-active' : ''; ?>" data-tooltip="Activities" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v13c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zM9 13l2 2 4-4-1.4-1.4L11 12.2 10.4 11.6 9 13z"/></svg>
        <span>Activities</span>
    </a>
    <a href="yield_monitoring.php" class="sidebar__link <?php echo $current === 'yield_monitoring.php' ? 'is-active' : ''; ?>" data-tooltip="Yield Monitoring" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M3 3v18h18v-2H5V3H3zm6 12l4-5 4 6 2-3 1 1-3 5-5-7-4 5V15z"/></svg>
        <span>Yield Monitoring</span>
    </a>
    <a href="reports.php" class="sidebar__link <?php echo $current === 'reports.php' ? 'is-active' : ''; ?>" data-tooltip="Reports" style="padding:9px 13px;font-size:16px;">
        <svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zM8 18v-2h8v2H8zm8-4H8v-2h8v2z"/></svg>
        <span>Reports</span>
    </a>
    <!-- Admin section removed -->
    </nav>
    <!-- Collapse toggle button at the bottom -->
    <div class="sidebar__collapse-wrap" style="flex-shrink:0;padding:8px 8px 16px;background:inherit;border-top:1px solid rgba(255,255,255,0.1);">
        <button id="sidebarCollapseBtn" class="sidebar__collapse-btn" title="Collapse sidebar">
            <span class="collapse-label"><svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon mr-1 text-xs"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>Collapse</span>
            <span class="collapse-icon" style="display:none;"><svg aria-hidden="true" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" class="sidebar-icon"><path d="M18.3 5.71L12 12l6.3 6.29-1.41 1.41L10.59 13.41 4.29 19.71 2.88 18.3 9.17 12 2.88 5.71 4.29 4.29 10.59 10.59 16.88 4.29z"/></svg></span>
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
