<?php
require_once 'conn.php';
require_once 'check_session.php';
require_once __DIR__ . '/includes/name_helpers.php';

function imageToBase64($path) {
    if (!file_exists($path)) {
        return '';
    }
    $type = mime_content_type($path);
    $data = file_get_contents($path);
    return 'data:' . $type . ';base64,' . base64_encode($data);
}

class SimplePDFPreview {
    private $content = '';
    private $title = '';

    public function __construct($title = 'Document') {
        $this->title = $title;
    }

    public function addHTML($html) {
        $this->content .= $html;
    }

    public function output() {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($this->title) . '</title>
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
            color: #222;
            margin: -20px -20px 30px -20px;
            padding: 20px;
        }
        .title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
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
        .activity-meta {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        .activity-meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        .activity-meta-item {
            flex: 1;
            text-align: center;
        }
        .meta-label {
            color: #64748b;
            font-size: 11px;
            margin-bottom: 4px;
        }
        .meta-value {
            color: #0f172a;
            font-size: 13px;
            font-weight: 600;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            font-size: 11px;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
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
        .stat-item { flex: 1; }
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
        .preview-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: #15803d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            font-family: Arial, sans-serif;
            font-size: 13px;
        }
        .preview-toolbar .toolbar-title {
            font-weight: bold;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        .preview-toolbar .toolbar-actions {
            display: flex;
            gap: 10px;
        }
        .preview-toolbar button {
            padding: 7px 18px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-print {
            background: white;
            color: #15803d;
        }
        .btn-print:hover {
            background: #f0fdf4;
        }
        .btn-close {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.4) !important;
        }
        .btn-close:hover {
            background: rgba(255,255,255,0.25);
        }
        @media print {
            body { margin: 0; padding-top: 0 !important; }
            .no-print,
            .preview-toolbar,
            .preview-toolbar * {
                display: none !important;
                visibility: hidden !important;
            }
        }
        @page {
            size: A4 portrait;
            margin: 0.5in;
        }
    </style>
</head>
<body style="padding-top: 56px;">
<div class="preview-toolbar no-print">
    <span class="toolbar-title">&#127807; PDF Preview &mdash; ' . htmlspecialchars($this->title) . '</span>
    <div class="toolbar-actions">
        <button class="btn-print" onclick="window.print()">&#128424; Print / Save as PDF</button>
        <button class="btn-close" onclick="window.close()">&#10005; Close Tab</button>
    </div>
</div>
' . $this->content . '
</body>
</html>';

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}

try {
    if (!isset($_GET['activity_id'])) {
        throw new Exception('Activity ID is required');
    }

    $activity_id = (int)$_GET['activity_id'];
    if ($activity_id <= 0) {
        throw new Exception('Invalid activity ID');
    }

    $activityStmt = $conn->prepare('SELECT title, activity_date, location FROM mao_activities WHERE activity_id = ? LIMIT 1');
    if (!$activityStmt) {
        throw new Exception('Failed to prepare activity query: ' . $conn->error);
    }
    $activityStmt->bind_param('i', $activity_id);
    $activityStmt->execute();
    $activityRes = $activityStmt->get_result();
    if ($activityRes->num_rows === 0) {
        throw new Exception('Activity not found');
    }
    $activity = $activityRes->fetch_assoc();

    $attendanceStmt = $conn->prepare(
        'SELECT maa.time_in AS registered_at, maa.farmer_id AS fallback_farmer_id,
                f.farmer_id, f.first_name, f.middle_name, f.last_name, f.suffix, f.contact_number, b.barangay_name
         FROM mao_activity_attendance maa
         LEFT JOIN farmers f ON maa.farmer_id = f.farmer_id
         LEFT JOIN barangays b ON f.barangay_id = b.barangay_id
         WHERE maa.activity_id = ?
         ORDER BY maa.time_in DESC'
    );
    if (!$attendanceStmt) {
        throw new Exception('Failed to prepare attendance query: ' . $conn->error);
    }
    $attendanceStmt->bind_param('i', $activity_id);
    $attendanceStmt->execute();
    $attendanceRes = $attendanceStmt->get_result();

    $attendees = [];
    while ($row = $attendanceRes->fetch_assoc()) {
        $hasFarmer = !empty($row['farmer_id']);
        $farmerId = $hasFarmer ? $row['farmer_id'] : ($row['fallback_farmer_id'] ?? '');
        $fullName = formatFarmerName($row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '', $row['suffix'] ?? '');
        if ($fullName === '') {
            $fullName = $farmerId !== '' ? ('Farmer ' . $farmerId) : 'Unknown Farmer';
        }

        $registeredOn = '-';
        if (!empty($row['registered_at'])) {
            $ts = strtotime($row['registered_at']);
            if ($ts !== false) {
                $registeredOn = date('M d, Y g:i A', $ts);
            }
        }

        $attendees[] = [
            'full_name' => $fullName,
            'contact_number' => $row['contact_number'] ?? '-',
            'barangay_name' => $row['barangay_name'] ?? '-',
            'registered_on' => $registeredOn
        ];
    }

    $titleText = trim((string)($activity['title'] ?? 'Activity Attendance'));
    $reportTitle = 'Activity Attendance - ' . ($titleText !== '' ? $titleText : 'Activity');
    $pdf = new SimplePDFPreview($reportTitle);

    $logoLeftBase64 = imageToBase64('assets/Logo/Lagonglong_seal_SVG.svg.png');
    $logoRightBase64 = imageToBase64('assets/Logo/E1361954-133F-4560-86CA-E4E3A2D916B8-removebg-preview.png');

    $activityDateText = '-';
    if (!empty($activity['activity_date'])) {
        $dateTs = strtotime($activity['activity_date']);
        if ($dateTs !== false) {
            $activityDateText = date('F d, Y', $dateTs);
        } else {
            $activityDateText = htmlspecialchars((string)$activity['activity_date']);
        }
    }

    $html = '<div class="header" style="position: relative; min-height: 140px; display: flex; align-items: center; justify-content: center; background: none;">'
        . '<img src="' . $logoLeftBase64 . '" alt="Lagonglong Municipal Seal" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); height: 110px; width: auto;">'
        . '<img src="' . $logoRightBase64 . '" alt="Lagonglong Agriculture Logo" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); height: 110px; width: auto;">'
        . '<div style="width: 100%;">'
            . '<div class="title">Lagonglong FARMS</div>'
            . '<div class="subtitle">' . htmlspecialchars($titleText !== '' ? $titleText : 'Activity Attendance') . '</div>'
            . '<div class="export-info">Generated on: ' . date('F d, Y g:i A') . ' <br> Total Attendees: ' . count($attendees) . '</div>'
        . '</div>'
    . '</div>';

    $html .= '<div class="summary-stats">'
        . '<div class="stat-item"><div class="stat-number">' . count($attendees) . '</div><div class="stat-label">Total Attendees</div></div>'
        . '<div class="stat-item"><div class="stat-number">' . date('Y') . '</div><div class="stat-label">Export Year</div></div>'
        . '<div class="stat-item"><div class="stat-number">' . date('M d') . '</div><div class="stat-label">Export Date</div></div>'
    . '</div>';

    $html .= '<table><thead><tr>'
        . '<th style="width: 26%;">Farmer Name</th>'
        . '<th style="width: 15%;">Contact</th>'
        . '<th style="width: 15%;">Barangay</th>'
        . '<th style="width: 15%;">Activity Date</th>'
        . '<th style="width: 14%;">Location</th>'
        . '<th style="width: 15%;">Registered On</th>'
        . '</tr></thead><tbody>';

    if (count($attendees) === 0) {
        $html .= '<tr><td colspan="6" class="text-center" style="padding: 20px; color: #6b7280;">No attendance records found for this activity.</td></tr>';
    } else {
        foreach ($attendees as $attendee) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars($attendee['full_name']) . '</td>'
                . '<td>' . htmlspecialchars((string)$attendee['contact_number']) . '</td>'
                . '<td>' . htmlspecialchars((string)$attendee['barangay_name']) . '</td>'
                . '<td class="text-center">' . htmlspecialchars($activityDateText) . '</td>'
                . '<td class="text-center">' . htmlspecialchars((string)($activity['location'] ?? '-')) . '</td>'
                . '<td class="text-center">' . htmlspecialchars($attendee['registered_on']) . '</td>'
                . '</tr>';
        }
    }

    $html .= '</tbody></table>';
    $html .= '<div class="footer">'
        . '<div style="font-weight: bold; color: #16a34a;">Report generated by: ' . htmlspecialchars($_SESSION['full_name'] ?? 'System User') . '</div>'
        . '<div style="margin-top: 5px; font-style: italic;">Confidential - For Official Use Only</div>'
        . '</div>';

    $pdf->addHTML($html);
    $pdf->output();

    $activityStmt->close();
    $attendanceStmt->close();
    $conn->close();
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error generating attendance export: ' . $e->getMessage();
}
