<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

$pageTitle = 'Yield Monitoring';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

// Future database queries will go here
// Example queries (commented out for now):
// $visits_query = "SELECT * FROM yield_visits ORDER BY visit_date DESC";
// $stats_query = "SELECT COUNT(*) as total_visits, SUM(yield_amount) as total_yield FROM yield_visits";

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
        
        .yield-card {
            transition: all 0.3s ease;
        }
        .yield-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .status-completed {
            border-left: 4px solid #16a34a;
        }
        .status-pending {
            border-left: 4px solid #d97706;
        }
        .status-overdue {
            border-left: 4px solid #dc2626;
        }
        .status-scheduled {
            border-left: 4px solid #2563eb;
        }
        
        /* Filter tabs */
        .filter-tab {
            transition: all 0.2s ease;
        }
        .filter-tab.active {
            background-color: #16a34a;
            color: white;
        }
        .filter-tab:not(.active):hover {
            background-color: #f3f4f6;
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

        // Function to toggle custom date range
        function toggleCustomDateRange() {
            const dateRangeSelect = document.getElementById('dateRangeSelect');
            const customDateRange = document.getElementById('customDateRange');
            
            if (dateRangeSelect.value === 'custom') {
                customDateRange.classList.remove('hidden');
            } else {
                customDateRange.classList.add('hidden');
                // Clear the date inputs when hiding
                document.getElementById('fromDate').value = '';
                document.getElementById('toDate').value = '';
            }
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
                            <i class="fas fa-chart-line text-agri-green mr-3"></i>
                            Yield Monitoring
                        </h1>
                        <p class="text-gray-600 mt-2">Track and monitor agricultural yield from distributed inputs</p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" onclick="openModal('addVisitModal')">
                            <i class="fas fa-plus mr-2"></i>Record Visit
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

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Visits -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-eye text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">0</h3>
                            <p class="text-gray-600">Total Visits</p>
                        </div>
                    </div>
                </div>

                <!-- Fry/Fingerlings -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-cyan-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-cyan-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-fish text-cyan-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">0</h3>
                            <p class="text-gray-600">Fry/Fingerlings</p>
                        </div>
                    </div>
                </div>

                <!-- Poultry & Livestock -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-paw text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">0</h3>
                            <p class="text-gray-600">Poultry & Livestock</p>
                        </div>
                    </div>
                </div>

                <!-- Average Yield -->
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-agri-green">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-seedling text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">0 sacks</h3>
                            <p class="text-gray-600">Average Yield</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-wrap gap-2">
                    <button class="filter-tab active px-4 py-2 rounded-lg font-medium" data-filter="all">
                        <i class="fas fa-list mr-2"></i>All Visits
                    </button>
                    <button class="filter-tab px-4 py-2 rounded-lg font-medium" data-filter="seeds">
                        <i class="fas fa-seedling mr-2"></i>Seeds
                    </button>
                    <button class="filter-tab px-4 py-2 rounded-lg font-medium" data-filter="livestock">
                        <i class="fas fa-horse mr-2"></i>Livestock
                    </button>
                    <button class="filter-tab px-4 py-2 rounded-lg font-medium" data-filter="fry">
                        <i class="fas fa-fish mr-2"></i>Fry/Fingerlings
                    </button>
                    <button class="filter-tab px-4 py-2 rounded-lg font-medium" data-filter="chicken">
                        <i class="fas fa-egg mr-2"></i>Chicken
                    </button>
                    <button class="filter-tab px-4 py-2 rounded-lg font-medium" data-filter="tools">
                        <i class="fas fa-tools mr-2"></i>Agricultural Tools
                    </button>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Farmer</label>
                        <div class="relative">
                            <input type="text" class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" placeholder="Search by farmer name...">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                        <select id="dateRangeSelect" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" onchange="toggleCustomDateRange()">
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                </div>
                
                <!-- Custom Date Range Section (Hidden by default) -->
                <div id="customDateRange" class="hidden mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" id="fromDate" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" id="toDate" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green">
                    </div>
                </div>
            </div>

            <!-- Yield Monitoring Content -->
            <!-- Empty State - Ready for Database Data -->
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="text-gray-500">
                    <i class="fas fa-chart-line text-6xl mb-6 text-gray-300"></i>
                    <h3 class="text-2xl font-semibold mb-4 text-gray-700">No Yield Data Available</h3>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">
                        Start monitoring agricultural yields by recording visits and tracking farmer progress. 
                        Data will appear here once you begin adding visit records.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Visit Record Modal -->
    <div id="addVisitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[95vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <h3 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-clipboard-check text-agri-green mr-3"></i>Record Yield Visit
                </h3>
                <p class="text-sm text-gray-600 mt-1">Document yield results and farmer progress</p>
            </div>
            <form method="POST" action="record_yield.php">
                <div class="px-6 py-6">
                    <!-- Visit Information Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="farmer_name_yield" class="block text-sm font-medium text-gray-700 mb-2">Select Farmer</label>
                            <div class="relative">
                                <input type="text" 
                                       class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" 
                                       id="farmer_name_yield" 
                                       placeholder="Type farmer name..."
                                       autocomplete="off"
                                       required
                                       onkeyup="searchFarmersYield(this.value)"
                                       onfocus="showSuggestionsYield()"
                                       onblur="hideSuggestionsYield()">
                                <input type="hidden" name="farmer_id" id="selected_farmer_id_yield" required>
                                
                                <!-- Suggestions dropdown -->
                                <div id="farmer_suggestions_yield" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-60 overflow-y-auto hidden">
                                    <!-- Suggestions will be populated here -->
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">Start typing to search for farmers</p>
                        </div>
                        <div>
                            <label for="input_select" class="block text-sm font-medium text-gray-700 mb-2">Distributed Input</label>
                            <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="input_id" required>
                                <option value="">Select input type...</option>
                                <!-- Options will be populated based on farmer selection -->
                            </select>
                        </div>
                    </div>
                    
                    <!-- Visit Details -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label for="visit_date" class="block text-sm font-medium text-gray-700 mb-2">Visit Date</label>
                            <input type="date" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label for="yield_amount" class="block text-sm font-medium text-gray-700 mb-2">Yield Amount</label>
                            <input type="number" step="0.1" class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="yield_amount" placeholder="0.0" required>
                        </div>
                        <div>
                            <label for="yield_unit" class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                            <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="yield_unit" required>
                                <option value="">Select unit...</option>
                                <option value="sacks">Sacks</option>
                                <option value="kilograms">Kilograms</option>
                                <option value="tons">Tons</option>
                                <option value="pieces">Pieces</option>
                                <option value="heads">Heads</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Quality and Assessment -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="quality_grade" class="block text-sm font-medium text-gray-700 mb-2">Quality Grade</label>
                            <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="quality_grade" required>
                                <option value="">Select grade...</option>
                                <option value="Grade A">Grade A - Excellent</option>
                                <option value="Grade B">Grade B - Good</option>
                                <option value="Grade C">Grade C - Fair</option>
                                <option value="Grade D">Grade D - Poor</option>
                            </select>
                        </div>
                        <div>
                            <label for="growth_stage" class="block text-sm font-medium text-gray-700 mb-2">Growth Stage</label>
                            <select class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="growth_stage">
                                <option value="">Select stage...</option>
                                <option value="Seedling">Seedling</option>
                                <option value="Vegetative">Vegetative</option>
                                <option value="Flowering">Flowering</option>
                                <option value="Fruiting">Fruiting</option>
                                <option value="Mature">Mature</option>
                                <option value="Harvested">Harvested</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Conditions and Notes -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Field Conditions</label>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="conditions[]" value="good_weather" class="mr-2">
                                <span class="text-sm">Good Weather</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="conditions[]" value="adequate_water" class="mr-2">
                                <span class="text-sm">Adequate Water</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="conditions[]" value="pest_issues" class="mr-2">
                                <span class="text-sm">Pest Issues</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="conditions[]" value="disease_present" class="mr-2">
                                <span class="text-sm">Disease Present</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="visit_notes" class="block text-sm font-medium text-gray-700 mb-2">Visit Notes</label>
                        <textarea class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="visit_notes" rows="4" placeholder="Record observations, recommendations, and any issues noted during the visit..."></textarea>
                    </div>
                    
                    <!-- Recommendations -->
                    <div class="mb-6">
                        <label for="recommendations" class="block text-sm font-medium text-gray-700 mb-2">Recommendations</label>
                        <textarea class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-agri-green focus:border-agri-green" name="recommendations" rows="3" placeholder="Provide recommendations for improvement or next steps..."></textarea>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-4 rounded-b-lg sticky bottom-0 border-t border-gray-200 z-10">
                    <button type="button" class="px-6 py-3 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors font-medium" onclick="closeModal('addVisitModal')">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-agri-green text-white rounded-lg hover:bg-agri-dark transition-colors font-medium">
                        <i class="fas fa-save mr-2"></i>Record Visit
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
            const modals = ['addVisitModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        });

        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterTabs = document.querySelectorAll('.filter-tab');
            
            filterTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    filterTabs.forEach(t => t.classList.remove('active'));
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Here you would implement the actual filtering logic
                    // For now, we'll just show console log
                    const filter = this.getAttribute('data-filter');
                                
            console.log('Filtering by:', filter);
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

        // Farmer auto-suggestion functionality for yield modal
        let farmersYield = [];

        // Load farmers data when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadFarmersYield();
        });

        function loadFarmersYield() {
            fetch('get_farmers.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Ensure data is an array, even if empty
                    farmersYield = Array.isArray(data) ? data : [];
                })
                .catch(error => {
                    console.error('Error loading farmers:', error);
                    farmersYield = []; // Set to empty array on error
                });
        }

        function searchFarmersYield(query) {
            const suggestions = document.getElementById('farmer_suggestions_yield');
            const selectedFarmerField = document.getElementById('selected_farmer_id_yield');
            
            if (!suggestions) return; // Exit if element doesn't exist
            
            if (!query || query.length < 1) {
                suggestions.innerHTML = '';
                suggestions.classList.add('hidden');
                if (selectedFarmerField) selectedFarmerField.value = '';
                return;
            }

            // Ensure farmers array exists and is not empty
            if (!Array.isArray(farmersYield) || farmersYield.length === 0) {
                suggestions.innerHTML = '<div class="px-3 py-2 text-orange-500">No farmers registered yet. Please register farmers first.</div>';
                suggestions.classList.remove('hidden');
                return;
            }

            const filteredFarmers = farmersYield.filter(farmer => {
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
                                  onclick="selectFarmerYield('${farmerId}', '${fullName}')" 
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

        function selectFarmerYield(farmerId, farmerName) {
            const farmerNameField = document.getElementById('farmer_name_yield');
            const selectedFarmerField = document.getElementById('selected_farmer_id_yield');
            const suggestions = document.getElementById('farmer_suggestions_yield');
            
            if (farmerNameField) farmerNameField.value = farmerName || '';
            if (selectedFarmerField) selectedFarmerField.value = farmerId || '';
            if (suggestions) suggestions.classList.add('hidden');
        }

        function showSuggestionsYield() {
            const query = document.getElementById('farmer_name_yield').value;
            if (query.length > 0) {
                searchFarmersYield(query);
            }
        }

        function hideSuggestionsYield() {
            // Delay hiding to allow click events on suggestions
            setTimeout(() => {
                const suggestions = document.getElementById('farmer_suggestions_yield');
                if (suggestions) suggestions.classList.add('hidden');
            }, 200);
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
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
