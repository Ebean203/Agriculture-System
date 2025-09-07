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
    
    // Calculate the date 10 days from now
    $reminder_date = date('Y-m-d', strtotime($today . ' + 10 days'));
    
    // Query for upcoming visitations from mao_distribution_log table
    $query = "
        SELECT 
            CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name, ' ', COALESCE(f.suffix, '')) as farmer_name,
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
        WHERE mdl.visitation_date BETWEEN '$today' AND '$reminder_date'
        AND mdl.visitation_date IS NOT NULL
        ORDER BY mdl.visitation_date ASC
    ";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $days_until = $row['days_until_visit'];
            
            if ($days_until == 0) {
                $message = "Visitation TODAY for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 1; // Highest priority
                $type = 'urgent';
            } elseif ($days_until == 1) {
                $message = "Visitation TOMORROW for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 2;
                $type = 'warning';
            } else {
                $message = "Visitation in " . $days_until . " days for " . $row['farmer_name'] . " in " . $row['barangay_name'] . " - " . $row['purpose'] . " follow-up";
                $priority = 3;
                $type = 'info';
            }
            
            $notifications[] = [
                'id' => 'visit_' . $row['log_id'],
                'type' => $type,
                'category' => 'visitation',
                'title' => 'Upcoming Visitation',
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
            ic.input_name as item_name,
            ic.input_name as category,
            mi.quantity_on_hand as current_stock,
            ic.unit,
            mi.last_updated,
            CASE 
                WHEN LOWER(ic.input_name) LIKE '%seed%' THEN 50
                WHEN LOWER(ic.input_name) LIKE '%fertilizer%' THEN 100
                WHEN LOWER(ic.input_name) LIKE '%pesticide%' THEN 20
                WHEN LOWER(ic.input_name) LIKE '%tool%' THEN 5
                WHEN LOWER(ic.input_name) LIKE '%equipment%' THEN 2
                ELSE 10
            END as minimum_level
        FROM mao_inventory mi
        JOIN input_categories ic ON mi.input_id = ic.input_id
        WHERE mi.quantity_on_hand >= 0
        HAVING mi.quantity_on_hand <= minimum_level
        ORDER BY (mi.quantity_on_hand / minimum_level) ASC
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
                $message = "Restock Soon: " . $row['item_name'] . " (" . $row['current_stock'] . " " . $row['unit'] . " remaining)";
                $priority = 4;
                $type = 'info';
            }
            
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
    
    return $notifications;
}

function getNotificationCount($conn) {
    $notifications = getNotifications($conn);
    return count($notifications);
}
?>
