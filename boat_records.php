<?php
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle PDF export
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Build search condition for export
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
    $search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
    $search_params = [];
    
    if (!empty($search)) {
        $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR boats.registration_number LIKE ?)";
        $search_term = "%$search%";
        $search_params = [$search_term, $search_term, $search_term, $search_term];
    }
    
    if (!empty($barangay_filter)) {
        $search_condition .= " AND f.barangay_id = ?";
        $search_params[] = $barangay_filter;
    }
    
    // Get Boat records for PDF export
    $export_sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
                   f.contact_number, f.address_details, b.barangay_name, c.commodity_name, 
                   boats.boat_id, boats.registration_number, boats.boat_name, boats.boat_type
                   FROM farmers f
                   INNER JOIN boats ON f.farmer_id = boats.farmer_id
                   LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                   LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
                   $search_condition
                   ORDER BY f.registration_date DESC";
    
    if (!empty($search_params)) {
        $stmt = $conn->prepare($export_sql);
        $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
        $stmt->execute();
        $export_result = $stmt->get_result();
    } else {
        $export_result = $conn->query($export_sql);
    }
    
    // Create PDF content
    $html = '<div class="header">
        <div class="title">Boat Records Report</div>
        <div class="subtitle">Fishing Boat Registration and Management System</div>
        <div class="subtitle">Generated on: ' . date('F d, Y h:i A') . '</div>
        <div class="subtitle">Total Records: ' . $export_result->num_rows . '</div>
    </div>';
    
    if ($export_result->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Farmer ID</th>
                    <th>Owner Name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Boat Name</th>
                    <th>Registration No.</th>
                    <th>Boat Type</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $export_result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            
            $html .= '<tr>
                <td>' . htmlspecialchars($row['farmer_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['boat_name']) . '</td>
                <td>' . htmlspecialchars($row['registration_number']) . '</td>
                <td>' . htmlspecialchars($row['boat_type']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div style="text-align: center; padding: 50px;">
            <h3>No Boat Records Found</h3>
            <p>No boats are currently registered in the system.</p>
        </div>';
    }
    
    // Output PDF-ready HTML
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Boat Records Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #7c3aed;
            padding-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #7c3aed;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 14px;
            color: #6d28d9;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        th {
            background-color: #7c3aed;
            color: white;
            padding: 8px 4px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
            max-width: 80px;
        }
        tr:nth-child(even) {
            background-color: #faf5ff;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    ' . $html . '
    <div class="footer">
        <p>Agriculture Management System - Boat Records</p>
        <p>This report contains ' . $export_result->num_rows . ' boat registration records</p>
    </div>
</body>
</html>';
    exit;
}

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';

// Build search condition
$search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
$search_params = [];

if (!empty($search)) {
    $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR boats.registration_number LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
}

if (!empty($barangay_filter)) {
    $search_condition .= " AND f.barangay_id = ?";
    $search_params[] = $barangay_filter;
}

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM farmers f 
              INNER JOIN boats ON f.farmer_id = boats.farmer_id
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
              LEFT JOIN commodities c ON f.commodity_id = c.commodity_id 
              $search_condition";

