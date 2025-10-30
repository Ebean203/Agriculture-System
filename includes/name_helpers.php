<?php
/**
 * Helper functions for name formatting across the application.
 *
 * formatFarmerName($first_name, $middle_name, $last_name, $suffix)
 * - Returns a string formatted as: "Last, First M. [Suffix]"
 * - Middle name is shortened to an initial with a trailing dot if present.
 * - Suffix is appended only when not blank and not one of 'N/A' or 'NA' (case-insensitive).
 */

if (!function_exists('formatFarmerName')) {
    function formatFarmerName($first_name, $middle_name, $last_name, $suffix) {
        // Format as: Last, First M. [Suffix]
        $first = trim((string)$first_name);
        $middle = trim((string)$middle_name);
        $last = trim((string)$last_name);
        $suf = trim((string)$suffix);

        $display = '';
        if (!empty($last)) {
            $display .= $last;
        }

        // Build given name portion
        $given = '';
        if (!empty($first)) {
            $given .= $first;
        }
        if (!empty($middle)) {
            // Use only the first letter of middle name as initial with dot
            $given .= ($given !== '' ? ' ' : '') . strtoupper(substr($middle, 0, 1)) . '.';
        }

        if ($display !== '' && $given !== '') {
            $display .= ', ' . $given;
        } elseif ($given !== '') {
            $display = $given;
        }

        // Append suffix if meaningful
        if (!empty($suf) && !in_array(strtolower($suf), ['n/a', 'na'])) {
            $display .= ($display !== '' ? ' ' : '') . $suf;
        }

        return trim($display);
    }
}
