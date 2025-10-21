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
    
    // Verify farmer exists
    $farmer_check = $conn->prepare("SELECT farmer_id, CONCAT(first_name, ' ', middle_name, ' ', last_name) as full_name FROM farmers WHERE farmer_id = ? AND archived = 0");
    $farmer_check->bind_param("s", $farmer_id);
    $farmer_check->execute();
    $farmer_result = $farmer_check->get_result();
    
    if ($farmer_result->num_rows === 0) {
        throw new Exception('Farmer not found or is archived');
    }
    
    $farmer_data = $farmer_result->fetch_assoc();
    
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

    // Insert photo record into database
    $stmt = $conn->prepare("INSERT INTO farmer_photos (farmer_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $farmer_id, $relative_path);

    if (!$stmt->execute()) {
        // If database insert fails, remove the uploaded file
        unlink($upload_path);
        throw new Exception('Failed to save photo information to database');
    }
    
    // Log the activity
    if (isset($_SESSION['user_id'])) {
        logActivity($conn, $_SESSION['user_id'], 'farmer', 'Photo uploaded for farmer: ' . $farmer_data['full_name']);
    }
    
    // Success response
    $_SESSION['success_message'] = "Photo uploaded successfully for farmer: " . $farmer_data['full_name'];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Photo uploaded successfully!',
        'farmer_name' => $farmer_data['full_name'],
        'photo_path' => $relative_path
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