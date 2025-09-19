<?php
// Notification System for Lagonglong FARMS
// Handles visitation reminders and inventory alerts using existing database tables

function getNotifications($conn) {
    $notifications = [];
    $today = date('Y-m-d');
    
    // Get visitation reminders (10 days before visitation date)
    $visitation_notifications = getVisitationNotifications($conn, $today);
    $notifications = array_merge($notifications, $visitation_notifications);
    
    // Get low stock alerts
    $stock_notifications = getStockNotifications($conn);
    $notifications = array_merge($notifications, $stock_notifications);
    
    // Sort notifications by priority and date
    usort($notifications, function($a, $b) {
        if ($a['priority'] == $b['priority']) {
            return strcmp($a['date'], $b['date']);
        }
        return $a['priority'] - $b['priority'];
    });
    
    return $notifications;
}

function getVisitationNotifications($conn, $today) {
    $notifications = [];
    
    // Calculate the date 5 days from now
    $reminder_date = date('Y-m-d', strtotime($today . ' + 5 days'));

    // Query for upcoming and overdue visitations from mao_distribution_log table
    $query = "
        SELECT 
            CONCAT(
                f.first_name, 
                CASE 
                    WHEN f.middle_name IS NOT NULL AND LOWER(f.middle_name) NOT IN ('n/a', 'na', '') 
                    THEN CONCAT(' ', f.middle_name) 
                    ELSE '' 
                END,
                ' ', f.last_name,
                CASE 
                    WHEN f.suffix IS NOT NULL AND LOWER(f.suffix) NOT IN ('n/a', 'na', '') 
                    THEN CONCAT(' ', f.suffix) 
                    ELSE '' 
                END
            ) as farmer_name,
            f.barangay_id,
            b.barangay_name,
            mdl.visitation_date,
            ic.input_name as purpose,
            mdl.quantity_distributed,
            mdl.log_id,
            DATEDIFF(mdl.visitation_date, '$today') as days_until_visit
        FROM mao_distribution_log mdl
        JOIN farmers f ON mdl.farmer_id = f.farmer_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN input_categories ic ON mdl.input_id = ic.input_id
        WHERE mdl.visitation_date IS NOT NULL
        AND mdl.visitation_date <= '$reminder_date'
        ORDER BY mdl.visitation_date ASC
    ";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $days_until = $row['days_until_visit'];
            $formatted_date = date('M j, Y', strtotime($row['visitation_date']));

            if ($days_until < 0) {
                $message = "OVERDUE Visitation (was " . $formatted_date . ") for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 0; // Highest priority
                $type = 'urgent';
            } elseif ($days_until == 0) {
                $message = "Visitation TODAY (" . $formatted_date . ") for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 1;
                $type = 'urgent';
            } elseif ($days_until == 1) {
                $message = "Visitation TOMORROW (" . $formatted_date . ") for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 2;
                $type = 'warning';
            } else {
                $message = "Visitation scheduled for " . $formatted_date . " (" . $days_until . " days) - " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 3;
                $type = 'info';
            }

            $notifications[] = [
                'id' => 'visit_' . $row['log_id'],
                'type' => $type,
                'category' => 'visitation',
                'title' => ($days_until < 0 ? 'Overdue Visitation' : 'Upcoming Visitation'),
                'message' => $message,
                'date' => $row['visitation_date'],
                'priority' => $priority,
                'icon' => 'fas fa-calendar-check',
                'data' => $row
            ];
        }
    }
    
    return $notifications;
}

function getStockNotifications($conn) {
    $notifications = [];
    
    // Query for low stock items from mao_inventory table
    $query = "
        SELECT 
            ic.input_id,
            ic.input_name as item_name,
            ic.input_name as category,
            COALESCE(mi.quantity_on_hand, 0) as current_stock,
            ic.unit,
            mi.last_updated,
            CASE 
                WHEN LOWER(ic.input_name) LIKE '%seed%' THEN 20
                WHEN LOWER(ic.input_name) LIKE '%fertilizer%' THEN 30
                WHEN LOWER(ic.input_name) LIKE '%pesticide%' THEN 10
                WHEN LOWER(ic.input_name) LIKE '%tool%' THEN 3
                WHEN LOWER(ic.input_name) LIKE '%equipment%' THEN 1
                ELSE 5
            END as minimum_level
        FROM input_categories ic
        LEFT JOIN mao_inventory mi ON ic.input_id = mi.input_id
        WHERE COALESCE(mi.quantity_on_hand, 0) >= 0
        HAVING current_stock <= minimum_level
        ORDER BY (current_stock / minimum_level) ASC
    ";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $stock_percentage = ($row['current_stock'] / $row['minimum_level']) * 100;
            
            if ($row['current_stock'] == 0) {
                $message = "OUT OF STOCK: " . $row['item_name'];
                $priority = 1; // Highest priority
                $type = 'urgent';
            } elseif ($stock_percentage <= 25) {
                $message = "CRITICAL LOW: " . $row['item_name'] . " (" . $row['current_stock'] . " " . $row['unit'] . " remaining)";
                $priority = 2;
                $type = 'urgent';
            } elseif ($stock_percentage <= 50) {
                $message = "LOW STOCK: " . $row['item_name'] . " (" . $row['current_stock'] . " " . $row['unit'] . " remaining)";
                $priority = 3;
                $type = 'warning';
            } else {
                // Skip info level notifications - only show warning and urgent
                continue;
            }
            
            // Only add warning and urgent notifications
            if ($type === 'warning' || $type === 'urgent') {
                $notifications[] = [
                    'id' => 'stock_' . str_replace(' ', '_', $row['item_name']),
                    'type' => $type,
                    'category' => 'inventory',
                    'title' => 'Inventory Alert',
                    'message' => $message,
                    'date' => $row['last_updated'],
                    'priority' => $priority,
                    'icon' => 'fas fa-exclamation-triangle',
                    'data' => $row
                ];
            }
        }
    }
    
    return $notifications;
}

function getNotificationCount($conn) {
    $notifications = getNotifications($conn);
    return count($notifications);
}

function getCriticalNotificationCount($conn) {
    $notifications = getNotifications($conn);
    $critical_count = 0;
    
    foreach ($notifications as $notification) {
        // Only count urgent and high priority notifications
        if ($notification['type'] === 'urgent' || $notification['priority'] <= 2) {
            $critical_count++;
        }
    }
    
    return $critical_count;
}

function getUnreadNotificationCount($conn) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $notifications = getNotifications($conn);
    $unread_count = 0;
    
    // Check if notification_reads table exists
    $table_check = "SHOW TABLES LIKE 'notification_reads'";
    $table_result = mysqli_query($conn, $table_check);
    
    if (mysqli_num_rows($table_result) == 0) {
        // Table doesn't exist, create it
        $create_table = "CREATE TABLE IF NOT EXISTS notification_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_id VARCHAR(255) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_notification (user_id, notification_id)
        )";
        mysqli_query($conn, $create_table);
        
        // All notifications are unread if table was just created
        return count($notifications);
    }
    
    foreach ($notifications as $notification) {
        // Check if this notification is read by this user
        $notification_id = mysqli_real_escape_string($conn, $notification['id']);
        $query = "SELECT id FROM notification_reads 
                  WHERE user_id = $user_id AND notification_id = '$notification_id'";
        $result = mysqli_query($conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            $unread_count++;
        }
    }
    
    return $unread_count;
}
?>
