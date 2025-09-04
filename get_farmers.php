<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

header('Content-Type: application/json');

try {
    $action = isset($_GET['action']) ? $_GET['action'] : 'get_all';
    
    if ($action === 'search') {
        // Search functionality for auto-suggest
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        $include_archived = isset($_GET['include_archived']) ? $_GET['include_archived'] === 'true' : false;
        
        if (strlen($query) < 1) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            exit;
        }
        
        // Search farmers by name, contact number
        $archived_condition = $include_archived ? "" : "f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers) AND";
        $sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.contact_number, b.barangay_name,
                       CASE WHEN af.farmer_id IS NOT NULL THEN 1 ELSE 0 END as is_archived
                FROM farmers f 
                LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                LEFT JOIN archived_farmers af ON f.farmer_id = af.farmer_id
                WHERE $archived_condition (CONCAT(f.first_name, ' ', f.middle_name, ' ', f.last_name) LIKE ? 
                     OR f.contact_number LIKE ?
                     OR f.farmer_id LIKE ?)
                ORDER BY f.first_name, f.last_name
                LIMIT 10";
        
        $searchTerm = "%{$query}%";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $searchTerm, $searchTerm, $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $farmers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
            $farmers[] = [
                'farmer_id' => $row['farmer_id'],
                'full_name' => $full_name,
                'contact_number' => $row['contact_number'],
                'barangay_name' => $row['barangay_name'] ?? 'Unknown',
                'is_archived' => (bool)$row['is_archived']
            ];
        }
        
        echo json_encode(['success' => true, 'farmers' => $farmers]);
        
    } else {
        // Original functionality - get all farmers
        $query = "SELECT farmer_id, first_name, last_name FROM farmers ORDER BY first_name, last_name";
        $result = mysqli_query($conn, $query);
        
        $farmers = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $farmers[] = [
                    'farmer_id' => $row['farmer_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name']
                ];
            }
        }
        
        echo json_encode($farmers);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
