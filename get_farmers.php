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
        $filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
        
        if (strlen($query) < 1) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            exit;
        }
        
        // Search farmers by name, contact number
        $archived_condition = $include_archived ? "" : "f.archived = 0 AND ";
        
        // Add type-specific filtering
        $type_condition = "";
        switch($filter_type) {
            case 'rsbsa':
                $type_condition = "f.is_rsbsa = 1 AND ";
                break;
            case 'ncfrs':
                $type_condition = "f.is_ncfrs = 1 AND ";
                break;
            case 'fisherfolk':
                $type_condition = "f.is_fisherfolk = 1 AND ";
                break;
            case 'boat':
                $type_condition = "f.is_boat = 1 AND ";
                break;
        }
        
        $sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.contact_number, b.barangay_name,
                       f.archived as is_archived
                FROM farmers f 
                LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                WHERE {$archived_condition}{$type_condition}(CONCAT(f.first_name, ' ', f.middle_name, ' ', f.last_name) LIKE ? 
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
            $full_name = trim($row['last_name'] . ', ' . $row['first_name'] .
                (!empty($row['middle_name']) ? ' ' . strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '')
            );
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
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
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
        mysqli_stmt_close($stmt);
        echo json_encode($farmers);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
