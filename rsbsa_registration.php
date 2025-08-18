<?php
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle RSBSA registration form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'register_rsbsa') {
    try {
        $farmer_id = mysqli_real_escape_string($conn, $_POST['farmer_id']);
        $rsbsa_registration_number = mysqli_real_escape_string($conn, $_POST['rsbsa_registration_number']);
        $rsbsa_input_id = mysqli_real_escape_string($conn, $_POST['rsbsa_input_id']);
        $geo_reference_status = mysqli_real_escape_string($conn, $_POST['geo_reference_status']);
        $date_of_registration = mysqli_real_escape_string($conn, $_POST['date_of_registration']);
        $proof_of_registration = '';
        
        // Handle file upload for proof of registration
        if (isset($_FILES['proof_of_registration']) && $_FILES['proof_of_registration']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/rsbsa_proofs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['proof_of_registration']['name'], PATHINFO_EXTENSION);
            $filename = 'rsbsa_' . $farmer_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['proof_of_registration']['tmp_name'], $upload_path)) {
                $proof_of_registration = $upload_path;
            }
        }
        
        // Check if farmer is already registered in RSBSA
        $check_sql = "SELECT rsbsa_id FROM rsbsa_registered_farmers WHERE farmer_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $farmer_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();
        
        if ($existing->num_rows > 0) {
            $error_message = "Farmer is already registered in RSBSA.";
        } else {
            // Insert into rsbsa_registered_farmers table
            $insert_sql = "INSERT INTO rsbsa_registered_farmers 
                          (farmer_id, rsbsa_registration_number, rsbsa_input_id, geo_reference_status, 
                           date_of_registration, proof_of_registration) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param('ssssss', $farmer_id, $rsbsa_registration_number, $rsbsa_input_id, 
                            $geo_reference_status, $date_of_registration, $proof_of_registration);
            
            if ($stmt->execute()) {
                $success_message = "Farmer successfully registered in RSBSA!";
            } else {
                $error_message = "Error registering farmer in RSBSA: " . $conn->error;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Pagination settings
$farmers_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $farmers_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';

// Get barangays for filter dropdown
$barangays_sql = "SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name";
$barangays_result = $conn->query($barangays_sql);
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
    $barangays[] = $row;
}

// Build search condition for farmers NOT registered in RSBSA
$search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers) 
                     AND f.farmer_id NOT IN (SELECT farmer_id FROM rsbsa_registered_farmers)';
$search_params = [];

if (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term];
}

if (!empty($barangay_filter)) {
    $search_condition .= " AND f.barangay_id = ?";
    $search_params[] = $barangay_filter;
}

// Get total farmers for pagination
$count_sql = "SELECT COUNT(*) as total FROM farmers f $search_condition";

if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
    $count_stmt->execute();
    $total_farmers = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->execute();
    $total_farmers = $count_stmt->get_result()->fetch_assoc()['total'];
}

$total_pages = max(1, ceil($total_farmers / $farmers_per_page));

// Get farmers data with pagination
$sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, f.gender, f.birth_date, f.address_details, f.registration_date,
        f.land_area_hectares, b.barangay_name, c.commodity_name, h.household_size
        FROM farmers f 
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
        LEFT JOIN household_info h ON f.farmer_id = h.farmer_id
        $search_condition
        ORDER BY f.registration_date DESC
        LIMIT ? OFFSET ?";

if (!empty($search_params)) {
    $stmt = $conn->prepare($sql);
    $all_params = array_merge($search_params, [$farmers_per_page, $offset]);
    $types = str_repeat('s', count($search_params)) . 'ii';
    $stmt->bind_param($types, ...$all_params);
    $stmt->execute();
    $farmers_result = $stmt->get_result();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $farmers_per_page, $offset);
    $stmt->execute();
    $farmers_result = $stmt->get_result();
}

