<!-- Simple Notification Dropdown JavaScript - Include this file in any page that needs notification functionality -->
<?php
// Ensure notification system is included
if (!function_exists('getNotifications')) {
    require_once __DIR__ . '/notification_system.php';
}
?>

<script>
// Simple notification dropdown system
window.notificationDropdown = {
    isOpen: false,
    
    toggle: function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },
    
    open: function() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.remove('hidden');
        this.isOpen = true;
        this.loadNotifications();
    },
    
    close: function() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.add('hidden');
        this.isOpen = false;
    },
    
    loadNotifications: function() {
        const notificationList = document.getElementById('notificationList');
        
        fetch('get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayNotifications(data.notifications);
                    this.updateNotificationCount(data.total_count);
                } else {
                    notificationList.innerHTML = `
                        <div class="p-4 text-center">
                            <i class="fas fa-exclamation-triangle text-red-400 mb-2"></i>
                            <p class="text-sm text-gray-500">Error loading notifications</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = `
                    <div class="p-4 text-center">
                        <i class="fas fa-exclamation-triangle text-red-400 mb-2"></i>
                        <p class="text-sm text-gray-500">Network error</p>
                    </div>
                `;
            });
    },
    
    displayNotifications: function(notifications) {
        const notificationList = document.getElementById('notificationList');
        
        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="p-4 text-center">
                    <i class="fas fa-check-circle text-green-400 text-xl mb-2"></i>
                    <p class="text-sm text-gray-600">No notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach((notification, index) => {
            const urgencyClass = this.getUrgencyClass(notification.type);
            const iconClass = this.getIconClass(notification.category, notification.type);
            
            // Prepare data for click handler
            const inputId = notification.data && notification.data.input_id ? notification.data.input_id : '';
            const itemName = notification.data && notification.data.item_name ? notification.data.item_name : '';
            const farmerName = notification.data && notification.data.farmer_name ? notification.data.farmer_name : '';
            const farmerId = notification.data && notification.data.farmer_id ? notification.data.farmer_id : '';
            
            html += `
                <div class="notification-item p-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer ${index === notifications.length - 1 ? 'border-b-0' : ''}"
                     data-notification-id="${notification.id}"
                     data-category="${notification.category}"
                     data-item-name="${itemName}"
                     data-input-id="${inputId}"
                     data-farmer-name="${farmerName}"
                     data-farmer-id="${farmerId}"
                     onclick="handleNotificationClick('${notification.id}', '${notification.category}', '${itemName}', '${inputId}', '${farmerName}', '${farmerId}')">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-xs font-medium text-gray-900">${notification.title}</p>
                                <span class="text-xs px-2 py-1 rounded-full ${urgencyClass.badge}">${notification.type.toUpperCase()}</span>
                            </div>
                            <p class="text-xs text-gray-700 leading-relaxed">${notification.message}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-clock mr-1"></i>
                                ${notification.date}
                            </p>
                        </div>
                    </div>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
    },
    
    getUrgencyClass: function(type) {
        switch(type) {
            case 'urgent':
                return { badge: 'bg-red-100 text-red-800' };
            case 'warning':
                return { badge: 'bg-yellow-100 text-yellow-800' };
            case 'info':
                return { badge: 'bg-green-100 text-green-800' };
            default:
                return { badge: 'bg-gray-100 text-gray-800' };
        }
    },
    
    getIconClass: function(category, type) {
        if (category === 'visitation') {
            return 'fas fa-calendar-check text-green-600';
        } else if (category === 'inventory') {
            if (type === 'urgent') {
                return 'fas fa-exclamation-triangle text-red-600';
            } else {
                return 'fas fa-info-circle text-yellow-600';
            }
        }
        return 'fas fa-info-circle text-blue-600';
    },
    
    updateNotificationBadge: function(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    },
    
    updateNotificationCount: function(count) {
        const countEl = document.getElementById('notificationCount');
        if (countEl) {
            countEl.textContent = count;
        }
        this.updateNotificationBadge(count);
    },
    
    // Auto-refresh notifications every 5 minutes
    startAutoRefresh: function() {
        setInterval(() => {
            fetch('get_notifications.php?count_only=true')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Use critical count for badge, but show total in dropdown
                        this.updateNotificationBadge(data.critical_count || data.unread_count);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing notification count:', error);
                });
        }, 300000); // 5 minutes
    },
    
    // Load notification count on page load
    init: function() {
        fetch('get_notifications.php?count_only=true')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Use critical count for badge, but show total in dropdown
                    this.updateNotificationBadge(data.critical_count || data.unread_count);
                }
            })
            .catch(error => {
                console.error('Error loading notification count:', error);
            });
        
        this.startAutoRefresh();
    }
};

// Global function for the notification bell click
function toggleNotificationDropdown() {
    window.notificationDropdown.toggle();
}

