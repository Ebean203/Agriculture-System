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
    $new_date = trim($_POST['new_date']);
    $reason   = isset($_POST['reschedule_reason']) ? trim($_POST['reschedule_reason']) : '';

    if ($new_date === '' || strlen($reason) < 3) {
        echo json_encode(['success' => false, 'message' => 'New date and a valid reason are required.']);
        exit;
    }

    // Fetch current date for history
    $curr = $conn->prepare("SELECT visitation_date FROM mao_distribution_log WHERE log_id = ? LIMIT 1");
    $curr->bind_param('i', $distribution_id);
    $curr->execute();
    $currRes = $curr->get_result();
    $row = $currRes->fetch_assoc();
    $curr->close();
    $old_date = $row ? $row['visitation_date'] : null;

    try {
        $conn->begin_transaction();

        // Insert history if table exists
        $tblCheck = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mao_distribution_reschedules'");
        if ($tblCheck && $tblCheck->num_rows > 0) {
            $staff_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $hist = $conn->prepare("INSERT INTO mao_distribution_reschedules (log_id, old_visitation_date, new_visitation_date, reason, staff_id) VALUES (?,?,?,?,?)");
            if ($hist) {
                $hist->bind_param('isssi', $distribution_id, $old_date, $new_date, $reason, $staff_id);
                $hist->execute();
                $hist->close();
            }
        }

        // Update main record
        $stmt = $conn->prepare("UPDATE mao_distribution_log SET status = 'rescheduled', visitation_date = ? WHERE log_id = ?");
        $stmt->bind_param('si', $new_date, $distribution_id);
        $ok = $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Distribution rescheduled.' : 'Failed to reschedule.']);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to reschedule.']);
    }
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
