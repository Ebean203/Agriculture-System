<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

header('Content-Type: application/json');

// Handle both GET and POST requests
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $query = $_GET['query'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = $_POST['query'];
}

if (strlen($query) < 1) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

try {
    // Search for farmers that are not archived (using archived field like in farmers.php)
    $sql = "SELECT f.farmer_id, f.first_name, f.last_name, f.middle_name, f.suffix, f.contact_number, b.barangay_name,
                   CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) as full_name
            FROM farmers f 
            LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
            WHERE f.archived = 0 
            AND (
                f.first_name LIKE ? OR 
                f.last_name LIKE ? OR 
                f.middle_name LIKE ? OR
                f.contact_number LIKE ? OR
                CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ?
            )
            ORDER BY f.first_name, f.last_name 
            LIMIT 10";
    
    $searchTerm = "%{$query}%";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $farmers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $farmers[] = [
                'farmer_id' => $row['farmer_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'middle_name' => $row['middle_name'],
                'suffix' => $row['suffix'],
                'contact_number' => $row['contact_number'],
                'barangay_name' => $row['barangay_name'],
                'full_name' => trim($row['full_name'])
            ];
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode([
            'success' => true,
            'farmers' => $farmers,
            'count' => count($farmers)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . mysqli_error($conn)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
