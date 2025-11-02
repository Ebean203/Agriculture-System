<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';

// Include name helpers if available, otherwise define a fallback
if (file_exists('includes/name_helpers.php')) {
    require_once 'includes/name_helpers.php';
}

// Fallback function if name_helpers.php doesn't have it
if (!function_exists('formatFullName')) {
    function formatFullName($first_name, $middle_name, $last_name, $suffix = '') {
        $name_parts = [$first_name];
        
        if (!empty($middle_name)) {
            $name_parts[] = $middle_name;
        }
        
        $name_parts[] = $last_name;
        
        $full_name = implode(' ', $name_parts);
        
        if (!empty($suffix)) {
            $full_name .= ' ' . $suffix;
        }
        
        return $full_name;
    }
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['activity_id'])) {
        echo json_encode(['success' => false, 'message' => 'Activity ID is required']);
        exit;
    }

    $activity_id = (int)$_GET['activity_id'];

    // Get activity details
    $activity_query = "SELECT title, activity_date, location FROM mao_activities WHERE activity_id = ?";
    $activity_stmt = $conn->prepare($activity_query);
    
    if (!$activity_stmt) {
        throw new Exception('Failed to prepare activity query: ' . $conn->error);
    }
    
    $activity_stmt->bind_param('i', $activity_id);
    if (!$activity_stmt->execute()) {
        throw new Exception('Failed to execute activity query: ' . $activity_stmt->error);
    }
    $activity_result = $activity_stmt->get_result();

    if ($activity_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Activity not found']);
        exit;
    }

    $activity = $activity_result->fetch_assoc();

    // Get attendance records with farmer details
    $attendance_query = "
        SELECT 
            maa.attendance_id,
            maa.time_in AS registered_at,
            maa.farmer_id AS fallback_farmer_id,
            f.farmer_id,
            f.first_name,
            f.middle_name,
            f.last_name,
            f.suffix,
            f.contact_number
        FROM mao_activity_attendance maa
        LEFT JOIN farmers f ON maa.farmer_id = f.farmer_id
        WHERE maa.activity_id = ?
        ORDER BY maa.time_in DESC
    ";

    $attendance_stmt = $conn->prepare($attendance_query);
    
    if (!$attendance_stmt) {
        throw new Exception('Failed to prepare attendance query: ' . $conn->error);
    }
    
    $attendance_stmt->bind_param('i', $activity_id);
    if (!$attendance_stmt->execute()) {
        throw new Exception('Failed to execute attendance query: ' . $attendance_stmt->error);
    }
    $attendance_result = $attendance_stmt->get_result();

    $attendees = [];
    $allowedSuffixes = ['JR', 'SR', 'II', 'III', 'IV', 'V', 'VI'];

    while ($row = $attendance_result->fetch_assoc()) {
        $hasFarmer = !empty($row['farmer_id']);
        $farmerId = $hasFarmer ? $row['farmer_id'] : ($row['fallback_farmer_id'] ?? '');

        $first = trim((string)($row['first_name'] ?? ''));
        $middle = trim((string)($row['middle_name'] ?? ''));
        $last = trim((string)($row['last_name'] ?? ''));
        $suffixRaw = trim((string)($row['suffix'] ?? ''));

        $nameParts = array_filter([$first, $middle, $last], static function ($part) {
            return $part !== '';
        });

        $displaySuffix = '';
        if ($suffixRaw !== '' && strcasecmp($suffixRaw, 'N/A') !== 0) {
            $normalizedSuffix = strtoupper(str_replace('.', '', $suffixRaw));
            if (in_array($normalizedSuffix, $allowedSuffixes, true)) {
                // Standardize suffix presentation (JR/SR -> Jr./Sr.)
                if ($normalizedSuffix === 'JR') {
                    $displaySuffix = 'Jr.';
                } elseif ($normalizedSuffix === 'SR') {
                    $displaySuffix = 'Sr.';
                } else {
                    $displaySuffix = $normalizedSuffix;
                }
            }
        }

        $fullName = implode(' ', $nameParts);
        if ($displaySuffix !== '') {
            $fullName = trim($fullName . ' ' . $displaySuffix);
        }

        if ($fullName === '') {
            $fullName = $farmerId !== '' ? ('Farmer ' . $farmerId) : 'Unknown Farmer';
        }

        $attendees[] = [
            'attendance_id' => $row['attendance_id'],
            'full_name' => $fullName,
            'registered_at' => $row['registered_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'activity' => [
            'title' => $activity['title'],
            'activity_date' => $activity['activity_date'],
            'location' => $activity['location']
        ],
        'attendees' => $attendees,
        'total_count' => count($attendees)
    ]);

    $activity_stmt->close();
    $attendance_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

