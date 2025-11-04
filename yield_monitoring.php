<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once __DIR__ . '/includes/yield_helpers.php';
$pageTitle = 'Yield Monitoring - Lagonglong FARMS';

// Check if yield_monitoring table exists
$table_check_stmt = $conn->prepare("SHOW TABLES LIKE 'yield_monitoring'");
$table_check_stmt->execute();
$table_check_result = $table_check_stmt->get_result();
if ($table_check_result->num_rows == 0) {
    die("Error: yield_monitoring table does not exist. Please import the yield_monitoring.sql file first.");
}
$table_check_stmt->close();

// Get commodity categories for filter dropdown
$commodity_categories = [];
$categories_stmt = $conn->prepare("SELECT category_id, category_name FROM commodity_categories ORDER BY category_name");
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $commodity_categories[] = $row;
    }
}
$categories_stmt->close();

// Get commodities with their categories for dropdown
$commodities = [];
$commodities_stmt = $conn->prepare("SELECT c.commodity_id, c.commodity_name, c.category_id, cc.category_name 
                                          FROM commodities c 
                                          LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id 
                                          ORDER BY cc.category_name, c.commodity_name");
$commodities_stmt->execute();
$commodities_result = $commodities_stmt->get_result();
if ($commodities_result) {
    while ($row = $commodities_result->fetch_assoc()) {
        $commodities[] = $row;
    }
}
$commodities_stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_visit') {
    $farmer_id = $_POST['farmer_id'] ?? '';
    $commodity_id = $_POST['commodity_id'] ?? '';
    $season = $_POST['season'] ?? '';
    $yield_amount = $_POST['yield_amount'] ?? '';
    $distributed_input = $_POST['distributed_input'] ?? null;
    $visit_date = $_POST['visit_date'] ?? null;
    $unit = $_POST['unit'] ?? null;
    $quality_grade = $_POST['quality_grade'] ?? null;
    $growth_stage = $_POST['growth_stage'] ?? null;
    $field_conditions = $_POST['field_conditions'] ?? null;
    $visit_notes = $_POST['visit_notes'] ?? null;
    $errors = [];
    $staff_id = $_SESSION['staff_id'] ?? null;
    if (empty($farmer_id)) $errors[] = 'Farmer selection is required';
    if (empty($commodity_id)) $errors[] = 'Commodity selection is required';
    if (empty($season)) $errors[] = 'Season is required';
    if (empty($yield_amount)) $errors[] = 'Yield amount is required';
    if (empty($staff_id)) $errors[] = 'Staff ID is missing. Please log in again.';

    if (empty($errors)) {
        $sql = "INSERT INTO yield_monitoring (
            farmer_id, commodity_id, season, yield_amount, record_date, recorded_by_staff_id,
            distributed_input, visit_date, unit, quality_grade, growth_stage, field_conditions, visit_notes
        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "ssssisssssss",
                $farmer_id,
                $commodity_id,
                $season,
                $yield_amount,
                $staff_id,
                $distributed_input,
                $visit_date,
                $unit,
                $quality_grade,
                $growth_stage,
                $field_conditions,
                $visit_notes
            );
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Yield record added successfully.";
                header("Location: yield_monitoring.php");
                exit();
            } else {
                $errors[] = "Error recording yield: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
    }
}

