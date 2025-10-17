<?php
function logActivity($conn, $action, $action_type, $details = "") {
    if (!isset($_SESSION["user_id"])) return false;
    
    $user_id = (int)$_SESSION["user_id"];
    $query = "INSERT INTO activity_logs (staff_id, action, action_type, details) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $action, $action_type, $details);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}
