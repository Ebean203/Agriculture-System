<?php
require_once 'conn.php';
header('Content-Type: application/json');
$type = $_GET['type'] ?? 'yield';

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$barangay = $_GET['barangay'] ?? '';
$range = isset($_GET['range']) ? $_GET['range'] : 'monthly';
// Optional filters commonly reused
$input_id = isset($_GET['input_id']) ? (int)$_GET['input_id'] : 0;
$start_date_param = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date_param = isset($_GET['end_date']) ? $_GET['end_date'] : null;

 $data = [];
 $labels = [];
try {
// Debug: log incoming GET parameters for troubleshooting
if (isset($_GET['debug']) && $_GET['debug'] == '2') {
    header('Content-Type: text/plain');
    echo "GET params:\n";
    print_r($_GET);
}
switch ($type) {
    case 'expiring_soon':
        // Return inputs expiring within 60 days for analytics dashboard
        $sql = "SELECT ic.input_name, mi.expiration_date, mi.quantity_on_hand
                FROM mao_inventory mi
                JOIN input_categories ic ON mi.input_id = ic.input_id
                WHERE mi.expiration_date IS NOT NULL
                  AND mi.is_expired = 0
                  AND mi.quantity_on_hand > 0
                  AND mi.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                ORDER BY mi.expiration_date ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $expiring = [];
        while ($row = $result->fetch_assoc()) {
            $days_left = (int)((strtotime($row['expiration_date']) - strtotime(date('Y-m-d'))) / 86400);
            $expiring[] = [
                'name' => $row['input_name'],
                'days_left' => $days_left,
                'qty' => $row['quantity_on_hand'],
                'expiration_date' => $row['expiration_date']
            ];
        }
        $stmt->close();
        echo json_encode($expiring);
        return;
    case 'yield_breakdown':
        // Pie chart: Total yield per commodity for a given date range
        // Default to current year instead of current month to show more data
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $sql = "SELECT c.commodity_name, SUM(ym.yield_amount) as total_yield FROM yield_monitoring ym JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE ym.record_date BETWEEN ? AND ? GROUP BY c.commodity_name ORDER BY c.commodity_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $labels = [];
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['commodity_name'];
            $data[] = (float)$row['total_yield'];
        }
        $stmt->close();
        echo json_encode(['labels' => $labels, 'data' => $data]);
        return;
    case 'compliance_rate':
        // Compliance breakdown per input (bar chart)
        // Default to current year instead of current month to show more data
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $sql = "SELECT ic.input_name, COUNT(DISTINCT mdl.farmer_id) AS expected_count, COUNT(DISTINCT ym.farmer_id) AS compliant_count FROM mao_distribution_log mdl JOIN input_categories ic ON mdl.input_id = ic.input_id LEFT JOIN yield_monitoring ym ON mdl.farmer_id = ym.farmer_id AND ym.record_date BETWEEN ? AND ? WHERE mdl.date_given BETWEEN ? AND ? AND (ic.input_name LIKE '%Seed%' OR ic.input_name LIKE '%Seedling%' OR ic.input_name LIKE '%Palay%') GROUP BY ic.input_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $start_date, $end_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $compliance = [];
        while ($row = $result->fetch_assoc()) {
            $rate = ($row['expected_count'] > 0) ? round(($row['compliant_count'] / $row['expected_count']) * 100, 1) : 0;
            $compliance[] = [
                'input_name' => $row['input_name'],
                'rate' => $rate
            ];
        }
        $stmt->close();
        echo json_encode($compliance);
        return;
    case 'yield_by_category':
        $category_id = isset($_GET['category_id']) ? trim($_GET['category_id']) : '';
        if ($category_id !== '') {
            $debug_rows = [];
            $query = "SELECT c.commodity_id, c.commodity_name, c.category_id, SUM(ym.yield_amount) as total_yield FROM yield_monitoring ym INNER JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE c.category_id = ? GROUP BY c.commodity_id ORDER BY c.commodity_name ASC";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $category_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (!$result) throw new Exception(mysqli_error($conn));
            $units = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $labels[] = $row['commodity_name'];
                $data[] = (float)$row['total_yield'];
                // Get the most recent unit for this commodity
                $unit_stmt = $conn->prepare("SELECT unit FROM yield_monitoring WHERE commodity_id = ? AND unit IS NOT NULL AND unit != '' ORDER BY record_date DESC LIMIT 1");
                $unit_stmt->bind_param('i', $row['commodity_id']);
                $unit_stmt->execute();
                $unit_res = $unit_stmt->get_result();
                $unit = 'kg';
                if ($unit_row = $unit_res->fetch_assoc()) {
                    $unit = $unit_row['unit'];
                }
                $units[] = $unit;
                $unit_stmt->close();
                $debug_rows[] = $row + ['unit' => $unit];
            }
            mysqli_stmt_close($stmt);
            // Enhanced debug: show SQL, params, and result
            if (isset($_GET['debug'])) {
                header('Content-Type: text/plain');
                echo "category_id: $category_id\n";
                echo "SQL: $query\n";
                echo "Result: ";
                echo json_encode($debug_rows, JSON_PRETTY_PRINT);
                exit;
            }
        }
        break;
    case 'activities':
        // ...existing code...
        break;
    case 'yield':
        $commodity = isset($_GET['commodity']) ? trim($_GET['commodity']) : '';
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        $barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';
        $params = [];
        $types = '';
        $units = [];
        $commodities_for_points = [];
        if ($commodity !== '') {
            // Filter by commodity name
            $query = "SELECT ym.record_date, SUM(ym.yield_amount) as total_yield, ym.unit, c.commodity_name FROM yield_monitoring ym JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE c.commodity_name = ?";
            $params[] = $commodity;
            $types .= 's';
            if ($start_date && $end_date) {
                $query .= " AND ym.record_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';
            }
            if ($barangay) {
                $query .= " AND ym.farmer_id IN (SELECT farmer_id FROM farmers WHERE barangay_id = ?)";
                $params[] = $barangay;
                $types .= 's';
            }
            $query .= " GROUP BY ym.record_date, ym.unit, c.commodity_name ORDER BY ym.record_date ASC";
            $stmt = mysqli_prepare($conn, $query);
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
        } else {
            $query = "SELECT ym.record_date, SUM(ym.yield_amount) as total_yield, ym.unit, c.commodity_name FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE 1";
            if ($start_date && $end_date) {
                $query .= " AND ym.record_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';
            }
            if ($barangay) {
                $query .= " AND f.barangay_id = ?";
                $params[] = $barangay;
                $types .= 's';
            }
            $query .= " GROUP BY ym.record_date, ym.unit, c.commodity_name ORDER BY ym.record_date ASC";
            $stmt = mysqli_prepare($conn, $query);
            if (!empty($params)) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['record_date'];
            $data[] = (float)$row['total_yield'];
            $units[] = $row['unit'] ? $row['unit'] : 'kg';
            $commodities_for_points[] = $row['commodity_name'];
        }
        mysqli_stmt_close($stmt);
        break;
    case 'yield_per_barangay':
        $query = "SELECT b.barangay_name, SUM(ym.yield_amount) AS total_yield FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id JOIN barangays b ON f.barangay_id = b.barangay_id GROUP BY b.barangay_name ORDER BY b.barangay_name ASC";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['barangay_name'];
            $data[] = (float)$row['total_yield'];
        }
        mysqli_stmt_close($stmt);
        break;
    case 'input_distribution':
        // Support for range: monthly, 3months, 6months, annual, custom
        $params = [];
        $types = '';
        $date_select = '';
        $group_by = '';
        $order_by = '';
        $where = 'WHERE 1';
        $today = date('Y-m-d');

        if ($range === 'custom' && $start_date_param && $end_date_param) {
            // Explicit date window from UI
            $date_select = 'DATE_FORMAT(date_given, "%Y-%m") as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND DATE(date_given) BETWEEN ? AND ?';
            $params[] = $start_date_param;
            $params[] = $end_date_param;
            $types .= 'ss';
        } elseif ($range === 'monthly') {
            $date_select = 'DATE_FORMAT(date_given, "%Y-%m") as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND YEAR(date_given) = ? AND MONTH(date_given) = ?';
            $params[] = $year;
            $params[] = $month;
            $types .= 'ss';
        } elseif ($range === '3months') {
            $date_select = 'DATE_FORMAT(date_given, "%Y-%m") as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND date_given >= DATE_SUB(?, INTERVAL 3 MONTH) AND date_given <= ?';
            $params[] = $today;
            $params[] = $today;
            $types .= 'ss';
        } elseif ($range === '6months') {
            $date_select = 'DATE_FORMAT(date_given, "%Y-%m") as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND date_given >= DATE_SUB(?, INTERVAL 6 MONTH) AND date_given <= ?';
            $params[] = $today;
            $params[] = $today;
            $types .= 'ss';
        } elseif ($range === 'annual') {
            $date_select = 'YEAR(date_given) as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND date_given >= DATE_SUB(?, INTERVAL 1 YEAR) AND date_given <= ?';
            $params[] = $today;
            $params[] = $today;
            $types .= 'ss';
        } else {
            // Default to current month if unknown range
            $date_select = 'DATE_FORMAT(date_given, "%Y-%m") as period';
            $group_by = 'period';
            $order_by = 'period ASC';
            $where .= ' AND YEAR(date_given) = ? AND MONTH(date_given) = ?';
            $params[] = $year;
            $params[] = $month;
            $types .= 'ss';
        }

        if ($barangay) {
            // Expect barangay as ID
            $where .= ' AND f.barangay_id = ?';
            $params[] = (int)$barangay;
            $types .= 'i';
        }
        if ($input_id) {
            $where .= ' AND mdl.input_id = ?';
            $params[] = $input_id;
            $types .= 'i';
        }

        $query = "SELECT $date_select, SUM(quantity_distributed) as total_inputs FROM mao_distribution_log mdl JOIN farmers f ON mdl.farmer_id = f.farmer_id $where GROUP BY $group_by ORDER BY $order_by";
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['period'];
            $data[] = (float)$row['total_inputs'];
        }
        mysqli_stmt_close($stmt);
        break;
    case 'inventory':
    // Show current inventory available (sum of all items)
    $query = "SELECT SUM(quantity) as total_inventory FROM mao_inventory";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) throw new Exception(mysqli_error($conn));
    $row = mysqli_fetch_assoc($result);
    $labels = ['Current Inventory'];
    $data = [$row ? (float)$row['total_inventory'] : 0];
    mysqli_stmt_close($stmt);
    break;
    case 'boat':
        $query = "SELECT registration_date, COUNT(*) as total_boats FROM boat_records br JOIN farmers f ON br.farmer_id = f.farmer_id WHERE 1";
        $params = [];
        $types = '';
        if ($month) {
            $query .= " AND MONTH(registration_date) = ?";
            $params[] = $month;
            $types .= 's';
        }
        if ($year) {
            $query .= " AND YEAR(registration_date) = ?";
            $params[] = $year;
            $types .= 's';
        }
        if ($barangay) {
            $query .= " AND f.barangay = ?";
            $params[] = $barangay;
            $types .= 's';
        }
        $query .= " GROUP BY registration_date ORDER BY registration_date ASC";
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['registration_date'];
            $data[] = (int)$row['total_boats'];
        }
        mysqli_stmt_close($stmt);
        break;
    case 'farmer':
        $query = "SELECT registration_date, COUNT(*) as total_farmers FROM farmers WHERE 1";
        $params = [];
        $types = '';
        if ($month) {
            $query .= " AND MONTH(registration_date) = ?";
            $params[] = $month;
            $types .= 's';
        }
        if ($year) {
            $query .= " AND YEAR(registration_date) = ?";
            $params[] = $year;
            $types .= 's';
        }
        if ($barangay) {
            $query .= " AND barangay = ?";
            $params[] = $barangay;
            $types .= 's';
        }
        $query .= " GROUP BY registration_date ORDER BY registration_date ASC";
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['registration_date'];
            $data[] = (int)$row['total_farmers'];
        }
        mysqli_stmt_close($stmt);
        break;
}
// Output for Chart.js
// Output for Chart.js
$output = [
    'labels' => $labels,
    'data' => $data
];
if (!empty($units)) {
    $output['units'] = $units;
}
if (!empty($commodities_for_points)) {
    $output['commodities'] = $commodities_for_points;
}
echo json_encode($output);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'labels' => [],
        'data' => []
    ]);
}
