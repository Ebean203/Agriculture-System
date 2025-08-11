<?php
function logActivity($conn, $action, $action_type, $details = "") {
    if (!isset($_SESSION["user_id"])) return false;
    
    $user_id = (int)$_SESSION["user_id"];
    $action = mysqli_real_escape_string($conn, $action);
    $action_type = mysqli_real_escape_string($conn, $action_type);
    $details = mysqli_real_escape_string($conn, $details);
    
    $query = "INSERT INTO activity_logs (user_id, action, action_type, details) 
              VALUES ($user_id, '$action', '$action_type', '$details')";
    
    return mysqli_query($conn, $query);
}
