<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

// Only process if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required POST data
    if (!isset($_POST['input_id']) || !isset($_POST['farmer_id']) || !isset($_POST['quantity_distributed']) || !isset($_POST['date_given'])) {
        $_SESSION['error'] = "Missing required data for distribution!";
        header("Location: mao_inventory.php");
        exit();
    }

    // Additional validation for data quality
    $input_id = trim($_POST['input_id']);
    $farmer_id = trim($_POST['farmer_id']);
    $quantity_distributed = trim($_POST['quantity_distributed']);
    $date_given = trim($_POST['date_given']);

    // Validate data is not empty
    // ...existing code...
} else {
    // If not POST, do nothing or redirect as needed (no error set)
    header("Location: mao_inventory.php");
    exit();
}
if (empty($input_id) || empty($farmer_id) || empty($quantity_distributed) || empty($date_given)) {
    $_SESSION['error'] = "All fields are required and cannot be empty!";
    header("Location: mao_inventory.php");
    exit();
}

// Validate quantity is numeric and positive
if (!is_numeric($quantity_distributed) || floatval($quantity_distributed) <= 0) {
    $_SESSION['error'] = "Quantity must be a positive number!";
    header("Location: mao_inventory.php");
    exit();
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date_given)) {
    $_SESSION['error'] = "Invalid date format!";
    header("Location: mao_inventory.php");
    exit();
}

// No need for mysqli_real_escape_string, using prepared statements below

// Handle visitation date - now required for ALL inputs
$requires_visitation = 1; // Force all inputs to require visitation

// Visitation date is now mandatory for all distributions
if (!isset($_POST['visitation_date']) || empty($_POST['visitation_date'])) {
    $_SESSION['error'] = "Visitation date is required for all input distributions!";
    header("Location: mao_inventory.php");
    exit();
}

$visitation_date = trim($_POST['visitation_date']);

// Validate visitation date format
if (!DateTime::createFromFormat('Y-m-d', $visitation_date)) {
    $_SESSION['error'] = "Invalid visitation date format!";
    header("Location: mao_inventory.php");
    exit();
}

// Validate that visitation date is not before distribution date
if (strtotime($visitation_date) < strtotime($date_given)) {
    $_SESSION['error'] = "Visitation date cannot be earlier than the distribution date!";
    header("Location: mao_inventory.php");
    exit();
}

// No need for mysqli_real_escape_string, using prepared statements below

// Verify input exists and get current stock from master total_stock (source of truth)
$input_check = "SELECT ic.input_id, ic.input_name, COALESCE(ic.total_stock, 0) as total_stock FROM input_categories ic WHERE ic.input_id = ?";
$stmt = mysqli_prepare($conn, $input_check);
mysqli_stmt_bind_param($stmt, "s", $input_id);
mysqli_stmt_execute($stmt);
$input_result = mysqli_stmt_get_result($stmt);
if (!$input_result || $input_result->num_rows == 0) {
    $_SESSION['error'] = "Selected input does not exist!";
    header("Location: mao_inventory.php");
    exit();
}
$input_data = $input_result->fetch_assoc();
$current_stock = floatval($input_data['total_stock']);
mysqli_stmt_close($stmt);

// Verify farmer exists
$farmer_check = "SELECT farmer_id, first_name, last_name FROM farmers WHERE farmer_id = ?";
$stmt = mysqli_prepare($conn, $farmer_check);
mysqli_stmt_bind_param($stmt, "s", $farmer_id);
mysqli_stmt_execute($stmt);
$farmer_result = mysqli_stmt_get_result($stmt);
if (!$farmer_result || $farmer_result->num_rows == 0) {
    $_SESSION['error'] = "Selected farmer does not exist!";
    header("Location: mao_inventory.php");
    exit();
}
$farmer_data = $farmer_result->fetch_assoc();
mysqli_stmt_close($stmt);

// Check if there's enough stock
if ($current_stock < floatval($quantity_distributed)) {
    $_SESSION['error'] = "Insufficient stock! Available: " . $current_stock . ", Requested: " . $quantity_distributed;
    header("Location: mao_inventory.php");
    exit();
}

// Validate farmer exists using the farmer_id from dropdown
// Already checked above, so this block can be removed

