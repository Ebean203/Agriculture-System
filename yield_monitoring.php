
<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
$pageTitle = 'Yield Monitoring - Lagonglong FARMS';

// Check if yield_monitoring table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'yield_monitoring'");
if (mysqli_num_rows($table_check) == 0) {
    die("Error: yield_monitoring table does not exist. Please import the yield_monitoring.sql file first.");
}

// Get commodities for dropdown
$commodities = [];
$commodities_query = mysqli_query($conn, "SELECT commodity_id, commodity_name FROM commodities ORDER BY commodity_name");
if ($commodities_query) {
    while ($row = mysqli_fetch_assoc($commodities_query)) {
        $commodities[] = $row;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_visit') {
    $farmer_id = $_POST['farmer_id'] ?? '';
    $commodity_id = $_POST['commodity_id'] ?? '';
    $season = $_POST['season'] ?? '';
    $yield_amount = $_POST['yield_amount'] ?? '';
    
    // Validate required fields
    $errors = [];
    if (empty($farmer_id)) $errors[] = 'Farmer selection is required';
    if (empty($commodity_id)) $errors[] = 'Commodity selection is required';
    if (empty($season)) $errors[] = 'Season is required';
    if (empty($yield_amount)) $errors[] = 'Yield amount is required';
    
    if (empty($errors)) {
        // Insert into database using existing yield_monitoring table structure
        $sql = "INSERT INTO yield_monitoring (
            farmer_id, commodity_id, season, yield_amount, record_date, recorded_by_staff_id
        ) VALUES (?, ?, ?, ?, NOW(), ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sisii", 
                $farmer_id, $commodity_id, $season, $yield_amount, $_SESSION['user_id']
            );
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = "Yield record added successfully!";
                // Redirect to prevent form resubmission on refresh
                header("Location: yield_monitoring.php");
                exit();
            } else {
                $errors[] = "Error recording yield: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
    }
}

// Get messages from session and clear them
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch yield records from database
$yield_records = [];
$total_records = 0;

try {
    // Check if yield_monitoring table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'yield_monitoring'");
    if (mysqli_num_rows($table_check) == 0) {
        die("Error: yield_monitoring table does not exist. Please create the table first.");
    }
    
    // Fetch yield records with farmer and commodity information
    $query = "SELECT ym.*, f.first_name, f.last_name, f.middle_name, f.contact_number, 
                     b.barangay_name, c.commodity_name
              FROM yield_monitoring ym
              LEFT JOIN farmers f ON ym.farmer_id = f.farmer_id
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
              LEFT JOIN commodities c ON ym.commodity_id = c.commodity_id
              ORDER BY ym.record_date DESC";
    
    $result = mysqli_query($conn, $query);
    if ($result) {
        $yield_records = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $total_records = count($yield_records);
    }
} catch (Exception $e) {
    $errors[] = "Error fetching yield records: " . $e->getMessage();
}

include 'includes/layout_start.php';
?>

