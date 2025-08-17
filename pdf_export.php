<?php
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
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        th {
            background-color: #34495e;
            color: white;
            padding: 8px 4px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
            max-width: 80px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e8f4fd;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge-yes {
            background-color: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
        }
        .badge-no {
            background-color: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        @page {
            size: A4 landscape;
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
        $search_condition = 'WHERE f.farmer_id NOT IN (SELECT farmer_id FROM archived_farmers)';
        $search_params = [];
        
        if (!empty($search)) {
            $search_condition .= " AND (f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
            $search_term = "%$search%";
            $search_params = [$search_term, $search_term, $search_term];
        }
        
        // Query for export data
        $export_sql = "SELECT f.*, h.*, c.commodity_name, b.barangay_name,
                      CASE WHEN rsbsa.farmer_id IS NOT NULL THEN 'Yes' ELSE 'No' END as rsbsa_registered,
                      CASE WHEN ncfrs.farmer_id IS NOT NULL THEN 'Yes' ELSE 'No' END as ncfrs_registered,
                      COALESCE(ncfrs.ncfrs_registration_number, '') as ncfrs_number,
                      CASE WHEN fisherfolk.farmer_id IS NOT NULL THEN 'Yes' ELSE 'No' END as fisherfolk_registered,
                      COALESCE(fisherfolk.fisherfolk_registration_number, '') as fisherfolk_number,
                      COALESCE(fisherfolk.vessel_id, '') as vessel_id
                      FROM farmers f 
                      LEFT JOIN household_info h ON f.farmer_id = h.farmer_id 
                      LEFT JOIN commodities c ON f.commodity_id = c.commodity_id 
                      LEFT JOIN barangays b ON f.barangay_id = b.barangay_id 
                      LEFT JOIN rsbsa_registered_farmers rsbsa ON f.farmer_id = rsbsa.farmer_id
                      LEFT JOIN ncfrs_registered_farmers ncfrs ON f.farmer_id = ncfrs.farmer_id
                      LEFT JOIN fisherfolk_registered_farmers fisherfolk ON f.farmer_id = fisherfolk.farmer_id
                      $search_condition
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
        
        // Add header
        $html = '<div class="header">
            <div class="title">FARMERS DATABASE EXPORT</div>
            <div class="subtitle">Generated on: ' . date('F d, Y H:i:s') . '</div>
            <div class="subtitle">Total Records: ' . $export_result->num_rows . '</div>
        </div>';
        
        // Start table
        $html .= '<table>
            <thead>
                <tr>
                    <th>ID</th>
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
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $row['suffix']);
            
            $html .= '<tr>
                <td class="text-center">' . formatData($row['farmer_id']) . '</td>
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
                <td class="text-right">' . formatData($row['land_area_hectares'], 'number') . '</td>
                <td class="text-center">' . formatData($row['years_farming']) . '</td>
                <td class="text-center">' . formatData($row['is_member_of_4ps'], 'yesno') . '</td>
                <td class="text-center">' . formatData($row['is_ip'], 'yesno') . '</td>
                <td class="text-center">' . formatData($row['rsbsa_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['ncfrs_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['fisherfolk_registered'], 'badge') . '</td>
                <td class="text-center">' . formatData($row['registration_date'], 'datetime') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        
        // Add footer
        $html .= '<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #7f8c8d;">
            <p>Agriculture System - Farmers Database Export</p>
            <p>This report contains ' . $export_result->num_rows . ' farmer records</p>
        </div>';
        
        $pdf->addHTML($html);
        $pdf->output('farmers_export_' . date('Y-m-d_H-i-s'));
        
    } catch (Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
}
?>
