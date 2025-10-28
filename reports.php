<?php
// Helper function to convert an image to a Base64 string for embedding
function imageToBase64($path) {
    if (!file_exists($path)) {
        return '';
    }
    $type = mime_content_type($path);
    $data = file_get_contents($path);
    return 'data:' . $type . ';base64,' . base64_encode($data);
}

// Consolidated Yield Report Function
function generateConsolidatedYieldReport($start_date, $end_date, $conn) {
    $html = '';
    // Poultry/Livestock categories (category names used in DB)
    $head_categories = ['Livestocks', 'Poultry'];
    // Desired category order (user-requested)
    $category_order = ['Agronomic Crops', 'High Value Crops', 'Poultry', 'Livestocks'];
    // Load categories as id => name map
    $categories = [];
    $cat_res = $conn->query("SELECT category_id, category_name FROM commodity_categories");
    while ($row = $cat_res->fetch_assoc()) {
        $categories[(int)$row['category_id']] = $row['category_name'];
    }
    // Build commodities keyed by category_id
    $commodities = [];
    $com_res = $conn->query("SELECT commodity_id, commodity_name, category_id FROM commodities ORDER BY commodity_name");
    while ($row = $com_res->fetch_assoc()) {
        $cat_id = (int)$row['category_id'];
        $commodities[$cat_id][$row['commodity_name']] = $row['commodity_id'];
    }
    // Build a map of commodity_id => unit (if the column exists)
    $commodity_units = [];
    $col_check = $conn->query("SHOW COLUMNS FROM commodities LIKE 'unit'");
    if ($col_check && $col_check->num_rows > 0) {
        $unit_res = $conn->query("SELECT commodity_id, COALESCE(unit,'') AS unit FROM commodities");
        while ($ur = $unit_res->fetch_assoc()) {
            $commodity_units[(int)$ur['commodity_id']] = $ur['unit'];
        }
    }
    // Get all barangays
    $barangays = [];
    $brgy_res = $conn->query("SELECT barangay_id, barangay_name FROM barangays ORDER BY barangay_name");
    while ($row = $brgy_res->fetch_assoc()) {
        $barangays[$row['barangay_id']] = $row['barangay_name'];
    }
    // Get all yield data in the range
    $sql = "SELECT cc.category_name, c.commodity_name, b.barangay_name, b.barangay_id, y.unit,
                SUM(y.yield_amount) as total_yield, COALESCE(MAX(y.unit),'') AS unit
            FROM yield_monitoring y
            INNER JOIN commodities c ON y.commodity_id = c.commodity_id
            INNER JOIN commodity_categories cc ON c.category_id = cc.category_id
            INNER JOIN farmers f ON y.farmer_id = f.farmer_id
            INNER JOIN barangays b ON f.barangay_id = b.barangay_id
            WHERE y.record_date BETWEEN ? AND ?
            GROUP BY cc.category_name, c.commodity_name, b.barangay_id, y.unit
            ORDER BY cc.category_name, c.commodity_name, b.barangay_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $yield_data = [];
    while ($row = $result->fetch_assoc()) {
        $cat = $row['category_name'];
        $com = $row['commodity_name'];
        $brgy = $row['barangay_name'];
        $unit = $row['unit'];
        // If unit not provided in yield record, attempt to get from commodities table map
        if (empty($unit) && isset($commodity_units[$com]) && !empty($commodity_units[$com])) {
            $unit = $commodity_units[$com];
        }
        $is_head = in_array($cat, $head_categories);
        $label = $is_head ? 'Number of Heads' : 'Total Yield';
        $yield_data[$cat][$com][$brgy] = [
            'amount' => $row['total_yield'],
            'unit' => $unit,
            'is_head' => $is_head,
            'label' => $label
        ];
    }
    $html .= '<div class="summary-box">';
    $html .= '<h3 style="margin-top: 0; color: #15803d;">🧮 Consolidated Yield of All Commodities</h3>';
    $html .= '<div class="summary-item"><span class="summary-label">Analysis Period:</span> ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</div>';
    $html .= '</div>';
    // Build name=>id map so we can iterate requested category names and find their IDs
    $category_name_to_id = array_flip($categories); // name => id
    foreach ($category_order as $cat_name) {
        $cat_id = $category_name_to_id[$cat_name] ?? null;
        if (!$cat_id) continue;
        if (!isset($commodities[$cat_id])) continue;
        // Category header (use DB's category display name)
        $cat_display = $categories[$cat_id] ?? $cat_name;
        $html .= '<h3 style="margin-top:24px; color:#0f766e;">' . htmlspecialchars($cat_display) . '</h3>';
        foreach ($commodities[$cat_id] as $com => $com_id) {
            // Find unit and label from yield_data if exists, else default
            $sample = null;
            // yield_data is keyed by category_name, so use $cat_display
            foreach ($yield_data[$cat_display][$com] ?? [] as $brgy => $info) { $sample = $info; break; }
            $unit = $sample ? $sample['unit'] : '';
            // Fallback to commodity_units map if unit is not present in yield records
            if (empty($unit) && isset($commodity_units[$com]) && !empty($commodity_units[$com])) {
                $unit = $commodity_units[$com];
            }
            // If still empty, query yield_monitoring for this commodity and period to get recorded unit
            if (empty($unit) && isset($com_id)) {
                $u_stmt = $conn->prepare("SELECT COALESCE(MAX(unit),'') AS unit FROM yield_monitoring WHERE commodity_id = ? AND record_date BETWEEN ? AND ?");
                if ($u_stmt) {
                    $u_stmt->bind_param('iss', $com_id, $start_date, $end_date);
                    $u_stmt->execute();
                    $u_res = $u_stmt->get_result();
                    if ($u_res && $u_res->num_rows > 0) {
                        $urow = $u_res->fetch_assoc();
                        if (!empty($urow['unit'])) {
                            $unit = $urow['unit'];
                        }
                    }
                    $u_stmt->close();
                }
            }
            $is_head = $sample ? $sample['is_head'] : in_array($cat_display, $head_categories);
            $label = $is_head ? 'Number of Heads' : 'Total Yield';
            $unit_label = (!$is_head && $unit) ? ' (' . htmlspecialchars($unit) . ')' : '';
            $label_full = $label . $unit_label;
            $unit_header = $unit ? ' <span style="font-size:12px; color:#888;">(' . htmlspecialchars($unit) . ')</span>' : ' <span style="font-size:12px; color:#ef4444;">(unit unspecified)</span>';
            $html .= '<h4 style="margin-bottom:5px;">' . htmlspecialchars($com) . $unit_header . '</h4>';
            // If there are no yield records for this commodity in the selected period, show a uniform table indicating no data
            $has_yield_for_commodity = !empty($yield_data[$cat][$com]);
            $html .= '<table style="margin-bottom:20px; width:100%; border-collapse:collapse;">';
            $html .= '<thead><tr><th style="border:1px solid #e5e7eb; padding:8px;">Barangay</th><th style="border:1px solid #e5e7eb; padding:8px;">' . htmlspecialchars($label_full) . '</th></tr></thead><tbody>';
            if (!$has_yield_for_commodity) {
                $html .= '<tr><td colspan="2" style="border:1px solid #e5e7eb; padding:12px; text-align:center; color:#6b7280;">No available yield data recorded for this commodity in the selected period.</td></tr>';
                $html .= '</tbody></table>';
                continue;
            }
            $total = 0;
            foreach ($barangays as $brgy_id => $brgy_name) {
                $amount = isset($yield_data[$cat][$com][$brgy_name]) ? $yield_data[$cat][$com][$brgy_name]['amount'] : 0;
                $display_amount = number_format($amount, 2) . ($unit ? ' ' . htmlspecialchars($unit) : ' <span style="color:#ef4444;">(unit unspecified)</span>');
                $html .= '<tr><td style="border:1px solid #e5e7eb; padding:8px;">' . htmlspecialchars($brgy_name) . '</td><td style="border:1px solid #e5e7eb; padding:8px;">' . $display_amount . '</td></tr>';
                $total += $amount;
            }
            $display_total = number_format($total, 2) . ($unit ? ' ' . htmlspecialchars($unit) : ' <span style="color:#ef4444;">(unit unspecified)</span>');
            $html .= '<tr style="font-weight:bold; background:#f0fdf4;"><td style="border:1px solid #16a34a; padding:8px;">Total</td><td style="border:1px solid #16a34a; padding:8px;">' . $display_total . '</td></tr>';
            $html .= '</tbody></table>';
        }
    }
    return $html;
}
require_once 'check_session.php';
require_once 'conn.php';

