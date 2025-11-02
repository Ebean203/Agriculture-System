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

    // Get upcoming MAO activities (today + next 14 days, plus overdue not completed)
    if (function_exists('mysqli_prepare')) {
        $activity_notifications = getActivityNotifications($conn, $today);
        $notifications = array_merge($notifications, $activity_notifications);
    }

    // Get expiring input batches (expired and within next 30 days)
    if (function_exists('mysqli_prepare')) {
        $expiry_notifications = getExpiringInputBatchNotifications($conn, $today);
        $notifications = array_merge($notifications, $expiry_notifications);
    }
    
    // Sort notifications by priority and date
    usort($notifications, function($a, $b) {
        if ($a['priority'] == $b['priority']) {
            // Handle null dates safely
            $date_a = $a['date'] ?? '';
            $date_b = $b['date'] ?? '';
            return strcmp($date_a, $date_b);
        }
        return $a['priority'] - $b['priority'];
    });
    
    return $notifications;
}

function getVisitationNotifications($conn, $today) {
    $notifications = [];
    
    // Calculate the date 7 days from now (1 week prior reminders)
    $reminder_date = date('Y-m-d', strtotime($today . ' + 7 days'));
    // Query for upcoming and overdue visitations from mao_distribution_log table
    $query = "
        SELECT 
            f.farmer_id,
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
            mdl.status,
            DATEDIFF(mdl.visitation_date, ?) as days_until_visit
        FROM mao_distribution_log mdl
        JOIN farmers f ON mdl.farmer_id = f.farmer_id
        LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
        LEFT JOIN input_categories ic ON mdl.input_id = ic.input_id
        WHERE mdl.visitation_date IS NOT NULL
        AND mdl.visitation_date <= ?
        AND (mdl.status = 'pending' OR mdl.status = 'rescheduled')
        ORDER BY mdl.visitation_date ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $today, $reminder_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
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
                'date' => $row['visitation_date'] ?? '',
                'priority' => $priority,
                'icon' => 'fas fa-calendar-check',
                'data' => $row
            ];
        }
    }
    
    return $notifications;
}

/**
 * Upcoming/overdue MAO activities notifications
 * - Urgent: today or overdue
 * - Warning: tomorrow to 7 days
 * - Info: 8 to 14 days
 */
function getActivityNotifications($conn, $today) {
    $notifications = [];
    // Activities within the next 14 days or overdue and not completed
    $query = "
        SELECT ma.activity_id, ma.title, ma.location, ma.activity_date, ma.status,
               DATEDIFF(ma.activity_date, ?) AS days_until
        FROM mao_activities ma
        WHERE ma.activity_date IS NOT NULL
          AND (ma.status IS NULL OR ma.status <> 'completed')
          AND (
                ma.activity_date <= DATE_ADD(?, INTERVAL 14 DAY)
          )
        ORDER BY ma.activity_date ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) { return $notifications; }
    mysqli_stmt_bind_param($stmt, "ss", $today, $today);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $days = (int)$row['days_until'];
            $dateStr = $row['activity_date'] ? date('M j, Y', strtotime($row['activity_date'])) : '';
            if ($days < 0) {
                $msg = "OVERDUE Activity (was {$dateStr}): {$row['title']}" . (!empty($row['location']) ? " at {$row['location']}" : '');
                $priority = 0; $type = 'urgent'; $title = 'Overdue Activity';
            } elseif ($days === 0) {
                $msg = "Activity TODAY: {$row['title']}" . (!empty($row['location']) ? " at {$row['location']}" : '') . " ({$dateStr})";
                $priority = 1; $type = 'urgent'; $title = 'Activity Today';
            } elseif ($days === 1) {
                $msg = "Activity TOMORROW: {$row['title']}" . (!empty($row['location']) ? " at {$row['location']}" : '') . " ({$dateStr})";
                $priority = 2; $type = 'warning'; $title = 'Activity Tomorrow';
            } elseif ($days <= 7) {
                $msg = "Activity in {$days} days: {$row['title']}" . (!empty($row['location']) ? " at {$row['location']}" : '') . " ({$dateStr})";
                $priority = 3; $type = 'warning'; $title = 'Upcoming Activity';
            } elseif ($days <= 14) {
                $msg = "Activity in {$days} days: {$row['title']}" . (!empty($row['location']) ? " at {$row['location']}" : '') . " ({$dateStr})";
                $priority = 4; $type = 'info'; $title = 'Upcoming Activity';
            } else {
                continue;
            }
            $notifications[] = [
                'id' => 'act_' . $row['activity_id'],
                'type' => $type,
                'category' => 'activity',
                'title' => $title,
                'message' => $msg,
                'date' => $row['activity_date'] ?? '',
                'priority' => $priority,
                'icon' => 'fas fa-calendar',
                'data' => $row
            ];
        }
    }
    if ($stmt) { mysqli_stmt_close($stmt); }
    return $notifications;
}

