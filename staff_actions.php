<?php
session_start();
require_once 'check_session.php';
require_once 'conn.php';
require_once 'includes/activity_logger.php';

// Only admin can manage staff
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. Only administrators can manage staff.";
    header("Location: staff.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_staff') {
        // Add new staff member
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '');
        $role_id = $_POST['role_id'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($position) || empty($contact_number) || empty($role_id) || empty($username) || empty($password)) {
            $_SESSION['error'] = "Please fill in all required fields.";
            header("Location: staff.php");
            exit();
        }
        
        // Validate username uniqueness
        $check_username = "SELECT staff_id FROM mao_staff WHERE username = ?";
        $check_stmt = mysqli_prepare($conn, $check_username);
        mysqli_stmt_bind_param($check_stmt, "s", $username);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
    if ($check_result->num_rows > 0) {
            $_SESSION['error'] = "Username already exists. Please choose a different username.";
            header("Location: staff.php");
            exit();
        }
        
        // Validate role exists
        $role_check = "SELECT role FROM roles WHERE role_id = ?";
        $role_stmt = mysqli_prepare($conn, $role_check);
        mysqli_stmt_bind_param($role_stmt, "i", $role_id);
        mysqli_stmt_execute($role_stmt);
        $role_result = mysqli_stmt_get_result($role_stmt);
        
    if ($role_result->num_rows === 0) {
            $_SESSION['error'] = "Selected role not found.";
            header("Location: staff.php");
            exit();
        }
        
    $role_data = $role_result->fetch_assoc();
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new staff member
        $insert_query = "INSERT INTO mao_staff (first_name, last_name, position, contact_number, role_id, username, password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "sssssss", $first_name, $last_name, $position, $contact_number, $role_id, $username, $password_hash);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $new_staff_id = mysqli_insert_id($conn);
            
            // Log activity
            $staff_name = $first_name . ' ' . $last_name;
            logActivity($conn, "Added new staff member: $staff_name", 'staff', "Staff ID: $new_staff_id, Position: $position, Role: {$role_data['role']}");
            
            $_SESSION['success'] = "Staff member added successfully.";
        } else {
            $_SESSION['error'] = "Failed to add staff member. Please try again. Error: " . mysqli_error($conn);
        }
        
    } else {
        $_SESSION['error'] = "Invalid action.";
    }
    
} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: staff.php");
exit();
?>