<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once __DIR__ . '/includes/name_helpers.php';

header('Content-Type: application/json');

// Handle both GET and POST requests
$query = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $query = $_GET['query'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = $_POST['query'];
}

// Prefix-based suggestions should only use meaningful input.
$query = trim($query);

if (strlen($query) < 1) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

try {
    // Search for farmers that are not archived (using archived field like in farmers.php)
    $sql = "SELECT f.farmer_id, f.first_name, f.last_name, f.middle_name, f.suffix, f.contact_number, b.barangay_name
            FROM farmers f 
            LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
            WHERE f.archived = 0 
            AND (
                f.last_name LIKE ? OR 
                f.first_name LIKE ? OR 
                f.middle_name LIKE ? OR
                CONCAT(f.last_name, ', ', f.first_name) LIKE ? OR
                CONCAT(f.last_name, ', ', f.first_name, ' ', COALESCE(f.middle_name, '')) LIKE ? OR
                CONCAT(f.last_name, ', ', f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', COALESCE(f.suffix, '')) LIKE ?
            )
            ORDER BY f.last_name, f.first_name, f.middle_name
            LIMIT 10";
    
    $searchTerm = "{$query}%";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => false,
                'message' => 'Database execute error: ' . mysqli_stmt_error($stmt)
            ]);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        $result = mysqli_stmt_get_result($stmt);
        if ($result === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Database result error: ' . mysqli_stmt_error($stmt)
            ]);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        }
        
        $farmers = [];
        while ($row = $result->fetch_assoc()) {
            $farmers[] = [
                'farmer_id' => $row['farmer_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'middle_name' => $row['middle_name'],
                'suffix' => $row['suffix'],
                'contact_number' => $row['contact_number'],
                'barangay_name' => $row['barangay_name'],
                'full_name' => formatFarmerName($row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '', $row['suffix'] ?? '')
            ];
        }
        
    $stmt->close();
        
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
