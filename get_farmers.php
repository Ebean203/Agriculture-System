<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

header('Content-Type: application/json');

try {
    // Get all farmers
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
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>
