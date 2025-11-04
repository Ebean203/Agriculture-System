<?php
require_once 'conn.php';
header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = 10;

try {
    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql = "SELECT input_id, input_name FROM input_categories WHERE input_name LIKE ? ORDER BY input_name ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $like, $limit);
    } else {
        $sql = "SELECT input_id, input_name FROM input_categories ORDER BY input_name ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['input_id'],
            'label' => $row['input_name']
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'items' => $out]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'items' => []]);
}
