<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

header('Content-Type: application/json');

if (!isset($_GET['input_id'])) {
    echo json_encode(['error' => 'Input ID is required']);
    exit();
}

$input_id = mysqli_real_escape_string($conn, $_GET['input_id']);

try {
    $query = "SELECT input_name, requires_visitation FROM input_categories WHERE input_id = '$input_id'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $data = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'input_name' => $data['input_name'],
            'requires_visitation' => (bool)$data['requires_visitation']
        ]);
    } else {
        echo json_encode(['error' => 'Input not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
