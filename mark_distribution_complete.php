<?php
require_once 'check_session.php';
require_once 'conn.php';
header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['distribution_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$distribution_id = intval($_POST['distribution_id']);


// Check if this is a reschedule action
if (isset($_POST['reschedule']) && $_POST['reschedule'] == '1' && isset($_POST['new_date'])) {
    $new_date = $_POST['new_date'];
    // Update status to 'rescheduled' and visitation_date
    $stmt = $conn->prepare("UPDATE mao_distribution_log SET status = 'rescheduled', visitation_date = ? WHERE log_id = ?");
    $stmt->bind_param('si', $new_date, $distribution_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Distribution rescheduled.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reschedule.']);
    }
    $stmt->close();
    exit;
}

// Otherwise, mark as completed
$stmt = $conn->prepare("UPDATE mao_distribution_log SET status = 'completed' WHERE log_id = ?");
$stmt->bind_param('i', $distribution_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Distribution marked as completed.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update record.']);
}
$stmt->close();
