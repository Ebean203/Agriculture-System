<?php
// Helper functions for yield aggregation and unit normalization
function normalize_unit_label($unit) {
    if (!$unit) return '';
    $u = strtolower(trim($unit));
    $map = [
        'heads' => 'Heads',
        'head' => 'Heads',
        'sacks' => 'Sacks',
        'sack' => 'Sacks',
        'kg' => 'Kilograms',
        'kgs' => 'Kilograms',
        'kilogram' => 'Kilograms',
        'kilograms' => 'Kilograms',
        'bags' => 'Bags',
        'bag' => 'Bags',
        'tons' => 'Tons',
        'ton' => 'Tons',
        'pieces' => 'Pieces',
        'piece' => 'Pieces'
    ];
    return $map[$u] ?? ucfirst($u);
}

// Aggregate totals grouped by commodity and unit from a DB connection
function aggregate_totals_by_commodity_db($conn, $start_date, $end_date, $barangay_filter = '') {
    $where_barangay = $barangay_filter ? "AND f.barangay_id = ?" : '';
    $sql = "SELECT c.commodity_name, y.unit, SUM(y.yield_amount) as total_yield
            FROM yield_monitoring y
            JOIN commodities c ON y.commodity_id = c.commodity_id
            JOIN farmers f ON y.farmer_id = f.farmer_id
            WHERE DATE(y.record_date) BETWEEN ? AND ? " . $where_barangay . "
            GROUP BY c.commodity_name, y.unit
            ORDER BY total_yield DESC";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        if ($barangay_filter) {
            mysqli_stmt_bind_param($stmt, "sss", $start_date, $end_date, $barangay_filter);
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $out = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $com = $row['commodity_name'];
                $unit = $row['unit'] ?? '';
                $amt = is_numeric($row['total_yield']) ? (float)$row['total_yield'] : 0.0;
                if (!isset($out[$com])) $out[$com] = [];
                if (!isset($out[$com][$unit])) $out[$com][$unit] = 0.0;
                $out[$com][$unit] += $amt;
            }
        }
        mysqli_stmt_close($stmt);
        return $out;
    }
    return [];
}

// Aggregate totals grouped by commodity and unit from an array of records
function aggregate_totals_by_commodity_from_array($records) {
    $out = [];
    foreach ($records as $r) {
        $com = $r['commodity_name'] ?? 'Unknown';
        $unit = $r['unit'] ?? '';
        $amt = is_numeric($r['yield_amount']) ? (float)$r['yield_amount'] : 0.0;
        if (!isset($out[$com])) $out[$com] = [];
        if (!isset($out[$com][$unit])) $out[$com][$unit] = 0.0;
        $out[$com][$unit] += $amt;
    }
    return $out;
}

// Flatten aggregated totals into arrays suitable for charting: returns ['labels'=>[], 'data'=>[]]
function flatten_aggregated_totals_for_chart($aggregated, $limit = 20, $normalize = false) {
    // Convert to list of [commodity, unit, amount]
    $rows = [];
    foreach ($aggregated as $com => $units) {
        foreach ($units as $unit => $amt) {
            $rows[] = ['commodity' => $com, 'unit' => $unit, 'amount' => $amt];
        }
    }
    // Sort by amount desc
    usort($rows, function($a,$b){ return ($b['amount'] <=> $a['amount']); });
    $labels = [];
    $data = [];
    $count = 0;
    foreach ($rows as $r) {
        $unitLabel = $r['unit'] ? ($normalize ? normalize_unit_label($r['unit']) : $r['unit']) : '';
        $labels[] = $r['commodity'] . ($unitLabel ? ' (' . $unitLabel . ')' : '');
        $data[] = $r['amount'];
        $count++;
        if ($count >= $limit) break;
    }
    return ['labels' => $labels, 'data' => $data];
}

?>