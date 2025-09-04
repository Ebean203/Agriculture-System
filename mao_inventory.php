<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

$pageTitle = 'Manage Inventory';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Get all input categories with their current inventory
$query = "SELECT 
    ic.input_id,
    ic.input_name,
    ic.unit,
    COALESCE(mi.quantity_on_hand, 0) as quantity_on_hand,
    mi.last_updated
FROM input_categories ic
LEFT JOIN mao_inventory mi ON ic.input_id = mi.input_id
ORDER BY ic.input_name";

$result = mysqli_query($conn, $query);

// Check for query errors
if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}

// Check if we have any data
$row_count = mysqli_num_rows($result);
if ($row_count == 0) {
    // No input categories found, let's create some basic ones
    $create_inputs = "INSERT IGNORE INTO input_categories (input_name, unit) VALUES 
        ('Rice Seeds', 'kg'),
        ('Corn Seeds', 'kg'),
        ('Vegetable Seeds', 'pack'),
        ('Urea Fertilizer', 'sack'),
        ('Complete Fertilizer', 'sack'),
        ('Organic Fertilizer', 'sack'),
        ('Pesticide', 'liter'),
        ('Herbicides', 'liter')";
    
    mysqli_query($conn, $create_inputs);
    
    // Re-run the main query
    $result = mysqli_query($conn, $query);
    $row_count = mysqli_num_rows($result);
}

// Get total distributed amounts for each input (handle empty table after truncation)
$distribution_query = "SELECT 
    input_id,
    SUM(quantity_distributed) as total_distributed
FROM mao_distribution_log
GROUP BY input_id";

$distribution_result = mysqli_query($conn, $distribution_query);
$distributions = [];

