<?php
require_once 'conn.php';
header('Content-Type: application/json');
$commodity = isset($_GET['commodity']) ? trim($_GET['commodity']) : '';
$unit = 'kg';
if ($commodity !== '') {
	// Find the commodity_id for the given commodity name
	$stmt = $conn->prepare("SELECT commodity_id FROM commodities WHERE commodity_name = ? LIMIT 1");
	$stmt->bind_param('s', $commodity);
	$stmt->execute();
	$res = $stmt->get_result();
	if ($row = $res->fetch_assoc()) {
		$commodity_id = $row['commodity_id'];
		// Get the most recent yield_monitoring record for this commodity_id with a non-null unit
		$stmt2 = $conn->prepare("SELECT unit FROM yield_monitoring WHERE commodity_id = ? AND unit IS NOT NULL AND unit != '' ORDER BY record_date DESC LIMIT 1");
		$stmt2->bind_param('i', $commodity_id);
		$stmt2->execute();
		$res2 = $stmt2->get_result();
		if ($row2 = $res2->fetch_assoc()) {
			$unit = $row2['unit'];
		}
		$stmt2->close();
	}
	$stmt->close();
}
echo json_encode(['unit' => $unit]);
