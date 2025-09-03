<?php
session_start();
require_once 'conn.php';
require_once 'check_session.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action'])) {
        throw new Exception('No action specified');
    }
    
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if ($input['action'] === 'mark_all_read') {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_id VARCHAR(255) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_notification (user_id, notification_id)
        )";
        mysqli_query($conn, $create_table);
        
        // Get all current notification IDs
        require_once 'includes/notification_system.php';
        $notifications = getNotifications($conn);
        
        // Mark all as read
        foreach ($notifications as $notification) {
            $notification_id = mysqli_real_escape_string($conn, $notification['id']);
            $query = "INSERT INTO notification_reads (user_id, notification_id) 
                      VALUES ($user_id, '$notification_id') 
                      ON DUPLICATE KEY UPDATE read_at = NOW()";
            mysqli_query($conn, $query);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    } else if ($input['action'] === 'mark_read' && isset($input['notification_id'])) {
        // Mark single notification as read
        $notification_id = mysqli_real_escape_string($conn, $input['notification_id']);
        
        $query = "INSERT INTO notification_reads (user_id, notification_id) 
                  VALUES ($user_id, '$notification_id') 
                  ON DUPLICATE KEY UPDATE read_at = NOW()";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
        } else {
            throw new Exception('Failed to mark notification as read');
        }
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