// Function to build URL parameters
function buildUrlParams($page, $search = '', $barangay = '') {
    $params = "?page=$page";
    if (!empty($search)) {
        $params .= "&search=" . urlencode($search);
    }
    if (!empty($barangay)) {
        $params .= "&barangay=" . urlencode($barangay);
    }
    return $params;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RSBSA Registration - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
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
        .agri-gradient {
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>

    <div class="container-fluid mt-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Header Section -->
            <div class="agri-gradient rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold flex items-center">
                            <i class="fas fa-user-plus text-yellow-300 mr-3"></i>
                            RSBSA Registration
                        </h1>
                        <p class="mt-2 text-green-100">Register farmers in the Registry System for Basic Sectors in Agriculture</p>
                        <div class="mt-3 text-sm text-green-100">
                            <i class="fas fa-info-circle mr-2"></i>
                            Available Farmers: <span class="font-bold text-yellow-300"><?php echo $total_farmers; ?></span>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 mt-4 md:mt-0">
                        <a href="rsbsa_records.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors flex items-center">
                            <i class="fas fa-list mr-2"></i>View RSBSA Records
                        </a>
                        <a href="farmers.php" class="bg-white text-green-900 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors flex items-center">
                            <i class="fas fa-users mr-2"></i>All Farmers
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1"></i>Search Farmers
                            </label>
                            <div class="relative">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or contact number..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>Filter by Barangay
                            </label>
                            <select name="barangay" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" 
                                            <?php echo ($barangay_filter == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="bg-agri-green text-white px-6 py-2 rounded-lg hover:bg-agri-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                            <a href="rsbsa_registration.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Farmers Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-users mr-2 text-agri-green"></i>
                        Farmers Available for RSBSA Registration
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            (Page <?php echo $page; ?> of <?php echo $total_pages; ?> - Showing <?php echo min($farmers_per_page, $total_farmers - $offset); ?> of <?php echo $total_farmers; ?> farmers)
                        </span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-agri-green text-white">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-id-card mr-1"></i>Farmer ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-user mr-1"></i>Full Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-phone mr-1"></i>Contact
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Barangay
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-seedling mr-1"></i>Commodity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-ruler-combined mr-1"></i>Land Area
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-cogs mr-1"></i>Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($farmers_result->num_rows > 0): ?>
                                <?php while ($farmer = $farmers_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-agri-light transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <span class="bg-agri-green text-white px-2 py-1 rounded text-xs">
                                                #<?php echo $farmer['farmer_id']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(trim($farmer['first_name'] . ' ' . $farmer['middle_name'] . ' ' . $farmer['last_name'] . ' ' . $farmer['suffix'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-birthday-cake mr-1"></i>
                                                <?php echo $farmer['birth_date'] ? date('M d, Y', strtotime($farmer['birth_date'])) : 'N/A'; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <i class="fas fa-phone mr-1 text-green-600"></i>
                                            <?php echo htmlspecialchars($farmer['contact_number']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <i class="fas fa-map-pin mr-1 text-red-600"></i>
                                            <?php echo htmlspecialchars($farmer['barangay_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                                <i class="fas fa-leaf mr-1"></i>
                                                <?php echo htmlspecialchars($farmer['commodity_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="font-medium">
                                                <?php echo $farmer['land_area_hectares'] ? number_format($farmer['land_area_hectares'], 2) . ' ha' : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <button onclick="openRSBSAModal('<?php echo $farmer['farmer_id']; ?>', '<?php echo htmlspecialchars(trim($farmer['first_name'] . ' ' . $farmer['middle_name'] . ' ' . $farmer['last_name'] . ' ' . $farmer['suffix'])); ?>')" 
                                                    class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition-colors text-xs">
                                                <i class="fas fa-user-plus mr-1"></i>Register RSBSA
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Farmers Available</h3>
                                            <?php if (!empty($search) || !empty($barangay_filter)): ?>
                                                <p class="text-sm text-gray-600 mb-2">No farmers match your search criteria</p>
                                                <p class="text-xs text-gray-500">Try adjusting your search terms or filters</p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-600 mb-2">All farmers are already registered in RSBSA</p>
                                                <p class="text-xs text-gray-500">or no farmers exist in the system</p>
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
                                Showing <?php echo min(($page - 1) * $farmers_per_page + 1, $total_farmers); ?> to 
                                <?php echo min($page * $farmers_per_page, $total_farmers); ?> of <?php echo $total_farmers; ?> farmers
                            </div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo buildUrlParams($page - 1, $search, $barangay_filter); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="<?php echo buildUrlParams($i, $search, $barangay_filter); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                                              <?php echo $i == $page ? 'bg-agri-green text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo buildUrlParams($page + 1, $search, $barangay_filter); ?>" 
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

    <!-- RSBSA Registration Modal -->
    <div class="modal fade" id="rsbsaRegistrationModal" tabindex="-1" aria-labelledby="rsbsaRegistrationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-agri-green text-white">
                    <h5 class="modal-title" id="rsbsaRegistrationModalLabel">
                        <i class="fas fa-user-plus mr-2"></i>RSBSA Registration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="register_rsbsa">
                        <input type="hidden" name="farmer_id" id="modal_farmer_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Farmer Information</label>
                            <div class="p-3 bg-light rounded">
                                <div class="text-muted">Farmer ID: <span id="modal_farmer_display_id" class="fw-bold text-dark"></span></div>
                                <div class="text-muted">Name: <span id="modal_farmer_name" class="fw-bold text-dark"></span></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rsbsa_registration_number" class="form-label">RSBSA Registration Number *</label>
                                    <input type="text" class="form-control" id="rsbsa_registration_number" name="rsbsa_registration_number" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rsbsa_input_id" class="form-label">RSBSA Input ID *</label>
                                    <input type="text" class="form-control" id="rsbsa_input_id" name="rsbsa_input_id" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="geo_reference_status" class="form-label">Geo Reference Status *</label>
                                    <select class="form-select" id="geo_reference_status" name="geo_reference_status" required>
                                        <option value="">Select Status</option>
                                        <option value="Completed">Completed</option>
                                        <option value="Pending">Pending</option>
                                        <option value="In Progress">In Progress</option>
                                        <option value="Not Required">Not Required</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_registration" class="form-label">Date of Registration *</label>
                                    <input type="date" class="form-control" id="date_of_registration" name="date_of_registration" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="proof_of_registration" class="form-label">Proof of Registration (Optional)</label>
                            <input type="file" class="form-control" id="proof_of_registration" name="proof_of_registration" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">Upload PDF, JPG, JPEG, or PNG files only</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save mr-1"></i>Register in RSBSA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function openRSBSAModal(farmerId, farmerName) {
            document.getElementById('modal_farmer_id').value = farmerId;
            document.getElementById('modal_farmer_display_id').textContent = farmerId;
            document.getElementById('modal_farmer_name').textContent = farmerName;
            
            const modal = new bootstrap.Modal(document.getElementById('rsbsaRegistrationModal'));
            modal.show();
        }

        // Auto-dismiss alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
