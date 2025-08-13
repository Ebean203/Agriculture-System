<?php
require_once 'conn.php';

// First, let's create the roles
$roles_sql = "INSERT INTO roles (role) VALUES ('admin'), ('staff')";
if(mysqli_query($conn, $roles_sql)) {
    echo "Roles created successfully\n";
} else {
    echo "Error creating roles: " . mysqli_error($conn) . "\n";
}

// Get the admin role ID
$admin_role_query = "SELECT role_id FROM roles WHERE role = 'admin'";
$result = mysqli_query($conn, $admin_role_query);
$admin_role = mysqli_fetch_assoc($result);
$admin_role_id = $admin_role['role_id'];

// Create admin user with hashed password
$username = 'admin';
$password = password_hash('1234', PASSWORD_DEFAULT);

$admin_sql = "INSERT INTO mao_staff (
    first_name,
    last_name,
    position,
    contact_number,
    username,
    password,
    role_id
) VALUES (
    'System',
    'Administrator',
    'Administrator',
    'N/A',
    '$username',
    '$password',
    $admin_role_id
)";

if(mysqli_query($conn, $admin_sql)) {
    echo "Admin user created successfully\n";
} else {
    echo "Error creating admin user: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
echo "Initial data population complete!\n";
?>
