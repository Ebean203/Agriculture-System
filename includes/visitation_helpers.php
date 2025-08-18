<?php
/**
 * Helper functions for handling visitation dates
 */

/**
 * Format visitation date for display
 * Converts NULL to 'N/A' and formats actual dates
 */
function formatVisitationDate($visitation_date) {
    if ($visitation_date === NULL || $visitation_date === '') {
        return 'N/A';
    }
    return date('M d, Y', strtotime($visitation_date));
}

/**
 * Check if visitation is required based on date
 */
function isVisitationRequired($visitation_date) {
    return $visitation_date !== NULL && $visitation_date !== '';
}

/**
 * Get visitation status text
 */
function getVisitationStatus($visitation_date) {
    if ($visitation_date === NULL || $visitation_date === '') {
        return 'Not Required';
    }
    
    $today = date('Y-m-d');
    if ($visitation_date < $today) {
        return 'Overdue';
    } elseif ($visitation_date == $today) {
        return 'Due Today';
    } else {
        return 'Scheduled';
    }
}
?>
