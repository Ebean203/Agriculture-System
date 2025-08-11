<?php
require_once 'conn.php';
session_start();

// Make sure we have an admin user in the session
$admin_query = "SELECT staff_id FROM mao_staff WHERE username = 'admin' LIMIT 1";
$result = mysqli_query($conn, $admin_query);
$admin = mysqli_fetch_assoc($result);
$_SESSION['user_id'] = $admin['staff_id'];

require_once 'includes/activity_logger.php';

// Add some sample activities
$activities = [
    ['action' => 'Logged into the system', 'type' => 'login'],
    ['action' => 'Added new farmer: John Doe', 'type' => 'farmer'],
    ['action' => 'Updated commodity inventory', 'type' => 'commodity'],
    ['action' => 'Recorded yield for Rice cultivation', 'type' => 'yield'],
    ['action' => 'Distributed farming inputs', 'type' => 'input'],
    ['action' => 'Registered RSBSA for farmer Maria Garcia', 'type' => 'rsbsa'],
];

foreach ($activities as $activity) {
    logActivity($conn, $activity['action'], $activity['type']);
    echo "Logged activity: {$activity['action']}\n";
}

echo "\nSample activities have been added to the log!\n";
?>