// Check available stock
// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Record distribution - visitation_date is now always required
    $distribution_query = "INSERT INTO mao_distribution_log (farmer_id, input_id, quantity_distributed, date_given, visitation_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $distribution_query);
    mysqli_stmt_bind_param($stmt, "sssss", $farmer_id, $input_id, $quantity_distributed, $date_given, $visitation_date);
    $success = mysqli_stmt_execute($stmt);
    if (!$success) {
        mysqli_stmt_close($stmt);
        throw new Exception("Error recording distribution: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);

    // FEFO: Deduct from batches with earliest expiration first
    $remaining = floatval($quantity_distributed);
    $batch_query = "SELECT inventory_id, quantity_on_hand FROM mao_inventory WHERE input_id = ? AND quantity_on_hand > 0 ORDER BY expiration_date ASC, inventory_id ASC FOR UPDATE";
    $stmt = mysqli_prepare($conn, $batch_query);
    mysqli_stmt_bind_param($stmt, "s", $input_id);
    mysqli_stmt_execute($stmt);
    $batch_result = mysqli_stmt_get_result($stmt);
    $batches = [];
    while ($row = mysqli_fetch_assoc($batch_result)) {
        $batches[] = $row;
    }
    mysqli_stmt_close($stmt);

    $total_available = 0;
    foreach ($batches as $batch) {
        $total_available += floatval($batch['quantity_on_hand']);
    }
    if ($total_available < $remaining) {
        throw new Exception("Insufficient stock across all batches! Available: $total_available, Requested: $remaining");
    }

    foreach ($batches as $batch) {
        if ($remaining <= 0) break;
        $batch_id = $batch['inventory_id'];
        $batch_qty = floatval($batch['quantity_on_hand']);
        $deduct = min($batch_qty, $remaining);
    $update_batch = "UPDATE mao_inventory SET quantity_on_hand = quantity_on_hand - ? WHERE inventory_id = ?";
        $stmt = mysqli_prepare($conn, $update_batch);
        mysqli_stmt_bind_param($stmt, "di", $deduct, $batch_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!$success) {
            throw new Exception("Error updating batch inventory: " . mysqli_error($conn));
        }
        $remaining -= $deduct;
    }
    // Also deduct from master total_stock in input_categories
    $update_master = "UPDATE input_categories SET total_stock = total_stock - ? WHERE input_id = ?";
    $stmt = mysqli_prepare($conn, $update_master);
    mysqli_stmt_bind_param($stmt, "ds", $quantity_distributed, $input_id);
    $master_success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$master_success) {
        throw new Exception("Error updating master total_stock: " . mysqli_error($conn));
    }
    // Get input name for logging
    $input_query = "SELECT input_name FROM input_categories WHERE input_id = ?";
    $stmt = mysqli_prepare($conn, $input_query);
    mysqli_stmt_bind_param($stmt, "s", $input_id);
    mysqli_stmt_execute($stmt);
    $input_result = mysqli_stmt_get_result($stmt);
    $input_data = $input_result->fetch_assoc();
    mysqli_stmt_close($stmt);
    // Log activity with visitation info
    $activity_details = "Input ID: $input_id, Farmer ID: $farmer_id, Quantity: $quantity_distributed, Date: $date_given, Visitation Date: $visitation_date";
    logActivity($conn, "Distributed $quantity_distributed units of {$input_data['input_name']} to {$farmer_data['first_name']} {$farmer_data['last_name']} ($farmer_id)", 'input', $activity_details);
    // Commit transaction
    mysqli_commit($conn);
    // Create success message - all inputs now require visitation
    $success_message = "Input distributed successfully to {$farmer_data['first_name']} {$farmer_data['last_name']}! Visitation scheduled for " . date('M d, Y', strtotime($visitation_date)) . ".";
    $_SESSION['success'] = $success_message;
    
} catch (Exception $e) {
    // Rollback transaction
    mysqli_rollback($conn);
    
    // Create user-friendly error message
    $error_message = "Failed to distribute input to {$farmer_data['first_name']} {$farmer_data['last_name']}. ";
    
    // Add specific error details
    if (strpos($e->getMessage(), 'distribution') !== false) {
        $error_message .= "Error recording distribution.";
    } elseif (strpos($e->getMessage(), 'inventory') !== false) {
        $error_message .= "Error updating inventory.";
    } else {
        $error_message .= "Please try again.";
    }
    
    $_SESSION['error'] = $error_message;
}

header("Location: mao_inventory.php");
exit();
?>
