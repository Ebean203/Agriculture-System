<?php
require_once __DIR__ . '/conn.php';

// --- Export to PDF/Print-friendly HTML handler ---
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    // Accept POST for filters
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $barangay_filter = isset($_POST['barangay']) ? trim($_POST['barangay']) : '';
    $input_filter = isset($_POST['input_id']) ? trim($_POST['input_id']) : '';
    $status_filter = '';
    $farmer_id_filter = '';

    // Build search condition (same as main logic, but no pagination)
    $search_condition = 'WHERE 1=1';
    $search_params = [];
    if ($status_filter !== '') {
        $search_condition .= " AND mdl.status = ?";
        $search_params[] = $status_filter;
    }
    if (!empty($farmer_id_filter)) {
        $search_condition .= " AND f.farmer_id = ?";
        $search_params[] = $farmer_id_filter;
    } elseif (!empty($search)) {
        $search_condition .= " AND (
            f.first_name LIKE ? OR 
            f.middle_name LIKE ? OR 
            f.last_name LIKE ? OR 
            f.contact_number LIKE ? OR 
            CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ? OR
            CONCAT(
                f.first_name, 
                CASE 
                    WHEN f.middle_name IS NOT NULL AND LOWER(f.middle_name) NOT IN ('n/a', 'na', '') 
                    THEN CONCAT(' ', f.middle_name) 
                    ELSE '' 
                END,
                ' ', f.last_name,
                CASE 
                    WHEN f.suffix IS NOT NULL AND LOWER(f.suffix) NOT IN ('n/a', 'na', '') 
                    THEN CONCAT(' ', f.suffix) 
                    ELSE '' 
                END
            ) LIKE ?
        )";
        $search_term = "%$search%";
        $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
    }
    if (!empty($barangay_filter)) {
        $search_condition .= " AND f.barangay_id = ?";
        $search_params[] = $barangay_filter;
    }
    if (!empty($input_filter)) {
        $search_condition .= " AND mdl.input_id = ?";
        $search_params[] = $input_filter;
    }

    $sql = "SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, 
        mdl.visitation_date, mdl.status, f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
        f.contact_number, b.barangay_name, ic.input_name, ic.unit
        FROM mao_distribution_log mdl
        INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id
        INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        $search_condition
        ORDER BY mdl.date_given DESC, mdl.log_id DESC";

    if (!empty($search_params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    // Output print-friendly HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<title>Input Distribution Records Export</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        h1 { color: #256029; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #bbb; padding: 8px 10px; font-size: 13px; }
        th { background: #256029; color: #fff; }
        tr:nth-child(even) { background: #f6f6f6; }
        .status-badge { border-radius: 12px; padding: 2px 10px; font-size: 12px; display: inline-block; }
        .completed { background: #d1fae5; color: #065f46; }
        .pending { background: #fef3c7; color: #92400e; }
        .rescheduled, .scheduled { background: #dbeafe; color: #1e40af; }
        .cancelled { background: #e5e7eb; color: #374151; }
    </style>';
    echo '</head><body>';
    echo '<h1>Input Distribution Records Export</h1>';
    echo '<p>Exported on: <b>' . date('F d, Y h:i A') . '</b></p>';
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th><th>Farmer Name</th><th>Contact</th><th>Barangay</th><th>Input</th><th>Quantity</th><th>Given Date</th><th>Visit Date</th><th>Status</th>';
    echo '</tr>';
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $suffix = isset($row['suffix']) ? trim($row['suffix']) : '';
            if (in_array(strtolower($suffix), ['n/a', 'na','N/A', 'n/A','NA','N/a'])) {
                $suffix = '';
            }
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $suffix);
            $status = $row['status'];
            $status_class = 'status-badge ' . ($status === 'completed' ? 'completed' : ($status === 'pending' ? 'pending' : ($status === 'rescheduled' ? 'rescheduled' : ($status === 'scheduled' ? 'scheduled' : ($status === 'cancelled' ? 'cancelled' : '')))));
            echo '<tr>';
            echo '<td>#' . htmlspecialchars($row['log_id']) . '</td>';
            echo '<td>' . htmlspecialchars($full_name) . '</td>';
            echo '<td>' . htmlspecialchars($row['contact_number']) . '</td>';
            echo '<td>' . htmlspecialchars($row['barangay_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['input_name']) . '</td>';
            echo '<td>' . number_format($row['quantity_distributed']) . ' ' . htmlspecialchars($row['unit']) . '</td>';
            echo '<td>' . ($row['date_given'] ? date('M d, Y', strtotime($row['date_given'])) : '-') . '</td>';
            echo '<td>' . ($row['visitation_date'] ? date('M d, Y', strtotime($row['visitation_date'])) : '-') . '</td>';
            echo '<td><span class="' . $status_class . '">' . ucfirst($status) . '</span></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="9" style="text-align:center; color:#888;">No records found for the selected filters.</td></tr>';
    }
    echo '</table>';
    echo '<p style="margin-top:40px; font-size:12px; color:#888;">Lagonglong FARMS - Input Distribution Management System</p>';
    echo '<script>window.print();</script>';
    echo '</body></html>';
    exit;
}

// --- Main logic for UI (not export) ---

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $records_per_page;

// Search and filter variables - GET parameters take priority over POST
$search = isset($_GET['farmer']) ? trim($_GET['farmer']) : (isset($_POST['search']) ? trim($_POST['search']) : '');
$farmer_id_filter = isset($_GET['farmer_id']) ? trim($_GET['farmer_id']) : '';
$barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : (isset($_POST['barangay']) ? trim($_POST['barangay']) : '');
$input_filter = isset($_GET['input_id']) ? trim($_GET['input_id']) : (isset($_POST['input_id']) ? trim($_POST['input_id']) : '');

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : (isset($_POST['status']) ? trim($_POST['status']) : '');
if (!in_array($status_filter, ['pending', 'rescheduled', 'completed', 'scheduled', 'cancelled', ''])) {
    $status_filter = '';
}

// Handle clear all - reset search and filter
if (isset($_POST['clear_all'])) {
    $search = '';
    $farmer_id_filter = '';
    $barangay_filter = '';
    $input_filter = '';
    $status_filter = 'pending';
}

// Build search condition
$search_condition = 'WHERE 1=1';
$search_params = [];
// Status filter (only apply if user selected a status)
if ($status_filter !== '') {
    $search_condition .= " AND mdl.status = ?";
    $search_params[] = $status_filter;
}

// Prioritize farmer_id for exact matching if available
if (!empty($farmer_id_filter)) {
    $search_condition .= " AND f.farmer_id = ?";
    $search_params[] = $farmer_id_filter;
} elseif (!empty($search)) {
    $search_condition .= " AND (
        f.first_name LIKE ? OR 
        f.middle_name LIKE ? OR 
        f.last_name LIKE ? OR 
        f.contact_number LIKE ? OR 
        CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ? OR
        CONCAT(
            f.first_name, 
            CASE 
                WHEN f.middle_name IS NOT NULL AND LOWER(f.middle_name) NOT IN ('n/a', 'na', '') 
                THEN CONCAT(' ', f.middle_name) 
                ELSE '' 
            END,
            ' ', f.last_name,
            CASE 
                WHEN f.suffix IS NOT NULL AND LOWER(f.suffix) NOT IN ('n/a', 'na', '') 
                THEN CONCAT(' ', f.suffix) 
                ELSE '' 
            END
        ) LIKE ?
    )";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
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
    mdl.visitation_date, mdl.status, f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix,
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
function buildUrlParams($page, $search = '', $barangay = '', $input_id = '', $status = '') {
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
    if (!empty($status)) {
        $params .= "&status=" . urlencode($status);
    }
    return $params;
}
?>
<?php $pageTitle = 'Input Distribution Records - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>

            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                
                <?php if (isset($_GET['farmer']) && !empty($_GET['farmer'])): ?>
                    <!-- Notification Filter Banner -->
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <i class="fas fa-filter text-blue-600 text-xl mr-3"></i>
                                <div>
                                    <h3 class="text-sm font-semibold text-blue-900">Viewing Notification Results</h3>
                                    <p class="text-sm text-blue-700">
                                        Showing distribution records for: <span class="font-bold"><?php echo htmlspecialchars($_GET['farmer']); ?></span>
                                    </p>
                                </div>
                            </div>
                            <a href="input_distribution_records.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                <i class="fas fa-times-circle mr-1"></i>View All Records
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Header Section -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-share-square text-agri-green mr-3"></i>
                                Input Distribution Records
                            </h1>
                            <p class="text-gray-600 mt-2">Lagonglong FARMS - Input Distribution Management</p>
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
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <form method="POST" class="flex flex-col gap-4">
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
                            <?php if (!empty($search) || !empty($farmer_id_filter) || !empty($barangay_filter) || !empty($input_filter)): ?>
                                <button type="submit" name="clear_all" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Clear All
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>


                <!-- Distribution Records Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-list-alt mr-2 text-agri-green"></i>
                                Distribution Records
                                <?php if (!empty($search) || !empty($farmer_id_filter) || !empty($barangay_filter) || !empty($input_filter)): ?>
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
                        <form method="GET" class="flex items-center gap-3">
                            <label for="status_filter" class="text-sm font-medium text-gray-700 mr-2">
                                <i class="fas fa-filter mr-1"></i>Status:
                            </label>
                            <select name="status" id="status_filter" class="py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" onchange="this.form.submit()">
                                <option value="" <?php echo ($status_filter === '') ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="rescheduled" <?php echo ($status_filter === 'rescheduled') ? 'selected' : ''; ?>>Rescheduled</option>
                                <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            <!-- Keep other filters in the form as hidden fields -->
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($barangay_filter); ?>">
                            <input type="hidden" name="input_id" value="<?php echo htmlspecialchars($input_filter); ?>">
                        </form>
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
                                        <i class="fas fa-calendar mr-1"></i>Given Date
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-calendar-check mr-1"></i>Visit Date
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-info-circle mr-1"></i>Status
                                    </th>
                                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                        <i class="fas fa-cogs mr-1"></i>Actions
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
                                                    <?php 
                                                        $suffix = isset($distribution['suffix']) ? trim($distribution['suffix']) : '';
                                                        if (in_array(strtolower($suffix), ['n/a', 'na','N/A', 'n/A','NA','N/a'])) {
                                                            $suffix = '';
                                                        }
                                                        $full_name = trim($distribution['first_name'] . ' ' . $distribution['middle_name'] . ' ' . $distribution['last_name'] . ' ' . $suffix);
                                                        echo htmlspecialchars($full_name);
                                                    ?>
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
                                                <span class="text-xs"><?php echo date('M d, Y', strtotime($distribution['date_given'])); ?></span>
                                            </td>
                                            <td class="px-3 py-4">
                                                <?php if ($distribution['visitation_date']): ?>
                                                    <span class="text-xs"><?php echo date('M d, Y', strtotime($distribution['visitation_date'])); ?></span>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400 italic">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-4">
                                                <?php
                                                // Always show the raw status from the database, with proper formatting
                                                $visit_status = $distribution['status'] ?? '';
                                                $status_map = [
                                                    'completed' => ['class' => 'bg-green-100 text-green-800', 'text' => 'Completed', 'icon' => 'fa-check-circle'],
                                                    'pending' => ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'Pending', 'icon' => 'fa-clock'],
                                                    'rescheduled' => ['class' => 'bg-blue-100 text-blue-800', 'text' => 'Rescheduled', 'icon' => 'fa-calendar-alt'],
                                                    'scheduled' => ['class' => 'bg-blue-100 text-blue-800', 'text' => 'Scheduled', 'icon' => 'fa-calendar-alt'],
                                                    'cancelled' => ['class' => 'bg-gray-200 text-gray-600', 'text' => 'Cancelled', 'icon' => 'fa-ban'],
                                                ];
                                                $status_info = $status_map[$visit_status] ?? ['class' => 'bg-gray-100 text-gray-800', 'text' => ucfirst($visit_status), 'icon' => 'fa-info-circle'];
                                                ?>
                                                <span class="<?php echo $status_info['class']; ?> px-2 py-1 rounded-full text-xs font-medium flex items-center w-fit status-badge">
                                                    <i class="fas <?php echo $status_info['icon']; ?> mr-1"></i>
                                                    <?php echo $status_info['text']; ?>
                                                </span>
                                                <?php if ($visit_status === 'rescheduled'): ?>
                                                    <button type="button" class="ml-2 text-blue-600 hover:text-blue-800 align-middle resched-info" 
                                                            data-id="<?php echo (int)$distribution['log_id']; ?>" 
                                                            aria-label="View latest reschedule details" title="View latest reschedule details">
                                                        <i class="fas fa-info-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-3 py-4">
                                                <?php
                                                // Show actions based on status
                                                if ($visit_status === 'completed') {
                                                    echo '<span class="text-gray-400 italic">No actions needed</span>';
                                                } elseif ($visit_status === 'pending' || $visit_status === 'rescheduled') {
                                                ?>
                                                    <div class="flex items-center gap-2">
                                                        <button title="Mark as Done" aria-label="Mark as Done" 
                                                                class="p-2 rounded-full hover:bg-green-100 text-green-600 hover:text-green-700"
                                                                onclick="openDistMarkDone(<?php echo $distribution['log_id']; ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button title="Reschedule" aria-label="Reschedule" 
                                                                class="p-2 rounded-full hover:bg-blue-100 text-blue-600 hover:text-blue-700"
                                                                onclick="openDistReschedule(<?php echo $distribution['log_id']; ?>)">
                                                            <i class="fas fa-calendar-alt"></i>
                                                        </button>
                                                    </div>
                                                <?php
                                                } else {
                                                    echo '<span class="text-gray-400 italic">No actions needed</span>';
                                                }
                                                ?>
                                                <!-- Mark as Done Modal -->
                                                <div id="markDoneModal-<?php echo $distribution['log_id']; ?>" class="fixed z-50 inset-0 bg-gray-900 bg-opacity-40 flex items-center justify-center hidden">
                                                    <div class="modal-content-custom bg-white rounded-lg shadow-lg p-6 w-full max-w-sm opacity-0 scale-95 transition-all duration-200" style="pointer-events: none;">
                                                        <h3 class="text-lg font-bold mb-4 text-gray-900 flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i>Mark as Done</h3>
                                                        <p class="mb-4 text-gray-700">Are you sure you want to mark this distribution as <span class="font-bold text-green-600">Completed</span>?</p>
                                                        <div class="flex justify-end gap-2">
                                                            <button class="close-mark-done-modal px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800" data-id="<?php echo $distribution['log_id']; ?>">Cancel</button>
                                                            <button class="confirm-mark-done px-4 py-2 rounded bg-green-600 hover:bg-green-700 text-white font-semibold" data-id="<?php echo $distribution['log_id']; ?>">Yes, Mark as Done</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Reschedule Modal -->
                                                <div id="rescheduleModal-<?php echo $distribution['log_id']; ?>" class="fixed z-50 inset-0 bg-gray-900 bg-opacity-40 flex items-center justify-center hidden">
                                                    <div class="modal-content-custom bg-white rounded-lg shadow-lg p-6 w-full max-w-sm opacity-0 scale-95 transition-all duration-200" style="pointer-events: none;">
                                                        <h3 class="text-lg font-bold mb-4 text-gray-900 flex items-center"><i class="fas fa-calendar-alt text-blue-500 mr-2"></i>Reschedule Visitation</h3>
                                                        <form class="reschedule-form" data-id="<?php echo $distribution['log_id']; ?>">
                                                            <label class="block mb-2 text-gray-700">New Visitation Date</label>
                                                            <input type="date" name="new_date" class="w-full border border-gray-300 rounded px-3 py-2 mb-3" required>
                                                            <label class="block mb-2 text-gray-700">Reason for Rescheduling <span class="text-red-600">*</span></label>
                                                            <textarea name="reschedule_reason" class="w-full border border-gray-300 rounded px-3 py-2 mb-2" rows="3" maxlength="500" required placeholder="Provide a short reason (max 500 chars)"></textarea>
                                                            <button type="button" class="text-blue-600 text-xs underline mb-4" onclick="viewReschedHistory(<?php echo $distribution['log_id']; ?>)">
                                                                View previous reschedules
                                                            </button>
                                                            <div class="flex justify-end gap-2">
                                                                <button type="button" class="close-reschedule-modal px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800" data-id="<?php echo $distribution['log_id']; ?>">Cancel</button>
                                                                <button type="button" class="open-reschedule-confirm px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold" data-id="<?php echo $distribution['log_id']; ?>">Continue</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                                <!-- Reschedule Confirmation Modal -->
                                                <div id="rescheduleConfirmModal-<?php echo $distribution['log_id']; ?>" class="fixed z-50 inset-0 bg-gray-900 bg-opacity-40 flex items-center justify-center hidden">
                                                    <div class="modal-content-custom bg-white rounded-lg shadow-lg p-6 w-full max-w-sm opacity-0 scale-95 transition-all duration-200" style="pointer-events: none;">
                                                        <h3 class="text-lg font-bold mb-4 text-gray-900 flex items-center"><i class="fas fa-calendar-alt text-blue-500 mr-2"></i>Confirm Reschedule</h3>
                                                        <p class="mb-4 text-gray-700">Are you sure you want to reschedule?</p>
                                                        <div class="flex justify-end gap-2">
                                                            <button type="button" class="close-reschedule-confirm-modal px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800" data-id="<?php echo $distribution['log_id']; ?>">Cancel</button>
                                                            <button type="button" class="confirm-reschedule px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold" data-id="<?php echo $distribution['log_id']; ?>">Yes, Reschedule</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div id="actionToast" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-[9999] hidden">
                                                    <div id="actionToastInner" class="toast-success px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 text-base font-semibold">
                                                        <i id="actionToastIcon" class="fas fa-check-circle"></i>
                                                        <span id="actionToastText">Action successful!</span>
                                                    </div>
                                                </div>
                                                <script>
                                                // Ellipsis dropdown controls for Distribution rows
                                                function toggleDistMenu(id){
                                                    const menu = document.getElementById('distMenu'+id);
                                                    const all = document.querySelectorAll('[id^="distMenu"]');
                                                    all.forEach(m=>{ if(m!==menu) m.classList.add('hidden'); });
                                                    // default show above
                                                    menu.style.transform='';
                                                    menu.classList.remove('top-full','mt-2');
                                                    menu.classList.add('bottom-full','mb-2');
                                                    menu.classList.toggle('hidden');
                                                    if(menu.classList.contains('hidden')) return;
                                                    // keep within table cell top
                                                    requestAnimationFrame(()=>{
                                                        const rect = menu.getBoundingClientRect();
                                                        const cell = menu.closest('td');
                                                        const cellRect = cell ? cell.getBoundingClientRect() : {top:0};
                                                        const desiredTop = cellRect.top + 8;
                                                        if(rect.top < desiredTop){
                                                            const push = desiredTop - rect.top;
                                                            menu.style.transform = `translateY(${push}px)`;
                                                        }
                                                    });
                                                }
                                                function closeDistMenu(id){
                                                    const menu = document.getElementById('distMenu'+id);
                                                    if(menu) menu.classList.add('hidden');
                                                }
                                                document.addEventListener('click', function(ev){
                                                    const isBtn = ev.target.closest('button[onclick^="toggleDistMenu("]');
                                                    const isMenu = ev.target.closest('[id^="distMenu"]');
                                                    if(!isBtn && !isMenu){
                                                        document.querySelectorAll('[id^="distMenu"]').forEach(m=>m.classList.add('hidden'));
                                                    }
                                                });
                                                function openDistMarkDone(id){
                                                    // open existing modal for that row
                                                    const modalId = 'markDoneModal-' + id;
                                                    const modal = document.getElementById(modalId);
                                                    if(!modal) return;
                                                    modal.classList.remove('hidden');
                                                    const content = modal.querySelector('.modal-content-custom');
                                                    if(content){
                                                        setTimeout(()=>{
                                                            content.classList.remove('opacity-0','scale-95');
                                                            content.classList.add('opacity-100','scale-100');
                                                            content.style.pointerEvents='auto';
                                                        },10);
                                                    }
                                                }
                                                function openDistReschedule(id){
                                                    const modalId = 'rescheduleModal-' + id;
                                                    const modal = document.getElementById(modalId);
                                                    if(!modal) return;
                                                    modal.classList.remove('hidden');
                                                    const content = modal.querySelector('.modal-content-custom');
                                                    if(content){
                                                        setTimeout(()=>{
                                                            content.classList.remove('opacity-0','scale-95');
                                                            content.classList.add('opacity-100','scale-100');
                                                            content.style.pointerEvents='auto';
                                                        },10);
                                                    }
                                                }
                                                function showActionToast(msg, type) {
                                                    var box = document.getElementById('actionToast');
                                                    var inner = document.getElementById('actionToastInner');
                                                    var icon = document.getElementById('actionToastIcon');
                                                    var text = document.getElementById('actionToastText');
                                                    if (!box || !inner || !icon || !text) return;

                                                    text.textContent = msg;
                                                    inner.classList.remove('toast-success', 'toast-error');
                                                    if (type === 'error') {
                                                        inner.classList.add('toast-error');
                                                        icon.className = 'fas fa-exclamation-circle';
                                                    } else {
                                                        inner.classList.add('toast-success');
                                                        icon.className = 'fas fa-check-circle';
                                                    }

                                                    box.classList.remove('hidden');
                                                    clearTimeout(box.__toastTimer);
                                                    box.__toastTimer = setTimeout(function() {
                                                        box.classList.add('hidden');
                                                    }, 2000);
                                                }
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    // Helper to show modal with fade/scale effect
                                                    function showModal(modalId) {
                                                        const modal = document.getElementById(modalId);
                                                        if (!modal) return;
                                                        modal.classList.remove('hidden');
                                                        const content = modal.querySelector('.modal-content-custom');
                                                        if (content) {
                                                            setTimeout(() => {
                                                                content.classList.remove('opacity-0', 'scale-95');
                                                                content.classList.add('opacity-100', 'scale-100');
                                                                content.style.pointerEvents = 'auto';
                                                            }, 10);
                                                        }
                                                    }
                                                    // Helper to hide modal with fade/scale effect
                                                    function hideModal(modalId) {
                                                        const modal = document.getElementById(modalId);
                                                        if (!modal) return;
                                                        const content = modal.querySelector('.modal-content-custom');
                                                        if (content) {
                                                            content.classList.remove('opacity-100', 'scale-100');
                                                            content.classList.add('opacity-0', 'scale-95');
                                                            content.style.pointerEvents = 'none';
                                                            setTimeout(() => {
                                                                modal.classList.add('hidden');
                                                            }, 200);
                                                        } else {
                                                            modal.classList.add('hidden');
                                                        }
                                                    }
                                                    // Mark as Done button
        document.querySelectorAll('.mark-done-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var id = this.getAttribute('data-id');
                showModal('markDoneModal-' + id);
            });
        });
                                                    // Close Mark as Done modal
        document.querySelectorAll('.close-mark-done-modal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                hideModal('markDoneModal-' + id);
            });
        });
                                                    // Confirm Mark as Done
                                                    document.querySelectorAll('.confirm-mark-done').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            var button = this;
                                                            button.disabled = true;
                                                            fetch('mark_distribution_complete.php', {
                                                                method: 'POST',
                                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                                body: 'distribution_id=' + encodeURIComponent(id)
                                                            })
                                                            .then(response => response.json())
                                                            .then(data => {
                                                                if (data.success) {
                                                                    var statusSpan = document.querySelector('button.mark-done-btn[data-id="'+id+'"], button.reschedule-btn[data-id="'+id+'"], #markDoneModal-'+id).closest('td').previousElementSibling.querySelector('.status-badge');
                                                                    statusSpan.className = 'bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium flex items-center w-fit status-badge';
                                                                    statusSpan.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Completed';
                                                                    document.getElementById('markDoneModal-' + id).classList.add('hidden');
                                                                    document.querySelector('button.mark-done-btn[data-id="'+id+'"], button.reschedule-btn[data-id="'+id+'"], #markDoneModal-'+id).closest('td').querySelectorAll('.mark-done-btn, .reschedule-btn').forEach(function(b){b.remove();});
                                                                    showActionToast('Marked as completed!', 'success');
                                                                    setTimeout(function(){ location.reload(); }, 1300);
                                                                } else {
                                                                    showActionToast(data.message || 'Failed to update.', 'error');
                                                                    button.disabled = false;
                                                                }
                                                            })
                                                            .catch(() => {
                                                                showActionToast('Failed to update.', 'error');
                                                                button.disabled = false;
                                                            });
                                                        });
                                                    });
                                                    // Reschedule button
        document.querySelectorAll('.reschedule-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var id = this.getAttribute('data-id');
                showModal('rescheduleModal-' + id);
            });
        });
                                                    // Close Reschedule modal
        document.querySelectorAll('.close-reschedule-modal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                hideModal('rescheduleModal-' + id);
            });
        });

                                                    // Open reschedule confirmation modal when user clicks Continue
                                                    document.querySelectorAll('.open-reschedule-confirm').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            var form = document.querySelector('.reschedule-form[data-id="'+id+'"]');
                                                            
                                                            // Check HTML5 form validity
                                                            if (!form.checkValidity()) {
                                                                form.reportValidity();
                                                                return;
                                                            }
                                                            
                                                            // Hide the reschedule modal and show confirmation modal
                                                            hideModal('rescheduleModal-' + id);
                                                            setTimeout(function() {
                                                                showModal('rescheduleConfirmModal-' + id);
                                                            }, 250);
                                                        });
                                                    });

                                                    // Close reschedule confirmation modal
                                                    document.querySelectorAll('.close-reschedule-confirm-modal').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            hideModal('rescheduleConfirmModal-' + id);
                                                            // Re-open the reschedule modal
                                                            setTimeout(function() {
                                                                showModal('rescheduleModal-' + id);
                                                            }, 250);
                                                        });
                                                    });

                                                    // Confirm and submit reschedule
                                                    document.querySelectorAll('.confirm-reschedule').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            var form = document.querySelector('.reschedule-form[data-id="'+id+'"]');
                                                            var newDate = form.querySelector('input[name="new_date"]').value;
                                                            var reason  = (form.querySelector('textarea[name="reschedule_reason"]').value || '').trim();
                                                            
                                                            btn.disabled = true;
                                                            fetch('mark_distribution_complete.php', {
                                                                method: 'POST',
                                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                                body: 'distribution_id=' + encodeURIComponent(id) + '&reschedule=1&new_date=' + encodeURIComponent(newDate) + '&reschedule_reason=' + encodeURIComponent(reason)
                                                            })
                                                            .then(response => response.json())
                                                            .then(data => {
                                                                if (data.success) {
                                                                    hideModal('rescheduleConfirmModal-' + id);
                                                                    showActionToast('Rescheduled successfully!', 'success');
                                                                    setTimeout(function(){ location.reload(); }, 1300);
                                                                } else {
                                                                    hideModal('rescheduleConfirmModal-' + id);
                                                                    showActionToast(data.message || 'Failed to reschedule.', 'error');
                                                                    btn.disabled = false;
                                                                    setTimeout(function(){ showModal('rescheduleModal-' + id); }, 250);
                                                                }
                                                            })
                                                            .catch(() => {
                                                                hideModal('rescheduleConfirmModal-' + id);
                                                                showActionToast('Failed to reschedule.', 'error');
                                                                btn.disabled = false;
                                                                setTimeout(function(){ showModal('rescheduleModal-' + id); }, 250);
                                                            });
                                                        });
                                                    });

                                                    // View reschedule history (lightweight alert for now)
                                                    window.viewReschedHistory = function(id) {
                                                        fetch('get_distribution_reschedules.php?log_id=' + encodeURIComponent(id))
                                                            .then(r => r.json())
                                                            .then(data => {
                                                                if (!data || !data.success || !Array.isArray(data.items) || data.items.length === 0) {
                                                                    alert('No reschedule history yet.');
                                                                    return;
                                                                }
                                                                const lines = data.items.map(i => {
                                                                    const oldd = i.old_visitation_date || 'N/A';
                                                                    const newd = i.new_visitation_date || 'N/A';
                                                                    const who  = i.staff_name || 'System';
                                                                    const when = i.created_at || '';
                                                                    return `• ${oldd} → ${newd} — ${i.reason} (${who}, ${when})`;
                                                                }).join('\n');
                                                                alert(lines);
                                                            })
                                                            .catch(() => alert('Failed to load history.'));
                                                    }

                                                    // Initialize one-time tooltip logic for latest reschedule details
                                                    if (!window.__reschedTooltipInit) {
                                                        window.__reschedTooltipInit = true;
                                                        window.__reschedCache = {};

                                                        function getTooltipEl() {
                                                            let el = document.getElementById('reschedTooltip');
                                                            if (!el) {
                                                                el = document.createElement('div');
                                                                el.id = 'reschedTooltip';
                                                                el.className = 'tooltip-box hidden';
                                                                document.body.appendChild(el);
                                                            }
                                                            return el;
                                                        }

                                                        function escapeHtml(s){
                                                            return (s||'').replace(/[&<>"]+/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]});
                                                        }

                                                        function formatDate(d){
                                                            if(!d) return 'N/A';
                                                            const dt = new Date(d.replace(' ', 'T'));
                                                            if(isNaN(dt)) return d;
                                                            return dt.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'2-digit'});
                                                        }
                                                        function formatDateTime(d){
                                                            if(!d) return 'N/A';
                                                            const dt = new Date(d.replace(' ', 'T'));
                                                            if(isNaN(dt)) return d;
                                                            return dt.toLocaleString('en-US', {year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit'});
                                                        }

                                                        function positionTooltip(el, anchor){
                                                            const rect = anchor.getBoundingClientRect();
                                                            const margin = 8;
                                                            el.style.left = Math.max(8, rect.right + margin) + 'px';
                                                            el.style.top  = Math.max(8, rect.top) + 'px';
                                                        }

                                                        function showTooltipFor(btn){
                                                            const id = btn.getAttribute('data-id');
                                                            const tooltip = getTooltipEl();
                                                            const cached = window.__reschedCache[id];

                                                            function render(item){
                                                                const html = `
                                                                    <div class="tooltip-title">Latest Reschedule</div>
                                                                    <div><span class="tooltip-muted">New date:</span> ${formatDate(item.new_visitation_date)}</div>
                                                                    <div><span class="tooltip-muted">Reason:</span> ${escapeHtml(item.reason || '—')}</div>
                                                                    <div class="tooltip-muted mt-1">By ${escapeHtml(item.staff_name || 'System')} on ${formatDateTime(item.created_at)}</div>
                                                                `;
                                                                tooltip.innerHTML = html;
                                                                tooltip.classList.remove('hidden');
                                                                positionTooltip(tooltip, btn);
                                                            }

                                                            if (cached) {
                                                                render(cached);
                                                                return;
                                                            }

                                                            tooltip.innerHTML = '<div class="tooltip-title">Loading…</div>';
                                                            tooltip.classList.remove('hidden');
                                                            positionTooltip(tooltip, btn);

                                                            fetch('get_distribution_reschedules.php?log_id=' + encodeURIComponent(id))
                                                                .then(r => r.json())
                                                                .then(data => {
                                                                    if (data && data.success && Array.isArray(data.items) && data.items.length > 0) {
                                                                        const latest = data.items[0];
                                                                        window.__reschedCache[id] = latest;
                                                                        render(latest);
                                                                    } else {
                                                                        tooltip.innerHTML = '<div class="tooltip-title">No reschedule history found</div>';
                                                                        positionTooltip(tooltip, btn);
                                                                    }
                                                                })
                                                                .catch(() => {
                                                                    tooltip.innerHTML = '<div class="tooltip-title">Failed to load</div>';
                                                                    positionTooltip(tooltip, btn);
                                                                });
                                                        }

                                                        function hideTooltip(){
                                                            const tooltip = document.getElementById('reschedTooltip');
                                                            if (tooltip) tooltip.classList.add('hidden');
                                                        }

                                                        // Hover and focus handlers (event delegation)
                                                        document.body.addEventListener('mouseenter', function(e){
                                                            const btn = e.target.closest('.resched-info');
                                                            if (btn) showTooltipFor(btn);
                                                        }, true);
                                                        document.body.addEventListener('mouseleave', function(e){
                                                            const btn = e.target.closest('.resched-info');
                                                            if (btn) hideTooltip();
                                                        }, true);
                                                        document.body.addEventListener('focusin', function(e){
                                                            const btn = e.target.closest('.resched-info');
                                                            if (btn) showTooltipFor(btn);
                                                        });
                                                        document.body.addEventListener('focusout', function(e){
                                                            const btn = e.target.closest('.resched-info');
                                                            if (btn) hideTooltip();
                                                        });
                                                        // Click toggles (useful on mobile)
                                                        document.body.addEventListener('click', function(e){
                                                            const btn = e.target.closest('.resched-info');
                                                            const tooltip = document.getElementById('reschedTooltip');
                                                            if (btn) {
                                                                if (tooltip && !tooltip.classList.contains('hidden')) hideTooltip();
                                                                showTooltipFor(btn);
                                                                e.stopPropagation();
                                                            } else {
                                                                hideTooltip();
                                                            }
                                                        });
                                                    }
                                                });
                                                </script>
                                            </td>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
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
<?php include 'includes/notification_complete.php'; ?>
<script>
// Export the current Distribution Records view to a PDF-friendly HTML (opens in new tab)
function exportToPDF() {
    try {
        // Read current search and filter values
        const searchVal = document.getElementById('distribution_search')?.value || '';
        const barangayVal = document.querySelector('select[name="barangay"]')?.value || '';
        const inputIdVal = document.querySelector('select[name="input_id"]')?.value || '';

        let exportUrl = 'export_distribution_pdf.php?action=export_distribution_pdf';
        if (searchVal) exportUrl += `&search=${encodeURIComponent(searchVal)}`;
        if (barangayVal) exportUrl += `&barangay=${encodeURIComponent(barangayVal)}`;
        if (inputIdVal) exportUrl += `&input_id=${encodeURIComponent(inputIdVal)}`;

        // Show loading indicator
        const exportBtn = document.querySelector('button[onclick="exportToPDF()"]');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
        exportBtn.disabled = true;

        window.open(exportUrl, '_blank');

        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 3000);
    } catch (e) {
        console.error('Export to PDF failed:', e);
        alert('Sorry, something went wrong while generating the export.');
    }
}
</script>
<script>
// --- Auto-suggest search for Distribution Records (mirrors farmers.php pattern) ---
function searchDistributionAutoSuggest(query) {
    const suggestions = document.getElementById('distribution_suggestions');
    if (!suggestions) return;

    if (!query || query.length < 1) {
        suggestions.innerHTML = '';
        suggestions.classList.add('hidden');
        return;
    }

    // Loading indicator
    suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Searching...</div>';
    suggestions.classList.remove('hidden');

    // Query common endpoint used across the app
    fetch('get_farmers.php?action=search&include_archived=false&query=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.farmers) && data.farmers.length > 0) {
                let html = '';
                data.farmers.forEach(farmer => {
                    const safeName = (farmer.full_name || '').replace(/'/g, "\\'");
                    html += `
                        <div class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b last:border-b-0" 
                             onclick="selectDistributionSuggestion('${farmer.farmer_id}', '${safeName}')">
                            <div class="font-medium text-gray-900">${farmer.full_name}</div>
                            <div class="text-sm text-gray-600">ID: ${farmer.farmer_id} | Contact: ${farmer.contact_number || 'N/A'}</div>
                            <div class="text-xs text-gray-500">${farmer.barangay_name || ''}</div>
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
        .catch(err => {
            console.error('Search error:', err);
            suggestions.innerHTML = '<div class="px-3 py-2 text-red-500">Error loading suggestions</div>';
            suggestions.classList.remove('hidden');
        });
}

function selectDistributionSuggestion(farmerId, farmerName) {
    const input = document.getElementById('distribution_search');
    const suggestions = document.getElementById('distribution_suggestions');
    if (input) input.value = farmerName || '';
    if (suggestions) suggestions.classList.add('hidden');

    // Submit the surrounding form to apply filter
    const form = input ? input.closest('form') : null;
    if (form) form.submit();
}

function showDistributionSuggestions() {
    const input = document.getElementById('distribution_search');
    if (input && input.value.length >= 1) {
        searchDistributionAutoSuggest(input.value);
    }
}

function hideDistributionSuggestions() {
    const suggestions = document.getElementById('distribution_suggestions');
    if (suggestions) {
        setTimeout(() => suggestions.classList.add('hidden'), 200);
    }
}
</script>
<style>
/* Simple tooltip styling for reschedule details */
.tooltip-box{position:fixed;z-index:99999;background:#fff;border:1px solid #e5e7eb;box-shadow:0 10px 15px rgba(0,0,0,0.1);border-radius:8px;padding:8px 12px;min-width:220px;max-width:320px;font-size:12px;color:#111827}
.tooltip-box.hidden{display:none}
.tooltip-title{font-weight:600;margin-bottom:4px;color:#111827}
.tooltip-muted{color:#6b7280}
.toast-success{background:#16a34a;color:#fff}
.toast-error{background:#dc2626;color:#fff}
</style>