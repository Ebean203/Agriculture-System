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
        $check_query = "SELECT input_id FROM input_categories WHERE LOWER(input_name) = LOWER(?)";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $input_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "An input with this name already exists.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Insert new input type (input_categories table handles auto-increment for input_id)
        $insert_query = "INSERT INTO input_categories (input_name, unit) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ss", $input_name, $unit);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $new_input_id = mysqli_insert_id($conn);
            
            // If initial quantity > 0, add to mao_inventory table
            if ($quantity_on_hand > 0) {
                $inventory_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, last_updated) VALUES (?, ?, NOW())";
                $inventory_stmt = mysqli_prepare($conn, $inventory_query);
                mysqli_stmt_bind_param($inventory_stmt, "ii", $new_input_id, $quantity_on_hand);
                mysqli_stmt_execute($inventory_stmt);
            }
        
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, "Added new input type: $input_name" . ($quantity_on_hand > 0 ? " with $quantity_on_hand $unit initial stock" : ""), 'inventory', "Input: $input_name, Unit: $unit, Initial Stock: $quantity_on_hand");
            
            $_SESSION['success'] = "New input type '$input_name' added successfully" . ($quantity_on_hand > 0 ? " with $quantity_on_hand $unit in stock" : "") . ".";
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
        
        // Get input details and current stock
        $input_query = "SELECT ic.input_name, ic.unit, COALESCE(mi.quantity_on_hand, 0) as quantity_on_hand
                       FROM input_categories ic
                       LEFT JOIN mao_inventory mi ON ic.input_id = mi.input_id
                       WHERE ic.input_id = ?";
        $input_stmt = mysqli_prepare($conn, $input_query);
        mysqli_stmt_bind_param($input_stmt, "i", $input_id);
        mysqli_stmt_execute($input_stmt);
        $input_result = mysqli_stmt_get_result($input_stmt);
        
        if (mysqli_num_rows($input_result) === 0) {
            $_SESSION['error'] = "Selected input not found.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        $input_data = mysqli_fetch_assoc($input_result);
        $current_stock = intval($input_data['quantity_on_hand']);
        $new_stock = $current_stock + $add_quantity;
        
        // Check if inventory record exists, update or insert
        $check_inventory = "SELECT input_id FROM mao_inventory WHERE input_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_inventory);
        mysqli_stmt_bind_param($check_stmt, "i", $input_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update existing inventory
            $update_query = "UPDATE mao_inventory SET quantity_on_hand = ?, last_updated = NOW() WHERE input_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $new_stock, $input_id);
        } else {
            // Insert new inventory record
            $update_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, last_updated) VALUES (?, ?, NOW())";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $input_id, $new_stock);
        }
        
        if (mysqli_stmt_execute($update_stmt)) {
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, "Added $add_quantity {$input_data['unit']} to {$input_data['input_name']} (Total: $new_stock {$input_data['unit']})", 'inventory', "Input ID: $input_id, Added: $add_quantity, New Total: $new_stock");
            
            $_SESSION['success'] = "Successfully added $add_quantity {$input_data['unit']} to {$input_data['input_name']}. New total: $new_stock {$input_data['unit']}.";
        } else {
            $_SESSION['error'] = "Failed to update stock. Please try again.";
        }
        
    } elseif ($action === 'new_commodity') {
        // Add new commodity
        $commodity_name = trim($_POST['commodity_name'] ?? '');
        $category_id = $_POST['category_id'] ?? '';
        $commodity_description = trim($_POST['commodity_description'] ?? '');
        
        if (empty($commodity_name) || empty($category_id)) {
            $_SESSION['error'] = "Please fill in commodity name and select a category.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Check if commodity name already exists
        $check_query = "SELECT commodity_id FROM commodities WHERE LOWER(commodity_name) = LOWER(?)";
        $check_stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($check_stmt, "s", $commodity_name);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "A commodity with this name already exists.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        // Verify category exists
        $category_check = "SELECT category_name FROM commodity_categories WHERE category_id = ?";
        $category_stmt = mysqli_prepare($conn, $category_check);
        mysqli_stmt_bind_param($category_stmt, "i", $category_id);
        mysqli_stmt_execute($category_stmt);
        $category_result = mysqli_stmt_get_result($category_stmt);
        
        if (mysqli_num_rows($category_result) === 0) {
            $_SESSION['error'] = "Selected category not found.";
            header("Location: mao_inventory.php");
            exit();
        }
        
        $category_data = mysqli_fetch_assoc($category_result);
        
        // Insert new commodity
        $insert_query = "INSERT INTO commodities (commodity_name, category_id) VALUES (?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "si", $commodity_name, $category_id);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, "Added new commodity: $commodity_name in category {$category_data['category_name']}", 'commodity', "Commodity: $commodity_name, Category: {$category_data['category_name']}");
            
            $_SESSION['success'] = "New commodity '$commodity_name' added successfully in category '{$category_data['category_name']}'.";
        } else {
            $_SESSION['error'] = "Failed to add new commodity. Please try again. Error: " . mysqli_error($conn);
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
