<?php
$host     = "localhost";
$db_user  = "root";        // Your MySQL username
$db_pass  = "";            // Your MySQL password (blank by default in XAMPP)
$db_name  = "agriculture-system";
$conn     = new mysqli($host, $db_user, $db_pass, $db_name,3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>