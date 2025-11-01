<?php
require_once __DIR__ . '/conn.php';
header('Content-Type: application/json');

$logId = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
if ($logId <= 0) { echo json_encode(['success' => false, 'items' => []]); exit; }

// Ensure history table exists
$chk = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mao_distribution_reschedules'");
if (!$chk || $chk->num_rows === 0) { echo json_encode(['success' => false, 'items' => []]); exit; }

$sql = "SELECT r.old_visitation_date, r.new_visitation_date, r.reason, r.created_at,
               CONCAT(ms.first_name, ' ', ms.last_name) AS staff_name
        FROM mao_distribution_reschedules r
        LEFT JOIN mao_staff ms ON ms.staff_id = r.staff_id
        WHERE r.log_id = ?
        ORDER BY r.created_at DESC, r.reschedule_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $logId);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) { $items[] = $row; }
$stmt->close();

echo json_encode(['success' => true, 'items' => $items]);
