<?php
require_once 'conn.php';
require_once 'check_session.php';
class SimplePDF {
    private $content = '';
    private $title = '';
    public function __construct($title = 'Document') { $this->title = $title; }
    public function addHTML($html) { $this->content .= $html; }
    public function output($filename = 'document.pdf') {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $this->title . '</title><style>';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; color: #2c3e50; }';
        $html .= '.header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #16a34a; padding-bottom: 15px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; margin: -20px -20px 30px -20px; padding: 20px; }';
        $html .= '.title { font-size: 28px; font-weight: bold; margin-bottom: 8px; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }';
        $html .= '.subtitle { font-size: 16px; opacity: 0.9; margin-bottom: 5px; }';
        $html .= '.export-info { font-size: 14px; opacity: 0.8; margin-top: 10px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }';
        $html .= 'th { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 12px 8px; text-align: left; font-weight: bold; border: 1px solid #15803d; font-size: 11px; }';
        $html .= 'td { padding: 10px 8px; border: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }';
        $html .= 'tr:nth-child(even) { background-color: #f8fafc; }';
        $html .= 'tr:hover { background-color: #dcfce7; }';
        $html .= '.text-center { text-align: center; } .text-right { text-align: right; }';
        $html .= '.badge-completed { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; padding: 3px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }';
        $html .= '.badge-pending { background: linear-gradient(135deg, #facc15 0%, #eab308 100%); color: #92400e; padding: 3px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }';
        $html .= '.badge-rescheduled, .badge-scheduled { background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%); color: #1e40af; padding: 3px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }';
        $html .= '.badge-cancelled { background: linear-gradient(135deg, #e5e7eb 0%, #9ca3af 100%); color: #374151; padding: 3px 8px; border-radius: 12px; font-size: 9px; font-weight: bold; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }';
        $html .= '.footer { margin-top: 30px; text-align: center; font-size: 11px; color: #6b7280; border-top: 2px solid #16a34a; padding-top: 15px; }';
        $html .= '.summary-stats { background: #f0fdf4; border: 1px solid #16a34a; border-radius: 8px; padding: 15px; margin: 20px 0; display: flex; justify-content: space-around; text-align: center; }';
        $html .= '.stat-item { flex: 1; } .stat-number { font-size: 24px; font-weight: bold; color: #16a34a; } .stat-label { font-size: 12px; color: #6b7280; margin-top: 5px; }';
        $html .= '@media print { body { margin: 0; } .no-print { display: none; } } @page { size: A4 landscape; margin: 0.5in; }';
        $html .= '</style></head><body>' . $this->content . '</body></html>';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        echo $html;
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'export_distribution_pdf') {
    try {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $barangay_filter = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
        $input_filter = isset($_GET['input_id']) ? trim($_GET['input_id']) : '';
        $search_condition = 'WHERE 1=1';
        $search_params = [];
        if (!empty($search)) {
            $search_condition .= " AND (f.first_name LIKE ? OR f.middle_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ? OR CONCAT(f.first_name, ' ', COALESCE(f.middle_name, ''), ' ', f.last_name) LIKE ? OR CONCAT(f.first_name, CASE WHEN f.middle_name IS NOT NULL AND LOWER(f.middle_name) NOT IN ('n/a', 'na', '') THEN CONCAT(' ', f.middle_name) ELSE '' END, ' ', f.last_name, CASE WHEN f.suffix IS NOT NULL AND LOWER(f.suffix) NOT IN ('n/a', 'na', '') THEN CONCAT(' ', f.suffix) ELSE '' END) LIKE ? )";
            $search_term = "%$search%";
            $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
        }
        if (!empty($barangay_filter)) {
            $search_condition .= " AND f.barangay_id = ?";
            $search_params[] = $barangay_filter;
        }
        if (!empty($input_filter)) {
            $search_condition .= " AND mdl.input_id = ?";
            $search_params[] = $input_filter;
        }
        $sql = "SELECT mdl.log_id, mdl.date_given, mdl.quantity_distributed, mdl.visitation_date, mdl.status, f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix, f.contact_number, b.barangay_name, ic.input_name, ic.unit FROM mao_distribution_log mdl INNER JOIN farmers f ON mdl.farmer_id = f.farmer_id INNER JOIN input_categories ic ON mdl.input_id = ic.input_id LEFT JOIN barangays b ON f.barangay_id = b.barangay_id $search_condition ORDER BY mdl.date_given DESC, mdl.log_id DESC";
        if (!empty($search_params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }
        $pdf = new SimplePDF('Input Distribution Records Export');
        $html = '<div class="header"><div class="title">🌾 LAGONGLONG FARMS</div><div class="subtitle">INPUT DISTRIBUTION RECORDS</div><div class="export-info">Generated on: ' . date('F d, Y g:i A') . ' <br> Total Records: ' . $result->num_rows . '</div></div>';
        $total_records = $result->num_rows;
        $html .= '<div class="summary-stats"><div class="stat-item"><div class="stat-number">' . $total_records . '</div><div class="stat-label">Total Records</div></div><div class="stat-item"><div class="stat-number">' . date('Y') . '</div><div class="stat-label">Export Year</div></div><div class="stat-item"><div class="stat-number">' . date('M d') . '</div><div class="stat-label">Export Date</div></div></div>';
        $html .= '<table><thead><tr><th>ID</th><th>Farmer Name</th><th>Contact</th><th>Barangay</th><th>Input</th><th>Quantity</th><th>Given Date</th><th>Visit Date</th><th>Status</th></tr></thead><tbody>';
        while ($row = $result->fetch_assoc()) {
            $suffix = isset($row['suffix']) ? trim($row['suffix']) : '';
            if (in_array(strtolower($suffix), ['n/a', 'na','N/A', 'n/A','NA','N/a'])) { $suffix = ''; }
            $full_name = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ' ' . $suffix);
            $status = $row['status'];
            $status_label = ucfirst($status);
            $badge_class = 'badge-' . strtolower($status_label);
            $html .= '<tr>';
            $html .= '<td>#' . htmlspecialchars($row['log_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($full_name) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['contact_number']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['barangay_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($row['input_name']) . '</td>';
            $html .= '<td>' . number_format($row['quantity_distributed']) . ' ' . htmlspecialchars($row['unit']) . '</td>';
            $html .= '<td>' . ($row['date_given'] ? date('M d, Y', strtotime($row['date_given'])) : '-') . '</td>';
            $html .= '<td>' . ($row['visitation_date'] ? date('M d, Y', strtotime($row['visitation_date'])) : '-') . '</td>';
            $html .= '<td><span class="' . $badge_class . '">' . $status_label . '</span></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<div class="footer"><div style="font-weight: bold; color: #16a34a;">🌾 LAGONGLONG FARMS - Agriculture System</div><div style="margin-top: 8px;">This report contains ' . $result->num_rows . ' distribution records exported on ' . date('F d, Y') . '</div><div style="margin-top: 5px; font-style: italic;">Confidential - For Official Use Only</div></div>';
        $pdf->addHTML($html);
        $pdf->output('distribution_export_' . date('Y-m-d_H-i-s'));
    } catch (Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
}
?>
