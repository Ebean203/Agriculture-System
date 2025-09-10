<?php
session_start();
require_once 'conn.php';
require_once 'check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'new_type') {
        // Add new input type
        $input_name = trim($_POST['input_name'] ?? '');
        $unit = $_POST['unit'] ?? '';
        $quantity_on_hand = intval($_POST['quantity_on_hand'] ?? 0);
        
        if (empty($input_name) || empty($unit) || $quantity_on_hand < 0) {
            $_SESSION['error'] = "Please fill in all required fields with valid values.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Check if input name already exists
        $check_query = "SELECT input_id FROM inputs WHERE LOWER(input_name) = LOWER(?)";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $input_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "An input with this name already exists.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Generate new input ID
        $id_query = "SELECT MAX(CAST(SUBSTRING(input_id, 4) AS UNSIGNED)) as max_id FROM inputs WHERE input_id LIKE 'INP%'";
        $id_result = mysqli_query($conn, $id_query);
        $id_row = mysqli_fetch_assoc($id_result);
        $next_id = ($id_row['max_id'] ?? 0) + 1;
        $input_id = 'INP' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        
        // Insert new input
        $insert_query = "INSERT INTO inputs (input_id, input_name, unit, quantity_on_hand, date_added) VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "sssi", $input_id, $input_name, $unit, $quantity_on_hand);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, $_SESSION['username'], "Added new input type: $input_name ($quantity_on_hand $unit)", 'inventory');
            
            $_SESSION['success'] = "New input type '$input_name' added successfully with $quantity_on_hand $unit in stock.";
        } else {
            $_SESSION['error'] = "Failed to add new input type. Please try again.";
        }
        
    } elseif ($action === 'add_stock') {
        // Add stock to existing input
        $input_id = $_POST['input_id'] ?? '';
        $add_quantity = intval($_POST['add_quantity'] ?? 0);
        
        if (empty($input_id) || $add_quantity <= 0) {
            $_SESSION['error'] = "Please select an input and enter a valid quantity.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Get current stock and input details
        $current_query = "SELECT input_name, unit, quantity_on_hand FROM inputs WHERE input_id = ?";
        $current_stmt = mysqli_prepare($conn, $current_query);
        mysqli_stmt_bind_param($current_stmt, "s", $input_id);
        mysqli_stmt_execute($current_stmt);
        $current_result = mysqli_stmt_get_result($current_stmt);
        
        if (mysqli_num_rows($current_result) === 0) {
            $_SESSION['error'] = "Selected input not found.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        $current_data = mysqli_fetch_assoc($current_result);
        $current_stock = intval($current_data['quantity_on_hand']);
        $new_stock = $current_stock + $add_quantity;
        
        // Update stock
        $update_query = "UPDATE inputs SET quantity_on_hand = ? WHERE input_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "is", $new_stock, $input_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, $_SESSION['username'], "Added $add_quantity {$current_data['unit']} to {$current_data['input_name']} (Total: $new_stock {$current_data['unit']})", 'inventory');
            
            $_SESSION['success'] = "Successfully added $add_quantity {$current_data['unit']} to {$current_data['input_name']}. New total: $new_stock {$current_data['unit']}.";
        } else {
            $_SESSION['error'] = "Failed to update stock. Please try again.";
        }
        
    } else {
        $_SESSION['error'] = "Invalid action.";
    }
    
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: mao_inventory.php");
exit();
?>