// Check if user has admin access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    header("Location: index.php");
    exit();
}

// Handle report saving (from generated report)
// NOTE: Saving of generated reports has been disabled. Replies with an informational message.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_generated_report') {
    // Return a consistent JSON response so client-side code can handle it gracefully
    echo json_encode(['success' => false, 'message' => 'Saving generated reports has been disabled by the administrator. You may still generate and print reports.']);
    exit;
}

// Handle report generation
// Saving of generated reports to the database or file system has been disabled.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_report') {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Generate report content based on type and stream it to the browser (no file or DB writes)
    $report_content = generateReportContent($report_type, $start_date, $end_date, $conn);

    // Optionally log the generation action to activity_logs only (not the generated_reports table)
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt_log = $conn->prepare("INSERT INTO activity_logs (staff_id, action, action_type, details) VALUES (?, ?, 'system', ?)");
            $action = "Generated {$report_type} report";
            $details = "Period: {$start_date} to {$end_date}";
            $stmt_log->bind_param("iss", $_SESSION['user_id'], $action, $details);
            $stmt_log->execute();
        } catch (Exception $e) {
            // Swallow logging errors to avoid breaking report generation
        }
    }

    // Store recent report in session so UI can show "Recent Reports" without DB/files
    if (!isset($_SESSION['recent_reports']) || !is_array($_SESSION['recent_reports'])) {
        $_SESSION['recent_reports'] = [];
    }
    $recent_item = [
        'report_type' => $report_type,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'timestamp' => date('Y-m-d H:i:s'),
        'staff_name' => isset($_SESSION['user_id']) ? (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '') : ''
    ];
    // prepend and keep only last 10
    array_unshift($_SESSION['recent_reports'], $recent_item);
    $_SESSION['recent_reports'] = array_slice($_SESSION['recent_reports'], 0, 10);

    // Store generated report HTML in session and redirect (POST-Redirect-GET)
    if (!isset($_SESSION)) session_start();
    $_SESSION['generated_report_content'] = $report_content;
    // Redirect to a GET so the new tab can be refreshed without re-submitting the POST
    header('Location: reports.php?show_generated=1');
    exit;
}

