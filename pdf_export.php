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
require_once 'conn.php';
require_once 'check_session.php';

// Simple PDF class for table generation
class SimplePDF {
    private $content = '';
    private $title = '';
    
    public function __construct($title = 'Document') {
        $this->title = $title;
    }
    
    public function addHTML($html) {
        $this->content .= $html;
    }
    
    public function output($filename = 'document.pdf') {
        // Create HTML document
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $this->title . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            font-size: 12px;
            color: #2c3e50;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #16a34a;
            padding-bottom: 15px;
            /* background removed */
            color: #222;
            margin: -20px -20px 30px -20px;
            padding: 20px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .export-info {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        th {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #15803d;
            font-size: 11px;
        }
        td {
            padding: 10px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        tr:hover {
            background-color: #dcfce7;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-yes {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .badge-no {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            border-top: 2px solid #16a34a;
            padding-top: 15px;
        }
        .summary-stats {
            background: #f0fdf4;
            border: 1px solid #16a34a;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #16a34a;
        }
        .stat-label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        @page {
            size: A4 portrait;
            margin: 0.5in;
        }
    </style>
</head>
<body>' . $this->content . '</body>
</html>';
        
        // Set headers for PDF download
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        echo $html;
    }
}

// Handle PDF export
if (isset($_GET['action']) && $_GET['action'] === 'export_pdf') {
    try {
        // Build search condition
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
        $search_condition = 'WHERE f.archived = 0';
        $search_params = [];

        // If is_rsbsa=1 is set, filter only RSBSA-registered farmers
        if (isset($_GET['is_rsbsa']) && $_GET['is_rsbsa'] == '1') {
            $search_condition .= ' AND f.is_rsbsa = 1';
        }
        // If is_ncfrs=1 is set, filter only NCFRS-registered farmers
        if (isset($_GET['is_ncfrs']) && $_GET['is_ncfrs'] == '1') {
            $search_condition .= ' AND f.is_ncfrs = 1';
        }
        // If is_fishr=1 is set, filter only FishR-registered farmers
        if (isset($_GET['is_fishr']) && $_GET['is_fishr'] == '1') {
            $search_condition .= ' AND f.is_fisherfolk = 1';
        }
        // If is_boat=1 is set, filter only boat-registered farmers
        if (isset($_GET['is_boat']) && $_GET['is_boat'] == '1') {
            $search_condition .= ' AND f.is_boat = 1';
        }

        if (!empty($search)) {
            $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
            $search_term = "%$search%";
            $search_params = [$search_term, $search_term, $search_term];
        }

        if (!empty($barangay_filter)) {
            $search_condition .= " AND f.barangay_id = ?";
            $search_params[] = $barangay_filter;
        }
        
        // Query for export data
        $export_sql = "SELECT f.*, h.*, 
                      GROUP_CONCAT(DISTINCT c.commodity_name SEPARATOR ', ') as commodity_name, 
                      b.barangay_name,
                      CASE WHEN f.is_rsbsa = 1 THEN 'Yes' ELSE 'No' END as rsbsa_registered,
                      CASE WHEN f.is_ncfrs = 1 THEN 'Yes' ELSE 'No' END as ncfrs_registered,
                      CASE WHEN f.is_fisherfolk = 1 THEN 'Yes' ELSE 'No' END as fisherfolk_registered,
                      CASE WHEN f.is_boat = 1 THEN 'Has Boat' ELSE 'No Boat' END as boat_status
                      FROM farmers f 
                      LEFT JOIN household_info h ON f.farmer_id = h.farmer_id 
                      LEFT JOIN farmer_commodities fc ON f.farmer_id = fc.farmer_id
                      LEFT JOIN commodities c ON fc.commodity_id = c.commodity_id 
                      LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
                      $search_condition
                      GROUP BY f.farmer_id
                      ORDER BY f.registration_date DESC";
        
        if (!empty($search_params)) {
            $export_stmt = $conn->prepare($export_sql);
            $export_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
        } else {
            $export_stmt = $conn->prepare($export_sql);
        }
        
        $export_stmt->execute();
        $export_result = $export_stmt->get_result();
        
        // Create PDF
        $pdf = new SimplePDF('Farmers Database Export');
        
        // Get the Base64 encoded strings for your logos
        $logoLeftPath = 'assets/Logo/Lagonglong_seal_SVG.svg.png';
        $logoRightPath = 'assets/Logo/E1361954-133F-4560-86CA-E4E3A2D916B8-removebg-preview.png';

        $logoLeftBase64 = imageToBase64($logoLeftPath);
        $logoRightBase64 = imageToBase64($logoRightPath);

        // Add header with embedded logos
        $html = '<div class="header" style="position: relative; min-height: 140px; display: flex; align-items: center; justify-content: center; background: none;">'
            . '<img src="' . $logoLeftBase64 . '" alt="Lagonglong Municipal Seal" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); height: 120px; width: auto;">'
            . '<img src="' . $logoRightBase64 . '" alt="Right Logo" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); height: 120px; width: auto;">'
            . '<div style="width: 100%;">'
                . '<div class="title"> Lagonglong FARMS</div>'
                . '<div class="subtitle">LIST OF FARMERS</div>'
                . '<div class="export-info">Generated on: ' . date('F d, Y g:i A') . ' <br> Total Records: ' . $export_result->num_rows . '</div>'
            . '</div>'
        . '</div>';
        
        // Add summary statistics
        $total_records = $export_result->num_rows;
        $html .= '<div class="summary-stats">
            <div class="stat-item">
                <div class="stat-number">' . $total_records . '</div>
                <div class="stat-label">Total Farmers</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">' . date('Y') . '</div>
                <div class="stat-label">Export Year</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">' . date('M d') . '</div>
                <div class="stat-label">Export Date</div>
            </div>
        </div>';
        
        // Start table
        $html .= '<table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Birth Date</th>
                    <th>Gender</th>
                    <th>Contact</th>
                    <th>Barangay</th>
                    <th>Civil Status</th>
                    <th>Spouse</th>
                    <th>Household Size</th>
                    <th>Education</th>
                    <th>Occupation</th>
                    <th>Commodity</th>
                    <th>Land Area (Ha)</th>
                    <th>Years Farming</th>
                    <th>4Ps</th>
                    <th>Indigenous</th>
                    <th>RSBSA</th>
                    <th>NCFRS</th>
                    <th>Fisherfolk</th>
                    <th>Registration Date</th>
                </tr>
            </thead>
            <tbody>';
        
        // Function to format data for display
        function formatData($value, $type = 'text') {
            if (is_null($value) || $value === '') return '-';
            
            switch ($type) {
                case 'date':
                    return ($value && $value !== '0000-00-00') ? date('M d, Y', strtotime($value)) : '-';
                case 'datetime':
                    return ($value && $value !== '0000-00-00 00:00:00') ? date('M d, Y H:i', strtotime($value)) : '-';
                case 'number':
                    return is_numeric($value) ? number_format((float)$value, 2) : $value;
                case 'yesno':
                    return $value ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>';
                case 'badge':
                    return $value === 'Yes' ? '<span class="badge-yes">Yes</span>' : '<span class="badge-no">No</span>';
                default:
                    return htmlspecialchars(trim($value));
            }
        }
        
        // Add data rows
        while ($row = $export_result->fetch_assoc()) {
            $suffix = isset($row['suffix']) ? trim($row['suffix']) : '';
            // Exclude suffix if it is any case variation of 'N/A' or 'n/a'
            if (in_array(strtolower($suffix), ['n/a', 'na'])) {
                $suffix = '';
            }
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $suffix);
            $html .= '<tr>
                <td>' . formatData($full_name) . '</td>
                <td class="text-center">' . formatData($row['birth_date'], 'date') . '</td>
                <td class="text-center">' . formatData($row['gender']) . '</td>
                <td>' . formatData($row['contact_number']) . '</td>
                <td>' . formatData($row['barangay_name']) . '</td>
                <td class="text-center">' . formatData($row['civil_status']) . '</td>
                <td>' . formatData($row['spouse_name']) . '</td>
                <td class="text-center">' . formatData($row['household_size']) . '</td>
                <td>' . formatData($row['education_level']) . '</td>
                <td>' . formatData($row['occupation']) . '</td>
                <td>' . formatData($row['commodity_name']) . '</td>
                <td class="text-right">' . formatData($row['land_area_hectares'] ?? null, 'number') . '</td>
                <td class="text-center">' . formatData($row['years_farming'] ?? null) . '</td>
                <td class="text-center">' . formatData($row['is_member_of_4ps'] ?? null, 'yesno') . '</td>
                <td class="text-center">' . formatData($row['is_ip'] ?? null, 'yesno') . '</td>
                <td class="text-center">' . formatData($row['rsbsa_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['ncfrs_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['fisherfolk_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['registration_date'], 'datetime') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Add footer
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
        $html .= '<div class="footer">'
            . '<div style="margin-top: 8px;">Report generated by: <span style="color:#15803d;font-weight:bold;">' . htmlspecialchars($staff_name) . '</span></div>'
            . '<div style="margin-top: 5px; font-style: italic;">Confidential - For Official Use Only</div>'
        . '</div>';
        
        $pdf->addHTML($html);
        $pdf->output('farmers_export_' . date('Y-m-d_H-i-s'));
        
    } catch (Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
}
?>
    