// Handle AJAX edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_record') {
    $response = ['success' => false, 'message' => 'Unknown error'];
    $record_id = $_POST['record_id'] ?? '';
    $commodity_id = $_POST['commodity_id'] ?? null;
    $season = $_POST['season'] ?? null;
    $yield_amount = $_POST['yield_amount'] ?? null;
    $distributed_input = $_POST['distributed_input'] ?? null;
    $visit_date = $_POST['visit_date'] ?? null;
    $unit = $_POST['unit'] ?? null;
    $quality_grade = $_POST['quality_grade'] ?? null;
    $growth_stage = $_POST['growth_stage'] ?? null;
    $field_conditions = $_POST['field_conditions'] ?? null;
    $visit_notes = $_POST['visit_notes'] ?? null;

    if (empty($record_id)) {
        $response['message'] = 'Record ID is required.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Prepare update statement
    $update_sql = "UPDATE yield_monitoring SET commodity_id = ?, season = ?, yield_amount = ?, distributed_input = ?, visit_date = ?, unit = ?, quality_grade = ?, growth_stage = ?, field_conditions = ?, visit_notes = ? WHERE yield_id = ?";
    $stmt_upd = $conn->prepare($update_sql);
    if ($stmt_upd) {
        // Bind types: commodity_id (i), season (s), yield_amount (d), distributed_input (s), visit_date (s), unit (s), quality_grade (s), growth_stage (s), field_conditions (s), visit_notes (s), record_id (i)
        $yield_amount_val = is_numeric($yield_amount) ? (float)$yield_amount : null;
        $stmt_upd->bind_param("isdsssssssi",
            $commodity_id,
            $season,
            $yield_amount_val,
            $distributed_input,
            $visit_date,
            $unit,
            $quality_grade,
            $growth_stage,
            $field_conditions,
            $visit_notes,
            $record_id
        );

        if ($stmt_upd->execute()) {
            // Fetch updated record to return to client
            $fetch_sql = "SELECT ym.*, f.first_name, f.last_name, c.commodity_name, cc.category_id, cc.category_name FROM yield_monitoring ym LEFT JOIN farmers f ON ym.farmer_id = f.farmer_id LEFT JOIN commodities c ON ym.commodity_id = c.commodity_id LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id WHERE ym.yield_id = ? LIMIT 1";
            $stmt_fetch = $conn->prepare($fetch_sql);
            if ($stmt_fetch) {
                $stmt_fetch->bind_param('i', $record_id);
                $stmt_fetch->execute();
                $result_fetch = $stmt_fetch->get_result();
                $updated_record = $result_fetch ? $result_fetch->fetch_assoc() : null;
                $stmt_fetch->close();

                $response['success'] = true;
                $response['message'] = 'Record updated successfully.';
                $response['record'] = $updated_record;
            } else {
                $response['success'] = true;
                $response['message'] = 'Record updated, but failed to fetch updated data.';
            }
        } else {
            $response['message'] = 'Database error: ' . $stmt_upd->error;
        }
        $stmt_upd->close();
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Toast messages handled globally via includes/toast_flash.php

// Get filter parameters from GET
$farmer_filter = isset($_GET['farmer']) ? trim($_GET['farmer']) : '';
$farmer_id_filter = isset($_GET['farmer_id']) ? trim($_GET['farmer_id']) : '';
$farmer_search = isset($_GET['farmer_search']) ? trim($_GET['farmer_search']) : '';
$category_filter = isset($_GET['category_filter']) ? trim($_GET['category_filter']) : '';
$commodity_filter = isset($_GET['commodity_filter']) ? trim($_GET['commodity_filter']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';

// Fetch yield records from database
$yield_records = [];
$total_records = 0;

try {
    // Check if yield_monitoring table exists
    $table_check_stmt2 = $conn->prepare("SHOW TABLES LIKE 'yield_monitoring'");
    $table_check_stmt2->execute();
    $table_check_result2 = $table_check_stmt2->get_result();
    if ($table_check_result2->num_rows == 0) {
        die("Error: yield_monitoring table does not exist. Please create the table first.");
    }
    $table_check_stmt2->close();
    
    // Build query with filters
    $where_clause = "WHERE 1=1";
    $params = [];
    $types = "";
    
    // Farmer ID filter (for notification redirects)
    if (!empty($farmer_id_filter)) {
        // Exact match by farmer ID
        $where_clause .= " AND f.farmer_id = ?";
        $params[] = $farmer_id_filter;
        $types .= "s";
    } elseif (!empty($farmer_filter)) {
        // Search by farmer name
        $where_clause .= " AND (
            f.first_name LIKE ? OR 
            f.last_name LIKE ? OR 
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
        $search_term = "%$farmer_filter%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ssss";
    }
    
    // Farmer search filter (from new form)
    if (!empty($farmer_search)) {
        $where_clause .= " AND (
            f.first_name LIKE ? OR 
            f.last_name LIKE ? OR 
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
        $search_term = "%$farmer_search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ssss";
    }
    
    // Category filter
    if (!empty($category_filter)) {
        $where_clause .= " AND cc.category_id = ?";
        $params[] = $category_filter;
        $types .= "i";
    }
    
    // Commodity filter
    if (!empty($commodity_filter)) {
        $where_clause .= " AND c.commodity_id = ?";
        $params[] = $commodity_filter;
        $types .= "i";
    }
    
    // Date filter: support last N days (7, 30, 90)
    if (!empty($date_filter)) {
        if (ctype_digit($date_filter)) {
            // Relative range: include today
            $where_clause .= " AND DATE(ym.record_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params[] = (int)$date_filter;
            $types .= "i";
        } else {
            // Fallback: exact date if a YYYY-MM-DD slipped in
            $where_clause .= " AND DATE(ym.record_date) = ?";
            $params[] = $date_filter;
            $types .= "s";
        }
    }
    
    // Fetch yield records with farmer and commodity information
    $query = "SELECT ym.*, f.first_name, f.last_name, f.middle_name, f.suffix, f.contact_number, 
                     b.barangay_name, c.commodity_name, cc.category_id, cc.category_name
              FROM yield_monitoring ym
              LEFT JOIN farmers f ON ym.farmer_id = f.farmer_id
              LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
              LEFT JOIN commodities c ON ym.commodity_id = c.commodity_id
              LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id
              $where_clause
              ORDER BY ym.record_date DESC";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $yield_records = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $yield_records = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
    
    $total_records = count($yield_records);
} catch (Exception $e) {
    $errors[] = "Error fetching yield records: " . $e->getMessage();
}

include 'includes/layout_start.php';
?>

<main class="app-content">
    <!-- Toasts are emitted globally by includes/toast_flash.php -->
    
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
                <?php if (!empty($farmer_filter) || !empty($farmer_id_filter)): ?>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-filter mr-2"></i>
                            Filtered by: <?php echo htmlspecialchars($farmer_filter); ?>
                        </span>
                        <a href="yield_monitoring.php" class="text-sm text-gray-600 hover:text-agri-green">
                            <i class="fas fa-times-circle mr-1"></i>Clear Filter
                        </a>
                    </div>
                <?php endif; ?>
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

        <!-- Agronomic Crops -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-wheat-awn text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        // Count yield records for Agronomic Crops (category_id = 1)
                        $agronomic_count = 0;
                        foreach ($yield_records as $record) {
                            if (isset($record['category_id']) && $record['category_id'] == 1) {
                                $agronomic_count++;
                            }
                        }
                        echo $agronomic_count;
                    ?></h3>
                    <p class="text-gray-600">Agronomic Crops</p>
                </div>
            </div>
        </div>

        <!-- High Value Crops -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-apple-alt text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        // Count yield records for High Value Crops (category_id = 2)
                        $hvc_count = 0;
                        foreach ($yield_records as $record) {
                            if (isset($record['category_id']) && $record['category_id'] == 2) {
                                $hvc_count++;
                            }
                        }
                        echo $hvc_count;
                    ?></h3>
                    <p class="text-gray-600">High Value Crops</p>
                </div>
            </div>
        </div>

        <!-- Livestock & Poultry -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-paw text-orange-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-gray-900"><?php 
                        // Count yield records for Livestock (category_id = 3) and Poultry (category_id = 4)
                        $animal_count = 0;
                        foreach ($yield_records as $record) {
                            if (isset($record['category_id']) && ($record['category_id'] == 3 || $record['category_id'] == 4)) {
                                $animal_count++;
                            }
                        }
                        echo $animal_count;
                    ?></h3>
                    <p class="text-gray-600">Livestock & Poultry</p>
                </div>
            </div>
        </div>

    </div>

    <!-- Yield Total Cards by Category -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <?php
        // Calculate totals per category
        $category_totals = [
            1 => ['name' => 'Agronomic Crops', 'icon' => 'fa-wheat-awn', 'color' => 'green', 'yields' => []],
            2 => ['name' => 'High Value Crops', 'icon' => 'fa-apple-alt', 'color' => 'purple', 'yields' => []],
            3 => ['name' => 'Livestocks', 'icon' => 'fa-cow', 'color' => 'orange', 'yields' => []],
            4 => ['name' => 'Poultry', 'icon' => 'fa-dove', 'color' => 'yellow', 'yields' => []]
        ];

        // Aggregate yields by category and unit
        foreach ($yield_records as $record) {
            $cat_id = $record['category_id'] ?? null;
            if ($cat_id && isset($category_totals[$cat_id])) {
                $amount = floatval($record['yield_amount'] ?? 0);
                $unit = trim($record['unit'] ?? '');
                if ($unit === '') $unit = 'units';
                
                if (!isset($category_totals[$cat_id]['yields'][$unit])) {
                    $category_totals[$cat_id]['yields'][$unit] = 0;
                }
                $category_totals[$cat_id]['yields'][$unit] += $amount;
            }
        }

        // Display a card for each category
        foreach ($category_totals as $cat_id => $cat_data):
            $total_yield_display = '0';
            if (!empty($cat_data['yields'])) {
                // Show aggregated totals per unit
                $parts = [];
                foreach ($cat_data['yields'] as $unit => $total) {
                    $parts[] = number_format($total, 1) . ' ' . htmlspecialchars($unit);
                }
                $total_yield_display = implode('<br>', $parts);
            }
        ?>
        <!-- <?php echo htmlspecialchars($cat_data['name']); ?> Total -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-<?php echo $cat_data['color']; ?>-500">
            <div class="flex items-start">
                <div class="w-12 h-12 bg-<?php echo $cat_data['color']; ?>-100 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                    <i class="fas <?php echo $cat_data['icon']; ?> text-<?php echo $cat_data['color']; ?>-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-gray-900 leading-tight"><?php echo $total_yield_display; ?></h3>
                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($cat_data['name']); ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>



    <!-- Filter Tabs -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-wrap gap-3 mb-6">
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="agronomic">
                <i class="fas fa-wheat-awn mr-2"></i>Agronomic Crops
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="high-value">
                <i class="fas fa-apple-alt mr-2"></i>High Value Crops
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="livestock">
                <i class="fas fa-horse mr-2"></i>Livestock
            </button>
            <button class="filter-tab bg-white text-gray-700 px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors flex items-center" data-filter="poultry">
                <i class="fas fa-egg mr-2"></i>Poultry
            </button>
        </div>
    </div>

    <!-- Search and Filter Controls -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="yield_monitoring.php" class="flex flex-col gap-4">
            <input type="hidden" name="category_filter" id="hidden_category_filter" value="<?php echo htmlspecialchars($_GET['category_filter'] ?? ''); ?>">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">Commodity</label>
                    <select name="commodity_filter" id="commodity_filter" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        <option value="">All Commodities</option>
                        <?php foreach ($commodities as $commodity): ?>
                            <option value="<?php echo htmlspecialchars($commodity['commodity_id']); ?>" 
                                    data-category="<?php echo htmlspecialchars($commodity['category_id']); ?>"
                                    <?php echo (isset($_GET['commodity_filter']) && $_GET['commodity_filter'] == $commodity['commodity_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($commodity['commodity_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">Search Farmer</label>
                    <div class="relative">
                        <input type="text" id="farmer_search" name="farmer_search" autocomplete="off" placeholder="Search by farmer name..." 
                               value="<?php echo htmlspecialchars($_GET['farmer_search'] ?? ''); ?>"
                               class="search-input w-full px-4 py-2 pl-10 bg-gray-100 border border-gray-200 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        <input type="hidden" id="farmer_id" name="farmer_id" value="<?php echo htmlspecialchars($_GET['farmer_id'] ?? ''); ?>">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500"></i>
                        <button type="button" id="farmer_clear_btn" title="Clear" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden" style="background:transparent;border:none;">
                            &times;
                        </button>
                        <div id="farmer_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-2">Date Range</label>
                    <select name="date_filter" class="w-full px-4 py-2 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-agri-green focus:border-transparent">
                        <option value="">All Dates</option>
                        <option value="7" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter'] == '7') ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter'] == '30') ? 'selected' : ''; ?>>Last 30 days</option>
                        <option value="90" <?php echo (isset($_GET['date_filter']) && $_GET['date_filter'] == '90') ? 'selected' : ''; ?>>Last 3 months</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <?php 
                // Check if any filters are active
                $hasActiveFilters = !empty($_GET['category_filter']) || 
                                  !empty($_GET['commodity_filter']) || 
                                  !empty($_GET['farmer_search']) || 
                                  !empty($_GET['date_filter']) ||
                                  !empty($_GET['farmer']) ||
                                  !empty($_GET['farmer_id']);
                
                if ($hasActiveFilters): ?>
                    <a href="yield_monitoring.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors flex items-center">
                        <i class="fas fa-times mr-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
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
                            <tr class="hover:bg-gray-50" data-record-id="<?php echo htmlspecialchars($record['yield_id'] ?? $record['id'] ?? ''); ?>">
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
                                        <?php echo number_format($record['yield_amount'], 2); ?> <?php echo htmlspecialchars($record['unit'] ?? ''); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($record['record_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <!-- View button: opens readonly details modal -->
                                    <button type="button" class="text-agri-green hover:text-agri-dark mr-3 btn-view-record" title="View Details" data-bs-toggle="modal" data-bs-target="#viewRecordModal" data-record='<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES); ?>'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <!-- Edit button: opens edit modal pre-filled -->
                                    <button type="button" class="text-blue-600 hover:text-blue-800 mr-3 btn-edit-record" title="Edit" data-bs-toggle="modal" data-bs-target="#editRecordModal" data-record='<?php echo htmlspecialchars(json_encode($record), ENT_QUOTES); ?>'>
                                        <i class="fas fa-edit"></i>
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
                <i class="fas fa-info-circle text-3xl mb-3"></i>
                <p>No yield records found for the selected farmer.</p>
            </div>
        <?php endif; ?>
    </div>
    </main>


<?php include 'yield_record_modal.php'; ?>

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
    
    // Set active tab based on current category filter
    setActiveTab();
    
    // Initialize commodity filtering based on current category
    const urlParams = new URLSearchParams(window.location.search);
    const categoryFilter = urlParams.get('category_filter');
    if (categoryFilter) {
        filterCommodityDropdown(categoryFilter);
    }
    
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

    // Guard if elements are missing
    if (!farmerSearch || !farmerId || !suggestions) {
        return;
    }

    // Keyboard navigation state
    let activeIndex = -1;
    function clearActive() {
        suggestions.querySelectorAll('.farmer-suggestion-item').forEach(el => {
            el.classList.remove('bg-gray-200');
            el.classList.remove('ring-1');
            el.classList.remove('ring-agri-green');
        });
    }
    function setActive(i) {
        const items = suggestions.querySelectorAll('.farmer-suggestion-item');
        if (!items.length) return;
        activeIndex = Math.max(0, Math.min(i, items.length - 1));
        clearActive();
        const el = items[activeIndex];
        el.classList.add('bg-gray-200');
        el.classList.add('ring-1');
        el.classList.add('ring-agri-green');
        el.scrollIntoView({ block: 'nearest' });
    }

    farmerSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        activeIndex = -1;
        
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

    // Keyboard navigation on input
    farmerSearch.addEventListener('keydown', function(e) {
        const items = suggestions.querySelectorAll('.farmer-suggestion-item');
        const visible = !suggestions.classList.contains('hidden') && items.length > 0;
        if (e.key === 'Escape') {
            suggestions.classList.add('hidden');
            return;
        }
        if (!visible) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(activeIndex + 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(activeIndex - 1);
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (activeIndex >= 0 && activeIndex < items.length) {
                e.preventDefault();
                items[activeIndex].click();
            }
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!farmerSearch.contains(e.target) && !suggestions.contains(e.target)) {
            setTimeout(() => {
                suggestions.classList.add('hidden');
            }, 200); // Delay to allow click events on suggestions
        }
    });

    // Clear button
    const clearBtn = document.getElementById('farmer_clear_btn');
    if (clearBtn) {
        const updateClear = () => {
            if (farmerSearch.value && farmerSearch.value.trim() !== '') clearBtn.classList.remove('hidden');
            else clearBtn.classList.add('hidden');
        };
        updateClear();
        farmerSearch.addEventListener('input', updateClear);
        clearBtn.addEventListener('click', function(){
            farmerSearch.value = '';
            farmerId.value = '';
            suggestions.classList.add('hidden');
            updateClear();
        });
    }
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
                        // Fetch and populate categories and commodities for selected farmer
                        fetch('get_farmer_commodities.php?farmer_id=' + encodeURIComponent(farmer.farmer_id))
                            .then(response => response.json())
                            .then(data => {
                                const categoryFilter = document.getElementById('commodity_category_filter');
                                const commoditySelect = document.getElementById('commodity_id');
                                categoryFilter.disabled = false;
                                categoryFilter.innerHTML = '<option value="">Select Category</option>';
                                let categories = [];
                                let farmerCommodities = [];
                                if (data.success && data.commodities) {
                                    // Collect unique categories and all commodities for this farmer
                                    data.commodities.forEach(c => {
                                        let catName = c.category_name || c.category;
                                        if (catName && !categories.includes(catName)) {
                                            categories.push(catName);
                                            categoryFilter.innerHTML += `<option value="${catName}">${catName}</option>`;
                                        }
                                        farmerCommodities.push({
                                            id: c.commodity_id,
                                            name: c.commodity_name,
                                            category: catName
                                        });
                                    });
                                    // Store commodities for later filtering
                                    categoryFilter.farmerCommodities = farmerCommodities;
                                }
                                // Auto-select first category and filter commodities
                                if (categories.length > 0) {
                                    categoryFilter.value = categories[0];
                                    filterCommodities();
                                }
                            });
                    });
                    // Mouse move highlights
                    item.addEventListener('mouseenter', function(){
                        clearActive();
                        this.classList.add('bg-gray-200');
                    });
                    
                    suggestions.appendChild(item);
                });
                suggestions.classList.remove('hidden');
                // Reset keyboard index when new results render
                activeIndex = -1;
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

// Commodity filtering function for modal
function filterCommodities() {
    const categoryFilter = document.getElementById('commodity_category_filter');
    const commoditySelect = document.getElementById('commodity_id');
    const selectedCategory = categoryFilter.value;
    // Get commodities for selected farmer
    const farmerCommodities = categoryFilter.farmerCommodities || [];
    // Reset commodity dropdown
    commoditySelect.innerHTML = '<option value="">Select Commodity</option>';
    // Add only commodities matching selected category
    farmerCommodities.forEach(c => {
        // Normalize category values coming from different places (category, category_id, category_name)
        const cCatId = (c.category_id !== undefined) ? String(c.category_id) : '';
        const cCatName = (c.category_name !== undefined) ? String(c.category_name) : '';
        const cCatOther = (c.category !== undefined) ? String(c.category) : '';
        const matches = (selectedCategory === '') ||
                        (cCatId && String(selectedCategory) === cCatId) ||
                        (cCatName && String(selectedCategory) === cCatName) ||
                        (cCatOther && String(selectedCategory) === cCatOther);
        if (matches) {
            // Use data-category attribute to preserve whatever category identifier is present
            const dataCategory = c.category || c.category_id || c.category_name || '';
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            if (dataCategory) opt.setAttribute('data-category', dataCategory);
            commoditySelect.appendChild(opt);
        }
    });
    commoditySelect.value = '';
    // If a page-level function exists to update season options, call it for the first option
    if (typeof setSeasonOptionsForCategory === 'function') {
        // Trigger update for currently selected commodity (none) so it resets to default agronomic behavior
        setTimeout(function() { if (document.getElementById('season')) setSeasonOptionsForCategory(document.getElementById('season'), categoryFilter.options[categoryFilter.selectedIndex].textContent || ''); }, 50);
    }
}

// Initialize commodity filter when modal opens
document.getElementById('addVisitModal').addEventListener('shown.bs.modal', function() {
    // When modal is shown, select first available category and filter commodities
    const categoryFilter = document.getElementById('commodity_category_filter');
    if (!categoryFilter.disabled && categoryFilter.options.length > 1) {
        for (let option of categoryFilter.options) {
            if (option.value !== '') {
                categoryFilter.value = option.value;
                filterCommodities();
                // After filtering, ensure season select reflects the first commodity (if any)
                if (typeof setSeasonOptionsForCategory === 'function') {
                    setTimeout(function() {
                        const commoditySel = document.getElementById('commodity_id');
                        const seasonSel = document.getElementById('season');
                        if (commoditySel && seasonSel && commoditySel.options.length > 0) {
                            const opt = commoditySel.options[commoditySel.selectedIndex] || commoditySel.options[0];
                            const catId = opt ? opt.getAttribute('data-category') : '';
                            const catName = (typeof categoryMap !== 'undefined' && categoryMap[catId]) ? categoryMap[catId] : '';
                            setSeasonOptionsForCategory(seasonSel, catName);
                        }
                    }, 100);
                }
                break;
            }
        }
    }
});

// Reset form and filters when modal is closed
document.getElementById('addVisitModal').addEventListener('hidden.bs.modal', function() {
    // Reset form
    document.getElementById('yieldVisitForm').reset();
    
    // Reset category filter to Agronomic Crops default
    const categoryFilter = document.getElementById('commodity_category_filter');
    const options = categoryFilter.querySelectorAll('option');
    for (let option of options) {
        if (option.textContent.includes('Agronomic Crops')) {
            categoryFilter.value = option.value;
            break;
        }
    }
    
    // Clear farmer suggestions
    const suggestions = document.getElementById('farmer_suggestions');
    if (suggestions) {
        suggestions.classList.add('hidden');
    }
    
    // Remove validation classes
    document.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
    });
});

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
        if (window.AgriToast) { AgriToast.error('Please fill in all required fields.'); }
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
    if (window.APP_DEBUG) console.log('Filter changed to:', filterValue);
    
    // Map filter values to category IDs
    const categoryMap = {
        'agronomic': '1',
        'high-value': '2', 
        'livestock': '3',
        'poultry': '4'
    };
    
    const categoryId = categoryMap[filterValue];
    
    // Update hidden category filter field
    const hiddenField = document.getElementById('hidden_category_filter');
    if (hiddenField) {
        hiddenField.value = categoryId;
    }
    
    // Filter commodity dropdown based on selected category
    filterCommodityDropdown(categoryId);
}