<main class="app-content">
    <!-- Success/Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-chart-line text-agri-green mr-3"></i>
                    Yield Monitoring
                </h1>
                <p class="text-gray-600 mt-2">Track and monitor agricultural yield from distributed inputs</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" onclick="openModal('addVisitModal')">
                    <i class="fas fa-plus mr-2"></i>Record Visit
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <!-- Total Visits -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-eye text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php echo $total_records; ?></h3>
                    <p class="text-gray-600">Total Visits</p>
                </div>
            </div>
        </div>

        <!-- Fry/Fingerlings -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-cyan-500">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-fish text-cyan-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        $fry_count = 0;
                        foreach ($yield_records as $record) {
                            if (stripos($record['commodity_name'] ?? '', 'fry') !== false || 
                                stripos($record['commodity_name'] ?? '', 'fingerling') !== false) {
                                $fry_count++;
                            }
                        }
                        echo $fry_count;
                    ?></h3>
                    <p class="text-gray-600">Fry/Fingerlings</p>
                </div>
            </div>
        </div>

    <!-- Poultry & Livestock -->
    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                <i class="fas fa-paw text-orange-600 text-xl"></i>
            </div>
            <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        $livestock_count = 0;
                        foreach ($yield_records as $record) {
                            if (stripos($record['commodity_name'] ?? '', 'chicken') !== false || 
                                stripos($record['commodity_name'] ?? '', 'livestock') !== false ||
                                stripos($record['commodity_name'] ?? '', 'poultry') !== false) {
                                $livestock_count++;
                            }
                        }
                        echo $livestock_count;
                    ?></h3>
                <p class="text-gray-600">Poultry & Livestock</p>
            </div>
        </div>
    </div>

        <!-- Average Yield -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-agri-green">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-agri-light rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-seedling text-agri-green text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        if ($total_records > 0) {
                            $total_yield = array_sum(array_column($yield_records, 'yield_amount'));
                            $avg_yield = $total_yield / $total_records;
                            echo number_format($avg_yield, 1) . ' sacks';
                        } else {
                            echo '0 sacks';
                        }
                    ?></h3>
                    <p class="text-gray-600">Average Yield</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-wrap gap-3 mb-6">
            <button class="filter-tab active bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" data-filter="all">
                <i class="fas fa-list mr-2"></i>All Visits
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="seeds">
                <i class="fas fa-seedling mr-2"></i>Seeds
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="livestock">
                <i class="fas fa-horse mr-2"></i>Livestock
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="fry">
                <i class="fas fa-fish mr-2"></i>Fry/Fingerlings
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="chicken">
                <i class="fas fa-egg mr-2"></i>Chicken
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="tools">
                <i class="fas fa-wrench mr-2"></i>Agricultural Tools
            </button>
        </div>
    </div>

    <!-- Search and Date Range Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-6">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-800 mb-2">Search Farmer</label>
                <div class="relative">
                    <input type="text" placeholder="Search by farmer name..." class="search-input w-full px-4 py-2 pl-4 bg-gray-100 border border-gray-200 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500"></i>
                </div>
            </div>
            <div class="sm:w-48">
                <label class="block text-sm font-semibold text-gray-800 mb-2">Date Range</label>
                <select class="date-select w-full px-4 py-2 bg-white border-2 border-gray-800 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                    <option>All Dates</option>
                    <option>Today</option>
                    <option>This Week</option>
                    <option>This Month</option>
                    <option>This Year</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if ($total_records > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Farmer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commodity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Season</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yield Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Recorded</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($yield_records as $record): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-agri-light flex items-center justify-center">
                                                <i class="fas fa-user text-agri-green"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(trim($record['first_name'] . ' ' . $record['last_name'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($record['barangay_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($record['commodity_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($record['season']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($record['yield_amount'], 2); ?> sacks
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($record['record_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-agri-green hover:text-agri-dark mr-3" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-800 mr-3" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-4">
                <div class="flex-1 flex justify-between sm:hidden">
                    <a href="#" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <a href="#" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo $total_records; ?></span> of <span class="font-medium"><?php echo $total_records; ?></span> results
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-chart-line text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No yield records found</h3>
                <p class="text-gray-500">Start by recording your first visit to track agricultural yield</p>
            </div>
        <?php endif; ?>
    </div>
    </main>

<!-- Yield Monitoring Modal -->
<div class="modal fade" id="addVisitModal" tabindex="-1" aria-labelledby="addVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addVisitModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Record Yield Visit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="yieldVisitForm" method="POST" action="yield_monitoring.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_visit">
                    
                    <!-- Yield Information Section -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-seedling me-2"></i>Yield Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="farmer_search" class="form-label">Select Farmer <span class="text-danger">*</span></label>
                                    <div class="relative">
                                        <input type="text" id="farmer_search" class="form-control" placeholder="Type farmer name..." autocomplete="off" required>
                                        <input type="hidden" id="farmer_id" name="farmer_id" required>
                                        <div id="farmer_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="commodity_id" class="form-label">Commodity <span class="text-danger">*</span></label>
                                    <select class="form-select" id="commodity_id" name="commodity_id" required>
                                        <option value="">Select Commodity</option>
                                        <?php foreach ($commodities as $commodity): ?>
                                            <option value="<?php echo htmlspecialchars($commodity['commodity_id']); ?>">
                                                <?php echo htmlspecialchars($commodity['commodity_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="season" class="form-label">Season <span class="text-danger">*</span></label>
                                    <select class="form-select" id="season" name="season" required>
                                        <option value="">Select Season</option>
                                        <option value="Dry Season">Dry Season</option>
                                        <option value="Wet Season">Wet Season</option>
                                        <option value="First Cropping">First Cropping</option>
                                        <option value="Second Cropping">Second Cropping</option>
                                        <option value="Third Cropping">Third Cropping</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="yield_amount" class="form-label">Yield Amount <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="yield_amount" name="yield_amount" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle me-1"></i>Fields marked with <span class="text-danger">*</span> are required.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i>Record Visit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

function closeModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Initialize farmer autocomplete
    initializeFarmerAutocomplete();
    
    // Initialize filter tabs
    initializeFilterTabs();
    
    // Auto-hide success messages after 1.5 seconds
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(message => {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            setTimeout(() => {
                message.remove();
            }, 500);
        }, 1500);
    });
});

// Farmer Autocomplete Functionality - Matching farmers.php style
function initializeFarmerAutocomplete() {
    const farmerSearch = document.getElementById('farmer_search');
    const farmerId = document.getElementById('farmer_id');
    const suggestions = document.getElementById('farmer_suggestions');
    let searchTimeout;

    farmerSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (query.length < 1) {
            suggestions.innerHTML = '';
            suggestions.classList.add('hidden');
            farmerId.value = '';
            return;
        }
        
        // Show loading indicator
        suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
        suggestions.classList.remove('hidden');
        
        // Debounce search
        searchTimeout = setTimeout(() => {
            searchFarmers(query);
        }, 300);
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!farmerSearch.contains(e.target) && !suggestions.contains(e.target)) {
            setTimeout(() => {
                suggestions.classList.add('hidden');
            }, 200); // Delay to allow click events on suggestions
        }
    });
}

function searchFarmers(query) {
    const suggestions = document.getElementById('farmer_suggestions');
    
    fetch('search_farmers.php?query=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            suggestions.innerHTML = '';
            
            if (data.success && data.farmers && data.farmers.length > 0) {
                data.farmers.forEach(farmer => {
                    const item = document.createElement('div');
                    item.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-200 last:border-b-0 farmer-suggestion-item';
                    item.innerHTML = `
                        <div class="font-medium text-gray-900">${farmer.full_name}</div>
                        <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number || 'N/A'}</div>
                        <div class="text-xs text-gray-500">${farmer.barangay_name || 'N/A'}</div>
                    `;
                    
                    item.addEventListener('click', function() {
                        document.getElementById('farmer_search').value = farmer.full_name;
                        document.getElementById('farmer_id').value = farmer.farmer_id;
                        suggestions.classList.add('hidden');
                    });
                    
                    suggestions.appendChild(item);
                });
                suggestions.classList.remove('hidden');
            } else {
                const noResults = document.createElement('div');
                noResults.className = 'px-3 py-2 text-gray-500 text-center';
                noResults.textContent = 'No farmers found matching your search';
                suggestions.appendChild(noResults);
                suggestions.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error searching farmers:', error);
            suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
            suggestions.classList.remove('hidden');
        });
}

// Form validation and submission
document.getElementById('yieldVisitForm').addEventListener('submit', function(e) {
    // Basic validation
    const requiredFields = ['farmer_id', 'commodity_id', 'season', 'yield_amount'];
    let isValid = true;
    
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Recording...';
    submitBtn.disabled = true;
    
    // Re-enable button after a delay (in case of validation errors)
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

// Clear form when modal is hidden
document.getElementById('addVisitModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('yieldVisitForm').reset();
    
    // Remove validation classes
    const invalidFields = document.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => field.classList.remove('is-invalid'));
});

// Filter Tabs Functionality
function initializeFilterTabs() {
    const filterTabs = document.querySelectorAll('.filter-tab');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            filterTabs.forEach(t => {
                t.classList.remove('active', 'bg-agri-green', 'text-white');
                t.classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-300');
            });
            
            // Add active class to clicked tab
            this.classList.add('active', 'bg-agri-green', 'text-white');
            this.classList.remove('bg-white', 'text-gray-700', 'border', 'border-gray-300');
            
            // Get filter value
            const filterValue = this.getAttribute('data-filter');
            
            // Handle filter logic
            handleFilterChange(filterValue);
        });
    });
}

