<?php
// upload_farmer_photo.php - Handle farmer photo uploads with geo-tagging
ob_start(); // Start output buffering
session_start();

// Clear any previous output
ob_clean();
header('Content-Type: application/json');

// Check session for AJAX requests
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

require_once 'conn.php';
require_once 'includes/activity_logger.php';
require_once __DIR__ . '/includes/name_helpers.php';

function ensureFarmerPhotoGeoColumns($conn) {
    $columns = [
        'latitude' => "ALTER TABLE farmer_photos ADD COLUMN latitude DECIMAL(10,8) NULL",
        'longitude' => "ALTER TABLE farmer_photos ADD COLUMN longitude DECIMAL(11,8) NULL",
        'coordinate_source' => "ALTER TABLE farmer_photos ADD COLUMN coordinate_source VARCHAR(30) NULL"
    ];

    foreach ($columns as $column => $ddl) {
        $columnEscaped = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM farmer_photos LIKE '{$columnEscaped}'");
        if ($res && $res->num_rows === 0) {
            $conn->query($ddl);
        }
    }
}

function exifGpsToDecimal($coord, $hemisphere) {
    if (!is_array($coord) || count($coord) < 3) return null;

    $toFloat = function($value) {
        if (is_string($value) && strpos($value, '/') !== false) {
            list($num, $den) = explode('/', $value, 2);
            if ((float)$den == 0.0) return 0.0;
            return (float)$num / (float)$den;
        }
        return (float)$value;
    };

    $degrees = $toFloat($coord[0]);
    $minutes = $toFloat($coord[1]);
    $seconds = $toFloat($coord[2]);

    $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);
    $hem = strtoupper((string)$hemisphere);
    if ($hem === 'S' || $hem === 'W') {
        $decimal *= -1;
    }
    return $decimal;
}