function setActiveTab() {
    // Get current category filter from URL
    const urlParams = new URLSearchParams(window.location.search);
    const categoryFilter = urlParams.get('category_filter');
    
    // Map category IDs to filter values
    const filterMap = {
        '1': 'agronomic',
        '2': 'high-value',
        '3': 'livestock',
        '4': 'poultry'
    };
    
    const filterValue = filterMap[categoryFilter];
    if (filterValue) {
        // Find and activate the corresponding tab
        const filterTabs = document.querySelectorAll('.filter-tab');
        filterTabs.forEach(tab => {
            if (tab.getAttribute('data-filter') === filterValue) {
                // Remove active class from all tabs
                filterTabs.forEach(t => {
                    t.classList.remove('active', 'bg-agri-green', 'text-white');
                    t.classList.add('bg-white', 'text-gray-700', 'border', 'border-gray-300');
                });
                
                // Add active class to current tab
                tab.classList.add('active', 'bg-agri-green', 'text-white');
                tab.classList.remove('bg-white', 'text-gray-700', 'border', 'border-gray-300');
            }
        });
    }
}

function updateSummaryCards(filterValue) {
    // This function will update the summary cards based on the selected filter
    // For now, we'll just log the action
    if (window.APP_DEBUG) console.log('Updating summary cards for filter:', filterValue);
    
    // You can implement actual data filtering here
    // Example: fetch filtered data and update the card values
}