/**
 * Expiring inventory batch notifications
 * - Urgent: expired or expires today
 * - Warning: expires within 7 days, else within 30 days low-priority warning
 */
function getExpiringInputBatchNotifications($conn, $today) {
    $notifications = [];
    $query = "
        SELECT 
            mi.inventory_id,
            mi.input_id,
            mi.quantity_on_hand,
            mi.expiration_date,
            ic.input_name,
            ic.unit,
            DATEDIFF(mi.expiration_date, ?) AS days_left
        FROM mao_inventory mi
        JOIN input_categories ic ON mi.input_id = ic.input_id
        WHERE mi.expiration_date IS NOT NULL
          AND mi.expiration_date <= DATE_ADD(?, INTERVAL 10 DAY)
          AND COALESCE(mi.quantity_on_hand,0) > 0
        ORDER BY mi.expiration_date ASC, mi.inventory_id ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) { return $notifications; }
    mysqli_stmt_bind_param($stmt, "ss", $today, $today);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $days = (int)$row['days_left'];
            $dateStr = $row['expiration_date'] ? date('M j, Y', strtotime($row['expiration_date'])) : '';
            if ($days < 0) {
                $msg = "EXPIRED batch: {$row['input_name']} (expired {$dateStr})";
                $priority = 0; $type = 'urgent'; $title = 'Expired Batch';
            } elseif ($days === 0) {
                $msg = "Batch expires TODAY: {$row['input_name']}";
                $priority = 1; $type = 'urgent'; $title = 'Expiring Today';
            } elseif ($days <= 7) {
                $msg = "Batch expiring in {$days} day(s): {$row['input_name']} ({$dateStr})";
                $priority = 2; $type = 'warning'; $title = 'Expiring Soon';
            } else { // 8-10 days
                $msg = "Batch expiring in {$days} days: {$row['input_name']} ({$dateStr})";
                $priority = 3; $type = 'warning'; $title = 'Expiring in 10 Days';
            }
            $notifications[] = [
                'id' => 'expiry_' . $row['inventory_id'],
                'type' => $type,
                'category' => 'expiry',
                'title' => $title,
                'message' => $msg,
                'date' => $row['expiration_date'] ?? '',
                'priority' => $priority,
                'icon' => 'fas fa-hourglass-half',
                'data' => $row
            ];
        }
    }
    if ($stmt) { mysqli_stmt_close($stmt); }
    return $notifications;
}

function getStockNotifications($conn) {
    $notifications = [];
    
    // Query for low stock items (use master total in input_categories.total_stock if available)
    // New policy: treat the baseline "minimum_level" as 10 units for all items (unit label is ignored).
    // Critical when current_stock <= minimum_level/2 (i.e., 5 when minimum_level = 10).
    $query = "
        SELECT
            ic.input_id,
            ic.input_name as item_name,
            ic.input_name as category,
            COALESCE(ic.total_stock, 0) as current_stock,
            ic.unit,
            10 as minimum_level
        FROM input_categories ic
        WHERE COALESCE(ic.total_stock, 0) >= 0
        HAVING current_stock <= minimum_level
        ORDER BY (current_stock / minimum_level) ASC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Determine thresholds: minimum_level is 10 (see query above)
            $min = (int)$row['minimum_level'];
            $critical_threshold = floor($min / 2); // critical when stock <= half of minimum (e.g., 5)
            if ($row['current_stock'] == 0) {
                $message = "OUT OF STOCK: " . $row['item_name'];
                $priority = 1; // Highest priority
                $type = 'urgent';
            } elseif ($row['current_stock'] <= $critical_threshold) {
                $message = "CRITICAL LOW: " . $row['item_name'] . " (" . $row['current_stock'] . " " . $row['unit'] . " remaining)";
                $priority = 2;
                $type = 'urgent';
            } elseif ($row['current_stock'] <= $min) {
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
                    'date' => '',
                    'priority' => $priority,
                    'icon' => 'fas fa-exclamation-triangle',
                    'data' => $row
                ];
            }
        }
    }
    mysqli_stmt_close($stmt);
    
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
        // Table doesn't exist - return all notifications as unread without creating table
        return count($notifications);
    }
    
    $stmt = $conn->prepare("SELECT id FROM notification_reads WHERE user_id = ? AND notification_id = ?");
    foreach ($notifications as $notification) {
        $notification_id = $notification['id'];
        $stmt->bind_param("is", $user_id, $notification_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result || $result->num_rows == 0) {
            $unread_count++;
        }
    }
    $stmt->close();
    return $unread_count;
}
?>
