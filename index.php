<?php
require_once 'conn.php';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] == 'register_farmer') {
        // Generate unique farmer ID
        $farmer_id = 'FRM' . date('Ymd') . sprintf('%04d', rand(1, 9999));
        
        // Check if farmer ID already exists
        $check_query = "SELECT farmer_id FROM farmers WHERE farmer_id = '$farmer_id'";
        $check_result = mysqli_query($conn, $check_query);
        while (mysqli_num_rows($check_result) > 0) {
            $farmer_id = 'FRM' . date('Ymd') . sprintf('%04d', rand(1, 9999));
            $check_result = mysqli_query($conn, $check_query);
        }
        
        // Sanitize input data
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $barangay = mysqli_real_escape_string($conn, $_POST['barangay']);
        $address_details = mysqli_real_escape_string($conn, $_POST['address_details']);
        $rsbsa_registered = mysqli_real_escape_string($conn, $_POST['rsbsa_registered']);
        
        // Start transaction
        mysqli_autocommit($conn, FALSE);
        $success = true;
        $error_message = '';
        
        try {
            // Insert into farmers table
            $farmer_query = "INSERT INTO farmers (
                farmer_id, first_name, middle_name, last_name, birth_date, 
                gender, contact_number, barangay, address_details
            ) VALUES (
                '$farmer_id', '$first_name', '$middle_name', '$last_name', '$birth_date',
                '$gender', '$contact_number', '$barangay', '$address_details'
            )";
            
            $farmer_result = mysqli_query($conn, $farmer_query);
            
            if (!$farmer_result) {
                throw new Exception("Error inserting farmer: " . mysqli_error($conn));
            }
            
            // If RSBSA registered, insert into rsbsa_registered_farmers table
            if ($rsbsa_registered == 'Yes' && !empty($_POST['rsbsa_id'])) {
                $rsbsa_id = mysqli_real_escape_string($conn, $_POST['rsbsa_id']);
                
                // Check if RSBSA ID already exists
                $rsbsa_check_query = "SELECT rsbsa_id FROM rsbsa_registered_farmers WHERE rsbsa_id = '$rsbsa_id'";
                $rsbsa_check_result = mysqli_query($conn, $rsbsa_check_query);
                
                if (mysqli_num_rows($rsbsa_check_result) > 0) {
                    throw new Exception("RSBSA ID already exists in the system.");
                }
                
                $rsbsa_query = "INSERT INTO rsbsa_registered_farmers (
                    farmer_id, rsbsa_id
                ) VALUES (
                    '$farmer_id', '$rsbsa_id'
                )";
                
                $rsbsa_result = mysqli_query($conn, $rsbsa_query);
                
                if (!$rsbsa_result) {
                    throw new Exception("Error inserting RSBSA registration: " . mysqli_error($conn));
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            $success_message = "Farmer registered successfully! Farmer ID: " . $farmer_id;
            
        } catch (Exception $e) {
            // Rollback transaction
            mysqli_rollback($conn);
            $success = false;
            $error_message = $e->getMessage();
        }
        
        // Re-enable autocommit
        mysqli_autocommit($conn, TRUE);
        
        // Set session messages for display
        session_start();
        if ($success) {
            $_SESSION['success_message'] = $success_message;
        } else {
            $_SESSION['error_message'] = $error_message;
        }
        
        // Redirect to prevent form resubmission
        header("Location: index.php");
        exit();
    }
}

// Get session messages
session_start();
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Initialize default values
$total_farmers = $rsbsa_registered = $total_commodities = $recent_yields = 0;
$recent_activities = [];

// Get dashboard statistics using procedural MySQL
// Count total farmers
$query = "SELECT COUNT(*) as total_farmers FROM farmers";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_farmers = $row['total_farmers'];
}

// Count RSBSA registered farmers
$query = "SELECT COUNT(*) as rsbsa_registered FROM rsbsa_registered_farmers";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $rsbsa_registered = $row['rsbsa_registered'];
}

// Count total commodities
$query = "SELECT COUNT(*) as total_commodities FROM commodities";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $total_commodities = $row['total_commodities'];
}

// Count recent yield records (last 30 days)
$query = "SELECT COUNT(*) as recent_yields FROM yield_monitoring WHERE record_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$result = mysqli_query($conn, $query);
if ($result && $row = mysqli_fetch_assoc($result)) {
    $recent_yields = $row['recent_yields'];
}

// Get recent activities (last 5 yield records)
$query = "
    SELECT ym.*, f.first_name, f.last_name, c.commodity_name, s.first_name as staff_first_name, s.last_name as staff_last_name
    FROM yield_monitoring ym
    JOIN farmers f ON ym.farmer_id = f.farmer_id
    JOIN commodities c ON ym.commodity_id = c.commodity_id
    JOIN mao_staff s ON ym.recorded_by_staff_id = s.staff_id
    ORDER BY ym.record_date DESC
    LIMIT 5