// POST-Redirect-GET support: if a generated report was stored in the session,
// serve it on a GET request so refreshing the report tab does not resubmit the POST.
if (isset($_GET['show_generated']) && !empty($_SESSION['generated_report_content'])) {
    echo $_SESSION['generated_report_content'];
    // Remove stored report so refresh won't keep showing stale content
    unset($_SESSION['generated_report_content']);
    exit;
}

// Function to generate report content
function generateReportContent($report_type, $start_date, $end_date, $conn) {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title></title>';
    ob_start();
    include 'includes/assets.php';
    $assets = ob_get_clean();
    $html .= $assets . '<style>
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
                        // Refresh saved reports count in parent window if available
                        if (window.opener && typeof window.opener.refreshSavedReportsCount === "function") {
                            window.opener.refreshSavedReportsCount();
                        }
                        if (window.opener && typeof window.opener.refreshRecentReports === "function") {
                            window.opener.refreshRecentReports();
                        }
                        setTimeout(function() { window.close(); }, 800);
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
        
        // Function to handle navigation dropdown toggle
        function toggleNavigationDropdown() {
            const dropdown = document.getElementById("navigationDropdown");
            const arrow = document.getElementById("navigationArrow");
            
            dropdown.classList.toggle("hidden");
            arrow.classList.toggle("rotate-180");
        }

        // Close dropdown when clicking outside
        document.addEventListener("click", function(event) {
            const navigationButton = event.target.closest("button");
            const isNavigationButton = navigationButton && navigationButton.onclick && navigationButton.onclick.toString().includes("toggleNavigationDropdown");
            const navigationDropdown = document.getElementById("navigationDropdown");
            
            if (!isNavigationButton && navigationDropdown && !navigationDropdown.contains(event.target)) {
                navigationDropdown.classList.add("hidden");
                const navigationArrow = document.getElementById("navigationArrow");
                if (navigationArrow) {
                    navigationArrow.classList.remove("rotate-180");
                }
            }
        });
    </script>
</head>
<body>';
    
    // Add action buttons
    $html .= '<div class="no-print action-buttons">
        <button onclick="printReport()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
        <button onclick="closeReport()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
    </div>';
    $html .= '<div class="no-print" id="saveStatus"></div>';
    // Saved-to-database notice removed because auto-save is disabled to conserve storage.

    // Get the Base64 encoded strings for your logos
    $logoLeftPath = 'assets/Logo/Lagonglong_seal_SVG.svg.png';
    $logoRightPath = 'assets/Logo/E1361954-133F-4560-86CA-E4E3A2D916B8-removebg-preview.png';
    $logoLeftBase64 = function_exists('imageToBase64') ? imageToBase64($logoLeftPath) : '';
    $logoRightBase64 = function_exists('imageToBase64') ? imageToBase64($logoRightPath) : '';

    // Get the name of the currently logged-in staff
    $staff_name = '';
    if (isset($_SESSION['user_id'])) {
        $staff_id = $_SESSION['user_id'];
        $staff_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM mao_staff WHERE staff_id = ? LIMIT 1");
        $staff_query->bind_param('i', $staff_id);
        $staff_query->execute();
        $staff_result = $staff_query->get_result();
        if ($staff_row = $staff_result->fetch_assoc()) {
            $staff_name = $staff_row['full_name'];
        }
    }

    // Header with embedded logos (match PDF export)
    $report_title = ($report_type === 'consolidated_yield') ? 'Consolidated Yield Report' : ucfirst(str_replace('_', ' ', $report_type)) . ' Report';
    $html .= '<div class="header" style="position: relative; min-height: 140px; background: #fff;">'
        . '<img src="' . $logoLeftBase64 . '" alt="Lagonglong Municipal Seal" style="position: absolute; left: 20px; top: 0; height: 120px; width: auto;">'
        . '<img src="' . $logoRightBase64 . '" alt="Right Logo" style="position: absolute; right: 20px; top: 0; height: 120px; width: auto;">'
        . '<div style="position: absolute; left: 0; right: 0; top: 40px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; z-index: 2;">'
            . '<div class="title" style="margin: 0 0 4px 0; padding: 0; line-height: 1.3; font-size: 1.7em;"> Lagonglong FARMS</div>'
            . '<div class="subtitle" style="margin: 0 0 4px 0; padding: 0; line-height: 1.3; font-size: 1.25em;">' . $report_title . '</div>'
            . '<div class="report-info" style="margin: 0 0 10px 0; padding: 0; line-height: 1.3; font-size: 1.1em;">' . date('F d, Y', strtotime($end_date)) . '</div>'
        . '</div>'
    . '</div>';
    
    // Generate content based on report type
    switch ($report_type) {
        case 'consolidated_yield':
            $html .= generateConsolidatedYieldReport($start_date, $end_date, $conn);
            break;
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
    
    // Footer (match PDF export)
    $html .= '<div class="footer">'
        . '<div style="margin-top: 8px;">Report generated by: <span style="color:#15803d;font-weight:bold;">' . htmlspecialchars($staff_name) . '</span></div>'
        . '<div style="margin-top: 5px; font-style: italic;">Confidential - For Official Use Only</div>'
    . '</div>';
    $html .= '</body></html>';
    return $html;
}

// Report generation functions
function generateFarmersSummaryReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Get summary statistics
    $total_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $male_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE gender = 'Male' AND registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $female_farmers = $conn->query("SELECT COUNT(*) as count FROM farmers WHERE gender = 'Female' AND registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $total_land_area = $conn->query("SELECT COALESCE(SUM(f.land_area_hectares), 0) as total FROM farmers f WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
    $avg_farming_years = $conn->query("SELECT COALESCE(AVG(fc.years_farming), 0) as avg FROM farmers f LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['avg'];
    
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
                    f.contact_number, f.land_area_hectares, fc.years_farming, f.registration_date,
                    b.barangay_name, c.commodity_name, h.civil_status, h.household_size
                    FROM farmers f
                    LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                    LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id AND fc.is_primary = 1
                    LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id
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
            $suffix = isset($row['suffix']) ? trim($row['suffix']) : '';
            if (in_array(strtolower($suffix), ['n/a', 'na'])) {
                $suffix = '';
            }
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $suffix);
            $html .= '<tr>
                <td>' . htmlspecialchars($row['farmer_id']) . '</td>
                <td>' . htmlspecialchars($full_name) . '</td>
                <td>' . htmlspecialchars($row['gender']) . '</td>
                <td>' . htmlspecialchars($row['contact_number']) . '</td>
                <td>' . htmlspecialchars($row['barangay_name']) . '</td>
                <td>' . htmlspecialchars($row['commodity_name']) . '</td>
                <td>' . number_format($row['land_area_hectares'] ?? 0, 2) . '</td>
                <td>' . htmlspecialchars($row['years_farming'] ?? 'N/A') . '</td>
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
                     COUNT(DISTINCT f.farmer_id) as farmer_count,
                     COALESCE(SUM(f.land_area_hectares), 0) as total_land_area,
                     COALESCE(AVG(fc.years_farming), 0) as avg_farming_years,
                     COUNT(CASE WHEN f.gender = 'Male' THEN 1 END) as male_count,
                     COUNT(CASE WHEN f.gender = 'Female' THEN 1 END) as female_count
                     FROM barangays b
                     LEFT JOIN farmers f ON b.barangay_id = f.barangay_id 
                     AND f.registration_date BETWEEN '$start_date' AND '$end_date'
                     LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
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
    
    // Summary header for Commodity Production (top producers shown below)
    $html .= '<div class="summary-box">
        <h3 style="margin-top: 0; color: #15803d;">🌱 Commodity Production Summary</h3>
        <div class="summary-item"><span class="summary-label">Analysis Period:</span> ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)) . '</div>
        <div class="summary-item" style="color:#6b7280;">This report shows the top producing farmers per commodity with their barangay and land area.</div>
    </div>';

    // Top producers per commodity (show top 5 farmers and their barangay)
    $html .= '<h3 style="margin-top:30px; color:#166534;">🏆 Top Producers by Commodity</h3>';
    // Build categories and commodities mapping so we can order by category and prioritize commodities with data
    // Desired category order (user-requested)
    $category_order = ['Agronomic Crops', 'High Value Crops', 'Poultry', 'Livestocks'];
    // Load categories (id => name)
    $categories = [];
    $cat_res = $conn->query("SELECT category_id, category_name FROM commodity_categories");
    while ($r = $cat_res->fetch_assoc()) { $categories[$r['category_id']] = $r['category_name']; }
    // Build reverse map name => id for the requested ordering
    $category_name_to_id = array_flip($categories);
    // Build commodities keyed by category_id so grouping is robust
    $commodities = [];
    $commodity_units = [];
    // Check if 'unit' column exists, some installs may not have it
    $col_check = $conn->query("SHOW COLUMNS FROM commodities LIKE 'unit'");
    $select_unit = ($col_check && $col_check->num_rows > 0);
    if ($select_unit) {
        $com_q = $conn->query("SELECT commodity_id, commodity_name, category_id, COALESCE(unit,'') AS unit FROM commodities");
    } else {
        $com_q = $conn->query("SELECT commodity_id, commodity_name, category_id FROM commodities");
    }
    while ($r = $com_q->fetch_assoc()) {
        $cat_id = (int)$r['category_id'];
        $commodities[$cat_id][$r['commodity_name']] = $r['commodity_id'];
        $commodity_units[$r['commodity_id']] = isset($r['unit']) ? $r['unit'] : '';
    }
    // Find which commodities have yield in the selected period (one query)
    $has_yield_ids = [];
    $hy_stmt = $conn->prepare("SELECT DISTINCT commodity_id FROM yield_monitoring WHERE record_date BETWEEN ? AND ?");
    if ($hy_stmt) {
        $hy_stmt->bind_param('ss', $start_date, $end_date);
        $hy_stmt->execute();
        $hy_res = $hy_stmt->get_result();
        while ($hr = $hy_res->fetch_assoc()) { $has_yield_ids[] = (int)$hr['commodity_id']; }
        $hy_stmt->close();
    }
    // Iterate categories in desired order (use name->id map so names from DB are authoritative)
    foreach ($category_order as $cat_name) {
        $cat_id = $category_name_to_id[$cat_name] ?? null;
        if (!$cat_id) continue;
        if (!isset($commodities[$cat_id])) continue;
        $cat_display = $categories[$cat_id] ?? $cat_name;
    // Category header (bigger)
    $html .= '<h2 style="margin-top:28px; color:#0f766e; font-size:20px;">' . htmlspecialchars($cat_display) . '</h2>';
    // Build list and prioritize commodities with data
        $list = [];
        foreach ($commodities[$cat_id] as $name => $id) { $list[] = ['name'=>$name,'id'=>$id]; }
        usort($list, function($a,$b) use ($has_yield_ids) {
            $aHas = in_array($a['id'], $has_yield_ids);
            $bHas = in_array($b['id'], $has_yield_ids);
            if ($aHas && !$bHas) return -1;
            if (!$aHas && $bHas) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        foreach ($list as $comrow) {
            $cid = $comrow['id'];
            $cname = $comrow['name'];
            $unit = $commodity_units[$cid] ?? '';
            $unit_label = $unit ? ' (' . htmlspecialchars($unit) . ')' : '';
            // if unit missing, try to get from yield_monitoring
            if (empty($unit)) {
                $u_stmt2 = $conn->prepare("SELECT COALESCE(MAX(unit),'') AS unit FROM yield_monitoring WHERE commodity_id = ? AND record_date BETWEEN ? AND ?");
                if ($u_stmt2) {
                    $u_stmt2->bind_param('iss', $cid, $start_date, $end_date);
                    $u_stmt2->execute();
                    $u_res2 = $u_stmt2->get_result();
                    if ($u_res2 && $u_res2->num_rows > 0) {
                        $urow2 = $u_res2->fetch_assoc();
                        if (!empty($urow2['unit'])) {
                            $unit = $urow2['unit'];
                            $unit_label = ' (' . htmlspecialchars($unit) . ')';
                        }
                    }
                    $u_stmt2->close();
                }
            }

            $stmt_top = $conn->prepare("SELECT f.farmer_id, CONCAT(f.first_name, ' ', f.last_name) AS farmer_name, b.barangay_name, COALESCE(SUM(y.yield_amount),0) AS total_yield, COALESCE(MAX(f.land_area_hectares),0) AS land_area
                FROM yield_monitoring y
                INNER JOIN farmers f ON y.farmer_id = f.farmer_id
                LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
                WHERE y.commodity_id = ? AND y.record_date BETWEEN ? AND ?
                GROUP BY f.farmer_id
                ORDER BY total_yield DESC
                LIMIT 5");
            if ($stmt_top) {
                $stmt_top->bind_param('iss', $cid, $start_date, $end_date);
                $stmt_top->execute();
                $top_res = $stmt_top->get_result();
                if ($top_res && $top_res->num_rows > 0) {
                    // commodity header (keep compact)
                    $html .= '<h4 style="margin-bottom:8px; font-size:16px;">' . htmlspecialchars($cname) . $unit_label . '</h4>';
                    $html .= '<table style="margin-bottom:20px; width:100%; border-collapse:collapse;">';
                    $html .= '<thead><tr><th style="border:1px solid #e5e7eb; padding:8px;">Rank</th><th style="border:1px solid #e5e7eb; padding:8px;">Farmer</th><th style="border:1px solid #e5e7eb; padding:8px;">Barangay</th><th style="border:1px solid #e5e7eb; padding:8px;">Total Yield' . $unit_label . '</th><th style="border:1px solid #e5e7eb; padding:8px;">Land Area (ha)</th></tr></thead><tbody>';
                    $rank = 1;
                    while ($t = $top_res->fetch_assoc()) {
                        $html .= '<tr><td style="border:1px solid #e5e7eb; padding:8px;">' . $rank . '</td>';
                        $html .= '<td style="border:1px solid #e5e7eb; padding:8px;">' . htmlspecialchars($t['farmer_name']) . '</td>';
                        $html .= '<td style="border:1px solid #e5e7eb; padding:8px;">' . htmlspecialchars($t['barangay_name'] ?? 'N/A') . '</td>';
                        $html .= '<td style="border:1px solid #e5e7eb; padding:8px;">' . number_format($t['total_yield'], 2) . ($unit ? ' ' . htmlspecialchars($unit) : ' <span style="color:#ef4444;">(unit unspecified)</span>') . '</td>';
                        $html .= '<td style="border:1px solid #e5e7eb; padding:8px;">' . number_format($t['land_area'], 2) . '</td></tr>';
                        $rank++;
                    }
                    $html .= '</tbody></table>';
                } else {
                    // No entries for this commodity in the period — show uniform no-data table
                    $html .= '<table style="margin-bottom:20px; width:100%; border-collapse:collapse;">';
                    $html .= '<thead><tr><th style="border:1px solid #e5e7eb; padding:8px;">Rank</th><th style="border:1px solid #e5e7eb; padding:8px;">Farmer</th><th style="border:1px solid #e5e7eb; padding:8px;">Barangay</th><th style="border:1px solid #e5e7eb; padding:8px;">Total Yield' . $unit_label . '</th><th style="border:1px solid #e5e7eb; padding:8px;">Land Area (ha)</th></tr></thead><tbody>';
                    $html .= '<tr><td colspan="5" style="border:1px solid #e5e7eb; padding:12px; text-align:center; color:#6b7280;">No available yield data recorded for ' . htmlspecialchars($cname) . ' in the selected period.</td></tr>';
                    $html .= '</tbody></table>';
                }
                $stmt_top->close();
            }
        }
    }
    
    return $html;
}

function generateRegistrationAnalyticsReport($start_date, $end_date, $conn) {
    $html = '';
    
    // Registration statistics
    $rsbsa_count = $conn->query("SELECT COUNT(*) as count FROM farmers f WHERE f.is_rsbsa = 1 AND f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $ncfrs_count = $conn->query("SELECT COUNT(*) as count FROM farmers f WHERE f.is_ncfrs = 1 AND f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    $fisherfolk_count = $conn->query("SELECT COUNT(*) as count FROM farmers f WHERE f.is_fisherfolk = 1 AND f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
    
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
    $total_land_area = $conn->query("SELECT COALESCE(SUM(f.land_area_hectares), 0) as total FROM farmers f WHERE f.registration_date BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['total'];
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
// Get saved reports for history (only if the table exists)
$saved_reports_result = false;
$table_check = $conn->query("SHOW TABLES LIKE 'generated_reports'");
if ($table_check && $table_check->num_rows > 0) {
    $saved_reports_sql = "SELECT r.report_id, r.report_type, r.start_date, r.end_date, r.file_path, r.timestamp,
                          s.first_name, s.last_name
                          FROM generated_reports r
                          INNER JOIN mao_staff s ON r.staff_id = s.staff_id
                          ORDER BY r.timestamp DESC";
    $saved_reports_result = $conn->query($saved_reports_sql);
} else {
    // Feature disabled - no saved reports
    $saved_reports_result = false;
}
?>

<?php $pageTitle = 'Reports System - Lagonglong FARMS'; include 'includes/layout_start.php'; ?>
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
                        <div class="flex flex-col gap-4">
                            <!-- Page Navigation -->
                            <div class="relative">
                                <button class="bg-agri-green text-white px-4 py-2 rounded-lg hover:bg-agri-dark transition-colors flex items-center" onclick="toggleNavigationDropdown()">
                                    <i class="fas fa-compass mr-2"></i>Go to Page
                                    <i class="fas fa-chevron-down ml-2 transition-transform" id="navigationArrow"></i>
                                </button>
                                <div id="navigationDropdown" class="absolute left-0 top-full mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-[60] hidden overflow-y-auto" style="max-height: 500px;">
                                    <!-- Dashboard Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Dashboard</div>
                                        <a href="index.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-home text-blue-600 mr-3"></i>
                                            Dashboard
                                        </a>
                                        <a href="analytics_dashboard.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-chart-bar text-purple-600 mr-3"></i>
                                            Analytics Dashboard
                                        </a>
                                    </div>
                                    
                                    <!-- Inventory Management Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Inventory Management</div>
                                        <a href="mao_inventory.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-warehouse text-green-600 mr-3"></i>
                                            MAO Inventory
                                        </a>
                                        <a href="input_distribution_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-truck text-blue-600 mr-3"></i>
                                            Distribution Records
                                        </a>
                                        <a href="mao_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-calendar-check text-green-600 mr-3"></i>
                                            MAO Activities
                                        </a>
                                    </div>
                                    
                                    <!-- Records Management Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Records Management</div>
                                        <a href="farmers.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-users text-green-600 mr-3"></i>
                                            Farmers Registry
                                        </a>
                                        <a href="rsbsa_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-id-card text-blue-600 mr-3"></i>
                                            RSBSA Records
                                        </a>
                                        <a href="ncfrs_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-fish text-cyan-600 mr-3"></i>
                                            NCFRS Records
                                        </a>
                                        <a href="fishr_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-anchor text-blue-600 mr-3"></i>
                                            FishR Records
                                        </a>
                                        <a href="boat_records.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-ship text-indigo-600 mr-3"></i>
                                            Boat Records
                                        </a>
                                    </div>
                                    
                                    <!-- Monitoring & Reports Section -->
                                    <div class="border-b border-gray-200">
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Monitoring & Reports</div>
                                        <a href="yield_monitoring.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-seedling text-green-600 mr-3"></i>
                                            Yield Monitoring
                                        </a>
                                        <a href="reports.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700 bg-blue-50 border-l-4 border-blue-500 font-medium">
                                            <i class="fas fa-file-alt text-red-600 mr-3"></i>
                                            Reports
                                        </a>
                                        <a href="all_activities.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-list text-gray-600 mr-3"></i>
                                            All Activities
                                        </a>
                                    </div>
                                    
                                    <!-- Settings Section -->
                                    <div>
                                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Settings</div>
                                        <a href="staff.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-user-tie text-gray-600 mr-3"></i>
                                            Staff Management
                                        </a>
                                        <a href="settings.php" class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center text-gray-700">
                                            <i class="fas fa-cog text-gray-600 mr-3"></i>
                                            System Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <!-- Saved reports feature and auto-save removed (no UI shown) -->
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
                                
                                <form method="POST" target="_blank" class="space-y-8" id="generateReportForm">
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
                                            <option value="consolidated_yield">🧮 Consolidated Yield of All Commodities</option>
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
                                    
                                    <!-- Report auto-save information removed (feature disabled to conserve storage) -->
                                    
                                    <div class="flex gap-4 pt-4">
                                        <button type="submit" 
                                                class="btn-generate text-white px-8 py-4 rounded-xl font-semibold flex items-center text-lg shadow-lg bg-agri-green hover:bg-agri-dark transition-colors">
                                            <i class="fas fa-chart-bar mr-3"></i>Generate Report
                                        </button>
                                        <button type="reset" 
                                                class="bg-gray-100 text-gray-700 px-8 py-4 rounded-xl font-semibold hover:bg-gray-200 transition-colors flex items-center text-lg border border-gray-300">
                                            <i class="fas fa-undo mr-3"></i>Reset Form
                                        </button>
                                    </div>
                                </form>
                                <!-- Removed auto-reload after submit to avoid unexpected behavior -->
                            </div>
                        </div>

                        <!-- Report History (Compact) -->
                        <div>
                            <div class="bg-white rounded-lg shadow-md p-4">
                                <div class="border-b border-gray-200 pb-3 mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                        <div class="bg-agri-green p-1.5 rounded mr-2">
                                            <i class="fas fa-history text-white text-sm"></i>
                                        </div>
                                        Recent Reports
                                    </h3>
                                    <p class="text-gray-600 text-sm mt-1">Previously generated reports</p>
                                </div>
                                
                                <div id="recentReportsContainer">
                                    <?php if ($saved_reports_result->num_rows > 0): ?>
                                        <div class="space-y-2 max-h-72 overflow-y-auto">
                                            <?php while ($report = $saved_reports_result->fetch_assoc()): ?>
                                                <div class="recent-report-item border border-gray-200 rounded-md p-3 hover:shadow-sm transition-all">
                                                    <div class="flex items-start justify-between">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-gray-900 text-sm mb-1">
                                                                <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-600 mb-1">
                                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                                <?php echo date('M j', strtotime($report['start_date'])); ?> - 
                                                                <?php echo date('M j, Y', strtotime($report['end_date'])); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                <i class="fas fa-user mr-1"></i>
                                                                <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                                                <span class="mx-1">•</span>
                                                                <i class="fas fa-clock mr-1"></i>
                                                                <?php echo date('M j, h:i A', strtotime($report['timestamp'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                                        <a href="<?php echo htmlspecialchars($report['file_path']); ?>" 
                                                           target="_blank"
                                                           class="inline-flex items-center text-agri-green hover:text-agri-dark text-xs font-medium transition-colors">
                                                            <i class="fas fa-external-link-alt mr-1"></i>View Report
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <a href="#" class="text-agri-green hover:text-agri-dark font-medium text-xs flex items-center justify-center">
                                                <i class="fas fa-archive mr-1"></i>View All Reports
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-gray-500 text-sm">No reports found.</div>
                                    <?php endif; ?>
                                </div>
                                <script>
                                function refreshRecentReports() {
                                    $.ajax({
                                        url: 'get_saved_reports.php',
                                        method: 'GET',
                                        success: function(data) {
                                            $('#recentReportsContainer').html(data);
                                            refreshSavedReportsCount();
                                        },
                                        error: function() {
                                            $('#recentReportsContainer').html('<div class="text-red-500 text-sm">Failed to load reports.</div>');
                                        }
                                    });
                                }
                                function refreshSavedReportsCount() {
                                    $.ajax({
                                        url: 'get_saved_reports_count.php',
                                        method: 'GET',
                                        dataType: 'json',
                                        success: function(data) {
                                            if (typeof data.count !== 'undefined') {
                                                $('#savedReportsCount').text(data.count);
                                            }
                                        }
                                    });
                                }
                                window.refreshRecentReports = refreshRecentReports;
                                window.refreshSavedReportsCount = refreshSavedReportsCount;
                                // Always refresh on page load
                                $(document).ready(function() {
                                    refreshSavedReportsCount();
                                    refreshRecentReports();
                                });
                                </script>
<script>
    // Refresh when window regains focus (user returns from report tab)
    window.addEventListener('focus', function() {
        if (typeof refreshSavedReportsCount === 'function') refreshSavedReportsCount();
        if (typeof refreshRecentReports === 'function') refreshRecentReports();
    });
</script>

<?php include 'includes/notification_complete.php'; ?>
