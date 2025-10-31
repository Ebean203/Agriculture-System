<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: expiring_inputs.php');
    exit;
}

$inventory_id = isset($_POST['inventory_id']) ? (int)$_POST['inventory_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
$destroy_record = isset($_POST['destroy_record']) && $_POST['destroy_record'] == '1';

if ($inventory_id <= 0 || $quantity <= 0) {
    $_SESSION['error'] = 'Invalid inventory or quantity.';
    header('Location: expiring_inputs.php');
    exit;
}


// Start transaction
mysqli_begin_transaction($conn);
try {
    // Lock the batch row and get expiration date
    $sql = "SELECT inventory_id, input_id, quantity_on_hand, expiration_date FROM mao_inventory WHERE inventory_id = ? FOR UPDATE";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $inventory_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $batch = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$batch) {
        throw new Exception('Batch not found.');
    }

    $current_batch_qty = (int)$batch['quantity_on_hand'];
    $input_id = (int)$batch['input_id'];
    $expiration_date = $batch['expiration_date'];

    // Check if batch is within 1 month (30 days) of expiration
    $today = date('Y-m-d');
    $days_until_expiry = (int)((strtotime($expiration_date) - strtotime($today)) / 86400);
    if ($days_until_expiry > 30) {
        throw new Exception('Cannot stock out: Batch is not within 1 month of expiration. (' . $days_until_expiry . ' days left)');
    }

    if ($quantity > $current_batch_qty) {
        throw new Exception('Requested quantity exceeds batch quantity.');
    }

    // Lock master total row
    $sql2 = "SELECT total_stock FROM input_categories WHERE input_id = ? FOR UPDATE";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, 'i', $input_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $master = mysqli_fetch_assoc($res2);
    mysqli_stmt_close($stmt2);

    if (!$master) {
        throw new Exception('Input not found.');
    }

    $current_master = (int)$master['total_stock'];
    if ($quantity > $current_master) {
        throw new Exception('Insufficient master total stock.');
    }

    $new_batch_qty = $current_batch_qty - $quantity;

    if ($new_batch_qty > 0) {
        $upd = mysqli_prepare($conn, "UPDATE mao_inventory SET quantity_on_hand = ?, last_updated = NOW() WHERE inventory_id = ?");
        mysqli_stmt_bind_param($upd, 'ii', $new_batch_qty, $inventory_id);
        if (!mysqli_stmt_execute($upd)) {
            mysqli_stmt_close($upd);
            throw new Exception('Failed to update batch: ' . mysqli_error($conn));
        }
        mysqli_stmt_close($upd);
    } else {
        // Instead of deleting the batch record, mark it as expired (preserve history)
        if ($destroy_record) {
            // If user insisted on removing the record, we'll mark expired and set qty to 0
            $updDel = mysqli_prepare($conn, "UPDATE mao_inventory SET quantity_on_hand = 0, is_expired = 1, last_updated = NOW() WHERE inventory_id = ?");
            mysqli_stmt_bind_param($updDel, 'i', $inventory_id);
            if (!mysqli_stmt_execute($updDel)) {
                mysqli_stmt_close($updDel);
                throw new Exception('Failed to mark batch expired: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($updDel);
        } else {
            // Set to zero but keep record (do not mark deleted)
            $upd0 = mysqli_prepare($conn, "UPDATE mao_inventory SET quantity_on_hand = 0, last_updated = NOW() WHERE inventory_id = ?");
            mysqli_stmt_bind_param($upd0, 'i', $inventory_id);
            if (!mysqli_stmt_execute($upd0)) {
                mysqli_stmt_close($upd0);
                throw new Exception('Failed to update batch to zero: ' . mysqli_error($conn));
            }
            mysqli_stmt_close($upd0);
        }
    }

    // Update master total
    $updMaster = mysqli_prepare($conn, "UPDATE input_categories SET total_stock = total_stock - ? WHERE input_id = ?");
    mysqli_stmt_bind_param($updMaster, 'ii', $quantity, $input_id);
    if (!mysqli_stmt_execute($updMaster)) {
        mysqli_stmt_close($updMaster);
        throw new Exception('Failed to update master total: ' . mysqli_error($conn));
    }
    mysqli_stmt_close($updMaster);

    // Commit
    mysqli_commit($conn);

    // Log activity
    $details = "Stocked out {$quantity} from batch {$inventory_id} (input_id={$input_id}).";
    logActivity($conn, "Stock Out Batch", 'inventory', $details);

    $_SESSION['success'] = "Stock out successful. Removed {$quantity} from batch {$inventory_id}.";
    header('Location: expiring_inputs.php');
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Stock out failed: ' . $e->getMessage();
    header('Location: expiring_inputs.php');
    exit;
}
