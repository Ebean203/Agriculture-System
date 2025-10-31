<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

if ($_POST['action'] == 'add_stock') {
    // ADD STOCK: This action adds new inventory to existing stock (replenishment)
    // This increases the current quantity by the specified amount
    
    // Validate required fields
    if (!isset($_POST['input_id']) || !isset($_POST['quantity'])) {
        $_SESSION['error'] = "Missing required data for adding stock!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    $input_id = trim($_POST['input_id']);
    $quantity = trim($_POST['quantity']);
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    
    // Validate data is not empty
    if (empty($input_id) || empty($quantity)) {
        $_SESSION['error'] = "Input ID and quantity are required!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    // Validate quantity is numeric and non-negative
    if (!is_numeric($quantity) || floatval($quantity) < 0) {
        $_SESSION['error'] = "Quantity must be a non-negative number!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    // No need for mysqli_real_escape_string, using prepared statements below
    
    // Verify input exists
    $input_exists = "SELECT input_name FROM input_categories WHERE input_id = ?";
    $stmt = $conn->prepare($input_exists);
    $stmt->bind_param("s", $input_id);
    $stmt->execute();
    $input_check = $stmt->get_result();
    if (!$input_check || $input_check->num_rows == 0) {
        $_SESSION['error'] = "Selected input does not exist!";
        header("Location: mao_inventory.php");
        exit();
    }
    $stmt->close();
    
    // Check if inventory record exists
    $check_query = "SELECT * FROM mao_inventory WHERE input_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $input_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    if ($check_result->num_rows > 0) {
        $stmt->close();
        // Update existing record
            $update_query = "UPDATE mao_inventory 
                            SET quantity_on_hand = quantity_on_hand + ?,
                                expiration_date = ?
                            WHERE input_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("dss", $quantity, $expiration_date, $input_id);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = ?";
            $stmt = mysqli_prepare($conn, $input_query);
            mysqli_stmt_bind_param($stmt, "s", $input_id);
            mysqli_stmt_execute($stmt);
            $input_result = mysqli_stmt_get_result($stmt);
            $input_data = mysqli_fetch_assoc($input_result);
            mysqli_stmt_close($stmt);
            // Log activity
            logActivity($conn, "Added $quantity units to {$input_data['input_name']} inventory (Stock Replenishment)", 'input', "Input ID: $input_id, Quantity Added: $quantity");
            $_SESSION['success'] = "Stock added successfully!";
        } else {
            $_SESSION['error'] = "Error adding stock: " . mysqli_error($conn);
        }
    } else {
        // Insert new record
    $insert_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, expiration_date) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("sds", $input_id, $quantity, $expiration_date);
        $success = $stmt->execute();
        $stmt->close();
        if ($success) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = ?";
            $stmt = mysqli_prepare($conn, $input_query);
            mysqli_stmt_bind_param($stmt, "s", $input_id);
            mysqli_stmt_execute($stmt);
            $input_result = mysqli_stmt_get_result($stmt);
            $input_data = mysqli_fetch_assoc($input_result);
            
            // Log activity
            logActivity($conn, "Initialized {$input_data['input_name']} inventory with $quantity units", 'input', "Input ID: $input_id, Initial Quantity: $quantity");
            
            $_SESSION['success'] = "Stock added successfully!";
        } else {
            $_SESSION['error'] = "Error adding stock: " . mysqli_error($conn);
        }
    }
}

if ($_POST['action'] == 'update_stock') {
    // UPDATE STOCK: This action sets the inventory to a specific quantity
    // This is for inventory corrections/adjustments only - NOT for distributions
    // Distributions are handled separately in distribute_input.php
    
    // Validate required fields
    if (!isset($_POST['input_id']) || !isset($_POST['new_quantity'])) {
        $_SESSION['error'] = "Missing required data for updating stock!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    $input_id = trim($_POST['input_id']);
    $new_quantity = trim($_POST['new_quantity']);
    
    // Validate data is not empty
    if (empty($input_id) || empty($new_quantity)) {
        $_SESSION['error'] = "Input ID and new quantity are required!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    // Validate quantity is numeric and non-negative
    if (!is_numeric($new_quantity) || floatval($new_quantity) < 0) {
        $_SESSION['error'] = "New quantity must be a non-negative number!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    $input_id = mysqli_real_escape_string($conn, $input_id);
    // Verify input exists
    $input_exists = "SELECT input_name FROM input_categories WHERE input_id = ?";
    $input_check_stmt = $conn->prepare($input_exists);
    $input_check_stmt->bind_param("s", $input_id);
    $input_check_stmt->execute();
    $input_check_result = $input_check_stmt->get_result();
    if (!$input_check_result || $input_check_result->num_rows == 0) {
        $_SESSION['error'] = "Selected input does not exist!";
        header("Location: mao_inventory.php");
        exit();
    }
    $input_check_stmt->close();

    // Get current quantity for logging
    $current_query = "SELECT quantity_on_hand FROM mao_inventory WHERE input_id = ?";
    $current_stmt = $conn->prepare($current_query);
    $current_stmt->bind_param("s", $input_id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_data = $current_result->fetch_assoc();
    $old_quantity = $current_data ? $current_data['quantity_on_hand'] : 0;
    $current_stmt->close();

    // Check if inventory record exists
    $check_query = "SELECT * FROM mao_inventory WHERE input_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $input_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Update existing record
    $update_query = "UPDATE mao_inventory SET quantity_on_hand = ? WHERE input_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("is", $new_quantity, $input_id);
        if ($update_stmt->execute()) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = ?";
            $input_stmt = $conn->prepare($input_query);
            $input_stmt->bind_param("s", $input_id);
            $input_stmt->execute();
            $input_result = $input_stmt->get_result();
            $input_data = $input_result->fetch_assoc();
            $input_stmt->close();
            // Log activity
            logActivity($conn, "Adjusted {$input_data['input_name']} inventory from $old_quantity to $new_quantity units (Stock Correction)", 'input', "Input ID: $input_id, Old Quantity: $old_quantity, New Quantity: $new_quantity");
            $_SESSION['success'] = "Stock updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating stock: " . $update_stmt->error;
        }
        $update_stmt->close();
    } else {
        // Insert new record
    $insert_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("si", $input_id, $new_quantity);
        if ($insert_stmt->execute()) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = ?";
            $input_stmt = $conn->prepare($input_query);
            $input_stmt->bind_param("s", $input_id);
            $input_stmt->execute();
            $input_result = $input_stmt->get_result();
            $input_data = $input_result->fetch_assoc();
            $input_stmt->close();
            // Log activity
            logActivity($conn, "Initialized {$input_data['input_name']} inventory with $new_quantity units", 'input', "Input ID: $input_id, Initial Quantity: $new_quantity");
            $_SESSION['success'] = "Stock updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating stock: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
}

header("Location: mao_inventory.php");
exit();
?>
