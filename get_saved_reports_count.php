<?php
// get_saved_reports_count.php
require_once 'conn.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT COUNT(*) as count FROM saved_reports");
$count = $result ? (int)$result->fetch_assoc()['count'] : 0;

echo json_encode(['count' => $count]);
