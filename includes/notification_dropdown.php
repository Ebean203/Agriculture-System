<?php
// Notification Dropdown Component
// Include this in all pages that need notifications

require_once 'includes/notification_system.php';

function renderNotificationDropdown($conn) {
    $notifications = getNotifications($conn);
    $unread_count = getUnreadNotificationCount($conn);
    
    ob_start();
    ?>
    <!-- Notification Dropdown -->
    <div class="relative" id="notificationDropdown">
        <button onclick="toggleNotifications()" class="text-white hover:text-agri-light transition-colors relative focus:outline-none">
            <i class="fas fa-bell text-lg"></i>
            <?php if ($unread_count > 0): ?>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                    <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                </span>
            <?php endif; ?>
        </button>
        
        <!-- Dropdown Menu -->
        <div id="notificationMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 max-h-96 overflow-y-auto">
            <!-- Header -->
            <div class="bg-agri-green text-white px-4 py-3 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold">Notifications</h3>
                    <span class="text-sm bg-white bg-opacity-20 px-2 py-1 rounded-full">
                        <?php echo count($notifications); ?> total
                    </span>
                </div>
            </div>
            
            <!-- Notification List -->
            <div class="max-h-80 overflow-y-auto">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-gray-500">
                        <i class="fas fa-bell-slash text-2xl mb-2"></i>
                        <p>No notifications at this time</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item border-b border-gray-100 p-3 hover:bg-gray-50 cursor-pointer <?php echo $notification['type']; ?>" 
                             onclick="markAsRead('<?php echo $notification['id']; ?>')">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="<?php echo $notification['icon']; ?> text-lg 
                                        <?php 
                                        switch($notification['type']) {
                                            case 'urgent': echo 'text-red-500'; break;
                                            case 'warning': echo 'text-yellow-500'; break;
                                            case 'info': echo 'text-blue-500'; break;
                                            default: echo 'text-gray-500';
                                        }
                                        ?>"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        <?php 
                                        if ($notification['category'] == 'visitation') {
                                            echo 'Scheduled: ' . date('M j, Y', strtotime($notification['date']));
                                        } else {
                                            echo 'Updated: ' . date('M j, Y', strtotime($notification['date']));
                                        }
                                        ?>
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <?php if ($notification['type'] == 'urgent'): ?>
                                        <span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span>
                                    <?php elseif ($notification['type'] == 'warning'): ?>
                                        <span class="inline-block w-2 h-2 bg-yellow-500 rounded-full"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="border-t border-gray-200 p-3 bg-gray-50 rounded-b-lg">
                <div class="flex justify-between items-center text-sm">
                    <button onclick="markAllAsRead()" class="text-agri-green hover:text-agri-dark font-medium">
                        Mark all as read
                    </button>
                    <a href="notifications.php" class="text-agri-green hover:text-agri-dark font-medium">
                        View all notifications →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleNotifications() {
            const menu = document.getElementById('notificationMenu');
            menu.classList.toggle('hidden');
        }

        function markAsRead(notificationId) {
            fetch('ajax/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count
                    location.reload();
                }
            });
        }

        function markAllAsRead() {
            fetch('ajax/mark_all_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const menu = document.getElementById('notificationMenu');
            
            if (!dropdown.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>

    <style>
        .notification-item.urgent {
            border-left: 4px solid #ef4444;
        }
        .notification-item.warning {
            border-left: 4px solid #f59e0b;
        }
        .notification-item.info {
            border-left: 4px solid #3b82f6;
        }
    </style>
    <?php
    return ob_get_clean();
}
?>
