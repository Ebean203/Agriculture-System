<?php
require_once 'conn.php';
$count = $conn->query("SELECT COUNT(*) as count FROM generated_reports")->fetch_assoc()['count'];
echo $count;
