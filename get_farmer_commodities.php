<?php
require_once __DIR__ . '/conn.php';
header('Content-Type: application/json');

$farmer_id = isset($_GET['farmer_id']) ? trim($_GET['farmer_id']) : '';
if ($farmer_id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing farmer_id']);
    exit;
}

// Query: get all commodities assigned to this farmer (assuming a mapping table exists: farmer_commodities)
$sql = "SELECT c.commodity_id, c.commodity_name, c.category_id, cc.category_name
        FROM farmer_commodities fc
        INNER JOIN commodities c ON fc.commodity_id = c.commodity_id
        LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id
        WHERE fc.farmer_id = ?
        ORDER BY cc.category_name, c.commodity_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $farmer_id);
$stmt->execute();
$result = $stmt->get_result();
$commodities = [];
while ($row = $result->fetch_assoc()) {
    $commodities[] = $row;
}

echo json_encode(['success' => true, 'commodities' => $commodities]);
