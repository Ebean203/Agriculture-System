<?php
// Returns all commodity categories for filter dropdown
require_once '../conn.php';
header('Content-Type: application/json');

$sql = "SELECT category_id, category_name FROM commodity_categories ORDER BY category_name";
$res = mysqli_query($conn, $sql);
$out = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $out[] = $row;
    }
}
echo json_encode($out);