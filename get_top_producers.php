<?php
require_once 'conn.php';
header('Content-Type: application/json');
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;
$commodity = $_GET['commodity'] ?? '';

if ($type === 'top_producers') {
    $params = [];
    $types = '';
    $where = '1';
    if ($from && $to) {
        $where .= ' AND DATE(ym.record_date) BETWEEN ? AND ?';
        $params[] = $from;
        $params[] = $to;
        $types .= 'ss';
    }
    $sql = "SELECT f.farmer_id, CONCAT(f.last_name, ', ', f.first_name) AS farmer_name, SUM(ym.yield_amount) AS total_yield, c.commodity_name, ym.unit
            FROM yield_monitoring ym
            JOIN farmers f ON ym.farmer_id = f.farmer_id
            JOIN commodities c ON ym.commodity_id = c.commodity_id
            WHERE $where
            GROUP BY f.farmer_id, c.commodity_name, ym.unit
            ORDER BY total_yield DESC
            LIMIT 10";
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception($conn->error . "\nSQL: $sql");
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'farmer_name' => $row['farmer_name'],
                'total_yield' => $row['total_yield'] . ' ' . (($row['unit'] ?: 'kg')) . ' (' . $row['commodity_name'] . ')',
                'inputs_received' => '-' // Temporarily skip input lookup for debug
            ];
        }
        $stmt->close();
        echo json_encode($rows);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage(), 'sql' => $sql, 'params' => $params]);
        exit;
    }
}
echo json_encode([]);