// Initialize notification system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.notificationDropdown.init();
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const bellButton = document.querySelector('[onclick="toggleNotificationDropdown()"]');
    
    // Check if click is outside both dropdown and bell button
    if (!dropdown.contains(event.target) && 
        !bellButton.contains(event.target) && 
        window.notificationDropdown.isOpen) {
        window.notificationDropdown.close();
    }
});

// Global function for index.php notification button
function toggleNotificationDropdown() {
    window.notificationDropdown.toggle();
}

// Global click handler functions for notifications
function handleNotificationClick(notificationId, category, itemName, inputId, farmerName, farmerId) {
    console.log('Notification clicked:', {
        notificationId: notificationId,
        category: category,
        itemName: itemName,
        inputId: inputId,
        farmerName: farmerName,
        farmerId: farmerId
    });
    
    // Close notification dropdown
    window.notificationDropdown.close();
    
    // Navigate to appropriate page with specific item focus
    navigateToNotificationPage(category, itemName, inputId, farmerName, farmerId);
}

function navigateToNotificationPage(category, itemName, inputId, farmerName, farmerId) {
    console.log('Navigating to:', {
        category: category,
        itemName: itemName,
        inputId: inputId,
        farmerName: farmerName,
        farmerId: farmerId
    });
    
    // Navigate based on notification category with smart routing
    switch(category) {
        case 'inventory':
            // Redirect to inventory management page with specific item highlight
            if (inputId && inputId !== '') {
                console.log('Redirecting to inventory with inputId:', inputId);
                // Navigate to inventory page and highlight specific item
                window.location.href = 'mao_inventory.php?highlight=' + inputId + '&item=' + encodeURIComponent(itemName);
            } else {
                console.log('Redirecting to inventory with search:', itemName);
                // Search by item name if no ID available
                window.location.href = 'mao_inventory.php?search=' + encodeURIComponent(itemName);
            }
            break;
        case 'visitation':
            console.log('Redirecting to visitation records with farmer:', farmerName, 'ID:', farmerId);
            // Redirect to input distribution records page with farmer filter
            // Prefer farmer_id for exact matching, fallback to farmer name
            if (farmerId && farmerId !== '') {
                window.location.href = 'input_distribution_records.php?farmer_id=' + farmerId + '&farmer=' + encodeURIComponent(farmerName);
            } else if (farmerName && farmerName !== '') {
                window.location.href = 'input_distribution_records.php?farmer=' + encodeURIComponent(farmerName);
            } else if (itemName && itemName !== '') {
                window.location.href = 'input_distribution_records.php?search=' + encodeURIComponent(itemName);
            } else {
                window.location.href = 'input_distribution_records.php';
            }
            break;
        case 'farmer':
            console.log('Redirecting to farmers page with farmer:', farmerName, 'ID:', farmerId);
            // Redirect to farmers page with farmer filter
            if (farmerId && farmerId !== '') {
                window.location.href = 'farmers.php?farmer_id=' + farmerId;
            } else if (farmerName && farmerName !== '') {
                window.location.href = 'farmers.php?search=' + encodeURIComponent(farmerName);
            } else {
                window.location.href = 'farmers.php';
            }
            break;
        case 'yield':
            console.log('Redirecting to yield monitoring with farmer:', farmerName, 'ID:', farmerId);
            // Redirect to yield monitoring page with farmer filter
            if (farmerId && farmerId !== '') {
                window.location.href = 'yield_monitoring.php?farmer_id=' + farmerId + '&farmer=' + encodeURIComponent(farmerName);
            } else if (farmerName && farmerName !== '') {
                window.location.href = 'yield_monitoring.php?farmer=' + encodeURIComponent(farmerName);
            } else {
                window.location.href = 'yield_monitoring.php';
            }
            break;
        case 'activity':
            console.log('Redirecting to MAO Activities with activity id:', inputId || itemName);
            // Prefer activity_id (sent as inputId) then fallback to title search
            if (inputId && inputId !== '') {
                window.location.href = 'mao_activities.php?activity_id=' + inputId;
            } else if (itemName && itemName !== '') {
                window.location.href = 'mao_activities.php?search=' + encodeURIComponent(itemName);
            } else {
                window.location.href = 'mao_activities.php';
            }
            break;
        default:
            console.log('Redirecting to dashboard (default)');
            // Default to dashboard
            window.location.href = 'index.php';
    }
}
</script>

<style>
/* Notification dropdown styles */
#notificationDropdown {
    animation: slideDown 0.2s ease-out;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

#notificationDropdown.hidden {
    display: none !important;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-item:hover {
    background-color: #f9fafb;
    transition: background-color 0.2s ease;
}

/* Ensure dropdown is above everything */
#notificationDropdown {
    z-index: 99999 !important;
}
</style>