// Handle case where table might be empty after truncation
if ($distribution_result && mysqli_num_rows($distribution_result) > 0) {
    while ($row = mysqli_fetch_assoc($distribution_result)) {
        $distributions[$row['input_id']] = intval($row['total_distributed']);
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Agricultural Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-dark': '#16a34a',
                        'agri-light': '#dcfce7'
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom dropdown styles */
        #dropdownMenu {
            display: none;
            z-index: 50;
            transition: all 0.2s ease-in-out;
            transform-origin: top right;
        }

        #dropdownMenu.show {
            display: block !important;
            z-index: 999999 !important;
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            margin-top: 0.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4) !important;
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
        }

        #dropdownArrow.rotate {
            transform: rotate(180deg);
        }

        /* Ensure dropdown is above other content */
        .relative {
            z-index: 40;
        }
        
        .inventory-card {
            transition: all 0.3s ease;
        }
        .inventory-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .low-stock {
            border-left: 4px solid #dc2626;
        }
        .medium-stock {
            border-left: 4px solid #d97706;
        }
        .high-stock {
            border-left: 4px solid #16a34a;
        }
        .out-of-stock {
            border-left: 4px solid #6b7280;
            background-color: #f9fafb;
        }
        
        /* Farmer suggestions styling */
        #farmer_suggestions {
            z-index: 1000;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        .suggestion-item:hover {
            background-color: #f3f4f6 !important;
        }
        
        /* Modal enhancements for better layout */
        .distribute-modal-xl {
            max-width: 80rem; /* Even larger for visitation content */
        }
        
        .modal-content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .modal-content-grid {
                grid-template-columns: 1fr;
            }
            .distribute-modal-xl {
                max-width: 95%;
            }
        }
    </style>
    <script>
        // Function to handle dropdown toggle
        function toggleDropdown() {
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            dropdownMenu.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.getElementById('userMenu');
            const dropdownMenu = document.getElementById('dropdownMenu');
            const dropdownArrow = document.getElementById('dropdownArrow');
            
            if (!userMenu.contains(event.target) && dropdownMenu.classList.contains('show')) {
                dropdownMenu.classList.remove('show');
                dropdownArrow.classList.remove('rotate');
            }
        });
    </script>
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-warehouse text-agri-green mr-3"></i>
                            Manage Inventory
                        </h1>
                        <p class="text-gray-600 mt-2">Monitor and manage agricultural inputs available for distribution</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" onclick="openModal('addStockModal')">
                            <i class="fas fa-plus mr-2"></i>Add Stock
                        </button>
                    </div>
                </div>
            </div>

            <!-- Success and Error Messages -->
            <div id="messageContainer" class="mb-6" style="display: none;">
                <div id="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center" style="display: none;">
                    <i class="fas fa-check-circle mr-3 text-green-600"></i>
                    <div>
                        <strong>Success!</strong>
                        <span id="successText"></span>
                    </div>
                    <button onclick="closeMessage('successMessage')" class="ml-auto text-green-700 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center" style="display: none;">
                    <i class="fas fa-exclamation-circle mr-3 text-red-600"></i>
                    <div>
                        <strong>Error!</strong>
                        <span id="errorText"></span>
                    </div>
                    <button onclick="closeMessage('errorMessage')" class="ml-auto text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Inventory Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <?php
                // Calculate summary statistics (safe handling after truncation)
                $total_items = 0;
                $out_of_stock = 0;
                $low_stock = 0;
                
                if ($row_count > 0) {
                    mysqli_data_seek($result, 0);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $total_items++;
                        $current_quantity = intval($row['quantity_on_hand']); // Ensure integer
                        if ($current_quantity == 0) {
                            $out_of_stock++;
                        } elseif ($current_quantity <= 10) {
                            $low_stock++;
                        }
                    }
                }
                ?>
                
                <!-- Total Items -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-boxes text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $total_items; ?></h3>
                            <p class="text-gray-600">Total Input Types</p>
                        </div>
                    </div>
                </div>

                <!-- Out of Stock -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $out_of_stock; ?></h3>
                            <p class="text-gray-600">Out of Stock</p>
                        </div>
                    </div>
                </div>

                <!-- Low Stock -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900"><?php echo $low_stock; ?></h3>
                            <p class="text-gray-600">Low Stock Items</p>
                        </div>
                    </div>
                </div>

                <!-- Last Updated -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-agri-green">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900"><?php echo date('M d, Y'); ?></h3>
                            <p class="text-gray-600">Last Updated</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Items Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php
                if ($row_count == 0): ?>
                    <div class="col-span-full bg-white rounded-lg shadow-md p-8 text-center">
                        <div class="text-gray-500">
                            <i class="fas fa-box-open text-6xl mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">No Input Categories Found</h3>
                            <p class="text-gray-600 mb-4">There are no input categories in the database yet.</p>
                            <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors" onclick="openModal('addStockModal')">
                                <i class="fas fa-plus mr-2"></i>Add First Input
                            </button>
                        </div>
                    </div>
                <?php else:
                    mysqli_data_seek($result, 0);
                    while ($row = mysqli_fetch_assoc($result)):
                        $quantity = intval($row['quantity_on_hand']); // Ensure it's always an integer
                        $distributed = isset($distributions[$row['input_id']]) ? intval($distributions[$row['input_id']]) : 0;
                        
                        // Ensure we have valid values
                        if ($quantity < 0) $quantity = 0;
                        if ($distributed < 0) $distributed = 0;
                        
                        // Determine stock status
                        $stock_class = 'high-stock';
                        $stock_text = 'In Stock';
                        $stock_color = 'green';
                        
                        if ($quantity == 0) {
                            $stock_class = 'out-of-stock';
                            $stock_text = 'Out of Stock';
                            $stock_color = 'gray';
                        } elseif ($quantity <= 5) {
                            $stock_class = 'low-stock';
                            $stock_text = 'Low Stock';
                            $stock_color = 'red';
                        } elseif ($quantity <= 20) {
                            $stock_class = 'medium-stock';
                            $stock_text = 'Medium Stock';
                            $stock_color = 'yellow';
                        }
                        
                        // Define required variables for data attributes
                        $input_id = $row['input_id'];
                        $input_name_original = $row['input_name'];
                        $quantity_safe = $quantity; // Safe version for HTML attributes
                        
                        // Determine category icon
                        $input_name_lower = strtolower($row['input_name']);
                        $icon_bg = 'bg-gray-500';
                        $icon = 'fas fa-box';
                        
                        if (strpos($input_name_lower, 'seed') !== false) {
                            $icon_bg = 'bg-green-500';
                            $icon = 'fas fa-seedling';
                        } elseif (strpos($input_name_lower, 'fertilizer') !== false) {
                            $icon_bg = 'bg-blue-500';
                            $icon = 'fas fa-leaf';
                        } elseif (strpos($input_name_lower, 'pesticide') !== false || strpos($input_name_lower, 'herbicide') !== false) {
                            $icon_bg = 'bg-yellow-500';
                            $icon = 'fas fa-flask';
                        } elseif (strpos($input_name_lower, 'goat') !== false || strpos($input_name_lower, 'chicken') !== false) {
                            $icon_bg = 'bg-orange-500';
                            $icon = 'fas fa-paw';
                        } elseif (strpos($input_name_lower, 'tractor') !== false || strpos($input_name_lower, 'shovel') !== false || strpos($input_name_lower, 'sprayer') !== false || strpos($input_name_lower, 'pump') !== false) {
                            $icon_bg = 'bg-purple-500';
                            $icon = 'fas fa-tools';
                        }
                    ?>
                    <div class="inventory-card bg-white rounded-lg shadow-md <?php echo $stock_class; ?> h-full">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center">
                                    <div class="w-12 h-12 <?php echo $icon_bg; ?> rounded-lg flex items-center justify-center mr-4">
                                        <i class="<?php echo $icon; ?> text-white text-lg"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($row['input_name']); ?></h3>
                                        <p class="text-sm text-gray-600">Unit: <?php echo htmlspecialchars($row['unit']); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-<?php echo $stock_color; ?>-100 text-<?php echo $stock_color; ?>-800">
                                    <?php echo $stock_text; ?>
                                </span>
                            </div>
                            
                            <!-- Statistics -->
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="text-center border-r border-gray-200">
                                    <div class="text-2xl font-bold text-green-600"><?php echo number_format($quantity); ?></div>
                                    <div class="text-xs text-gray-600">Available</div>
                                </div>
                                <div class="text-center border-r border-gray-200">
                                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($distributed); ?></div>
                                    <div class="text-xs text-gray-600">Distributed</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-purple-600"><?php echo number_format($quantity + $distributed); ?></div>
                                    <div class="text-xs text-gray-600">Total</div>
                                </div>
                            </div>
                            
                            <!-- Last Updated -->
                            <?php if ($row['last_updated']): ?>
                            <div class="mb-4 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-600">
                                    <i class="fas fa-clock mr-1"></i> 
                                    Updated: <?php echo date('M d, Y g:i A', strtotime($row['last_updated'])); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                
                                <button class="flex-1 bg-agri-green text-white px-3 py-2 rounded-lg hover:bg-agri-dark transition-colors text-sm flex items-center justify-center update-btn" 
                                        data-input-id="<?php echo htmlspecialchars($input_id); ?>"
                                        data-input-name="<?php echo htmlspecialchars($input_name_original); ?>"
                                        data-quantity="<?php echo htmlspecialchars($quantity_safe); ?>">
                                    <i class="fas fa-edit mr-1"></i> Update
                                </button>
                                <button class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm flex items-center justify-center distribute-btn" 
                                        data-input-id="<?php echo htmlspecialchars($input_id); ?>"
                                        data-input-name="<?php echo htmlspecialchars($input_name_original); ?>"
                                        data-quantity="<?php echo htmlspecialchars($quantity_safe); ?>">
                                    <i class="fas fa-share mr-1"></i> Distribute
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; 
                endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Stock Modal -->
    <div id="addStockModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle text-agri-green mr-2"></i>Add Stock
                </h3>
            </div>
            <form method="POST" action="update_inventory.php">
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label for="input_id" class="block text-sm font-medium text-gray-700 mb-2">Input Type</label>
                        <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="input_id" required>
                            <option value="">Select Input Type</option>
                            <?php
                            if ($row_count > 0) {
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<option value='{$row['input_id']}'>{$row['input_name']} ({$row['unit']})</option>";
                                }
                            } else {
                                echo "<option value='' disabled>No input categories available</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">Quantity to Add</label>
                        <input type="number" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="quantity" min="1" required>
                    </div>
                    <input type="hidden" name="action" value="add_stock">
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors" onclick="closeModal('addStockModal')">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-agri-green text-white rounded-lg hover:bg-agri-dark transition-colors">Add Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="updateStockModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-edit text-agri-green mr-2"></i>Update Stock
                </h3>
            </div>
            <form method="POST" action="update_inventory.php">
                <div class="px-6 py-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Input Type</label>
                        <input type="text" class="w-full py-2 px-3 border border-gray-300 rounded-lg bg-gray-100" id="update_input_name" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Stock</label>
                        <input type="text" class="w-full py-2 px-3 border border-gray-300 rounded-lg bg-gray-100" id="current_stock" readonly>
                    </div>
                    <div class="mb-4">
                        <label for="new_quantity" class="block text-sm font-medium text-gray-700 mb-2">New Quantity</label>
                        <input type="number" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="new_quantity" id="new_quantity" min="0" required>
                    </div>
                    <input type="hidden" name="input_id" id="update_input_id">
                    <input type="hidden" name="action" value="update_stock">
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3 rounded-b-lg">
                    <button type="button" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors" onclick="closeModal('updateStockModal')">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-agri-green text-white rounded-lg hover:bg-agri-dark transition-colors" onclick="return validateUpdateForm()">Update Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Distribute Modal -->
    <div id="distributeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[95vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-share-square text-agri-green mr-3"></i>Distribute Agricultural Input
                </h3>
                <p class="text-sm text-gray-600 mt-1">Manage input distribution and tracking</p>
            </div>
            <form method="POST" action="distribute_input.php">
                <div class="px-6 py-6">
                    <!-- Hidden input for selected input ID -->
                    <input type="hidden" name="input_id" id="selected_input_id">
                    
                    <!-- Main form content with responsive grid -->
                    <div id="form_content_wrapper">
                        <!-- Selected Input Display (Full Width) -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Selected Input</label>
                            <div class="w-full py-3 px-4 bg-gray-50 border border-gray-300 rounded-lg">
                                <div class="flex items-center">
                                    <div id="input_icon" class="w-10 h-10 bg-gray-500 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-box text-white"></i>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900" id="selected_input_name">No input selected</div>
                                        <div class="text-sm text-gray-600">Available: <span id="available_quantity">0</span></div>
                                    </div>
                                </div>
                            </div>
                            <div id="visitation_indicator" class="mt-2 hidden">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center">
                                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                    <span class="text-blue-800 text-sm font-medium">
                                        This input requires follow-up visitation for yield monitoring.
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Distribution Details Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="farmer_name" class="block text-sm font-medium text-gray-700 mb-2">Farmer Name</label>
                                <div class="relative">
                                    <input type="text" 
                                           class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" 
                                           id="farmer_name" 
                                           placeholder="Type farmer name..."
                                           autocomplete="off"
                                           required
                                           onkeyup="searchFarmers(this.value)"
                                           onfocus="showSuggestions()"
                                           onblur="hideSuggestions()">
                                    <input type="hidden" name="farmer_id" id="selected_farmer_id" required>
                                    
                                    <!-- Suggestions dropdown -->
                                    <div id="farmer_suggestions" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                        <!-- Suggestions will be populated here -->
                                    </div>
                                </div>
                                <p class="text-sm text-gray-600 mt-1">Start typing to search for farmers</p>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="quantity_distributed" class="block text-sm font-medium text-gray-700 mb-2">Quantity to Distribute</label>
                                    <input type="number" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="quantity_distributed" id="quantity_distributed" min="1" required>
                                </div>
                                <div>
                                    <label for="date_given" class="block text-sm font-medium text-gray-700 mb-2">Date Given</label>
                                    <input type="date" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="date_given" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Visitation Tracking Section (Initially Hidden) -->
                    <div id="visitation_section" class="border-t border-gray-200 pt-6 mt-6 hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Visitation Info Panel -->
                            <div class="lg:col-span-2">
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <h4 class="text-lg font-semibold text-green-800 flex items-center mb-3">
                                        <i class="fas fa-calendar-check text-green-600 mr-2"></i>
                                        Visitation Tracking Required
                                    </h4>
                                    <div class="space-y-2 text-sm text-green-700">
                                        <p><i class="fas fa-check-circle mr-2"></i>Follow-up visitation for yield monitoring</p>
                                        <p><i class="fas fa-check-circle mr-2"></i>Agricultural compliance verification</p>
                                        <p><i class="fas fa-check-circle mr-2"></i>Progress tracking and support</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Visitation Date Input -->
                            <div class="lg:col-span-1">
                                <label for="visitation_date" class="block text-sm font-medium text-gray-700 mb-2">Planned Visitation Date</label>
                                <input type="date" class="w-full py-3 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green text-lg" name="visitation_date" id="visitation_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                <p class="text-sm text-gray-600 mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Recommended: 7-14 days after distribution
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-4 rounded-b-lg sticky bottom-0 border-t border-gray-200 z-10">
                    <button type="button" class="px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors font-medium" onclick="closeModal('distributeModal')">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium" onclick="return validateDistributeForm()">
                        <i class="fas fa-share mr-2"></i>Distribute Input
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="mt-12 bg-white shadow-md p-6 w-full">
        <div class="text-center text-gray-600">
            <div class="flex items-center justify-center mb-2">
                <i class="fas fa-seedling text-agri-green mr-2"></i>
                <span class="font-semibold">Agriculture Management System</span>
            </div>
            <p class="text-sm">&copy; <?php echo date('Y'); ?> All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.remove('hidden');
            document.getElementById(modalId).classList.add('flex');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
            document.getElementById(modalId).classList.remove('flex');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modals = ['addStockModal', 'updateStockModal', 'distributeModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });

        function updateStock(inputId, inputName, currentStock) {
            // Ensure all parameters have safe values
            inputId = inputId || '';
            inputName = inputName || 'Unknown Input';
            currentStock = currentStock || 0;
            
            // Safely set values with null checks
            const updateInputId = document.getElementById('update_input_id');
            const updateInputName = document.getElementById('update_input_name');
            const currentStockField = document.getElementById('current_stock');
            const newQuantityField = document.getElementById('new_quantity');
            
            if (updateInputId) updateInputId.value = inputId;
            if (updateInputName) updateInputName.value = inputName;
            if (currentStockField) currentStockField.value = currentStock;
            if (newQuantityField) newQuantityField.value = currentStock;
            
            openModal('updateStockModal');
        }

        function distributeInput(inputId, inputName, availableStock) {
            // Ensure all parameters have safe values
            inputId = inputId || '';
            inputName = inputName || 'Unknown Input';
            availableStock = availableStock || 0;
            
            // Safely set hidden input value with null check
            const selectedInputId = document.getElementById('selected_input_id');
            if (selectedInputId) selectedInputId.value = inputId;
            
            // Update display with null checks
            const selectedInputName = document.getElementById('selected_input_name');
            const availableQuantity = document.getElementById('available_quantity');
            const quantityDistributed = document.getElementById('quantity_distributed');
            
            if (selectedInputName) selectedInputName.textContent = inputName;
            if (availableQuantity) availableQuantity.textContent = availableStock;
            if (quantityDistributed) quantityDistributed.max = availableStock;
            
            // Set appropriate icon based on input type
            const inputIcon = document.getElementById('input_icon');
            if (inputIcon) {
                const iconElement = inputIcon.querySelector('i');
                if (iconElement) {
                    const inputNameLower = inputName.toLowerCase();
                    
                    // Reset classes
                    inputIcon.className = 'w-10 h-10 rounded-lg flex items-center justify-center mr-3';
                    
                    if (inputNameLower.includes('seed')) {
                        inputIcon.classList.add('bg-green-500');
                        iconElement.className = 'fas fa-seedling text-white';
                    } else if (inputNameLower.includes('fertilizer')) {
                        inputIcon.classList.add('bg-blue-500');
                        iconElement.className = 'fas fa-leaf text-white';
                    } else if (inputNameLower.includes('pesticide') || inputNameLower.includes('herbicide')) {
                        inputIcon.classList.add('bg-yellow-500');
                        iconElement.className = 'fas fa-flask text-white';
                    } else if (inputNameLower.includes('goat') || inputNameLower.includes('chicken')) {
                        inputIcon.classList.add('bg-orange-500');
                        iconElement.className = 'fas fa-paw text-white';
                    } else if (inputNameLower.includes('tractor') || inputNameLower.includes('shovel') || inputNameLower.includes('sprayer') || inputNameLower.includes('pump')) {
                        inputIcon.classList.add('bg-purple-500');
                        iconElement.className = 'fas fa-tools text-white';
                    } else {
                        inputIcon.classList.add('bg-gray-500');
                        iconElement.className = 'fas fa-box text-white';
                    }
                }
            }
            
            // Check visitation requirement for this input
            checkVisitationRequirement(inputId, inputName);
            
            openModal('distributeModal');
        }

        // Check if selected input requires visitation
        function checkVisitationRequirement(inputId, inputName) {
            // Ensure parameters have safe values
            inputId = inputId || '';
            inputName = inputName || '';
            
            const visitationSection = document.getElementById('visitation_section');
            const visitationIndicator = document.getElementById('visitation_indicator');
            const visitationDate = document.getElementById('visitation_date');
            
            if (visitationSection && visitationIndicator && visitationDate) {
                const inputNameLower = inputName.toLowerCase();
                
                // Check if input requires visitation based on name (seeds or live animals)
                const requiresVisitation = inputNameLower.includes('seed') || 
                                         inputNameLower.includes('grain') || 
                                         inputNameLower.includes('variety') ||
                                         inputNameLower.includes('chicken') || 
                                         inputNameLower.includes('pig') || 
                                         inputNameLower.includes('goat') || 
                                         inputNameLower.includes('duck') || 
                                         inputNameLower.includes('cattle') || 
                                         inputNameLower.includes('livestock') ||
                                         inputNameLower.includes('animal') ||
                                         inputNameLower.includes('poultry');
                
                if (requiresVisitation) {
                    // Show visitation section and set default date
                    visitationSection.classList.remove('hidden');
                    visitationIndicator.classList.remove('hidden');
                    
                    // Make visitation date required
                    visitationDate.setAttribute('required', 'required');
                    
                    // Set default visitation date (7 days after distribution)
                    const dateGivenField = document.querySelector('input[name="date_given"]');
                    const distributionDate = (dateGivenField && dateGivenField.value) ? dateGivenField.value : new Date().toISOString().split('T')[0];
                    const visitationDateObj = new Date(distributionDate);
                    visitationDateObj.setDate(visitationDateObj.getDate() + 7);
                    visitationDate.value = visitationDateObj.toISOString().split('T')[0];
                } else {
                    // Hide visitation section - not required
                    visitationSection.classList.add('hidden');
                    visitationIndicator.classList.add('hidden');
                    
                    // Remove required attribute since it's not needed
                    visitationDate.removeAttribute('required');
                    visitationDate.value = ''; // Clear the field since it will be set to NULL on backend
                }
            }
        }

        // Farmer autocomplete functionality
        let farmers = [];
        let selectedFarmerIndex = -1;

        // Load farmers data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadFarmers();
        });

        function loadFarmers() {
            fetch('get_farmers.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Ensure data is an array, even if empty
                    farmers = Array.isArray(data) ? data : [];
                })
                .catch(error => {
                    console.error('Error loading farmers:', error);
                    farmers = []; // Set to empty array on error
                });
        }

        function searchFarmers(query) {
            const suggestions = document.getElementById('farmer_suggestions');
            const selectedFarmerField = document.getElementById('selected_farmer_id');
            
            if (!suggestions) return; // Exit if element doesn't exist
            
            if (!query || query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                if (selectedFarmerField) selectedFarmerField.value = '';
                return;
            }

            // Ensure farmers array exists and is not empty
            if (!Array.isArray(farmers) || farmers.length === 0) {
                suggestions.innerHTML = '<div class="px-3 py-2 text-orange-500">No farmers registered yet. Please register farmers first.</div>';
                suggestions.classList.remove('hidden');
                return;
            }

            const filteredFarmers = farmers.filter(farmer => {
                if (!farmer || !farmer.first_name || !farmer.last_name) return false;
                const fullName = `${farmer.first_name} ${farmer.last_name}`.toLowerCase();
                return fullName.includes(query.toLowerCase());
            });

            if (filteredFarmers.length > 0) {
                let html = '';
                filteredFarmers.forEach((farmer, index) => {
                    const fullName = `${farmer.first_name || ''} ${farmer.last_name || ''}`.trim();
                    const farmerId = farmer.farmer_id || '';
                    html += `<div class="suggestion-item px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100" 
                                  onclick="selectFarmer('${farmerId}', '${fullName}')" 
                                  data-index="${index}">
                                <div class="font-medium">${fullName}</div>
                                <div class="text-sm text-gray-600">ID: ${farmerId}</div>
                             </div>`;
                });
                suggestions.innerHTML = html;
                suggestions.classList.remove('hidden');
            } else {
                suggestions.innerHTML = '<div class="px-3 py-2 text-gray-500">No farmers found matching your search</div>';
                suggestions.classList.remove('hidden');
            }
        }

        function selectFarmer(farmerId, farmerName) {
            const farmerNameField = document.getElementById('farmer_name');
            const selectedFarmerField = document.getElementById('selected_farmer_id');
            const suggestions = document.getElementById('farmer_suggestions');
            
            if (farmerNameField) farmerNameField.value = farmerName || '';
            if (selectedFarmerField) selectedFarmerField.value = farmerId || '';
            if (suggestions) suggestions.classList.add('hidden');
        }

        function showSuggestions() {
            const query = document.getElementById('farmer_name').value;
            if (query.length > 0) {
                searchFarmers(query);
            }
        }

        function hideSuggestions() {
            // Delay hiding to allow click events on suggestions
            setTimeout(() => {
                document.getElementById('farmer_suggestions').classList.add('hidden');
            }, 200);
        }

        // Form validation functions
        function validateDistributeForm() {
            const inputId = document.getElementById('selected_input_id');
            const farmerId = document.getElementById('selected_farmer_id');
            const quantity = document.getElementById('quantity_distributed');
            const dateGiven = document.querySelector('input[name="date_given"]');
            const visitationDate = document.getElementById('visitation_date');
            
            // Check if input is selected
            if (!inputId || !inputId.value) {
                alert('Please select an input to distribute.');
                return false;
            }
            
            // Check if farmer is selected
            if (!farmerId || !farmerId.value) {
                alert('Please select a farmer.');
                return false;
            }
            
            // Check quantity
            if (!quantity || !quantity.value || parseInt(quantity.value) <= 0) {
                alert('Please enter a valid quantity to distribute.');
                return false;
            }
            
            // Check if quantity exceeds available stock
            const maxQuantity = parseInt(quantity.max) || 0;
            if (parseInt(quantity.value) > maxQuantity) {
                alert(`Quantity cannot exceed available stock (${maxQuantity}).`);
                return false;
            }
            
            // Check date given
            if (!dateGiven || !dateGiven.value) {
                alert('Please select a distribution date.');
                return false;
            }
            
            // Check visitation date only if visitation section is visible (meaning it's required)
            const visitationSection = document.getElementById('visitation_section');
            if (visitationSection && !visitationSection.classList.contains('hidden')) {
                if (!visitationDate || !visitationDate.value) {
                    alert('Visitation date is required for this type of input.');
                    return false;
                }
            }
            
            return true;
        }
        
        function validateUpdateForm() {
            const inputId = document.getElementById('update_input_id');
            const newQuantity = document.getElementById('new_quantity');
            
            // Check if input is selected
            if (!inputId || !inputId.value) {
                alert('Please select an input to update.');
                return false;
            }
            
            // Check quantity
            if (!newQuantity || !newQuantity.value || parseInt(newQuantity.value) < 0) {
                alert('Please enter a valid quantity (0 or more).');
                return false;
            }
            
            return true;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to distribute buttons
            const distributeButtons = document.querySelectorAll('.distribute-btn');
            
            // Add event listeners to update buttons
            const updateButtons = document.querySelectorAll('.update-btn');
            
            // Add event listeners to distribute buttons
            distributeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const inputId = this.getAttribute('data-input-id');
                    const inputName = this.getAttribute('data-input-name');
                    const quantity = this.getAttribute('data-quantity');
                    distributeInput(inputId, inputName, quantity);
                });
            });
            
            // Add event listeners to update buttons
            updateButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const inputId = this.getAttribute('data-input-id');
                    const inputName = this.getAttribute('data-input-name');
                    const quantity = this.getAttribute('data-quantity');
                    updateStock(inputId, inputName, quantity);
                });
            });
        });

        // Function to show success message
        function showSuccessMessage(message) {
            const messageContainer = document.getElementById('messageContainer');
            const successMessage = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            
            successText.textContent = ' ' + message;
            successMessage.style.display = 'flex';
            messageContainer.style.display = 'block';
            
            // Scroll to top to show message
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                closeMessage('successMessage');
            }, 5000);
        }

        // Function to show error message
        function showErrorMessage(message) {
            const messageContainer = document.getElementById('messageContainer');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            errorText.textContent = ' ' + message;
            errorMessage.style.display = 'flex';
            messageContainer.style.display = 'block';
            
            // Scroll to top to show message
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Function to close message
        function closeMessage(messageId) {
            const message = document.getElementById(messageId);
            const messageContainer = document.getElementById('messageContainer');
            
            message.style.display = 'none';
            
            // Hide container if no messages are visible
            const successVisible = document.getElementById('successMessage').style.display !== 'none';
            const errorVisible = document.getElementById('errorMessage').style.display !== 'none';
            
            if (!successVisible && !errorVisible) {
                messageContainer.style.display = 'none';
            }
        }

        // Show success/error messages using HTML notifications
        <?php if (isset($_SESSION['success'])): ?>
            showSuccessMessage(<?php echo json_encode($_SESSION['success']); ?>);
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            showErrorMessage(<?php echo json_encode($_SESSION['error']); ?>);
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Include Notification System -->
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
