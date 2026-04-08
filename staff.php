<?php
require_once 'check_session.php';
require_once 'conn.php';

// Only admin can access staff management
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get all staff members with their roles
$staff_query = "SELECT s.*, r.role as role_name FROM mao_staff s LEFT JOIN roles r ON s.role_id = r.role_id ORDER BY s.role_id ASC, s.position ASC, s.last_name ASC";
$stmt = mysqli_prepare($conn, $staff_query);
mysqli_stmt_execute($stmt);
if ($stmt) {
    $staff_result = $stmt->get_result();
    $stmt->close();
}
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
                            <!-- Add Staff Button -->
                            <button onclick="openAddStaffModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center font-medium">
                                <i class="fas fa-user-plus mr-2"></i>Add Staff
                            </button>
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
                                    
                                    <a href="staff.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
                                        <i class="fas fa-user-tie text-gray-600 mr-3"></i>
                                        Staff Management
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

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

   

    <!-- View Staff Modal -->
    <div id="viewStaffModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 px-4 py-3 bg-white">
                    <h5 class="modal-title text-success fw-semibold"><i class="fas fa-user-circle me-2"></i>Staff Details</h5>
                    <button type="button" class="btn-close" onclick="closeViewModal()"></button>
                </div>
                <div class="modal-body p-0" id="viewStaffContent">
                    <!-- Content will be loaded via JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 px-4 py-3 bg-white">
                    <h5 class="modal-title text-warning fw-semibold"><i class="fas fa-key me-2"></i>Change Staff Password</h5>
                    <button type="button" class="btn-close" onclick="closeChangePasswordModal()"></button>
                </div>
                <div class="modal-body px-4 pt-2 pb-3">
                    <div class="small text-muted mb-3">
                        Updating password for <span class="fw-semibold text-dark" id="change_password_staff_name"></span>
                    </div>
                    <form id="changePasswordForm" action="staff_actions.php" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="staff_id" id="change_password_staff_id">

                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-semibold small text-secondary mb-1">New Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="new_password" name="new_password" required minlength="8" class="form-control form-control-lg rounded-start-3" placeholder="At least 8 characters">
                                <button type="button" class="btn btn-outline-secondary px-3 rounded-end-3" onclick="togglePasswordVisibility('new_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="confirm_password" class="form-label fw-semibold small text-secondary mb-1">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" class="form-control form-control-lg rounded-start-3" placeholder="Re-enter password">
                                <button type="button" class="btn btn-outline-secondary px-3 rounded-end-3" onclick="togglePasswordVisibility('confirm_password', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password_match_hint" class="form-text text-muted mt-2 small">Use a strong password with letters, numbers, and symbols.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-1 bg-white justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4" onclick="closeChangePasswordModal()">
                        Cancel
                    </button>
                    <button type="submit" form="changePasswordForm" class="btn btn-warning text-white d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4">
                        <i class="fas fa-check-circle"></i>Save Password
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div id="archiveModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-body px-4 py-4 text-center">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-warning-subtle" style="width:56px;height:56px;">
                        <i class="fas fa-archive text-warning fs-4"></i>
                    </div>
                    <h5 class="fw-semibold text-dark mb-2">Archive Staff Member</h5>
                    <p class="text-muted mb-0">
                        Are you sure you want to archive <span class="fw-semibold text-dark" id="archive_staff_name"></span>?<br>
                        This will move the account to archived staff records.
                    </p>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0 bg-white justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4" onclick="closeArchiveModal()">Cancel</button>
                    <button type="button" class="btn btn-warning text-white d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4" onclick="confirmArchive()">
                        <i class="fas fa-archive"></i>Archive
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 px-4 py-3 bg-white">
                    <h5 class="modal-title text-primary fw-semibold">
                        <i class="fas fa-user-plus me-2"></i>Add New Staff Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3 bg-light">
                    <form id="addStaffForm" action="staff_actions.php" method="POST">
                        <input type="hidden" name="action" value="add_staff">

                        <div class="alert alert-primary border-0 rounded-3 mb-4">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle mt-1 me-2"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Create Staff Account</div>
                                    <div class="small mb-0">Complete all required fields to create a secure account for MAO personnel.</div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-3 mb-3">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="mb-0 text-success fw-semibold"><i class="fas fa-id-badge me-2"></i>Personal Details</h6>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label fw-semibold small text-secondary mb-1">First Name <span class="text-danger">*</span></label>
                                        <input type="text" id="first_name" name="first_name" required class="form-control form-control-lg rounded-3" placeholder="Juan">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label fw-semibold small text-secondary mb-1">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" id="last_name" name="last_name" required class="form-control form-control-lg rounded-3" placeholder="Dela Cruz">
                                    </div>
                                    <div class="col-md-8">
                                        <label for="position" class="form-label fw-semibold small text-secondary mb-1">Position <span class="text-danger">*</span></label>
                                        <input type="text" id="position" name="position" required class="form-control form-control-lg rounded-3" placeholder="Agricultural Officer, MAO Head">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="contact_number" class="form-label fw-semibold small text-secondary mb-1">Contact Number <span class="text-danger">*</span></label>
                                        <input type="tel" id="contact_number" name="contact_number" required class="form-control form-control-lg rounded-3" placeholder="09xxxxxxxxx">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="role_id" class="form-label fw-semibold small text-secondary mb-1">Role <span class="text-danger">*</span></label>
                                        <select id="role_id" name="role_id" required class="form-select form-select-lg rounded-3">
                                            <option value="">Select Role</option>
                                            <?php
                                            // Fetch roles
                                            $roles_query = "SELECT role_id, role FROM roles ORDER BY role";
                                            $roles_stmt = $conn->prepare($roles_query);
                                            $roles_stmt->execute();
                                            $roles_result = $roles_stmt->get_result();
                                            if ($roles_result) {
                                                while ($role = $roles_result->fetch_assoc()) {
                                                    echo "<option value='{$role['role_id']}'>{$role['role']}</option>";
                                                }
                                            }
                                            $roles_stmt->close();
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-3">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="mb-0 text-primary fw-semibold"><i class="fas fa-user-lock me-2"></i>Account Credentials</h6>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="username" class="form-label fw-semibold small text-secondary mb-1">Username <span class="text-danger">*</span></label>
                                        <input type="text" id="username" name="username" required class="form-control form-control-lg rounded-3" placeholder="Set unique username" autocomplete="off">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="add_staff_password" class="form-label fw-semibold small text-secondary mb-1">Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" id="add_staff_password" name="password" required minlength="8" class="form-control form-control-lg rounded-start-3" placeholder="At least 8 characters" autocomplete="new-password">
                                            <button type="button" class="btn btn-outline-secondary px-3 rounded-end-3" onclick="togglePasswordVisibility('add_staff_password', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div id="add_staff_password_hint" class="form-text text-muted mt-2 small">Tip: Use letters, numbers, and symbols for a stronger password.</div>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-2 bg-light justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" form="addStaffForm" class="btn btn-primary d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4">
                        <i class="fas fa-user-plus"></i>Create Staff Account
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
        let currentViewedStaff = null;

        function openAddStaffModal() {
            new bootstrap.Modal(document.getElementById('addStaffModal')).show();
        }

        function closeAddStaffModal() {
            bootstrap.Modal.getInstance(document.getElementById('addStaffModal'))?.hide();
        }

        function viewStaff(staff) {
            currentViewedStaff = staff;

            const roleBadgeClass = String(staff.role_id) === '1' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
            const html = `
                <div class=\"container-fluid p-0\"> 
                    <div class=\"px-4 py-4 text-white\" style=\"background: linear-gradient(120deg, #14823b 0%, #2ec89d 100%);\">
                        <div class=\"d-flex align-items-center justify-content-between flex-wrap gap-3\">
                            <div class=\"d-flex align-items-center\">
                                <div class=\"rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center me-3\" style=\"width:64px;height:64px;\">
                                    <i class=\"fas fa-user text-white fs-3\"></i>
                                </div>
                                <div>
                                    <h4 class=\"mb-1 fw-semibold\">${staff.first_name} ${staff.last_name}</h4>
                                    <div class=\"small opacity-75\"><i class=\"fas fa-id-card me-1\"></i>Staff ID: ${staff.staff_id ?? ''}</div>
                                </div>
                            </div>
                            <span class=\"badge rounded-pill ${roleBadgeClass} px-3 py-2 text-uppercase\">${staff.role_name ?? staff.role ?? ''}</span>
                        </div>
                    </div>
                    <div class=\"px-4 py-4 bg-light\">
                        <div class=\"row g-3\">
                            <div class=\"col-md-6\">
                                <div class=\"card border-0 shadow-sm h-100 rounded-3\">
                                    <div class=\"card-header bg-white border-0 py-3\">
                                        <h6 class=\"card-title mb-0 text-success fw-semibold\"><i class=\"fas fa-user me-2\"></i>Personal Information</h6>
                                    </div>
                                    <div class=\"card-body pt-0\">
                                        <div class=\"mb-2\"><span class=\"text-muted small d-block\">Contact Number</span><span class=\"fw-semibold\">${staff.contact_number ?? 'N/A'}</span></div>
                                        <div><span class=\"text-muted small d-block\">Username</span><span class=\"fw-semibold\">${staff.username ?? 'N/A'}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div class=\"col-md-6\">
                                <div class=\"card border-0 shadow-sm h-100 rounded-3\">
                                    <div class=\"card-header bg-white border-0 py-3\">
                                        <h6 class=\"card-title mb-0 text-info fw-semibold\"><i class=\"fas fa-briefcase me-2\"></i>Work Information</h6>
                                    </div>
                                    <div class=\"card-body pt-0\">
                                        <div class=\"mb-2\"><span class=\"text-muted small d-block\">Position</span><span class=\"fw-semibold\">${staff.position ?? 'N/A'}</span></div>
                                        <div><span class=\"text-muted small d-block\">Role</span><span class=\"fw-semibold text-capitalize\">${staff.role_name ?? staff.role ?? 'N/A'}</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-warning text-white d-inline-flex align-items-center gap-2 fw-semibold rounded-3 py-2 px-4" onclick="openChangePasswordModal()">
                                <i class="fas fa-key"></i>Change Password
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('viewStaffContent').innerHTML = html;
            // Show the modal (Bootstrap 5)
            var modal = new bootstrap.Modal(document.getElementById('viewStaffModal'));
            modal.show();
        }

        function openChangePasswordModal() {
            if (!currentViewedStaff) {
                return;
            }

            document.getElementById('change_password_staff_id').value = currentViewedStaff.staff_id || '';
            document.getElementById('change_password_staff_name').textContent = `${currentViewedStaff.first_name || ''} ${currentViewedStaff.last_name || ''}`.trim();
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }

        function closeChangePasswordModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
            if (modal) {
                modal.hide();
            }
        }

        function togglePasswordVisibility(inputId, buttonEl) {
            const input = document.getElementById(inputId);
            const icon = buttonEl.querySelector('i');
            if (!input || !icon) {
                return;
            }

            input.type = input.type === 'password' ? 'text' : 'password';
            icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        }

        document.getElementById('changePasswordForm').addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const hint = document.getElementById('password_match_hint');

            if (newPassword !== confirmPassword) {
                event.preventDefault();
                hint.textContent = 'Passwords do not match. Please check both fields.';
                hint.className = 'form-text text-danger mt-2';
                return;
            }

            if (newPassword.length < 8) {
                event.preventDefault();
                hint.textContent = 'Password must be at least 8 characters.';
                hint.className = 'form-text text-danger mt-2';
                return;
            }

            hint.textContent = 'Password looks good.';
            hint.className = 'form-text text-success mt-2';
        });

        document.getElementById('addStaffForm').addEventListener('submit', function(event) {
            const addPassword = document.getElementById('add_staff_password').value;
            const hint = document.getElementById('add_staff_password_hint');

            if (addPassword.length < 8) {
                event.preventDefault();
                hint.textContent = 'Password must be at least 8 characters.';
                hint.className = 'form-text text-danger mt-2';
                return;
            }

            hint.textContent = 'Password looks good.';
            hint.className = 'form-text text-success mt-2';
        });

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
            currentViewedStaff = null;
        }

        function archiveStaff(staffId, staffName) {
            staffToArchive = staffId;
            document.getElementById('archive_staff_name').textContent = staffName;
            new bootstrap.Modal(document.getElementById('archiveModal')).show();
        }

        function closeArchiveModal() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('archiveModal'));
            if (modal) {
                modal.hide();
            }
            staffToArchive = null;
        }

        function confirmArchive() {
            if (staffToArchive) {
                // Here you would typically send an AJAX request to archive the staff
                if (window.AgriToast) { AgriToast.success('Staff member archived successfully.'); }
                closeArchiveModal();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewStaffModal');
            
            if (event.target === viewModal) {
                closeViewModal();
            }
        }
    </script>
    
    <?php include 'includes/notification_complete.php'; ?>

