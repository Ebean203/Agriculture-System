<?php
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle report saving (from generated report)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_generated_report') {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $report_content = $_POST['report_content'];
    
    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "report_{$report_type}_{$timestamp}.html";
    $file_path = "reports/{$filename}";
    
    // Create reports directory if it doesn't exist
    if (!is_dir('reports')) {
        mkdir('reports', 0755, true);
    }
    
    // Save report file
    file_put_contents($file_path, $report_content);
    
    // Check if generated_reports table exists and has correct structure
    $table_check = $conn->query("SHOW TABLES LIKE 'generated_reports'");
    if ($table_check->num_rows == 0) {
        // Create the table if it doesn't exist
        $create_table_sql = "CREATE TABLE `generated_reports` (
            `report_id` int(11) NOT NULL AUTO_INCREMENT,
            `report_type` varchar(100) NOT NULL,
            `start_date` date NOT NULL,
            `end_date` date NOT NULL,
            `file_path` varchar(255) NOT NULL,
            `staff_id` int(11) NOT NULL,
            `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`report_id`),
            KEY `staff_id` (`staff_id`),
            CONSTRAINT `generated_reports_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `mao_staff` (`staff_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        $conn->query($create_table_sql);
    }
    
    // Save to database
    try {
        $stmt = $conn->prepare("INSERT INTO generated_reports (report_type, start_date, end_date, file_path, staff_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $report_type, $start_date, $end_date, $file_path, $_SESSION['user_id']);
        $stmt->execute();
        
        // Log activity
        $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, details) VALUES (?, ?, 'farmer', ?)");
        $action = "Saved {$report_type} report";
        $details = "Report Period: {$start_date} to {$end_date}, File: {$filename}";
        $stmt_log->bind_param("iss", $_SESSION['user_id'], $action, $details);
        $stmt_log->execute();
        
        echo json_encode(['success' => true, 'message' => 'Report saved successfully!', 'filename' => $filename]);
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving report: ' . $e->getMessage()]);
    }
    exit;
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $save_report = isset($_POST['save_report']) ? 1 : 0;
    
    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "report_{$report_type}_{$timestamp}.html";
    $file_path = "reports/{$filename}";
    
    // Create reports directory if it doesn't exist
    if (!is_dir('reports')) {
        mkdir('reports', 0755, true);
    }
    
    // Generate report content based on type
    $report_content = generateReportContent($report_type, $start_date, $end_date, $conn, $save_report);
    
    // If save_report is checked, save to database and file
    if ($save_report) {
        // Save report file
        file_put_contents($file_path, $report_content);
        
        // Check if generated_reports table exists and has correct structure
        $table_check = $conn->query("SHOW TABLES LIKE 'generated_reports'");
        if ($table_check->num_rows == 0) {
            // Create the table if it doesn't exist
            $create_table_sql = "CREATE TABLE `generated_reports` (
                `report_id` int(11) NOT NULL AUTO_INCREMENT,
                `report_type` varchar(100) NOT NULL,
                `start_date` date NOT NULL,
                `end_date` date NOT NULL,
                `file_path` varchar(255) NOT NULL,
                `staff_id` int(11) NOT NULL,
                `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`report_id`),
                KEY `staff_id` (`staff_id`),
                CONSTRAINT `generated_reports_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `mao_staff` (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $conn->query($create_table_sql);
        }
        
        // Save to database
        try {
            $stmt = $conn->prepare("INSERT INTO generated_reports (report_type, start_date, end_date, file_path, staff_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $report_type, $start_date, $end_date, $file_path, $_SESSION['user_id']);
            $stmt->execute();
            
            // Log activity
            $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action, action_type, details) VALUES (?, ?, 'farmer', ?)");
            $action = "Generated and saved {$report_type} report";
            $details = "Report Period: {$start_date} to {$end_date}, File: {$filename}";
            $stmt_log->bind_param("iss", $_SESSION['user_id'], $action, $details);
            $stmt_log->execute();
            
            $success_message = "Report generated and saved successfully!";
        } catch (mysqli_sql_exception $e) {
            // If there's still an error, try to describe the table structure
            $columns = $conn->query("DESCRIBE generated_reports");
            $error_details = "Database error: " . $e->getMessage() . "\nTable structure: ";
            while ($col = $columns->fetch_assoc()) {
                $error_details .= $col['Field'] . " (" . $col['Type'] . "), ";
            }
            error_log($error_details);
            $success_message = "Report generated but could not be saved to database: " . $e->getMessage();
        }
    }
    
    // Output report for viewing/printing
    echo $report_content;
    exit;
}

// Function to generate report content
function generateReportContent($report_type, $start_date, $end_date, $conn, $save_report = false) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #16a34a;
            padding-bottom: 15px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            color: #16a34a;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 16px;
            color: #15803d;
            margin-bottom: 5px;
        }
        .report-info {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #15803d, #166534);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        .btn-secondary:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-success {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
        }
        .btn-success:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .save-status {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: bold;
        }
        .save-success {
            background-color: #dcfce7;
            color: #166534;
            border: 2px solid #16a34a;
        }
        .save-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px;
        }
        th {
            background-color: #16a34a;
            color: white;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
            font-size: 10px;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
        }
        tr:nth-child(even) {
            background-color: #dcfce7;
        }
        .summary-box {
            background-color: #f0fdf4;
            border: 2px solid #16a34a;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .summary-item {
            margin: 8px 0;
            font-size: 14px;
        }
        .summary-label {
            font-weight: bold;
            color: #15803d;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }
        @media print {
            .no-print { display: none !important; }
            
            /* Complete URL hiding for all browsers */
            @page {
                size: A4;
                margin: 0.75in 0.5in;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                /* Remove all browser-generated content */
                @top-left { content: ""; display: none; }
                @top-center { content: ""; display: none; }
                @top-right { content: ""; display: none; }
                @bottom-left { content: ""; display: none; }
                @bottom-center { content: ""; display: none; }
                @bottom-right { content: ""; display: none; }
                /* Additional margin adjustments to prevent URL space */
                margin-top: 0.5in !important;
                margin-bottom: 0.5in !important;
            }
            
            /* Force hide URL in all browsers */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Remove browser headers/footers completely */
            html {
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            body {
                margin: 0 !important;
                padding: 0 !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
            }
            
            /* Hide browser URL bar content */
            head, title {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Override any browser default print styles */
            html, body, * {
                box-sizing: border-box !important;
                -webkit-box-sizing: border-box !important;
                -moz-box-sizing: border-box !important;
            }
            
            /* Alternative for Legal size - uncomment if needed */
            /* @page { size: legal; margin: 0.75in 0.5in; } */
            
            body { 
                margin: 0;
                padding: 0;
                font-size: 10px;
                line-height: 1.3;
                background: white !important;
            }
            
            .header { 
                page-break-after: avoid;
                margin-bottom: 15px;
                border-bottom: 1px solid #16a34a !important;
                padding-bottom: 10px;
            }
            
            .title {
                font-size: 18px !important;
                margin-bottom: 5px;
            }
            
            .subtitle {
                font-size: 12px !important;
                margin-bottom: 3px;
            }
            
            .report-info {
                font-size: 9px !important;
                margin-bottom: 2px;
            }
            
            table { 
                page-break-inside: auto;
                margin-top: 10px;
                width: 100%;
                font-size: 8px !important;
                border-collapse: collapse;
            }
            
            thead {
                display: table-header-group;
            }
            
            tbody {
                display: table-row-group;
            }
            
            th {
                background-color: #16a34a !important;
                color: white !important;
                padding: 4px 3px !important;
                font-size: 7px !important;
                border: 0.5px solid #ddd !important;
                page-break-after: avoid;
            }
            
            td {
                padding: 3px 2px !important;
                font-size: 7px !important;
                border: 0.5px solid #ddd !important;
                word-wrap: break-word;
                max-width: 0;
                overflow: hidden;
            }
            
            tr { 
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            tr:nth-child(even) {
                background-color: #f8f9fa !important;
            }
            
            .summary-box {
                background-color: #f0fdf4 !important;
                border: 1px solid #16a34a !important;
                border-radius: 4px;
                padding: 8px;
                margin: 8px 0;
                page-break-inside: avoid;
                font-size: 9px;
            }
            
            .summary-box h3 {
                font-size: 11px !important;
                margin-top: 0;
                margin-bottom: 5px;
            }
            
            .summary-item {
                margin: 3px 0;
                font-size: 8px !important;
            }
            
            .summary-label {
                font-weight: bold;
                color: #15803d !important;
            }
            
            .footer {
                margin-top: 15px;
                text-align: center;
                font-size: 7px !important;
                color: #6b7280 !important;
                border-top: 0.5px solid #d1d5db !important;
                padding-top: 5px;
                page-break-inside: avoid;
            }
            
            h3 {
                font-size: 12px !important;
                margin: 8px 0 5px 0;
                page-break-after: avoid;
                color: #15803d !important;
            }
            
            p {
                font-size: 8px !important;
                margin: 3px 0;
            }
            
            /* Ensure content fits within page boundaries */
            * {
                box-sizing: border-box;
            }
            
            /* Hide title elements */
            title { display: none !important; }
            
            /* Force page breaks for large tables */
            .large-table {
                page-break-before: auto;
            }
            
            /* Prevent orphaned headers */
            h1, h2, h3, h4, h5, h6 {
                page-break-after: avoid;
            }
            
            /* Style for different stock status in inventory reports */
            td[style*="background-color: #fee2e2"] {
                background-color: #fee2e2 !important;
                color: #991b1b !important;
            }
            
            td[style*="background-color: #fef3c7"] {
                background-color: #fef3c7 !important;
                color: #92400e !important;
            }
            
            td[style*="background-color: #dcfce7"] {
                background-color: #dcfce7 !important;
                color: #166534 !important;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function printReport() {
            try {
                // Remove any browser-generated URL display
                document.title = "";
                
                // Create comprehensive print CSS
                const printCSS = `
                    @page { 
                        size: A4; 
                        margin: 0.5in !important; 
                        @top-left { content: "" !important; }
                        @top-center { content: "" !important; }
                        @top-right { content: "" !important; }
                        @bottom-left { content: "" !important; }
                        @bottom-center { content: "" !important; }
                        @bottom-right { content: "" !important; }
                    }
                    @media print {
                        html, body { margin: 0 !important; padding: 0 !important; }
                        .no-print { display: none !important; }
                    }
                `;
                
                // Show paper size selection dialog
                const paperSize = prompt(
                    "Select paper size for printing:\\n" +
                    "1 - A4 (Default)\\n" +
                    "2 - Legal\\n" +
                    "3 - Letter\\n\\n" +
                    "Enter 1, 2, or 3:",
                    "1"
                );
                
                // If user cancels, use default A4
                if (paperSize === null) {
                    // User cancelled paper size selection, using default print
                    // Still apply URL hiding
                    const style = document.createElement("style");
                    style.textContent = printCSS;
                    document.head.appendChild(style);
                    window.print();
                    return;
                }
                
                // Selected paper size for printing
                
                // Apply paper size CSS with URL hiding
                let sizeRule = printCSS;
                switch(paperSize) {
                    case "2":
                        sizeRule += "@page { size: legal; margin: 0.5in !important; }";
                        break;
                    case "3":
                        sizeRule += "@page { size: letter; margin: 0.5in !important; }";
                        break;
                    default:
                        sizeRule += "@page { size: A4; margin: 0.5in !important; }";
                }
                
                // Apply CSS rules for printing
                
                // Create and inject custom print CSS
                const printStyle = document.createElement("style");
                printStyle.id = "custom-print-size";
                printStyle.textContent = sizeRule;
                
                // Remove existing custom print style if any
                const existing = document.getElementById("custom-print-size");
                if (existing) {
                    existing.remove();
                    // Removed existing print style
                }
                
                document.head.appendChild(printStyle);
                // Added new print style
                
                // Small delay to ensure CSS is applied
                setTimeout(() => {
                    // Triggering print dialog
                    window.print();
                }, 100);
                
            } catch (error) {
                console.error("Error in printReport function:", error);
                alert("Error occurred while trying to print. Using fallback print method.");
                window.print();
            }
        }
        
        function closeReport() {
            window.close();
        }
        
        function saveReport() {
            const reportData = {
                action: "save_generated_report",
                report_type: "' . $report_type . '",
                start_date: "' . $start_date . '",
                end_date: "' . $end_date . '",
                report_content: document.documentElement.outerHTML
            };
            
            // Show loading state
            const saveBtn = document.getElementById("saveReportBtn");
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = "<i class=\\"fas fa-spinner fa-spin\\"></i> Saving...";
            saveBtn.disabled = true;
            
            $.ajax({
                url: window.location.href.split("?")[0],
                method: "POST",
                data: reportData,
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        document.getElementById("saveStatus").innerHTML = 
                            "<div class=\\"save-success\\"><i class=\\"fas fa-check-circle\\"></i> " + response.message + "</div>";
                        saveBtn.style.display = "none"; // Hide save button after successful save
                    } else {
                        document.getElementById("saveStatus").innerHTML = 
                            "<div class=\\"save-error\\"><i class=\\"fas fa-exclamation-circle\\"></i> " + response.message + "</div>";
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }
                },
                error: function() {
                    document.getElementById("saveStatus").innerHTML = 
                        "<div class=\\"save-error\\"><i class=\\"fas fa-exclamation-circle\\"></i> Error saving report. Please try again.</div>";
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            });
        }
    </script>
</head>
<body>';
    
    // Add action buttons
    $html .= '<div class="no-print action-buttons">
        <button onclick="printReport()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
        <button onclick="closeReport()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>';
    
    // Add save button only if report was not already saved
    if (!$save_report) {
        $html .= '<button onclick="saveReport()" id="saveReportBtn" class="btn btn-success">
            <i class="fas fa-save"></i> Save Report
        </button>';
    }
    
    $html .= '</div>';
    
    // Add save status area
    $html .= '<div class="no-print" id="saveStatus"></div>';
    
    // Show saved status if report was already saved
    if ($save_report) {
        $html .= '<div class="no-print save-success">
            <i class="fas fa-check-circle"></i> This report has been saved to the database
        </div>';
    }
    
    // Header
    $html .= '<div class="header">
        <div class="title">' . ucfirst(str_replace('_', ' ', $report_type)) . ' Report</div>
        <div class="subtitle">Agricultural Management System</div>
        <div class="report-info">Report Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</div>
        <div class="report-info">Generated by: ' . htmlspecialchars($_SESSION['full_name']) . '</div>
    </div>';
    
    // Generate content based on report type
    switch ($report_type) {
        case 'farmers_summary':
            $html .= generateFarmersSummaryReport($start_date, $end_date, $conn);
            break;
        case 'input_distribution':
            $html .= generateInputDistributionReport($start_date, $end_date, $conn);
            break;
        case 'yield_monitoring':
            $html .= generateYieldMonitoringReport($start_date, $end_date, $conn);
            break;
        case 'inventory_status':
            $html .= generateInventoryStatusReport($conn);
            break;
        case 'barangay_analytics':
            $html .= generateBarangayAnalyticsReport($start_date, $end_date, $conn);
            break;
        case 'commodity_production':
            $html .= generateCommodityProductionReport($start_date, $end_date, $conn);
            break;
        case 'registration_analytics':
            $html .= generateRegistrationAnalyticsReport($start_date, $end_date, $conn);
            break;
        case 'comprehensive_overview':
            $html .= generateComprehensiveOverviewReport($start_date, $end_date, $conn);
            break;
        default:
            $html .= '<p>Invalid report type selected.</p>';
    }
    
    $html .= '<div class="footer">
        <p>Agricultural Management System - ' . ucfirst(str_replace('_', ' ', $report_type)) . ' Report</p>
        <p>This report contains data from ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</p>
        <p>Generated on: ' . date('n/j/y, g:i A') . '</p>
    </div>
</body>
</html>';
    
    return $html;
}

// Report generation functions
function generateFarmersSummaryReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Get summary statistics
    $total_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $male_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE gender = 'Male' AND registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $female_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE gender = 'Female' AND registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $total_land_area = $conn->query("SELECT COALESCE(SUM(land_area_hectares), 0) as total FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
    $avg_farming_years = $conn->query("SELECT COALESCE(AVG(years_farming), 0) as avg FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['avg'];
    
    // Summary box
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">📊 Farmers Registration Summary</h3>
        <div class="summary-item"><span class="summary-label">Total Farmers Registered:</span> ' . number_format($total_farmers) . '</div>
        <div class="summary-item"><span class="summary-label">Male Farmers:</span> ' . number_format($male_farmers) . ' (' . ($total_farmers > 0 ? round(($male_farmers/$total_farmers)*100, 1) : 0) . '%)</div>
        <div class="summary-item"><span class="summary-label">Female Farmers:</span> ' . number_format($female_farmers) . ' (' . ($total_farmers > 0 ? round(($female_farmers/$total_farmers)*100, 1) : 0) . '%)</div>
        <div class="summary-item"><span class="summary-label">Total Land Area:</span> ' . number_format($total_land_area, 2) . ' hectares</div>
        <div class="summary-item"><span class="summary-label">Average Farming Experience:</span> ' . number_format($avg_farming_years, 1) . ' years</div>
    </div>';
    
    // Detailed farmers list
    $farmers_sql = "SELECT f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix, f.gender, 
                    f.contact_number, f.land_area_hectares, f.years_farming, f.registration_date,
                    b.barangay_name, c.commodity_name, h.civil_status, h.household_size
                    FROM farmers f
                    LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                    LEFT JOIN commodities c ON f.commodity_id = c.commodity_id
                    LEFT JOIN household_info h ON f.farmer_id = h.farmer_id
                    WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'
                    ORDER BY f.registration_date DESC";
    
    $farmers_result = $conn->query($farmers_sql);
    
    if ($farmers_result->num_rows > 0) {
        $html .= '<h3>👥 Detailed Farmers List</h3>
        <table>
            <thead>
                <tr>
                    <th>Farmer ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Primary Commodity</th>
                    <th>Land Area (ha)</th>
                    <th>Years Farming</th>
                    <th>Civil Status</th>
                    <th>Household Size</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $farmers_result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            $html .= '<tr>
                <td>' . htmlspecialchars($row['farmer_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['gender']) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['commodity_name']) . '</td>
                <td>' . number_format($row['land_area_hectares'], 2) . '</td>
                <td>' . htmlspecialchars($row['years_farming']) . '</td>
                <td>' . htmlspecialchars($row['civil_status'] ?: 'N/A') . '</td>
                <td>' . htmlspecialchars($row['household_size'] ?: 'N/A') . '</td>
                <td>' . date('M d, Y', strtotime($row['registration_date'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<p style="text-align: center; padding: 20px;">No farmers registered during the specified period.</p>';
    }
    
    return $html;
}

function generateInputDistributionReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Get summary statistics
    $total_distributions = $conn->query("SELECT COUNT(*) as count FROM mao_distribution_log WHERE date_given BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $unique_farmers = $conn->query("SELECT COUNT(DISTINCT farmer_id) as count FROM mao_distribution_log WHERE date_given BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $total_value_query = $conn->query("SELECT SUM(mdl.quantity_distributed) as total_qty FROM mao_distribution_log mdl WHERE mdl.date_given BETWEEN '$start_date' AND '$end_date'");
    $total_distributed = $total_value_query->fetch_assoc()['total_qty'] ?: 0;
    
    // Summary box
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">📦 Input Distribution Summary</h3>
        <div class="summary-item"><span class="summary-label">Total Distributions:</span> ' . number_format($total_distributions) . '</div>
        <div class="summary-item"><span class="summary-label">Unique Farmers Served:</span> ' . number_format($unique_farmers) . '</div>
        <div class="summary-item"><span class="summary-label">Total Quantity Distributed:</span> ' . number_format($total_distributed) . ' units</div>
    </div>';
    
    // Distribution by input type
    $input_summary_sql = "SELECT ic.input_name, ic.unit, SUM(mdl.quantity_distributed) as total_distributed,
                          COUNT(mdl.log_id) as distribution_count
                          FROM mao_distribution_log mdl
                          INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
                          WHERE mdl.date_given BETWEEN '$start_date' AND '$end_date'
                          GROUP BY ic.input_id, ic.input_name, ic.unit
                          ORDER BY total_distributed DESC";
    
    $input_summary_result = $conn->query($input_summary_sql);
    
    if ($input_summary_result->num_rows > 0) {
        $html .= '<h3>📋 Distribution by Input Type</h3>
        <table>
            <thead>
                <tr>
                    <th>Input Type</th>
                    <th>Unit</th>
                    <th>Total Quantity Distributed</th>
                    <th>Number of Distributions</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $input_summary_result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['input_name']) . '</td>
                <td>' . htmlspecialchars($row['unit']) . '</td>
                <td>' . number_format($row['total_distributed']) . '</td>
                <td>' . number_format($row['distribution_count']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    // Detailed distribution log
    $distributions_sql = "SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, mdl.visitation_date,
                          f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix, f.contact_number,
                          b.barangay_name, ic.input_name, ic.unit
                          FROM mao_distribution_log mdl
                          INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id
                          INNER JOIN input_categories ic ON mdl.input_id = ic.input_id
                          LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                          WHERE mdl.date_given BETWEEN '$start_date' AND '$end_date'
                          ORDER BY mdl.date_given DESC, mdl.log_id DESC";
    
    $distributions_result = $conn->query($distributions_sql);
    
    if ($distributions_result->num_rows > 0) {
        $html .= '<h3>📝 Detailed Distribution Log</h3>
        <table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Date</th>
                    <th>Farmer</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Input Type</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Visitation Date</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $distributions_result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            $visitation = $row['visitation_date'] ? date('M d, Y', strtotime($row['visitation_date'])) : 'N/A';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($row['log_id']) . '</td>
                <td>' . date('M d, Y', strtotime($row['date_given'])) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['input_name']) . '</td>
                <td>' . number_format($row['quantity_distributed']) . '</td>
                <td>' . htmlspecialchars($row['unit']) . '</td>
                <td>' . htmlspecialchars($visitation) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<p style="text-align: center; padding: 20px;">No input distributions recorded during the specified period.</p>';
    }
    
    return $html;
}

function generateYieldMonitoringReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Note: Since yield_monitoring table appears to be empty, we'll show the structure
    $total_records = $conn->query("SELECT COUNT(*) as count FROM yield_monitoring WHERE record_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">🌾 Yield Monitoring Summary</h3>
        <div class="summary-item"><span class="summary-label">Total Yield Records:</span> ' . number_format($total_records) . '</div>
    </div>';
    
    if ($total_records > 0) {
        // Add yield monitoring details when data exists
        $html .= '<h3>📊 Yield Records</h3>
        <p>Yield monitoring data will be displayed here when records are available.</p>';
    } else {
        $html .= '<p style="text-align: center; padding: 20px;">No yield monitoring records found during the specified period.</p>';
    }
    
    return $html;
}

function generateInventoryStatusReport($conn) {
    $html = '';
    
    // Get current inventory status
    $inventory_sql = "SELECT ic.input_name, ic.unit, mi.quantity_on_hand, mi.last_updated,
                      COALESCE(SUM(mdl.quantity_distributed), 0) as total_distributed
                      FROM mao_inventory mi
                      INNER JOIN input_categories ic ON mi.input_id = ic.input_id
                      LEFT JOIN mao_distribution_log mdl ON mi.input_id = mdl.input_id
                      GROUP BY mi.inventory_id, ic.input_name, ic.unit, mi.quantity_on_hand, mi.last_updated
                      ORDER BY ic.input_name";
    
    $inventory_result = $conn->query($inventory_sql);
    $total_value = 0;
    $low_stock_items = 0;
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">📦 Current Inventory Status</h3>
        <div class="summary-item"><span class="summary-label">Report Generated:</span> ' . date('F d, Y h:i A') . '</div>
        <div class="summary-item"><span class="summary-label">Total Input Categories:</span> ' . $inventory_result->num_rows . '</div>
    </div>';
    
    if ($inventory_result->num_rows > 0) {
        $html .= '<h3>📋 Detailed Inventory Status</h3>
        <table>
            <thead>
                <tr>
                    <th>Input Name</th>
                    <th>Unit</th>
                    <th>Current Stock</th>
                    <th>Total Distributed</th>
                    <th>Stock Status</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $inventory_result->fetch_assoc()) {
            $stock_status = '';
            $stock_class = '';
            
            if ($row['quantity_on_hand'] <= 10) {
                $stock_status = 'Critical Low';
                $stock_class = 'background-color: #fee2e2; color: #991b1b;';
                $low_stock_items++;
            } else if ($row['quantity_on_hand'] <= 50) {
                $stock_status = 'Low Stock';
                $stock_class = 'background-color: #fef3c7; color: #92400e;';
            } else {
                $stock_status = 'Adequate';
                $stock_class = 'background-color: #dcfce7; color: #166534;';
            }
            
            $html .= '<tr>
                <td>' . htmlspecialchars($row['input_name']) . '</td>
                <td>' . htmlspecialchars($row['unit']) . '</td>
                <td>' . number_format($row['quantity_on_hand']) . '</td>
                <td>' . number_format($row['total_distributed']) . '</td>
                <td style="' . $stock_class . ' padding: 5px; border-radius: 3px; font-weight: bold;">' . $stock_status . '</td>
                <td>' . date('M d, Y h:i A', strtotime($row['last_updated'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        if ($low_stock_items > 0) {
            $html .= '<div style="background-color: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0; color: #991b1b;">⚠️ Low Stock Alert</h4>
                <p style="margin-bottom: 0;"><strong>' . $low_stock_items . '</strong> items are running critically low and need immediate restocking.</p>
            </div>';
        }
    }
    
    return $html;
}

function generateBarangayAnalyticsReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Barangay-wise farmer statistics
    $barangay_sql = "SELECT b.barangay_name, 
                     COUNT(f.farmer_id) as farmer_count,
                     COALESCE(SUM(f.land_area_hectares), 0) as total_land_area,
                     COALESCE(AVG(f.years_farming), 0) as avg_farming_years,
                     COUNT(CASE WHEN f.gender = 'Male' THEN 1 END) as male_count,
                     COUNT(CASE WHEN f.gender = 'Female' THEN 1 END) as female_count
                     FROM barangays b
                     LEFT JOIN farmers f ON b.barangay_id = f.barangay_id 
                     AND f.registration_date BETWEEN '$start_date' AND '$end_date'
                     GROUP BY b.barangay_id, b.barangay_name
                     ORDER BY farmer_count DESC, b.barangay_name";
    
    $barangay_result = $conn->query($barangay_sql);
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">🗺️ Barangay Analytics Summary</h3>
        <div class="summary-item"><span class="summary-label">Analysis Period:</span> ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</div>
        <div class="summary-item"><span class="summary-label">Total Barangays:</span> ' . $barangay_result->num_rows . '</div>
    </div>';
    
    if ($barangay_result->num_rows > 0) {
        $html .= '<h3>📊 Farmers Distribution by Barangay</h3>
        <table>
            <thead>
                <tr>
                    <th>Barangay</th>
                    <th>Total Farmers</th>
                    <th>Male</th>
                    <th>Female</th>
                    <th>Total Land Area (ha)</th>
                    <th>Avg. Farming Years</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $barangay_result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . number_format($row['farmer_count']) . '</td>
                <td>' . number_format($row['male_count']) . '</td>
                <td>' . number_format($row['female_count']) . '</td>
                <td>' . number_format($row['total_land_area'], 2) . '</td>
                <td>' . number_format($row['avg_farming_years'], 1) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    return $html;
}

function generateCommodityProductionReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Commodity distribution among farmers
    $commodity_sql = "SELECT c.commodity_name, cc.category_name,
                      COUNT(f.farmer_id) as farmer_count,
                      COALESCE(SUM(f.land_area_hectares), 0) as total_land_area,
                      COALESCE(AVG(f.land_area_hectares), 0) as avg_land_area
                      FROM commodities c
                      INNER JOIN commodity_categories cc ON c.category_id = cc.category_id
                      LEFT JOIN farmers f ON c.commodity_id = f.commodity_id 
                      AND f.registration_date BETWEEN '$start_date' AND '$end_date'
                      GROUP BY c.commodity_id, c.commodity_name, cc.category_name
                      ORDER BY farmer_count DESC, c.commodity_name";
    
    $commodity_result = $conn->query($commodity_sql);
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">🌱 Commodity Production Summary</h3>
        <div class="summary-item"><span class="summary-label">Analysis Period:</span> ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</div>
        <div class="summary-item"><span class="summary-label">Total Commodities:</span> ' . $commodity_result->num_rows . '</div>
    </div>';
    
    if ($commodity_result->num_rows > 0) {
        $html .= '<h3>📊 Commodity Distribution Among Farmers</h3>
        <table>
            <thead>
                <tr>
                    <th>Commodity</th>
                    <th>Category</th>
                    <th>Number of Farmers</th>
                    <th>Total Land Area (ha)</th>
                    <th>Average Land Area (ha)</th>
                </tr>
            </thead>
            <tbody>';
        
        while ($row = $commodity_result->fetch_assoc()) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['commodity_name']) . '</td>
                <td>' . htmlspecialchars($row['category_name']) . '</td>
                <td>' . number_format($row['farmer_count']) . '</td>
                <td>' . number_format($row['total_land_area'], 2) . '</td>
                <td>' . number_format($row['avg_land_area'], 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    return $html;
}

function generateRegistrationAnalyticsReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Registration statistics
    $rsbsa_count = $conn->query("SELECT COUNT(*) as count FROM rsbsa_registered_farmers r INNER JOIN farmers f ON r.farmer_id = f.farmer_id WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $ncfrs_count = $conn->query("SELECT COUNT(*) as count FROM ncfrs_registered_farmers n INNER JOIN farmers f ON n.farmer_id = f.farmer_id WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $fisherfolk_count = $conn->query("SELECT COUNT(*) as count FROM fisherfolk_registered_farmers ff INNER JOIN farmers f ON ff.farmer_id = f.farmer_id WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">📋 Registration Analytics Summary</h3>
        <div class="summary-item"><span class="summary-label">RSBSA Registered:</span> ' . number_format($rsbsa_count) . '</div>
        <div class="summary-item"><span class="summary-label">NCFRS Registered:</span> ' . number_format($ncfrs_count) . '</div>
        <div class="summary-item"><span class="summary-label">Fisherfolk Registered:</span> ' . number_format($fisherfolk_count) . '</div>
    </div>';
    
    return $html;
}

function generateComprehensiveOverviewReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Comprehensive overview combining all metrics
    $total_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $total_distributions = $conn->query("SELECT COUNT(*) as count FROM mao_distribution_log WHERE date_given BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $total_land_area = $conn->query("SELECT COALESCE(SUM(land_area_hectares), 0) as total FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
    $active_barangays = $conn->query("SELECT COUNT(DISTINCT f.barangay_id) as count FROM farmers f WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">📈 Comprehensive System Overview</h3>
        <div class="summary-item"><span class="summary-label">Total Farmers Registered:</span> ' . number_format($total_farmers) . '</div>
        <div class="summary-item"><span class="summary-label">Total Input Distributions:</span> ' . number_format($total_distributions) . '</div>
        <div class="summary-item"><span class="summary-label">Total Land Under Cultivation:</span> ' . number_format($total_land_area, 2) . ' hectares</div>
        <div class="summary-item"><span class="summary-label">Active Barangays:</span> ' . number_format($active_barangays) . '</div>
    </div>';
    
    // Add sections from other reports
    $html .= generateFarmersSummaryReport($start_date, $end_date, $conn);
    $html .= generateInputDistributionReport($start_date, $end_date, $conn);
    $html .= generateInventoryStatusReport($conn);
    
    return $html;
}

// Get saved reports for history
$saved_reports_sql = "SELECT r.report_id, r.report_type, r.start_date, r.end_date, r.file_path, r.timestamp,
                      s.first_name, s.last_name
                      FROM generated_reports r
                      INNER JOIN mao_staff s ON r.staff_id = s.staff_id
                      ORDER BY r.timestamp DESC
                      LIMIT 10";
$saved_reports_result = $conn->query($saved_reports_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports System - Agricultural Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'agri-green': '#16a34a',
                        'agri-dark': '#15803d',
                        'agri-light': '#dcfce7'
                    }
                }
            }
        }
    </script>
    <style>
        .report-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(22, 163, 74, 0.15);
            border-color: #16a34a;
        }
        .btn-generate {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transition: all 0.3s ease;
        }
        .btn-generate:hover {
            background: linear-gradient(135deg, #15803d, #166534);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
        }
        .recent-report-item {
            transition: all 0.2s ease;
        }
        .recent-report-item:hover {
            background-color: #f0fdf4;
            border-color: #16a34a;
        }
        .input-focus {
            transition: all 0.2s ease;
        }
        .input-focus:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'nav.php'; ?>

    <!-- Main Content -->
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            
            <!-- Header Section -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8">
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="flex-1">
                        <h1 class="text-4xl font-bold text-gray-900 flex items-center mb-3">
                            <div class="bg-gradient-to-r from-agri-green to-agri-dark p-3 rounded-xl mr-4">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            Reports System
                        </h1>
                        <p class="text-gray-600 text-lg">Generate comprehensive reports for agricultural management and analytics</p>
                        <div class="flex items-center mt-4 text-sm text-gray-500">
                            <i class="fas fa-user mr-2"></i>
                            Logged in as: <span class="font-semibold ml-1"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <span class="mx-3">•</span>
                            <i class="fas fa-clock mr-2"></i>
                            <?php echo date('F d, Y h:i A'); ?>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-agri-green to-agri-dark p-6 rounded-xl text-white text-center">
                        <div class="text-3xl font-bold"><?php echo $saved_reports_result->num_rows; ?></div>
                        <div class="text-sm opacity-90">Saved Reports</div>
                    </div>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 p-6 rounded-lg mb-8 shadow-md">
                    <div class="flex items-center">
                        <div class="bg-green-500 rounded-full p-2 mr-4">
                            <i class="fas fa-check text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-green-800 font-semibold">Success!</h4>
                            <p class="text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                <!-- Report Generation Form -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <div class="border-b border-gray-200 pb-6 mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 flex items-center">
                                <div class="bg-agri-green p-2 rounded-lg mr-3">
                                    <i class="fas fa-file-alt text-white"></i>
                                </div>
                                Generate New Report
                            </h3>
                            <p class="text-gray-600 mt-2">Select report type and date range to generate comprehensive analytics</p>
                        </div>
                        
                        <form method="POST" target="_blank" class="space-y-8">
                            <input type="hidden" name="action" value="generate_report">
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">
                                    <i class="fas fa-list text-agri-green mr-2"></i>Report Type
                                </label>
                                <select name="report_type" required 
                                        class="w-full py-4 px-4 border border-gray-300 rounded-xl focus:ring-agri-green focus:border-agri-green input-focus text-gray-900 bg-white shadow-sm">
                                    <option value="">Choose a report type...</option>
                                    <option value="farmers_summary">👥 Farmers Summary Report</option>
                                    <option value="input_distribution">📦 Input Distribution Report</option>
                                    <option value="yield_monitoring">🌾 Yield Monitoring Report</option>
                                    <option value="inventory_status">📋 Current Inventory Status</option>
                                    <option value="barangay_analytics">🗺️ Barangay Analytics Report</option>
                                    <option value="commodity_production">🌱 Commodity Production Report</option>
                                    <option value="registration_analytics">📋 Registration Analytics Report</option>
                                    <option value="comprehensive_overview">📈 Comprehensive Overview Report</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-calendar-alt text-agri-green mr-2"></i>Start Date
                                    </label>
                                    <input type="date" name="start_date" required 
                                           value="<?php echo date('Y-m-01'); ?>"
                                           class="w-full py-4 px-4 border border-gray-300 rounded-xl focus:ring-agri-green focus:border-agri-green input-focus shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">
                                        <i class="fas fa-calendar-check text-agri-green mr-2"></i>End Date
                                    </label>
                                    <input type="date" name="end_date" required 
                                           value="<?php echo date('Y-m-d'); ?>"
                                           class="w-full py-4 px-4 border border-gray-300 rounded-xl focus:ring-agri-green focus:border-agri-green input-focus shadow-sm">
                                </div>
                            </div>
                            
                            <div class="bg-agri-light p-6 rounded-xl border border-agri-green/20">
                                <div class="flex items-start">
                                    <input type="checkbox" name="save_report" id="save_report" 
                                           class="h-5 w-5 text-agri-green focus:ring-agri-green border-gray-300 rounded mt-1 mr-4">
                                    <div>
                                        <label for="save_report" class="block text-sm font-medium text-gray-700">
                                            <i class="fas fa-save text-agri-green mr-2"></i>Save report to database
                                        </label>
                                        <p class="text-sm text-gray-600 mt-1">Keep a permanent copy for future reference and download</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex gap-4 pt-4">
                                <button type="submit" 
                                        class="btn-generate text-white px-8 py-4 rounded-xl font-semibold flex items-center text-lg shadow-lg">
                                    <i class="fas fa-chart-bar mr-3"></i>Generate Report
                                </button>
                                <button type="reset" 
                                        class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:bg-gray-200 transition-colors flex items-center text-lg border border-gray-300">
                                    <i class="fas fa-undo mr-3"></i>Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report History -->
                <div>
                    <div class="bg-white rounded-xl shadow-lg p-8">
                        <div class="border-b border-gray-200 pb-6 mb-6">
                            <h3 class="text-xl font-bold text-gray-900 flex items-center">
                                <div class="bg-agri-green p-2 rounded-lg mr-3">
                                    <i class="fas fa-history text-white"></i>
                                </div>
                                Recent Reports
                            </h3>
                            <p class="text-gray-600 mt-2">Previously generated reports</p>
                        </div>
                        
                        <?php if ($saved_reports_result->num_rows > 0): ?>
                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                <?php while ($report = $saved_reports_result->fetch_assoc()): ?>
                                    <div class="recent-report-item border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="font-semibold text-gray-900 text-sm mb-1">
                                                    <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-600 mb-2">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    <?php echo date('M d, Y', strtotime($report['start_date'])); ?> - 
                                                    <?php echo date('M d, Y', strtotime($report['end_date'])); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 mb-2">
                                                    <i class="fas fa-user mr-1"></i>
                                                    <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo date('M d, Y h:i A', strtotime($report['timestamp'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <a href="<?php echo htmlspecialchars($report['file_path']); ?>" 
                                               target="_blank"
                                               class="inline-flex items-center text-agri-green hover:text-agri-dark text-sm font-medium transition-colors">
                                                <i class="fas fa-external-link-alt mr-2"></i>View Report
                                            </a>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <a href="#" class="text-agri-green hover:text-agri-dark font-medium text-sm flex items-center justify-center">
                                    <i class="fas fa-archive mr-2"></i>View All Reports
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="bg-gray-100 rounded-full p-6 w-24 h-24 mx-auto mb-4 flex items-center justify-center">
                                    <i class="fas fa-file-alt text-3xl text-gray-400"></i>
                                </div>
                                <h4 class="text-gray-900 font-medium mb-2">No reports yet</h4>
                                <p class="text-gray-500 text-sm">Generate your first report to see it here</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set maximum date to today and add form enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const endDateInput = document.querySelector('input[name="end_date"]');
            const startDateInput = document.querySelector('input[name="start_date"]');
            
            endDateInput.setAttribute('max', today);
            startDateInput.setAttribute('max', today);
            
            // Auto-adjust end date when start date changes
            startDateInput.addEventListener('change', function() {
                const startDate = new Date(this.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate > endDate) {
                    endDateInput.value = this.value;
                }
                endDateInput.setAttribute('min', this.value);
            });
            
            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const reportType = document.querySelector('select[name="report_type"]').value;
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                
                if (!reportType) {
                    e.preventDefault();
                    alert('Please select a report type.');
                    return;
                }
                
                if (new Date(startDate) > new Date(endDate)) {
                    e.preventDefault();
                    alert('Start date cannot be later than end date.');
                    return;
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i>Generating...';
                submitBtn.disabled = true;
                
                // Re-enable after a delay (in case user comes back)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });
        });
    </script>
    
    <?php include 'includes/notification_complete.php'; ?>
</body>
</html>
