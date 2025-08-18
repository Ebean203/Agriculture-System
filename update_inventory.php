<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

if ($_POST['action'] == 'add_stock') {
    // Validate required fields
    if (!isset($_POST['input_id']) || !isset($_POST['quantity'])) {
        $_SESSION['error'] = "Missing required data for adding stock!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    $input_id = trim($_POST['input_id']);
    $quantity = trim($_POST['quantity']);
    
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
    
    $input_id = mysqli_real_escape_string($conn, $input_id);
    $quantity = mysqli_real_escape_string($conn, $quantity);
    
    // Verify input exists
    $input_exists = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
    $input_check = mysqli_query($conn, $input_exists);
    
    if (!$input_check || mysqli_num_rows($input_check) == 0) {
        $_SESSION['error'] = "Selected input does not exist!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    // Check if inventory record exists
    $check_query = "SELECT * FROM mao_inventory WHERE input_id = '$input_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing record
        $update_query = "UPDATE mao_inventory 
                        SET quantity_on_hand = quantity_on_hand + $quantity,
                            last_updated = NOW()
                        WHERE input_id = '$input_id'";
        
        if (mysqli_query($conn, $update_query)) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
            $input_result = mysqli_query($conn, $input_query);
            $input_data = mysqli_fetch_assoc($input_result);
            
            // Log activity
            logActivity($conn, "Added $quantity units to {$input_data['input_name']} inventory", 'input', "Input ID: $input_id, Quantity Added: $quantity");
            
            $_SESSION['success'] = "Stock added successfully!";
        } else {
            $_SESSION['error'] = "Error adding stock: " . mysqli_error($conn);
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, last_updated) 
                        VALUES ('$input_id', '$quantity', NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
            $input_result = mysqli_query($conn, $input_query);
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
    $new_quantity = mysqli_real_escape_string($conn, $new_quantity);
    
    // Verify input exists
    $input_exists = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
    $input_check = mysqli_query($conn, $input_exists);
    
    if (!$input_check || mysqli_num_rows($input_check) == 0) {
        $_SESSION['error'] = "Selected input does not exist!";
        header("Location: mao_inventory.php");
        exit();
    }
    
    // Get current quantity for logging
    $current_query = "SELECT quantity_on_hand FROM mao_inventory WHERE input_id = '$input_id'";
    $current_result = mysqli_query($conn, $current_query);
    $current_data = mysqli_fetch_assoc($current_result);
    $old_quantity = $current_data ? $current_data['quantity_on_hand'] : 0;
    
    // Check if inventory record exists
    $check_query = "SELECT * FROM mao_inventory WHERE input_id = '$input_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing record
        $update_query = "UPDATE mao_inventory 
                        SET quantity_on_hand = '$new_quantity',
                            last_updated = NOW()
                        WHERE input_id = '$input_id'";
        
        if (mysqli_query($conn, $update_query)) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
            $input_result = mysqli_query($conn, $input_query);
            $input_data = mysqli_fetch_assoc($input_result);
            
            // Log activity
            logActivity($conn, "Updated {$input_data['input_name']} inventory from $old_quantity to $new_quantity units", 'input', "Input ID: $input_id, Old Quantity: $old_quantity, New Quantity: $new_quantity");
            
            $_SESSION['success'] = "Stock updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating stock: " . mysqli_error($conn);
        }
    } else {
        // Insert new record
        $insert_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, last_updated) 
                        VALUES ('$input_id', '$new_quantity', NOW())";
        
        if (mysqli_query($conn, $insert_query)) {
            // Get input name for logging
            $input_query = "SELECT input_name FROM input_categories WHERE input_id = '$input_id'";
            $input_result = mysqli_query($conn, $input_query);
            $input_data = mysqli_fetch_assoc($input_result);
            
            // Log activity
            logActivity($conn, "Initialized {$input_data['input_name']} inventory with $new_quantity units", 'input', "Input ID: $input_id, Initial Quantity: $new_quantity");
            
            $_SESSION['success'] = "Stock updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating stock: " . mysqli_error($conn);
        }
    }
}

header("Location: mao_inventory.php");
exit();
?>
