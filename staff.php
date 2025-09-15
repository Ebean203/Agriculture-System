<?php
require_once 'check_session.php';
require_once 'conn.php';

// Only admin can access staff management
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get all staff members with their roles
$staff_query = "SELECT s.*, r.role as role_name 
                FROM mao_staff s 
                LEFT JOIN roles r ON s.role_id = r.role_id 
                ORDER BY s.role_id ASC, s.position ASC, s.last_name ASC";
$staff_result = mysqli_query($conn, $staff_query);
?>
<?php $pageTitle = 'MAO Staff Directory - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                <!-- Page Header (uniform white card like MAO Inventory) -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center mr-3">
                                <i class="fas fa-users-cog text-agri-green text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900">Manage Staff</h1>
                                <p class="text-gray-600">Manage Municipal Agriculture Office personnel</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Go to Page (rightmost) -->
                            <div class="relative">
                                <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center font-medium" onclick="toggleNavigationDropdown()">
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
                                        <a href="staff.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
                                            <i class="fas fa-user-tie text-gray-600 mr-3"></i>
                                            Staff Management
                                        </a>
                                        <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-cog text-gray-600 mr-3"></i>
                                            System Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Data Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-agri-green text-white">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-user mr-1"></i>Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-briefcase mr-1"></i>Position
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-phone mr-1"></i>Contact Number
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-user-tag mr-1"></i>Role
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-cogs mr-1"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($staff_result && mysqli_num_rows($staff_result) > 0): ?>
                                    <?php while ($staff = mysqli_fetch_assoc($staff_result)): 
                                        $is_head = stripos($staff['position'], 'head') !== false || 
                                                  stripos($staff['position'], 'officer') !== false ||
                                                  stripos($staff['position'], 'chief') !== false ||
                                                  stripos($staff['position'], 'director') !== false ||
                                                  stripos($staff['position'], 'manager') !== false;
                                    ?>
                                    <tr class="hover:bg-agri-light transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-600"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                        <?php if ($is_head): ?>
                                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                <i class="fas fa-crown mr-1"></i>Head
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($staff['position']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($staff['contact_number']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white <?php echo $staff['role_id'] == 1 ? 'bg-red-600' : 'bg-green-600'; ?>">
                                                <i class="fas <?php echo $staff['role_id'] == 1 ? 'fa-user-shield' : 'fa-user'; ?> mr-1"></i>
                                                <?php echo htmlspecialchars(ucfirst($staff['role_name'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                            <button onclick="viewStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 mr-3 transition-colors duration-200">
                                                <i class="fas fa-eye text-lg"></i>
                                            </button>
                                            <button onclick="archiveStaff(<?php echo $staff['staff_id']; ?>, '<?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>')" 
                                                    class="text-orange-600 hover:text-orange-900 transition-colors duration-200">
                                                <i class="fas fa-archive text-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center">
                                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Staff Members Found</h3>
                                            <p class="text-gray-600">There are currently no staff members in the system.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="mt-12 bg-white shadow-md p-6 w-full">
        <div class="text-center text-gray-600">
            <div class="flex items-center justify-center mb-2">
                <i class="fas fa-seedling text-agri-green mr-2"></i>
                <span class="font-semibold">Lagonglong FARMS</span>
            </div>
            <p class="text-sm">&copy; <?php echo date('Y'); ?> MAO Staff Directory. All rights reserved.</p>
        </div>
    </footer>

    <!-- View Staff Modal -->
    <div id="viewStaffModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title text-success"><i class="fas fa-user-circle me-2"></i>Staff Details</h5>
                    <button type="button" class="btn-close" onclick="closeViewModal()"></button>
                </div>
                <div class="modal-body p-0" id="viewStaffContent">
                    <!-- Content will be loaded via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div id="archiveModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 mb-4">
                    <i class="fas fa-archive text-orange-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Archive Staff Member</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Are you sure you want to archive <strong id="archive_staff_name"></strong>? 
                    This will move them to the archived staff list.
                </p>
                <div class="flex justify-center space-x-3">
                    <button onclick="closeArchiveModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        Cancel
                    </button>
                    <button onclick="confirmArchive()" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-archive mr-2"></i>Archive
                    </button>
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

        let staffToArchive = null;

        function openAddStaffModal() {
            // Placeholder function for Add Staff functionality
            alert('Add Staff functionality will be implemented here.');
            // This can be expanded to open a modal for adding new staff members
        }

        function viewStaff(staff) {
            // Build the HTML for staff details (uniform with farmers view)
            const html = `
                <div class=\"container-fluid p-0\">
                    <div class=\"bg-gradient-primary text-white p-3 rounded-top mb-3\" style=\"background: linear-gradient(135deg, #28a745, #20c997);\">
                        <div class=\"row align-items-center\">
                            <div class=\"col-auto\">
                                <div class=\"h-16 w-16 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 d-flex align-items-center justify-content-center me-3\">
                                    <i class=\"fas fa-user text-gray-600 fa-2x\"></i>
                                </div>
                            </div>
                            <div class=\"col\">
                                <h4 class=\"mb-1\"><i class=\"fas fa-user-circle me-2\"></i>${staff.first_name} ${staff.last_name}</h4>
                                <small class=\"opacity-75\"><i class=\"fas fa-id-card me-1\"></i>Staff ID: ${staff.staff_id ?? ''}</small>
                            </div>
                        </div>
                    </div>
                    <div class=\"row g-3\">
                        <div class=\"col-md-6\">
                            <div class=\"card border-0 shadow-sm h-100\">
                                <div class=\"card-header bg-light border-0\">
                                    <h6 class=\"card-title mb-0 text-success\"><i class=\"fas fa-user me-2\"></i>Personal Information</h6>
                                </div>
                                <div class=\"card-body\">
                                    <div><strong>Contact:</strong> ${staff.contact_number ?? ''}</div>
                                    <div><strong>Username:</strong> ${staff.username ?? ''}</div>
                                </div>
                            </div>
                        </div>
                        <div class=\"col-md-6\">
                            <div class=\"card border-0 shadow-sm h-100\">
                                <div class=\"card-header bg-light border-0\">
                                    <h6 class=\"card-title mb-0 text-info\"><i class=\"fas fa-briefcase me-2\"></i>Work Information</h6>
                                </div>
                                <div class=\"card-body\">
                                    <div><strong>Position:</strong> ${staff.position ?? ''}</div>
                                    <div><strong>Role:</strong> ${staff.role_name ?? staff.role ?? ''}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('viewStaffContent').innerHTML = html;
            // Show the modal (Bootstrap 5)
            var modal = new bootstrap.Modal(document.getElementById('viewStaffModal'));
            modal.show();
        }

        function closeViewModal() {
            // Use Bootstrap's API to hide the modal
            var modalEl = document.getElementById('viewStaffModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            } else {
                // fallback for manual hide if modal instance not found
                modalEl.classList.add('hidden');
            }
        }

        function archiveStaff(staffId, staffName) {
            staffToArchive = staffId;
            document.getElementById('archive_staff_name').textContent = staffName;
            document.getElementById('archiveModal').classList.remove('hidden');
        }

        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.add('hidden');
            staffToArchive = null;
        }

        function confirmArchive() {
            if (staffToArchive) {
                // Here you would typically send an AJAX request to archive the staff
                alert('Staff member archived successfully! (This would be implemented with actual archive functionality)');
                closeArchiveModal();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewStaffModal');
            const archiveModal = document.getElementById('archiveModal');
            
            if (event.target === viewModal) {
                closeViewModal();
            } else if (event.target === archiveModal) {
                closeArchiveModal();
            }
        }
    </script>
    
    <?php include 'includes/notification_complete.php'; ?>
<?php include 'includes/layout_end.php'; ?>
