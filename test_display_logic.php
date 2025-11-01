<?php
require_once __DIR__ . '/includes/yield_helpers.php';

// Simulate the exact data structure from the query
$yield_data = [
    4 => [
        '' => null,
        'heads' => 900.00
    ]
];

$cat_id = 4;
$units = isset($yield_data[$cat_id]) ? $yield_data[$cat_id] : [];
$parts = [];

echo "Units array for category 4:\n";
print_r($units);
echo "\n";

if (!empty($units)) {
    echo "Processing units...\n";
    foreach ($units as $unit => $amt) {
        echo "  Unit: '$unit', Amount: " . var_export($amt, true);
        if ($amt > 0) {
            echo " → ADDING to display\n";
            $labelUnit = normalize_unit_label($unit);
            $parts[] = '<span class="text-2xl font-bold">' . number_format($amt, 2) . '</span> <span class="text-base">' . htmlspecialchars($labelUnit) . '</span>';
        } else {
            echo " → SKIPPING (not > 0)\n";
        }
    }
}

echo "\nParts array:\n";
print_r($parts);

echo "\nFinal display: ";
if (empty($parts)) {
    echo "(empty - would show blank for category 4)\n";
} else {
    echo implode('<br>', $parts) . "\n";
}
