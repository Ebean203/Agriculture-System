<?php
require_once 'conn.php';
header('Content-Type: application/json');

$commodity_id = isset($_GET['commodity_id']) ? (int)$_GET['commodity_id'] : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = 10;

try {
    $params = [];
    $types = '';
    $where = 'WHERE unit IS NOT NULL AND unit <> ""';
    if ($commodity_id > 0) {
        $where .= ' AND commodity_id = ?';
        $params[] = $commodity_id;
        $types .= 'i';
    }
    if ($q !== '') {
        $where .= ' AND unit LIKE ?';
        $params[] = '%' . $q . '%';
        $types .= 's';
    }
    $sql = "SELECT unit, COUNT(*) as cnt FROM yield_monitoring $where GROUP BY unit ORDER BY cnt DESC, unit ASC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'label' => $row['unit']
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'items' => $out]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'items' => []]);
}
