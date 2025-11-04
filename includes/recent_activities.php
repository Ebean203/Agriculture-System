<?php
require_once 'activity_icons.php';

// Get recent activities
$query = "
    SELECT 
        al.*,
        CONCAT(ms.first_name, ' ', ms.last_name) as staff_name,
        r.role as staff_role
    FROM activity_logs al
    JOIN mao_staff ms ON al.staff_id = ms.staff_id
    LEFT JOIN roles r ON ms.role_id = r.role_id
    ORDER BY al.timestamp DESC
    LIMIT 5
";
$recent_activities = [];
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_activities[] = $row;
    }
}
mysqli_stmt_close($stmt);
?>

<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-gray-900">
            <i class="fas fa-clock text-agri-green mr-2"></i>Recent Activities
        </h3>
        <a href="all_activities.php" class="text-agri-green hover:text-agri-dark flex items-center">
            <span>See all</span>
            <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="space-y-4">
        <?php if (empty($recent_activities)): ?>
            <p class="text-gray-500 text-center py-4">No recent activities found</p>
        <?php else: ?>
            <?php foreach ($recent_activities as $activity): 
                $iconInfo = getActivityIcon($activity['action_type']);
            ?>
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <div class="p-2 rounded-full mr-3 <?php echo $iconInfo[1]; ?>">
                        <i class="<?php echo $iconInfo[0]; ?>"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($activity['action']); ?>
                        </p>
                        <?php if (!empty($activity['details'])): ?>
                            <p class="text-xs text-gray-500 mb-0">
                                <?php echo htmlspecialchars($activity['details']); ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400">
                            by <?php echo htmlspecialchars($activity['staff_name']); ?> • <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
