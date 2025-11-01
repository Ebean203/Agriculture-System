<?php
/**
 * Add status column to mao_activities table
 * This allows tracking of activity completion status
 */

require_once 'conn.php';

// Check if status column already exists
$check_query = "SHOW COLUMNS FROM mao_activities LIKE 'status'";
$result = $conn->query($check_query);

if ($result->num_rows > 0) {
    echo "✅ Column 'status' already exists in mao_activities table.<br>";
} else {
    // Add status column
    $sql = "ALTER TABLE `mao_activities` 
            ADD COLUMN `status` ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled' 
            AFTER `location`";
    
    try {
        if ($conn->query($sql) === TRUE) {
            echo "✅ SUCCESS: Column 'status' added to mao_activities table!<br>";
            echo "- Type: ENUM('scheduled', 'completed', 'cancelled')<br>";
            echo "- Default: 'scheduled'<br>";
        } else {
            throw new Exception($conn->error);
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "<br>";
    }
}

$conn->close();
?>