";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agricultural Management System - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-light': '#dcfce7',
                        'agri-dark': '#15803d'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-agri-green shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <i class="fas fa-seedling text-white text-2xl mr-3"></i>
                    <h1 class="text-white text-xl font-bold">Agricultural Management System</h1>
                </div>
                <div class="flex items-center space-x-4">
                    
                    <button class="text-white hover:text-agri-light transition-colors">
                        <i class="fas fa-bell text-lg"></i>
                    </button>
                    <div class="flex items-center text-white">
                        <i class="fas fa-user-circle text-lg mr-2"></i>
                        <span>Admin User</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show m-4" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show m-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="bg-gradient-to-r from-agri-green to-agri-dark rounded-lg shadow-md p-6 mb-8 text-white">
            <h2 class="text-3xl font-bold mb-2">Welcome to Agricultural Management</h2>
            <p class="text-agri-light">Monitor farmers, track yields, and manage agricultural inputs efficiently</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Farmers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_farmers); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-certificate text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">RSBSA Registered</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($rsbsa_registered); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-wheat-awn text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Commodities</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_commodities); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Recent Yields</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($recent_yields); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions and Recent Activities -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-bolt text-agri-green mr-2"></i>Quick Actions
                </h3>
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="openFarmerModal()" class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors cursor-pointer">
                        <i class="fas fa-user-plus text-2xl text-blue-600 mb-2"></i>
                        <span class="text-sm font-medium text-blue-800">Add Farmer</span>
                    </button>
                    
                    <button onclick="navigateTo('rsbsa_registration.php')" class="flex flex-col items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors cursor-pointer">
                        <i class="fas fa-certificate text-2xl text-green-600 mb-2"></i>
                        <span class="text-sm font-medium text-green-800">Commodity Inventory</span>
                    </button>
                    
                    <button onclick="navigateTo('input_distribution.php')" class="flex flex-col items-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition-colors cursor-pointer">
                        <i class="fas fa-boxes text-2xl text-yellow-600 mb-2"></i>
                        <span class="text-sm font-medium text-yellow-800">Distribute Inputs</span>
                    </button>
                    
                    <button onclick="navigateTo('yield_monitoring.php')" class="flex flex-col items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors cursor-pointer">
                        <i class="fas fa-chart-bar text-2xl text-purple-600 mb-2"></i>
                        <span class="text-sm font-medium text-purple-800">Record Yield</span>
                    </button>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                    <i class="fas fa-clock text-agri-green mr-2"></i>Recent Activities
                </h3>
                <div class="space-y-4">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-gray-500 text-center py-4">No recent activities found</p>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                                <div class="p-2 bg-green-100 rounded-full mr-3">
                                    <i class="fas fa-seedling text-green-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-600">
                                        Recorded <?php echo htmlspecialchars($activity['yield_volume']); ?> <?php echo htmlspecialchars($activity['unit_of_yield']); ?> 
                                        of <?php echo htmlspecialchars($activity['commodity_name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?php echo date('M j, Y', strtotime($activity['record_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-th-large text-agri-green mr-2"></i>System Modules
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <a href="farmers.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-users text-blue-600 text-xl mr-3"></i>
                    <span class="font-medium">Farmers Management</span>
                </a>
                
                <a href="rsbsa.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-certificate text-green-600 text-xl mr-3"></i>
                    <span class="font-medium">RSBSA Records</span>
                </a>
                
                <a href="commodities.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-wheat-awn text-yellow-600 text-xl mr-3"></i>
                    <span class="font-medium">Manage Commodities</span>
                </a>
                
                <a href="inputs.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-boxes text-orange-600 text-xl mr-3"></i>
                    <span class="font-medium">Input Distribution</span>
                </a>
                
                <a href="yields.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-chart-bar text-purple-600 text-xl mr-3"></i>
                    <span class="font-medium">Yield Monitoring</span>
                </a>
                
                <a href="staff.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-user-tie text-indigo-600 text-xl mr-3"></i>
                    <span class="font-medium">MAO Staff</span>
                </a>
                
                <a href="reports.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-file-alt text-red-600 text-xl mr-3"></i>
                    <span class="font-medium">Reports</span>
                </a>
                
                <a href="settings.php" class="flex items-center p-4 border rounded-lg hover:border-agri-green hover:shadow-md transition-all">
                    <i class="fas fa-cog text-gray-600 text-xl mr-3"></i>
                    <span class="font-medium">Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Include the farmer registration modal -->
    <?php include 'farmer_regmodal.php'; ?>

    <script>
        function navigateTo(url) {
            window.location.href = url;
        }

        function openFarmerModal() {
            const modal = new bootstrap.Modal(document.getElementById('farmerRegistrationModal'));
            modal.show();
        }

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics on load
            const stats = document.querySelectorAll('.text-2xl.font-bold');
            stats.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                let currentValue = 0;
                const increment = finalValue / 30;
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(currentValue).toLocaleString();
                }, 50);
            });

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>