function filterCommodityDropdown(categoryId) {
    const commoditySelect = document.getElementById('commodity_filter');
    const allOptions = commoditySelect.querySelectorAll('option');
    
    // Reset commodity selection when category changes
    commoditySelect.value = '';
    
    // Show/hide options based on selected category
    allOptions.forEach(option => {
        if (option.value === '') {
            // Always show the "All Commodities" option
            option.style.display = 'block';
        } else {
            const optionCategory = option.getAttribute('data-category');
            if (!categoryId || optionCategory === categoryId) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        }
    });
}

function filterDataTable(filterValue) {
    if (window.APP_DEBUG) console.log('Filtering data table for:', filterValue);
    
    // Get all table rows (assuming there will be a data table with commodity information)
    const tableRows = document.querySelectorAll('tbody tr');
    
    tableRows.forEach(row => {
        let showRow = false;
        
        // Get category ID from the row (adjust selector as needed when table is implemented)
        const categoryCell = row.querySelector('td[data-category-id]');
        const categoryId = categoryCell ? categoryCell.textContent.trim() : '';
        
        if (filterValue === 'agronomic') {
            showRow = (categoryId === '1'); // Agronomic Crops
        } else if (filterValue === 'high-value') {
            showRow = (categoryId === '2'); // High Value Crops
        } else if (filterValue === 'livestock') {
            showRow = (categoryId === '3'); // Livestock
        } else if (filterValue === 'poultry') {
            showRow = (categoryId === '4'); // Poultry
        } else {
            // Default: show all rows when no specific filter is active
            showRow = true;
        }
        
        // Show/hide row
        row.style.display = showRow ? '' : 'none';
    });
    
    // Update record count if there's a counter element
    const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
    const recordCounter = document.querySelector('.record-count');
    if (recordCounter) {
        recordCounter.textContent = `Showing ${visibleRows.length} records`;
    }
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

<?php include 'includes/notification_complete.php'; ?>