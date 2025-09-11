# Page Navigation Implementation Guide

## How to Add Navigation to Any Page

To add the navigation component to any page in your Agriculture System, follow these steps:

### 1. Include the Navigation Component

Add this line right after your main content container div and before your page header:

```php
<!-- Page Navigation -->
<?php include 'includes/page_navigation.php'; ?>
```

### 2. Example Implementation

Here's how it should look in your page structure (positioned after page header):

```php
<!-- Main Content -->
<div class="min-h-screen">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Page Header with title and action buttons -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Your Page Title
                    </h1>
                    <p class="text-gray-600 mt-2">Page description</p>
                </div>
                <!-- Action buttons (Add, etc.) -->
                <button class="bg-agri-green text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Add Something
                </button>
            </div>
        </div>
        
        <!-- Page Navigation (positioned after header) -->
        <?php include 'includes/page_navigation.php'; ?>
        
        <!-- Rest of your page content -->
    </div>
</div>
```

### 3. Pages Already Updated

✅ mao_activities.php
✅ mao_inventory.php

### 4. Suggested Pages to Add Navigation To

- [ ] farmers.php
- [ ] rsbsa_records.php
- [ ] ncfrs_records.php
- [ ] fishr_records.php
- [ ] boat_records.php
- [ ] yield_monitoring.php
- [ ] reports.php
- [ ] staff.php
- [ ] input_distribution_records.php
- [ ] all_activities.php

### 5. Features of the Navigation Component

**Back Button:**
- Smart back functionality (uses browser history)
- Falls back to dashboard if no history

**Navigation Dropdown:**
- Organized by sections (Dashboard, MAO Management, Records, etc.)
- Icons for each page
- Hover effects
- Auto-closes when clicking outside

**Responsive Design:**
- Works on mobile and desktop
- Uses Tailwind CSS classes
- Consistent with your existing design

### 6. Customization

To customize the navigation for specific pages, you can:

1. **Highlight Current Page:** Add active state styling
2. **Hide Certain Options:** Conditionally show/hide menu items
3. **Add New Pages:** Simply add new entries to the dropdown sections

### 7. Quick Add Script

Run this in each page you want to add navigation to:

1. Find the main content section
2. Look for the pattern: `<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">`
3. Add the include line right after it
4. Test the page to ensure it works correctly
