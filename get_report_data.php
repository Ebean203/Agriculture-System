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
switch ($type) {
    case 'activities':
        // Monthly activities count from mao_activities table
        $query = "SELECT MONTH(activity_date) as month, YEAR(activity_date) as year, COUNT(*) as total_activities FROM mao_activities WHERE 1";
        if ($month) $query .= " AND MONTH(activity_date) = '" . mysqli_real_escape_string($conn, $month) . "'";
        if ($year) $query .= " AND YEAR(activity_date) = '" . mysqli_real_escape_string($conn, $year) . "'";
        $query .= " GROUP BY year, month ORDER BY year, month ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $label = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            $labels[] = $label;
            $data[] = (int)$row['total_activities'];
        }
        break;
    case 'yield':
        $query = "SELECT record_date, SUM(yield_amount) as total_yield FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id WHERE 1";
        $query .= " GROUP BY record_date ORDER BY record_date ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['record_date'];
            $data[] = (float)$row['total_yield'];
        }
        break;
    case 'yield_per_barangay':
        $query = "SELECT b.barangay_name, SUM(ym.yield_amount) AS total_yield FROM yield_monitoring ym JOIN farmers f ON ym.farmer_id = f.farmer_id JOIN barangays b ON f.barangay_id = b.barangay_id GROUP BY b.barangay_name ORDER BY b.barangay_name ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['barangay_name'];
            $data[] = (float)$row['total_yield'];
        }
        break;
    case 'input_distribution':
        $query = "SELECT distribution_date, SUM(quantity) as total_inputs FROM input_distribution_records idr JOIN farmers f ON idr.farmer_id = f.farmer_id WHERE 1";
    if ($month) $query .= " AND MONTH(distribution_date) = '" . mysqli_real_escape_string($conn, $month) . "'";
    if ($year) $query .= " AND YEAR(distribution_date) = '" . mysqli_real_escape_string($conn, $year) . "'";
        if ($barangay) $query .= " AND f.barangay = '" . mysqli_real_escape_string($conn, $barangay) . "'";
        $query .= " GROUP BY distribution_date ORDER BY distribution_date ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['distribution_date'];
            $data[] = (float)$row['total_inputs'];
        }
        break;
    case 'inventory':
        // Show current inventory available (sum of all items)
        $query = "SELECT SUM(quantity) as total_inventory FROM mao_inventory";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        $row = mysqli_fetch_assoc($result);
        $labels = ['Current Inventory'];
        $data = [$row ? (float)$row['total_inventory'] : 0];
        break;
    case 'boat':
        $query = "SELECT registration_date, COUNT(*) as total_boats FROM boat_records br JOIN farmers f ON br.farmer_id = f.farmer_id WHERE 1";
    if ($month) $query .= " AND MONTH(registration_date) = '" . mysqli_real_escape_string($conn, $month) . "'";
    if ($year) $query .= " AND YEAR(registration_date) = '" . mysqli_real_escape_string($conn, $year) . "'";
        if ($barangay) $query .= " AND f.barangay = '" . mysqli_real_escape_string($conn, $barangay) . "'";
        $query .= " GROUP BY registration_date ORDER BY registration_date ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['registration_date'];
            $data[] = (int)$row['total_boats'];
        }
        break;
    case 'farmer':
        $query = "SELECT registration_date, COUNT(*) as total_farmers FROM farmers WHERE 1";
    if ($month) $query .= " AND MONTH(registration_date) = '" . mysqli_real_escape_string($conn, $month) . "'";
    if ($year) $query .= " AND YEAR(registration_date) = '" . mysqli_real_escape_string($conn, $year) . "'";
        if ($barangay) $query .= " AND barangay = '" . mysqli_real_escape_string($conn, $barangay) . "'";
        $query .= " GROUP BY registration_date ORDER BY registration_date ASC";
        $result = mysqli_query($conn, $query);
        if (!$result) throw new Exception(mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) {
            $labels[] = $row['registration_date'];
            $data[] = (int)$row['total_farmers'];
        }
        break;
}
// Output for Chart.js
echo json_encode([
    'labels' => $labels,
    'data' => $data
]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'labels' => [],
        'data' => []
    ]);
}