if (!empty($search_params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}

$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch Boat records with pagination
$sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, f.gender, f.birth_date, f.address_details, f.registration_date,
        f.land_area_hectares, b.barangay_name, c.commodity_name, h.household_size,
        boats.boat_id, boats.registration_number, boats.boat_name, boats.boat_type
        FROM farmers f
        INNER JOIN boats ON f.farmer_id = boats.farmer_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
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
$result = $stmt->get_result();

// Get barangays for filter dropdown
$barangays_result = $conn->query("SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
    $barangays[] = $row;
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
    <title>Boat Records - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-dark': '#16a34a',
                        'agri-light': '#dcfce7'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-ship text-agri-green mr-3"></i>
                            Boat Records
                        </h1>
                        <p class="text-gray-600 mt-2">Fishing Boat Registration and Management System</p>
                        <div class="mt-2 text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Total Boat Registered Farmers: <span class="font-bold text-agri-green"><?php echo $total_records; ?></span>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="exportToPDF()" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i>Export to PDF
                        </button>
                        <a href="farmers.php" class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-users mr-2"></i>All Farmers
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1"></i>Search Boat Records
                            </label>
                            <div class="relative">
                                <input type="text" name="search" id="boat_search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by owner name, contact, or registration number..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green"
                                       onkeyup="searchBoatAutoSuggest(this.value)"
                                       onfocus="showBoatSuggestions()"
                                       onblur="hideBoatSuggestions()">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                
                                <!-- Auto-suggest dropdown -->
                                <div id="boat_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <!-- Suggestions will be populated here -->
                                </div>
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
                            <a href="boat_records.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Boat Records Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-alt mr-2 text-agri-green"></i>
                        Registered Fishing Boats
                    <?php if (!empty($search) || !empty($barangay_filter)): ?>
                        <span class="text-sm font-normal text-gray-600 ml-2">
                            - Filtered by: 
                            <?php if (!empty($search)): ?>
                                Search "<?php echo htmlspecialchars($search); ?>"
                            <?php endif; ?>
                            <?php if (!empty($search) && !empty($barangay_filter)): ?> & <?php endif; ?>
                            <?php if (!empty($barangay_filter)): ?>
                                <?php 
                                $selected_barangay = '';
                                foreach ($barangays as $barangay) {
                                    if ($barangay['barangay_id'] == $barangay_filter) {
                                        $selected_barangay = $barangay['barangay_name'];
                                        break;
                                    }
                                }
                                ?>
                                Barangay "<?php echo htmlspecialchars($selected_barangay); ?>"
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </h3>
            </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-agri-green text-white">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-id-card mr-1"></i>Boat ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-user mr-1"></i>Owner Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-ship mr-1"></i>Boat Details
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-certificate mr-1"></i>Registration
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-map-marker-alt mr-1"></i>Location
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-clipboard-list mr-1"></i>Details
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($boat = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-agri-light transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <span class="bg-agri-green text-white px-2 py-1 rounded text-xs">
                                                #<?php echo $boat['boat_id']; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars(trim($boat['first_name'] . ' ' . $boat['middle_name'] . ' ' . $boat['last_name'] . ' ' . $boat['suffix'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($boat['contact_number']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <i class="fas fa-ship mr-1 text-green-600"></i>
                                                <?php echo htmlspecialchars($boat['boat_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($boat['boat_type']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                                                <i class="fas fa-certificate mr-1"></i>
                                                <?php echo htmlspecialchars($boat['registration_number']); ?>
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Reg: <?php echo date('M d, Y', strtotime($boat['registration_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <i class="fas fa-map-pin mr-1 text-red-600"></i>
                                            <?php echo htmlspecialchars($boat['barangay_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <span class="h-2 w-2 bg-green-400 rounded-full mr-2"></span>
                                                Active Boat
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-ship text-6xl text-gray-300 mb-4"></i>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Boat Records Found</h3>
                                        <?php if (!empty($search) || !empty($barangay_filter)): ?>
                                            <p class="text-sm text-gray-600 mb-2">No boats match your search criteria</p>
                                            <p class="text-xs text-gray-500">Try adjusting your search terms or filters</p>
                                        <?php else: ?>
                                            <p class="text-sm text-gray-600 mb-2">No boats are currently registered in the system</p>
                                            <p class="text-xs text-gray-500">The system is ready to display boat records when boats are registered</p>
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

    <script>
        // Function to export Boat records to PDF
        function exportToPDF() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const barangay = new URLSearchParams(window.location.search).get('barangay') || '';
            
            let exportUrl = 'boat_records.php?action=export_pdf';
            if (search) exportUrl += `&search=${encodeURIComponent(search)}`;
            if (barangay) exportUrl += `&barangay=${encodeURIComponent(barangay)}`;
            
            // Show loading indicator
            const exportBtn = document.querySelector('button[onclick="exportToPDF()"]');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Generating PDF...';
            exportBtn.disabled = true;
            
            // Open in new window for PDF download
            window.open(exportUrl, '_blank');
            
            // Reset button after delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 2000);
        }

        // Auto-suggest functionality for boat search
        function searchBoatAutoSuggest(query) {
            const suggestions = document.getElementById('boat_suggestions');
            
            if (!suggestions) return; // Exit if element doesn't exist

            if (query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                return;
            }

            // Show loading indicator
            suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
            suggestions.classList.remove('hidden');

            // Make AJAX request to get farmer suggestions
            fetch('get_farmers.php?action=search&query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.farmers && data.farmers.length > 0) {
                        let html = '';
                        data.farmers.forEach(farmer => {
                            html += `
                                <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0" 
                                     onclick="selectBoatSuggestion('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}')">
                                    <div class="font-medium text-gray-900">${farmer.full_name}</div>
                                    <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                                    <div class="text-xs text-gray-500">${farmer.barangay_name}</div>
                                </div>
                            `;
                        });
                        suggestions.innerHTML = html;
                        suggestions.classList.remove('hidden');
                    } else {
                        suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No boat owners found matching your search</div>';
                        suggestions.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
                    suggestions.classList.remove('hidden');
                });
        }

        function selectBoatSuggestion(farmerId, farmerName, contactNumber) {
            const searchInput = document.getElementById('boat_search');
            searchInput.value = farmerName;
            hideBoatSuggestions();
            
            // Trigger form submission to filter results
            const form = searchInput.closest('form');
            if (form) {
                form.submit();
            }
        }

        function showBoatSuggestions() {
            const searchInput = document.getElementById('boat_search');
            if (searchInput.value.length >= 1) {
                searchBoatAutoSuggest(searchInput.value);
            }
        }

        function hideBoatSuggestions() {
            const suggestions = document.getElementById('boat_suggestions');
            if (suggestions) {
                setTimeout(() => {
                    suggestions.classList.add('hidden');
                }, 200); // Delay to allow click events on suggestions
            }
        }
    </script>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
