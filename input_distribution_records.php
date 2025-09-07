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
    $input_filter = isset($_GET['input_id']) ? trim($_GET['input_id']) : '';
    $search_condition = 'WHERE 1=1';
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
    
    if (!empty($input_filter)) {
        $search_condition .= " AND mdl.input_id = ?";
        $search_params[] = $input_filter;
    }
    
    // Get distribution records for PDF export
    $export_sql = "SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, 
                   mdl.visitation_date, f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
                   f.contact_number, b.barangay_name, ic.input_name, ic.unit
                   FROM mao_distribution_log mdl
                   INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id
                   INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
                   LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                   $search_condition
                   ORDER BY mdl.date_given DESC, mdl.log_id DESC";
    
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
        <div class="title">Input Distribution Records Report</div>
        <div class="subtitle">Agricultural Input Distribution Management System</div>
        <div class="subtitle">Generated on: ' . date('F d, Y h:i A') . '</div>
        <div class="subtitle">Total Records: ' . $export_result->num_rows . '</div>
    </div>';
    
    if ($export_result->num_rows > 0) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Farmer Name</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Input Type</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Date Given</th>
                    <th>Visitation Date</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $export_result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            $visitation_status = $row['visitation_date'] ? date('M d, Y', strtotime($row['visitation_date'])) : 'Not Required';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($row['log_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['input_name']) . '</td>
                <td>' . htmlspecialchars($row['quantity_distributed']) . '</td>
                <td>' . htmlspecialchars($row['unit']) . '</td>
                <td>' . date('M d, Y', strtotime($row['date_given'])) . '</td>
                <td>' . htmlspecialchars($visitation_status) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div style="text-align: center; padding: 50px;">
            <h3>No Distribution Records Found</h3>
            <p>No input distribution records match the specified criteria.</p>
        </div>';
    }
    
    // Output PDF-ready HTML
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Input Distribution Records Report</title>
    <?php include 'includes/assets.php'; ?>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #16a34a;
            padding-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #16a34a;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 14px;
            color: #15803d;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        th {
            background-color: #16a34a;
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
            background-color: #dcfce7;
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
        <p>Agriculture Management System - Input Distribution Records</p>
        <p>This report contains ' . $export_result->num_rows . ' distribution records</p>
    </div>
</body>
</html>';
    exit;
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$input_filter = isset($_GET['input_id']) ? trim($_GET['input_id']) : '';

// Build search condition
$search_condition = 'WHERE 1=1';
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

if (!empty($input_filter)) {
    $search_condition .= " AND mdl.input_id = ?";
    $search_params[] = $input_filter;
}

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM mao_distribution_log mdl
              INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id
              INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
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
$total_pages = max(1, ceil($total_records / $records_per_page));

// Fetch distribution records with pagination
$sql = "SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, 
        mdl.visitation_date, f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, f.gender, f.birth_date, f.address_details, f.registration_date,
        b.barangay_name, ic.input_name, ic.unit
        FROM mao_distribution_log mdl
        INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id
        INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        $search_condition
        ORDER BY mdl.date_given DESC, mdl.log_id DESC
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

// Get input categories for filter dropdown
$inputs_result = $conn->query("SELECT input_id, input_name FROM input_categories ORDER BY input_name");
$inputs = [];
while ($row = $inputs_result->fetch_assoc()) {
    $inputs[] = $row;
}

// Function to build URL parameters
function buildUrlParams($page, $search = '', $barangay = '', $input_id = '') {
    $params = "?page=$page";
    if (!empty($search)) {
        $params .= "&search=" . urlencode($search);
    }
    if (!empty($barangay)) {
        $params .= "&barangay=" . urlencode($barangay);
    }
    if (!empty($input_id)) {
        $params .= "&input_id=" . urlencode($input_id);
    }
    return $params;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Distribution Records - Agricultural Management System</title>
    <?php include 'includes/assets.php'; ?>
    
    
    
    
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
                            <i class="fas fa-share-square text-agri-green mr-3"></i>
                            Input Distribution Records
                        </h1>
                        <p class="text-gray-600 mt-2">Agricultural Input Distribution Management System</p>
                        <div class="mt-2 text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Total Distribution Records: <span class="font-bold text-agri-green"><?php echo number_format($total_records); ?></span>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="exportToPDF()" 
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center">
                            <i class="fas fa-file-pdf mr-2"></i>Export to PDF
                        </button>
                        <a href="mao_inventory.php" class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                            <i class="fas fa-boxes mr-2"></i>Manage Inventory
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <form method="GET" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1"></i>Search Distribution Records
                            </label>
                            <div class="relative">
                                <input type="text" name="search" id="distribution_search" value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by farmer name or contact..." 
                                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green"
                                       onkeyup="searchDistributionAutoSuggest(this.value)"
                                       onfocus="showDistributionSuggestions()"
                                       onblur="hideDistributionSuggestions()">
                                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                                
                                <!-- Auto-suggest dropdown -->
                                <div id="distribution_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-seedling mr-1"></i>Filter by Input Type
                            </label>
                            <select name="input_id" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                                <option value="">All Input Types</option>
                                <?php foreach ($inputs as $input): ?>
                                    <option value="<?php echo $input['input_id']; ?>" 
                                            <?php echo ($input_filter == $input['input_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($input['input_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="bg-agri-green text-white px-6 py-2 rounded-lg hover:bg-agri-dark transition-colors">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search) || !empty($barangay_filter) || !empty($input_filter)): ?>
                            <a href="input_distribution_records.php" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times mr-2"></i>Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Distribution Records Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-list-alt mr-2 text-agri-green"></i>
                        Distribution Records
                        <?php if (!empty($search) || !empty($barangay_filter) || !empty($input_filter)): ?>
                            <span class="text-sm font-normal text-gray-600 ml-2">
                                - Filtered by: 
                                <?php if (!empty($search)): ?>
                                    Search "<?php echo htmlspecialchars($search); ?>"
                                <?php endif; ?>
                                <?php if (!empty($search) && (!empty($barangay_filter) || !empty($input_filter))): ?> & <?php endif; ?>
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
                                <?php if (!empty($input_filter)): ?>
                                    <?php if (!empty($barangay_filter)): ?> & <?php endif; ?>
                                    <?php 
                                    $selected_input = '';
                                    foreach ($inputs as $input) {
                                        if ($input['input_id'] == $input_filter) {
                                            $selected_input = $input['input_name'];
                                            break;
                                        }
                                    }
                                    ?>
                                    Input "<?php echo htmlspecialchars($selected_input); ?>"
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-agri-green text-white">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-hashtag mr-1"></i>ID
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-user mr-1"></i>Farmer Details
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-seedling mr-1"></i>Input & Quantity
                                </th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    <i class="fas fa-calendar mr-1"></i>Dates & Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($distribution = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-agri-light transition-colors">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <span class="bg-agri-green text-white px-2 py-1 rounded text-xs">
                                                #<?php echo $distribution['log_id']; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm font-medium text-gray-900 truncate max-w-48">
                                                <?php echo htmlspecialchars(trim($distribution['first_name'] . ' ' . $distribution['middle_name'] . ' ' . $distribution['last_name'] . ' ' . $distribution['suffix'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-phone mr-1"></i>
                                                <?php echo htmlspecialchars($distribution['contact_number']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-map-pin mr-1 text-red-600"></i>
                                                <?php echo htmlspecialchars($distribution['barangay_name']); ?>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <div class="flex items-center mb-2">
                                                    <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center mr-2">
                                                        <i class="fas fa-seedling text-green-600 text-xs"></i>
                                                    </div>
                                                    <span class="text-xs truncate max-w-24"><?php echo htmlspecialchars($distribution['input_name']); ?></span>
                                                </div>
                                                <div class="text-sm">
                                                    <span class="font-bold text-lg text-agri-green">
                                                        <?php echo number_format($distribution['quantity_distributed']); ?>
                                                    </span>
                                                    <span class="text-gray-500 ml-1 text-xs"><?php echo htmlspecialchars($distribution['unit']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-4">
                                            <div class="space-y-2">
                                                <div class="text-xs text-gray-900">
                                                    <div class="flex items-center mb-1">
                                                        <i class="fas fa-calendar mr-1 text-blue-600"></i>
                                                        <span class="font-medium">Given:</span>
                                                    </div>
                                                    <span class="text-xs"><?php echo date('M d, Y', strtotime($distribution['date_given'])); ?></span>
                                                </div>
                                                
                                                <?php if ($distribution['visitation_date']): ?>
                                                    <div class="text-xs text-gray-900">
                                                        <div class="flex items-center mb-1">
                                                            <i class="fas fa-calendar-check mr-1 text-green-600"></i>
                                                            <span class="font-medium">Visit:</span>
                                                        </div>
                                                        <span class="text-xs"><?php echo date('M d, Y', strtotime($distribution['visitation_date'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-2">
                                                    <?php
                                                    $status_class = 'bg-green-100 text-green-800';
                                                    $status_text = 'Distributed';
                                                    $status_icon = 'fa-check-circle';
                                                    
                                                    if ($distribution['visitation_date']) {
                                                        $current_date = new DateTime();
                                                        $visitation_date = new DateTime($distribution['visitation_date']);
                                                        
                                                        if ($visitation_date < $current_date) {
                                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                                            $status_text = 'Pending';
                                                            $status_icon = 'fa-clock';
                                                        } else {
                                                            $status_class = 'bg-blue-100 text-blue-800';
                                                            $status_text = 'Scheduled';
                                                            $status_icon = 'fa-calendar-alt';
                                                        }
                                                    }
                                                    ?>
                                                    <span class="<?php echo $status_class; ?> px-2 py-1 rounded-full text-xs font-medium flex items-center w-fit">
                                                        <i class="fas <?php echo $status_icon; ?> mr-1"></i>
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-share-square text-6xl text-gray-300 mb-4"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Distribution Records Found</h3>
                                            <?php if (!empty($search) || !empty($barangay_filter) || !empty($input_filter)): ?>
                                                <p class="text-sm text-gray-600 mb-2">No distribution records match your search criteria</p>
                                                <p class="text-xs text-gray-500">Try adjusting your search terms or filters</p>
                                            <?php else: ?>
                                                <p class="text-sm text-gray-600 mb-2">No input distributions have been recorded yet</p>
                                                <p class="text-xs text-gray-500">Start distributing inputs to farmers to see records here</p>
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
                                    <a href="input_distribution_records.php<?php echo buildUrlParams($page - 1, $search, $barangay_filter, $input_filter); ?>" 
                                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="input_distribution_records.php<?php echo buildUrlParams($i, $search, $barangay_filter, $input_filter); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium 
                                              <?php echo $i == $page ? 'bg-agri-green text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="input_distribution_records.php<?php echo buildUrlParams($page + 1, $search, $barangay_filter, $input_filter); ?>" 
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

    <script>
        // Function to export distribution records to PDF
        function exportToPDF() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            const barangay = new URLSearchParams(window.location.search).get('barangay') || '';
            const input_id = new URLSearchParams(window.location.search).get('input_id') || '';
            
            let exportUrl = 'input_distribution_records.php?action=export_pdf';
            if (search) exportUrl += `&search=${encodeURIComponent(search)}`;
            if (barangay) exportUrl += `&barangay=${encodeURIComponent(barangay)}`;
            if (input_id) exportUrl += `&input_id=${encodeURIComponent(input_id)}`;
            
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

        // Auto-suggest functionality for distribution search
        function searchDistributionAutoSuggest(query) {
            const suggestions = document.getElementById('distribution_suggestions');
            
            if (!suggestions) return; // Exit if element doesn't exist

            if (query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                return;
            }

            // Show loading indicator
            suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
            suggestions.classList.remove('hidden');

            // Make AJAX request to get farmer suggestions (including archived farmers for historical records)
            fetch('get_farmers.php?action=search&include_archived=true&query=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.farmers && data.farmers.length > 0) {
                        let html = '';
                        data.farmers.forEach(farmer => {
                            const archivedBadge = farmer.is_archived ? 
                                '<span class="inline-block px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full ml-2">Archived</span>' : '';
                            html += `
                                <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0" 
                                     onclick="selectDistributionSuggestion('${farmer.farmer_id}', '${farmer.full_name.replace(/'/g, "\\'")}', '${farmer.contact_number}')">
                                    <div class="font-medium text-gray-900">${farmer.full_name}${archivedBadge}</div>
                                    <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number}</div>
                                    <div class="text-xs text-gray-500">${farmer.barangay_name}</div>
                                </div>
                            `;
                        });
                        suggestions.innerHTML = html;
                        suggestions.classList.remove('hidden');
                    } else {
                        suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No farmers found matching your search</div>';
                        suggestions.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
                    suggestions.classList.remove('hidden');
                });
        }

        function selectDistributionSuggestion(farmerId, farmerName, contactNumber) {
            const searchInput = document.getElementById('distribution_search');
            searchInput.value = farmerName;
            hideDistributionSuggestions();
            
            // Trigger form submission to filter results
            const form = searchInput.closest('form');
            if (form) {
                form.submit();
            }
        }

        function showDistributionSuggestions() {
            const searchInput = document.getElementById('distribution_search');
            if (searchInput.value.length >= 1) {
                searchDistributionAutoSuggest(searchInput.value);
            }
        }

        function hideDistributionSuggestions() {
            const suggestions = document.getElementById('distribution_suggestions');
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
