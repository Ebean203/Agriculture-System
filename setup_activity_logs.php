<?php
require_once 'conn.php';

// Create activity_logs table
$create_logs_table = "
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `action_type` enum('login', 'farmer', 'rsbsa', 'yield', 'commodity', 'input') NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `mao_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if(mysqli_query($conn, $create_logs_table)) {
    echo "Activity logs table created successfully\n";
} else {
    echo "Error creating activity logs table: " . mysqli_error($conn) . "\n";
}

// Create the activity logger function in a new file
$logger_content = '<?php
function logActivity($conn, $action, $action_type, $details = "") {
    if (!isset($_SESSION["user_id"])) return false;
    
    $user_id = (int)$_SESSION["user_id"];
    $action = mysqli_real_escape_string($conn, $action);
    $action_type = mysqli_real_escape_string($conn, $action_type);
    $details = mysqli_real_escape_string($conn, $details);
    
    $query = "INSERT INTO activity_logs (user_id, action, action_type, details) 
              VALUES ($user_id, \'$action\', \'$action_type\', \'$details\')";
    
    return mysqli_query($conn, $query);
}
';

// Create includes directory if it doesn't exist
if (!file_exists('includes')) {
    mkdir('includes', 0777, true);
}

// Create activity_logger.php file
if (file_put_contents('includes/activity_logger.php', $logger_content) !== false) {
    echo "Activity logger file created successfully\n";
} else {
    echo "Error creating activity logger file\n";
}

echo "\nSetup complete! You can now use logActivity() function to log user activities.\n";
echo "Example usage:\n";
echo "require_once 'includes/activity_logger.php';\n";
echo "logActivity(\$conn, 'Added new farmer John Doe', 'farmer', json_encode(['farmer_id' => 'FRM123']));\n";
?>
