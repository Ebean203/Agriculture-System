<?php
require_once 'check_session.php';
require_once 'conn.php';

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$saved_reports_sql = "SELECT r.report_id, r.report_type, r.start_date, r.end_date, r.file_path, r.timestamp,
                      s.first_name, s.last_name
                      FROM generated_reports r
                      INNER JOIN mao_staff s ON r.staff_id = s.staff_id
                      ORDER BY r.timestamp DESC";
$saved_reports_result = $conn->query($saved_reports_sql);

$html = '';
if ($saved_reports_result && $saved_reports_result->num_rows > 0) {
    while ($report = $saved_reports_result->fetch_assoc()) {
        $html .= '<div class="recent-report-item border border-gray-200 rounded-md p-3 hover:shadow-sm transition-all">';
        $html .= '<div class="flex items-start justify-between">';
        $html .= '<div class="flex-1">';
        $html .= '<div class="font-medium text-gray-900 text-sm mb-1">' . ucfirst(str_replace('_', ' ', $report['report_type'])) . '</div>';
        $html .= '<div class="text-xs text-gray-600 mb-1"><i class="fas fa-calendar-alt mr-1"></i>' . date('M j', strtotime($report['start_date'])) . ' - ' . date('M j, Y', strtotime($report['end_date'])) . '</div>';
        $html .= '<div class="text-xs text-gray-500"><i class="fas fa-user mr-1"></i>' . htmlspecialchars($report['first_name'] . ' ' . $report['last_name']) . '<span class="mx-1">•</span><i class="fas fa-clock mr-1"></i>' . date('M j, h:i A', strtotime($report['timestamp'])) . '</div>';
        $html .= '</div></div>';
        $html .= '<div class="mt-2 pt-2 border-t border-gray-100">';
        $html .= '<a href="' . htmlspecialchars($report['file_path']) . '" target="_blank" class="inline-flex items-center text-agri-green hover:text-agri-dark text-xs font-medium transition-colors"><i class="fas fa-external-link-alt mr-1"></i>View Report</a>';
        $html .= '</div></div>';
    }
} else {
    $html = '<div class="text-gray-500 text-sm">No reports found.</div>';
}

echo $html;
