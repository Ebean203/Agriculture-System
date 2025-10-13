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
                                            </td>
                                            <td class="px-3 py-4">
                                                <?php
                                                // Show actions based on status
                                                if ($visit_status === 'completed') {
                                                    echo '<span class="text-gray-400 italic">No actions needed</span>';
                                                } elseif ($visit_status === 'pending' || $visit_status === 'rescheduled') {
                                                ?>
                                                    <button class="ml-2 px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-semibold flex items-center mark-done-btn" data-id="<?php echo $distribution['log_id']; ?>">
                                                        <i class="fas fa-check mr-1"></i> Mark as Done
                                                    </button>
                                                    <button class="ml-2 px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-semibold flex items-center reschedule-btn" data-id="<?php echo $distribution['log_id']; ?>">
                                                        <i class="fas fa-calendar-alt mr-1"></i> Reschedule
                                                    </button>
                                                <?php
                                                } else {
                                                    echo '<span class="text-gray-400 italic">No actions needed</span>';
                                                }
                                                ?>
                                                <!-- Mark as Done Modal -->
                                                <div id="markDoneModal-<?php echo $distribution['log_id']; ?>" class="fixed z-50 inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
                                                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
                                                        <h3 class="text-lg font-bold mb-4 text-gray-900 flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i>Mark as Done</h3>
                                                        <p class="mb-4 text-gray-700">Are you sure you want to mark this distribution as <span class="font-bold text-green-600">Completed</span>?</p>
                                                        <div class="flex justify-end gap-2">
                                                            <button class="close-mark-done-modal px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800" data-id="<?php echo $distribution['log_id']; ?>">Cancel</button>
                                                            <button class="confirm-mark-done px-4 py-2 rounded bg-green-600 hover:bg-green-700 text-white font-semibold" data-id="<?php echo $distribution['log_id']; ?>">Yes, Mark as Done</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Reschedule Modal -->
                                                <div id="rescheduleModal-<?php echo $distribution['log_id']; ?>" class="fixed z-50 inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
                                                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
                                                        <h3 class="text-lg font-bold mb-4 text-gray-900 flex items-center"><i class="fas fa-calendar-alt text-blue-500 mr-2"></i>Reschedule Visitation</h3>
                                                        <form class="reschedule-form" data-id="<?php echo $distribution['log_id']; ?>">
                                                            <label class="block mb-2 text-gray-700">New Visitation Date</label>
                                                            <input type="date" name="new_date" class="w-full border border-gray-300 rounded px-3 py-2 mb-4" required>
                                                            <div class="flex justify-end gap-2">
                                                                <button type="button" class="close-reschedule-modal px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 text-gray-800" data-id="<?php echo $distribution['log_id']; ?>">Cancel</button>
                                                                <button type="submit" class="px-4 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold">Reschedule</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                                <script>
                                                document.addEventListener('DOMContentLoaded', function() {
                                                    // Mark as Done button
                                                    document.querySelectorAll('.mark-done-btn').forEach(function(btn) {
                                                        btn.addEventListener('click', function(e) {
                                                            e.preventDefault();
                                                            var id = this.getAttribute('data-id');
                                                            document.getElementById('markDoneModal-' + id).classList.remove('hidden');
                                                        });
                                                    });
                                                    // Close Mark as Done modal
                                                    document.querySelectorAll('.close-mark-done-modal').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            document.getElementById('markDoneModal-' + id).classList.add('hidden');
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
                                                                } else {
                                                                    alert(data.message || 'Failed to update.');
                                                                    button.disabled = false;
                                                                }
                                                            })
                                                            .catch(() => {
                                                                alert('Failed to update.');
                                                                button.disabled = false;
                                                            });
                                                        });
                                                    });
                                                    // Reschedule button
                                                    document.querySelectorAll('.reschedule-btn').forEach(function(btn) {
                                                        btn.addEventListener('click', function(e) {
                                                            e.preventDefault();
                                                            var id = this.getAttribute('data-id');
                                                            document.getElementById('rescheduleModal-' + id).classList.remove('hidden');
                                                        });
                                                    });
                                                    // Close Reschedule modal
                                                    document.querySelectorAll('.close-reschedule-modal').forEach(function(btn) {
                                                        btn.addEventListener('click', function() {
                                                            var id = this.getAttribute('data-id');
                                                            document.getElementById('rescheduleModal-' + id).classList.add('hidden');
                                                        });
                                                    });
                                                    // Reschedule form submit
                                                    document.querySelectorAll('.reschedule-form').forEach(function(form) {
                                                        form.addEventListener('submit', function(e) {
                                                            e.preventDefault();
                                                            var id = this.getAttribute('data-id');
                                                            var newDate = this.querySelector('input[name="new_date"]').value;
                                                            if (!newDate) return alert('Please select a new visitation date.');
                                                            var submitBtn = this.querySelector('button[type="submit"]');
                                                            submitBtn.disabled = true;
                                                            fetch('mark_distribution_complete.php', {
                                                                method: 'POST',
                                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                                body: 'distribution_id=' + encodeURIComponent(id) + '&reschedule=1&new_date=' + encodeURIComponent(newDate)
                                                            })
                                                            .then(response => response.json())
                                                            .then(data => {
                                                                if (data.success) {
                                                                    var statusSpan = document.querySelector('button.mark-done-btn[data-id="'+id+'"], button.reschedule-btn[data-id="'+id+'"], #rescheduleModal-'+id).closest('td').previousElementSibling.querySelector('.status-badge');
                                                                    statusSpan.className = 'bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium flex items-center w-fit status-badge';
                                                                    statusSpan.innerHTML = '<i class="fas fa-calendar-alt mr-1"></i>Rescheduled';
                                                                    document.getElementById('rescheduleModal-' + id).classList.add('hidden');
                                                                    // Do NOT remove the action buttons for rescheduled status; leave them visible
                                                                } else {
                                                                    alert(data.message || 'Failed to reschedule.');
                                                                    submitBtn.disabled = false;
                                                                }
                                                            })
                                                            .catch(() => {
                                                                alert('Failed to reschedule.');
                                                                submitBtn.disabled = false;
                                                            });
                                                        });
                                                    });
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