function extractExifCoordinates($filePath) {
    if (!function_exists('exif_read_data')) {
        return [null, null];
    }

    $exif = @exif_read_data($filePath);
    if (!$exif || empty($exif['GPSLatitude']) || empty($exif['GPSLongitude']) || empty($exif['GPSLatitudeRef']) || empty($exif['GPSLongitudeRef'])) {
        return [null, null];
    }

    $latitude = exifGpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
    $longitude = exifGpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

    if ($latitude === null || $longitude === null) {
        return [null, null];
    }

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        return [null, null];
    }

    return [$latitude, $longitude];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Validate required fields
    if (!isset($_POST['farmer_id']) || empty($_POST['farmer_id'])) {
        throw new Exception('Farmer ID is required');
    }
    
    if (!isset($_FILES['farmer_photo']) || $_FILES['farmer_photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Photo upload is required');
    }
    
    $farmer_id = trim($_POST['farmer_id']);
    $photo = $_FILES['farmer_photo'];
    $latitude = null;
    $longitude = null;
    $coordinate_source = 'none';

    // Optional manual/device coordinates from the client.
    if (isset($_POST['latitude']) && isset($_POST['longitude']) && $_POST['latitude'] !== '' && $_POST['longitude'] !== '') {
        $lat = (float)$_POST['latitude'];
        $lng = (float)$_POST['longitude'];
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            $latitude = $lat;
            $longitude = $lng;
            $coordinate_source = isset($_POST['coordinate_source']) && $_POST['coordinate_source'] !== '' ? trim($_POST['coordinate_source']) : 'manual';
        }
    }
    
    // Verify farmer exists
    $farmer_check = $conn->prepare("SELECT farmer_id, first_name, middle_name, last_name, suffix FROM farmers WHERE farmer_id = ? AND archived = 0");
    $farmer_check->bind_param("s", $farmer_id);
    $farmer_check->execute();
    $farmer_result = $farmer_check->get_result();
    
    if ($farmer_result->num_rows === 0) {
        throw new Exception('Farmer not found or is archived');
    }
    
    $farmer_data = $farmer_result->fetch_assoc();
    $farmer_name = formatFarmerName($farmer_data['first_name'] ?? '', $farmer_data['middle_name'] ?? '', $farmer_data['last_name'] ?? '', $farmer_data['suffix'] ?? '');
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $file_type = $photo['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Only JPEG and PNG images are allowed');
    }
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($photo['size'] > $max_size) {
        throw new Exception('Photo size must be less than 5MB');
    }
    
    // Use original filename with farmer ID prefix for uniqueness
    $original_filename = basename($photo['name']);
    $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $filename = $farmer_id . '_' . $original_filename;
    $upload_path = 'uploads/farmer_photos/' . $filename;
    $relative_path = $upload_path; // Store relative path in database

    // Ensure upload directory exists
    if (!file_exists('uploads/farmer_photos')) {
        mkdir('uploads/farmer_photos', 0755, true);
    }

    // Delete previous photo with same name for this farmer (if exists)
    $prev_stmt = $conn->prepare("SELECT file_path FROM farmer_photos WHERE farmer_id = ? AND file_path = ?");
    $prev_stmt->bind_param("ss", $farmer_id, $relative_path);
    $prev_stmt->execute();
    $prev_result = $prev_stmt->get_result();
    if ($prev_result && $prev_result->num_rows > 0) {
        $prev_photo = $prev_result->fetch_assoc();
        if (file_exists($prev_photo['file_path'])) {
            unlink($prev_photo['file_path']);
        }
        // Remove previous DB record
        $del_stmt = $conn->prepare("DELETE FROM farmer_photos WHERE farmer_id = ? AND file_path = ?");
        $del_stmt->bind_param("ss", $farmer_id, $relative_path);
        $del_stmt->execute();
    }

    // Move uploaded file
    if (!move_uploaded_file($photo['tmp_name'], $upload_path)) {
        $error = error_get_last();
        $msg = 'Failed to save uploaded photo.';
        if ($error) {
            $msg .= ' System error: ' . $error['message'];
        }
        $msg .= ' Check folder permissions and file type.';
        throw new Exception($msg);
    }

    // Fallback to EXIF GPS if coordinates were not supplied by device/manual input.
    if ($latitude === null || $longitude === null) {
        list($exifLat, $exifLng) = extractExifCoordinates($upload_path);
        if ($exifLat !== null && $exifLng !== null) {
            $latitude = $exifLat;
            $longitude = $exifLng;
            $coordinate_source = 'photo_exif';
        }
    }

    // Ensure database can store geo coordinates.
    ensureFarmerPhotoGeoColumns($conn);

    // Insert photo record into database
    $latitudeValue = ($latitude === null) ? '' : (string)$latitude;
    $longitudeValue = ($longitude === null) ? '' : (string)$longitude;
    $stmt = $conn->prepare("INSERT INTO farmer_photos (farmer_id, file_path, uploaded_at, latitude, longitude, coordinate_source) VALUES (?, ?, NOW(), NULLIF(?, ''), NULLIF(?, ''), ?)");
    $stmt->bind_param("sssss", $farmer_id, $relative_path, $latitudeValue, $longitudeValue, $coordinate_source);

    if (!$stmt->execute()) {
        // If database insert fails, remove the uploaded file
        unlink($upload_path);
        throw new Exception('Failed to save photo information to database');
    }
    
    // Log the activity
    if (isset($_SESSION['user_id'])) {
        logActivity($conn, $_SESSION['user_id'], 'farmer', 'Photo uploaded for farmer: ' . $farmer_name);
    }
    
    // Success response
    $_SESSION['success_message'] = "Photo uploaded successfully.";
    
    echo json_encode([
        'success' => true, 
        'message' => 'Photo uploaded successfully!',
        'farmer_name' => $farmer_name,
        'photo_path' => $relative_path,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'coordinate_source' => $coordinate_source
    ]);

} catch (Exception $e) {
    error_log("Photo upload error: " . $e->getMessage());
    
    $_SESSION['error_message'] = "Error uploading photo: " . $e->getMessage();
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// End output buffering and clean exit
ob_end_flush();
exit;