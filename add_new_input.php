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
        
        if (empty($input_name) || empty($unit)) {
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
        
        // Insert new input type with total_stock set to 0
        $insert_query = "INSERT INTO input_categories (input_name, unit, total_stock) VALUES (?, ?, 0)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ss", $input_name, $unit);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $new_input_id = mysqli_insert_id($conn);
        
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, "Added new input type: $input_name", 'inventory', "Input: $input_name, Unit: $unit, Initial Stock: 0");
            
            $_SESSION['success'] = "New input type '$input_name' added successfully with initial stock of 0. You can now stock in using 'Add to Existing Input'.";
        } else {
            $_SESSION['error'] = "Failed to add new input type. Please try again.";
        }
        
    } elseif ($action === 'add_stock') {
    // Add stock to existing input (batch-based, FEFO)
        // Add stock to existing input (hybrid batch + master total)
        $input_id = $_POST['input_id'] ?? '';
        $add_quantity = intval($_POST['add_quantity'] ?? 0);
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
        // Require expiration date for batch stock-ins
        if (empty($input_id) || $add_quantity <= 0 || empty($expiration_date)) {
            $_SESSION['error'] = "Please select an input and enter a valid quantity.";
            header("Location: mao_inventory.php");
            exit();
        }
        // Get input details
        $input_query = "SELECT input_name, unit FROM input_categories WHERE input_id = ?";
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
    // Ensure DB allows multiple batches per input_id by removing old unique index if present
        // Check if unique index exists
        $idx_check_sql = "SELECT INDEX_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mao_inventory' AND COLUMN_NAME = 'input_id'";
        $idx_check_res = mysqli_query($conn, $idx_check_sql);
        $has_unique_idx = false;
        if ($idx_check_res) {
            while ($r = mysqli_fetch_assoc($idx_check_res)) {
                if ($r['INDEX_NAME'] === 'uq_inventory_input' || (int)$r['NON_UNIQUE'] === 0) {
                    $has_unique_idx = true;
                    break;
                }
            }
        }

        if ($has_unique_idx) {
            // If a unique index exists on input_id and it's required by a foreign key, we need to replace it with a non-unique index.
            // Find any foreign key constraints on mao_inventory that reference input_categories
            $fk_sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mao_inventory' AND REFERENCED_TABLE_NAME = 'input_categories' AND REFERENCED_COLUMN_NAME = 'input_id'";
            $fk_res = mysqli_query($conn, $fk_sql);
            $fks = [];
            if ($fk_res) {
                while ($fk = mysqli_fetch_assoc($fk_res)) {
                    $fks[] = $fk['CONSTRAINT_NAME'];
                }
            }

            // Temporarily drop foreign keys, drop the unique index, create a non-unique index, then recreate foreign keys
            foreach ($fks as $fk_name) {
                @mysqli_query($conn, "ALTER TABLE mao_inventory DROP FOREIGN KEY `" . $fk_name . "`");
            }

            // Drop unique index (if still present)
            @mysqli_query($conn, "ALTER TABLE mao_inventory DROP INDEX uq_inventory_input");

            // Create a normal (non-unique) index on input_id so FK can be recreated
            @mysqli_query($conn, "CREATE INDEX idx_mao_inventory_input ON mao_inventory (input_id)");

            // Recreate foreign keys (pointing to input_categories.input_id)
            foreach ($fks as $fk_name) {
                // Attempt to add FK back using standard name; if fails, skip silently
                @mysqli_query($conn, "ALTER TABLE mao_inventory ADD CONSTRAINT `" . $fk_name . "` FOREIGN KEY (input_id) REFERENCES input_categories(input_id) ON DELETE CASCADE ON UPDATE CASCADE");
            }
        }

    // Always insert a new batch row
    $insert_query = "INSERT INTO mao_inventory (input_id, quantity_on_hand, expiration_date) VALUES (?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($insert_stmt, "iis", $input_id, $add_quantity, $expiration_date);
        if (mysqli_stmt_execute($insert_stmt)) {
            // Update master total in input_categories
            $update_total_query = "UPDATE input_categories SET total_stock = total_stock + ? WHERE input_id = ?";
            $update_total_stmt = mysqli_prepare($conn, $update_total_query);
            mysqli_stmt_bind_param($update_total_stmt, "ii", $add_quantity, $input_id);
            mysqli_stmt_execute($update_total_stmt);
            mysqli_stmt_close($update_total_stmt);
            // Reconcile master total to exact sum of batches for this input (safety)
            $recalc_sql = "UPDATE input_categories ic
                           SET ic.total_stock = (
                               SELECT COALESCE(SUM(quantity_on_hand),0)
                               FROM mao_inventory
                               WHERE input_id = ?
                           )
                           WHERE ic.input_id = ?";
            $re_stmt = mysqli_prepare($conn, $recalc_sql);
            mysqli_stmt_bind_param($re_stmt, "ii", $input_id, $input_id);
            mysqli_stmt_execute($re_stmt);
            mysqli_stmt_close($re_stmt);
            // Log activity
            require_once 'includes/activity_logger.php';
            logActivity($conn, "Stock-in: Added $add_quantity {$input_data['unit']} to {$input_data['input_name']} (Batch)", 'inventory', "Input ID: $input_id, Added: $add_quantity, Expiry: $expiration_date");
            $_SESSION['success'] = "Successfully added $add_quantity {$input_data['unit']} to {$input_data['input_name']} as a new batch.";
        } else {
            $_SESSION['error'] = "Failed to add stock batch. Please try again.";
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
