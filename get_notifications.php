<?php
session_start();
require_once 'conn.php';
require_once 'includes/notification_system.php';
require_once 'check_session.php';

header('Content-Type: application/json');

try {
    $count_only = isset($_GET['count_only']) && $_GET['count_only'] === 'true';
    
    if ($count_only) {
        // Return only notification count
        $total_count = getNotificationCount($conn);
        $unread_count = getUnreadNotificationCount($conn);
        
        echo json_encode([
            'success' => true,
            'unread_count' => $unread_count,
            'total_count' => $total_count
        ]);
    } else {
        // Return full notifications
        $notifications = getNotifications($conn);
        $unread_count = getUnreadNotificationCount($conn);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'total_count' => count($notifications)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
?>
