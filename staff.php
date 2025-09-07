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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MAO Staff Directory - Lagonglong FARMS</title>
    <?php include 'includes/assets.php'; ?>
    
    
    
    <style>
        .staff-card {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff, #f8fafc);
        }
        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .role-badge-admin {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .role-badge-staff {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }
        .position-head {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
        }
        .stats-card {
            background: linear-gradient(145deg, #ffffff, #f1f5f9);
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'nav.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-lg shadow-md p-6 mb-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-10 rounded-full -ml-12 -mb-12"></div>
                <div class="relative">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold mb-2 flex items-center">
                                <i class="fas fa-users-cog mr-3"></i>
                                MAO Staff Directory
                            </h1>
                            <p class="text-lg text-green-100">Municipal Agriculture Office Personnel</p>
                            <div class="flex items-center text-green-200 mt-2">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span><?php echo date('l, F j, Y'); ?></span>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <i class="fas fa-user-tie text-6xl text-white opacity-20"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Data Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-agri-green to-agri-dark">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Contact Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Actions</th>
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
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
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
    <div id="viewStaffModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full hidden z-50 transition-all duration-300">
        <div class="relative top-4 mx-auto p-0 w-11/12 md:w-4/5 lg:w-3/5 xl:w-1/2 max-w-3xl">
            <div class="bg-white rounded-xl shadow-2xl overflow-hidden transform transition-all duration-300 hover:scale-[1.01]">
                <!-- Modal Header with Gradient Background -->
                <div class="bg-gradient-to-r from-agri-green to-agri-dark p-4 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-10 rounded-full -mr-12 -mt-12"></div>
                    <div class="absolute bottom-0 left-0 w-16 h-16 bg-white opacity-10 rounded-full -ml-8 -mb-8"></div>
                    <div class="relative flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user-circle text-xl text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Staff Profile</h3>
                                <p class="text-green-100 text-xs">Detailed Information</p>
                            </div>
                        </div>
                        <button onclick="closeViewModal()" class="text-white hover:text-green-200 transition-colors duration-200 p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-5 bg-gradient-to-br from-gray-50 to-white">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                        <!-- Profile Section -->
                        <div class="lg:col-span-1">
                            <div class="text-center bg-white rounded-xl shadow-md p-4 hover:shadow-lg transition-shadow duration-300">
                                <div class="relative mb-3">
                                    <div class="w-20 h-20 mx-auto bg-gradient-to-br from-agri-green to-agri-dark rounded-full flex items-center justify-center shadow-md">
                                        <i class="fas fa-user text-3xl text-white"></i>
                                    </div>
                                    <div id="modal_head_badge_top" class="absolute -top-1 -right-1 hidden">
                                        <div class="w-7 h-7 bg-yellow-400 rounded-full flex items-center justify-center shadow-md">
                                            <i class="fas fa-crown text-yellow-800 text-xs"></i>
                                        </div>
                                    </div>
                                </div>
                                <h4 id="modal_staff_name" class="text-lg font-bold text-gray-900 mb-1"></h4>
                                <p id="modal_staff_position" class="text-sm text-gray-600 mb-3 font-medium"></p>
                                <div id="modal_head_badge" class="hidden mb-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-yellow-400 to-yellow-500 text-yellow-900 shadow-sm">
                                        <i class="fas fa-crown mr-1"></i>Head
                                    </span>
                                </div>
                                <div class="pt-3 border-t border-gray-200">
                                    <div class="flex items-center justify-center text-xs text-gray-600">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1 animate-pulse"></span>
                                        Active
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Details Section -->
                        <div class="lg:col-span-2 space-y-4">
                            <!-- Personal Information Card -->
                            <div class="bg-white rounded-xl shadow-md p-4 hover:shadow-lg transition-all duration-300 border-l-4 border-blue-500">
                                <div class="flex items-center mb-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <h5 class="text-lg font-bold text-gray-800">Personal Info</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">First Name</label>
                                        <p id="modal_first_name" class="text-sm font-medium text-gray-900 mt-1"></p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Last Name</label>
                                        <p id="modal_last_name" class="text-sm font-medium text-gray-900 mt-1"></p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200 md:col-span-2">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Contact</label>
                                        <p id="modal_contact_number" class="text-sm font-medium text-gray-900 mt-1 flex items-center">
                                            <i class="fas fa-phone text-green-600 mr-2 text-xs"></i>
                                            <span></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Work Information Card -->
                            <div class="bg-white rounded-xl shadow-md p-4 hover:shadow-lg transition-all duration-300 border-l-4 border-green-500">
                                <div class="flex items-center mb-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-2">
                                        <i class="fas fa-briefcase text-green-600 text-sm"></i>
                                    </div>
                                    <h5 class="text-lg font-bold text-gray-800">Work Info</h5>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Position</label>
                                        <p id="modal_position" class="text-sm font-medium text-gray-900 mt-1"></p>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Role</label>
                                        <div class="mt-1">
                                            <span id="modal_role" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold text-white shadow-sm">
                                                <i class="fas fa-user-shield mr-1"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors duration-200 md:col-span-2">
                                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Username</label>
                                        <p id="modal_username" class="text-sm font-mono bg-white px-2 py-1 rounded border mt-1 flex items-center">
                                            <i class="fas fa-at text-gray-500 mr-1 text-xs"></i>
                                            <span></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="bg-gray-50 px-5 py-3 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Updated: <?php echo date('M j, Y'); ?>
                        </div>
                        <div>
                            <button onclick="closeViewModal()" class="px-4 py-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 shadow-sm hover:shadow-md text-sm">
                                <i class="fas fa-times mr-1"></i>Close
                            </button>
                        </div>
                    </div>
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
        let staffToArchive = null;

        function viewStaff(staff) {
            // Populate modal with staff data
            document.getElementById('modal_staff_name').textContent = staff.first_name + ' ' + staff.last_name;
            document.getElementById('modal_staff_position').textContent = staff.position;
            document.getElementById('modal_first_name').textContent = staff.first_name;
            document.getElementById('modal_last_name').textContent = staff.last_name;
            
            // Update contact number with phone icon
            const contactElement = document.getElementById('modal_contact_number').querySelector('span');
            contactElement.textContent = staff.contact_number;
            
            document.getElementById('modal_position').textContent = staff.position;
            
            // Update username with @ icon
            const usernameElement = document.getElementById('modal_username').querySelector('span');
            usernameElement.textContent = staff.username;
            
            // Set role badge with icon
            const roleElement = document.getElementById('modal_role');
            const roleIcon = staff.role_id == 1 ? 'fa-user-shield' : 'fa-user';
            roleElement.innerHTML = `<i class="fas ${roleIcon} mr-2"></i>${staff.role_name.charAt(0).toUpperCase() + staff.role_name.slice(1)}`;
            roleElement.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold text-white shadow-md ' + 
                                  (staff.role_id == 1 ? 'bg-gradient-to-r from-red-500 to-red-600' : 'bg-gradient-to-r from-green-500 to-green-600');
            
            // Show head badges if applicable
            const headBadge = document.getElementById('modal_head_badge');
            const headBadgeTop = document.getElementById('modal_head_badge_top');
            const isHead = staff.position.toLowerCase().includes('head') || 
                          staff.position.toLowerCase().includes('officer') ||
                          staff.position.toLowerCase().includes('chief') ||
                          staff.position.toLowerCase().includes('director') ||
                          staff.position.toLowerCase().includes('manager');
            
            if (isHead) {
                headBadge.classList.remove('hidden');
                headBadgeTop.classList.remove('hidden');
            } else {
                headBadge.classList.add('hidden');
                headBadgeTop.classList.add('hidden');
            }
            
            // Show modal with animation
            const modal = document.getElementById('viewStaffModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.querySelector('.relative').classList.add('animate-in');
            }, 10);
        }

        function closeViewModal() {
            const modal = document.getElementById('viewStaffModal');
            modal.classList.add('hidden');
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
</body>
</html>