function handleFilterChange(filterValue) {
    console.log('Filter changed to:', filterValue);
    
    // Update summary cards based on filter
    updateSummaryCards(filterValue);
    
    // Filter data table (when implemented)
    filterDataTable(filterValue);
    
    // You can add more filter logic here
    switch(filterValue) {
        case 'all':
            console.log('Showing all visits');
            break;
        case 'seeds':
            console.log('Filtering by seeds');
            break;
        case 'livestock':
            console.log('Filtering by livestock');
            break;
        case 'fry':
            console.log('Filtering by fry/fingerlings');
            break;
        case 'chicken':
            console.log('Filtering by chicken');
            break;
        case 'tools':
            console.log('Filtering by agricultural tools');
            break;
    }
}

function updateSummaryCards(filterValue) {
    // This function will update the summary cards based on the selected filter
    // For now, we'll just log the action
    console.log('Updating summary cards for filter:', filterValue);
    
    // You can implement actual data filtering here
    // Example: fetch filtered data and update the card values
}

function filterDataTable(filterValue) {
    // This function will filter the data table based on the selected filter
    console.log('Filtering data table for:', filterValue);
    
    // You can implement actual table filtering here
    // Example: show/hide table rows based on filter criteria
}
</script>

<style>
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

.modal-content {
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.modal-header {
    border-radius: 10px 10px 0 0;
}

.modal-footer {
    border-radius: 0 0 10px 10px;
}

/* Animation for modal */
.modal.fade .modal-dialog {
    transition: transform 0.3s ease-out;
    transform: translate(0, -50px);
}

.modal.show .modal-dialog {
    transform: none;
}

/* Form validation styles */
.is-invalid {
    border-color: #dc3545;
}

.is-invalid:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

/* Filter Tab Styles */
.filter-tab {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.filter-tab:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.filter-tab.active {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.filter-tab:active {
    transform: translateY(0);
}

/* Search and Date Range Styling to match image */
.search-input {
    background-color: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #374151;
}

.search-input:focus {
    background-color: #ffffff;
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

.date-select {
    background-color: #ffffff;
    border: 2px solid #1f2937;
    color: #374151;
    font-weight: 500;
}

.date-select:focus {
    border-color: #16a34a;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

/* Label styling */
label {
    font-weight: 600;
    color: #1f2937;
}

/* Search input text positioning */
.search-input::placeholder {
    padding-left: 2rem;
}
</style>

