<?php
require_once 'conn.php';
header('Content-Type: application/json');
$type = $_GET['type'] ?? 'yield';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$barangay = $_GET['barangay'] ?? '';

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
        if ($commodity !== '') {
            // Filter by commodity name
            $query = "SELECT ym.record_date, SUM(ym.yield_amount) as total_yield FROM yield_monitoring ym JOIN commodities c ON ym.commodity_id = c.commodity_id WHERE c.commodity_name = ? GROUP BY ym.record_date ORDER BY ym.record_date ASC";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 's', $commodity);
        } else {
            $query = "SELECT record_date, SUM(yield_amount) as total_yield FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id WHERE 1 GROUP BY record_date ORDER BY record_date ASC";
            $stmt = mysqli_prepare($conn, $query);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['record_date'];
            $data[] = (float)$row['total_yield'];
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
        $query = "SELECT distribution_date, SUM(quantity) as total_inputs FROM input_distribution_records idr JOIN farmers f ON idr.farmer_id = f.farmer_id WHERE 1";
        $params = [];
        $types = '';
        if ($month) {
            $query .= " AND MONTH(distribution_date) = ?";
            $params[] = $month;
            $types .= 's';
        }
        if ($year) {
            $query .= " AND YEAR(distribution_date) = ?";
            $params[] = $year;
            $types .= 's';
        }
        if ($barangay) {
            $query .= " AND f.barangay = ?";
            $params[] = $barangay;
            $types .= 's';
        }
        $query .= " GROUP BY distribution_date ORDER BY distribution_date ASC";
        $stmt = mysqli_prepare($conn, $query);
        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['distribution_date'];
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
$output = [
    'labels' => $labels,
    'data' => $data
];
if (isset($units)) {
    $output['units'] = $units;
}
echo json_encode($output);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'labels' => [],
        'data' => []
    ]);
}
