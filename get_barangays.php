<?php
require_once 'conn.php';
header('Content-Type: application/json');
$barangays = [];

if (!$conn || $conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

$result = $conn->query("SELECT barangay_name FROM barangay ORDER BY barangay_name ASC");
if (!$result) {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
    exit;
}

while ($row = $result->fetch_assoc()) {
    $barangays[] = $row['barangay_name'];
}
echo json_encode($barangays);
