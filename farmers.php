<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'validation_functions.php';

$pageTitle = 'Farmers Management';

// Function to handle farmer archiving
function handleFarmerArchive($conn, $farmer_id, $archive_reason = null) {
    $archived_by = $_SESSION['username'] ?? 'admin';
    $archive_reason = $archive_reason ?? 'Archived by admin';
    
    try {
        // Check if farmer is already archived
        $check_stmt = $conn->prepare("SELECT archive_id FROM archived_farmers WHERE farmer_id = ?");
        $check_stmt->bind_param("s", $farmer_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Farmer is already archived!'];
        }
        
        // Archive the farmer
        $stmt = $conn->prepare("INSERT INTO archived_farmers (farmer_id, archived_by, archive_reason) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $farmer_id, $archived_by, $archive_reason);
        $stmt->execute();
        
        return ['success' => true, 'message' => 'Farmer archived successfully!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error archiving farmer: ' . $e->getMessage()];
    }
}

// Function to handle farmer editing
function handleFarmerEdit($conn, $post_data) {
    try {
        // Check for required field
        if (!isset($post_data['farmer_id']) || empty($post_data['farmer_id'])) {
            throw new Exception("Farmer ID is missing from form data");
        }
        
        // Update validation function to handle edit mode
        $validated = validateFarmerDataForEdit($post_data);
        
        // Start transaction
        $conn->autocommit(false);
        
        // Update farmers table
        $stmt = $conn->prepare("UPDATE farmers SET 
            first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
            birth_date = ?, gender = ?, contact_number = ?, barangay_id = ?, 
            address_details = ?, is_member_of_4ps = ?, is_ip = ?, other_income_source = ?,
            commodity_id = ?, land_area_hectares = ?, years_farming = ?
            WHERE farmer_id = ?");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare UPDATE statement: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssisiisidis", 
            $validated['first_name'], $validated['middle_name'], $validated['last_name'], $validated['suffix'],
            $validated['birth_date'], $validated['gender'], $validated['contact_number'], $validated['barangay_id'],
            $validated['address_details'], $validated['is_member_of_4ps'], $validated['is_ip'], $validated['other_income_source'],
            $validated['primary_commodity'], $validated['land_area_hectares'], $validated['years_farming'], 
            $validated['farmer_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute UPDATE: " . $stmt->error);
        }
        
        $farmers_updated = $stmt->affected_rows > 0;
        
        // Update or insert household_info
        $household_stmt = $conn->prepare("SELECT id FROM household_info WHERE farmer_id = ?");
        if (!$household_stmt) {
            throw new Exception("Failed to prepare household SELECT: " . $conn->error);
        }
        
        $household_stmt->bind_param("s", $validated['farmer_id']);
        $household_stmt->execute();
        $household_result = $household_stmt->get_result();
        
        if ($household_result->num_rows > 0) {
            // Update existing household info
            $update_household = $conn->prepare("UPDATE household_info SET 
                civil_status = ?, spouse_name = ?, household_size = ?, 
                education_level = ?, occupation = ? 
                WHERE farmer_id = ?");
            
            if (!$update_household) {
                throw new Exception("Failed to prepare household UPDATE: " . $conn->error);
            }
            
            $update_household->bind_param("ssisss", 
                $validated['civil_status'], $validated['spouse_name'], $validated['household_size'], 
                $validated['education_level'], $validated['occupation'], $validated['farmer_id']);
            
            if (!$update_household->execute()) {
                throw new Exception("Failed to execute household UPDATE: " . $update_household->error);
            }
        } else {
            // Insert new household info
            $insert_household = $conn->prepare("INSERT INTO household_info 
                (farmer_id, civil_status, spouse_name, household_size, education_level, occupation) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            if (!$insert_household) {
                throw new Exception("Failed to prepare household INSERT: " . $conn->error);
            }
            
            $insert_household->bind_param("sssiss", 
                $validated['farmer_id'], $validated['civil_status'], $validated['spouse_name'], $validated['household_size'], 
                $validated['education_level'], $validated['occupation']);
            
            if (!$insert_household->execute()) {
                throw new Exception("Failed to execute household INSERT: " . $insert_household->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        if ($farmers_updated) {
            return ['success' => true, 'message' => 'Farmer updated successfully!'];
        } else {
            return ['success' => false, 'message' => 'No changes were made. The farmer data may be identical to what was already saved.'];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        return ['success' => false, 'message' => 'Error updating farmer: ' . $e->getMessage()];
    }
}



// Function to handle farmer registration
function handleFarmerRegistration($conn, $post_data) {
    // Generate farmer ID using timestamp for better ordering
    // Format: F + Year + Month + Day + Hour + Minute + Second
    $farmer_id = 'F' . date('YmdHis');
    
    // Check if farmer ID already exists and generate new one if needed
    while (true) {
        $check_stmt = $conn->prepare("SELECT farmer_id FROM farmers WHERE farmer_id = ?");
        $check_stmt->bind_param("s", $farmer_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows == 0) {
            break;
        }
        // If exists, add microseconds or increment
        $farmer_id = 'F' . date('YmdHis') . substr(microtime(), 2, 3);
    }
    
    try {
        // Validate all input data using validation functions
        $validated = validateFarmerData($post_data); // removed isEdit parameter
        
        $conn->begin_transaction();
        
        // Insert farmer with all fields matching new schema including registration timestamp
        $stmt = $conn->prepare("INSERT INTO farmers 
            (farmer_id, first_name, middle_name, last_name, suffix, birth_date, gender, 
             contact_number, barangay_id, address_details, commodity_id, 
             other_income_source, is_member_of_4ps, is_ip, land_area_hectares, years_farming, registration_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->bind_param("ssssssssisissiid", 
            $farmer_id, $validated['first_name'], $validated['middle_name'], $validated['last_name'], $validated['suffix'], 
            $validated['birth_date'], $validated['gender'], $validated['contact_number'], $validated['barangay_id'], $validated['address_details'], 
            $validated['primary_commodity'], $validated['other_income_source'], $validated['is_member_of_4ps'], $validated['is_ip'], 
            $validated['land_area_hectares'], $validated['years_farming']);
        $stmt->execute();
        
        // Insert household info
        $household_stmt = $conn->prepare("INSERT INTO household_info 
            (farmer_id, civil_status, spouse_name, household_size, education_level, occupation) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $household_stmt->bind_param("sssiss", 
            $farmer_id, $validated['civil_status'], $validated['spouse_name'], $validated['household_size'], 
            $validated['education_level'], $validated['occupation']);
        $household_stmt->execute();
        
        // Handle RSBSA registration
        if (isset($post_data['rsbsa_registered']) && $post_data['rsbsa_registered'] === 'Yes') {
            $rsbsa_stmt = $conn->prepare("INSERT INTO rsbsa_registered_farmers (farmer_id) VALUES (?)");
            $rsbsa_stmt->bind_param("s", $farmer_id);
            $rsbsa_stmt->execute();
        }
        
        // Handle NCFRS registration
        if (isset($post_data['ncfrs_registered']) && $post_data['ncfrs_registered'] === 'Yes') {
            $ncfrs_registration_number = !empty($post_data['ncfrs_registration_number']) ? trim($post_data['ncfrs_registration_number']) : '';
            $ncfrs_stmt = $conn->prepare("INSERT INTO ncfrs_registered_farmers (farmer_id, ncfrs_registration_number) VALUES (?, ?)");
            $ncfrs_stmt->bind_param("ss", $farmer_id, $ncfrs_registration_number);
            $ncfrs_stmt->execute();
        }
        
        // Handle Fisherfolk registration
        if (isset($post_data['fisherfolk_registered']) && $post_data['fisherfolk_registered'] === 'Yes') {
            $fisherfolk_registration_number = !empty($post_data['fisherfolk_registration_number']) ? trim($post_data['fisherfolk_registration_number']) : '';
            $vessel_id = !empty($post_data['vessel_id']) ? intval($post_data['vessel_id']) : 1;
            $fisherfolk_stmt = $conn->prepare("INSERT INTO fisherfolk_registered_farmers (farmer_id, fisherfolk_registration_number, vessel_id) VALUES (?, ?, ?)");
            $fisherfolk_stmt->bind_param("ssi", $farmer_id, $fisherfolk_registration_number, $vessel_id);
            $fisherfolk_stmt->execute();
        }
        
        $conn->commit();
        return ['success' => true, 'message' => "Farmer registered successfully! Farmer ID: " . $farmer_id];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => "Error registering farmer: " . $e->getMessage()];
    }
}

// Handle farmer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'archive':
                $result = handleFarmerArchive($conn, $_POST['farmer_id'], $_POST['archive_reason'] ?? null);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
                
            case 'edit':
                $result = handleFarmerEdit($conn, $_POST);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
                
            case 'register_farmer':
                $result = handleFarmerRegistration($conn, $_POST);
                if ($result['success']) {
                    $_SESSION['success_message'] = $result['message'];
                } else {
                    $_SESSION['error_message'] = $result['message'];
                }
                // Redirect to prevent resubmission
                header("Location: farmers.php");
                exit();
                break;
        }
    }
}

// Get messages from session and clear them
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
$search_params = [];

if (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term];
}

// Get total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM farmers f $search_condition";
if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
} else {
    $count_stmt = $conn->prepare($count_sql);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get farmers data with commodity and household information
$sql = "SELECT f.*, c.commodity_name, b.barangay_name, h.civil_status, h.spouse_name, 
               h.household_size, h.education_level, h.occupation
        FROM farmers f 
        LEFT JOIN commodities c ON f.commodity_id = c.commodity_id 
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN household_info h ON f.farmer_id = h.farmer_id
        $search_condition 
        ORDER BY f.registration_date DESC, f.farmer_id DESC 
        LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $all_params = array_merge($search_params, [$records_per_page, $offset]);
    $stmt->bind_param(str_repeat('s', count($search_params)) . 'ii', ...$all_params);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $records_per_page, $offset);
}

$stmt->execute();
$farmers = $stmt->get_result();

// Get commodities for edit form
$commodities_result = $conn->query("SELECT * FROM commodities ORDER BY commodity_name");

// Get barangays for form
$barangays_result = $conn->query("SELECT * FROM barangays ORDER BY barangay_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#22c55e',
                        'agri-dark': '#16a34a',
                        'agri-light': '#dcfce7'
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom dropdown styles */
        #dropdownMenu {
            display: none;
            z-index: 50;
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

        /* Edit Modal Styles - Match Registration Modal */
        .modal-lg {
            max-width: 800px;
        }

        .card-header {
            font-weight: 600;
        }

        .form-label {
            font-weight: 500;
        }

        .text-danger {
            font-weight: bold;
        }

        .alert-info {
            border-left: 4px solid #17a2b8;
        }
    </style>
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
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>

    <!-- Alert Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show mx-6 mt-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-6 mt-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-users text-agri-green mr-3"></i>
                            Farmers Management
                        </h1>
                        <p class="text-gray-600 mt-2">Manage and monitor all registered farmers</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button data-bs-toggle="modal" data-bs-target="#farmerRegistrationModal"
                                class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-plus mr-2"></i>Add New Farmer
                        </button>
                        <button onclick="exportFarmers()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center">
                            <i class="fas fa-download mr-2"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Farmers</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search by name, mobile, or email..." 
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3 items-end">
                        <button type="submit" class="bg-agri-green text-white px-6 py-2 rounded-lg hover:bg-agri-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <a href="farmers.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Farmers Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Farmers List 
                        <?php if (!empty($search)): ?>
                            <span class="text-sm font-normal text-gray-600">- Search results for "<?php echo htmlspecialchars($search); ?>"</span>
                        <?php endif; ?>
                    </h3>
                </div>

                <div class="overflow-hidden">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Farmer</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Contact</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Location</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Commodity</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">RegDate</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($farmers->num_rows > 0): ?>
                                <?php while ($farmer = $farmers->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-3 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-8 w-8">
                                                    <div class="h-8 w-8 rounded-full bg-agri-green flex items-center justify-center">
                                                        <span class="text-white font-medium text-sm">
                                                            <?php echo strtoupper(substr($farmer['first_name'], 0, 1) . substr($farmer['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?>
                                                        <?php if (!empty($farmer['suffix']) && strtolower($farmer['suffix']) !== 'n/a'): ?>
                                                            <?php echo ' ' . htmlspecialchars($farmer['suffix']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo ucfirst($farmer['gender']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="text-sm text-gray-900">
                                                <?php if (!empty($farmer['contact_number'])): ?>
                                                    <div class="flex items-center"><i class="fas fa-mobile-alt mr-1 text-gray-400 text-xs"></i><span class="text-xs"><?php echo htmlspecialchars($farmer['contact_number']); ?></span></div>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">No contact</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="text-sm text-gray-900 font-medium">
                                                <?php echo htmlspecialchars($farmer['barangay_name'] ?? 'Not specified'); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 break-words max-w-xs">
                                                <?php echo htmlspecialchars($farmer['address_details'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-agri-light text-agri-dark">
                                                <?php echo htmlspecialchars($farmer['commodity_name'] ?? 'Not specified'); ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 text-xs text-gray-500">
                                            <?php 
                                            if (!empty($farmer['registration_date'])) {
                                                echo date('M j, Y', strtotime($farmer['registration_date'])) . '<br>';
                                                echo '<span class="text-gray-400">' . date('g:i A', strtotime($farmer['registration_date'])) . '</span>';
                                            } else {
                                                echo 'Not available';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-3 py-4 text-sm font-medium">
                                            <div class="flex space-x-2">
                                                <button onclick="viewFarmer('<?php echo $farmer['farmer_id']; ?>')" 
                                                        class="text-blue-600 hover:text-blue-900 transition-colors p-2 rounded hover:bg-blue-50" title="View">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <button onclick="editFarmer('<?php echo $farmer['farmer_id']; ?>')" 
                                                        class="text-agri-green hover:text-agri-dark transition-colors p-2 rounded hover:bg-green-50" title="Edit">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>
                                                <button onclick="archiveFarmer('<?php echo $farmer['farmer_id']; ?>', '<?php echo htmlspecialchars($farmer['first_name'] . ' ' . $farmer['last_name']); ?>')" 
                                                        class="text-orange-600 hover:text-orange-900 transition-colors p-2 rounded hover:bg-orange-50" title="Archive">
                                                    <i class="fas fa-archive text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center py-8">
                                            <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                                            <p class="text-lg">No farmers found</p>
                                            <?php if (!empty($search)): ?>
                                                <p class="text-sm">Try adjusting your search criteria</p>
                                            <?php else: ?>
                                                <p class="text-sm">Get started by adding your first farmer</p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-700">
                                Showing <?php echo min(($page - 1) * $records_per_page + 1, $total_records); ?> to 
                                <?php echo min($page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> results
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo $i == $page ? 'bg-agri-green text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Farmer Modal -->
    <div class="modal fade" id="viewFarmerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Farmer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewFarmerContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Confirmation Modal -->
    <div class="modal fade" id="archiveConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Archive</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to archive farmer <strong id="farmerNameToArchive"></strong>?</p>
                    <p class="text-info"><small>The farmer will be moved to archives but all data will be preserved. You can restore them later if needed.</small></p>
                    
                    <div class="mb-3">
                        <label for="archiveReason" class="form-label">Reason for archiving (optional):</label>
                        <textarea class="form-control" id="archiveReason" name="archive_reason" rows="3" placeholder="Enter reason for archiving this farmer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="farmer_id" id="farmerIdToArchive">
                        <input type="hidden" name="archive_reason" id="archiveReasonHidden">
                        <button type="submit" class="btn btn-warning" onclick="document.getElementById('archiveReasonHidden').value = document.getElementById('archiveReason').value;">Archive Farmer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFarmer(farmerId) {
            fetch(`farmer_details.php?id=${farmerId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewFarmerContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewFarmerModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading farmer details');
                });
        }

        function archiveFarmer(farmerId, farmerName) {
            document.getElementById('farmerIdToArchive').value = farmerId;
            document.getElementById('farmerNameToArchive').textContent = farmerName;
            new bootstrap.Modal(document.getElementById('archiveConfirmModal')).show();
        }

        function exportFarmers() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            window.location.href = `export_farmers.php?search=${encodeURIComponent(search)}`;
        }

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>

    <!-- Include Farmer Registration Modal -->
    <?php include 'farmer_regmodal.php'; ?>
    
    <!-- Include Farmer Edit Modal -->
    <?php include 'farmer_editmodal.php'; ?>
</body>
</html>
