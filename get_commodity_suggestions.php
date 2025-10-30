<?php
require_once 'conn.php';
header('Content-Type: application/json');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$result = ['success' => false, 'commodities' => []];
if ($q !== '') {
    $limit = 10;
    $foundIds = [];

    // 1) Exact equals first (case-insensitive)
    $sqlExact = "SELECT c.commodity_id, c.commodity_name, cc.category_name
                 FROM commodities c
                 LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id
                 WHERE LOWER(c.commodity_name) = LOWER(?)
                 ORDER BY c.commodity_name LIMIT ?";
    $stmt = mysqli_prepare($conn, $sqlExact);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $q, $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $result['commodities'][] = [
                'commodity_id' => $row['commodity_id'],
                'commodity_name' => $row['commodity_name'],
                'category_name' => $row['category_name']
            ];
            $foundIds[] = (int)$row['commodity_id'];
        }
        mysqli_stmt_close($stmt);
    }

    // 2) Prefix (starts-with) matches next
    $remaining = $limit - count($result['commodities']);
    if ($remaining > 0) {
        $excludeSql = '';
        if (!empty($foundIds)) {
            $excludeSql = ' AND c.commodity_id NOT IN (' . implode(',', $foundIds) . ') ';
        }
        $sqlPrefix = "SELECT c.commodity_id, c.commodity_name, cc.category_name
                      FROM commodities c
                      LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id
                      WHERE LOWER(c.commodity_name) LIKE CONCAT(LOWER(?), '%') " . $excludeSql . "
                      ORDER BY c.commodity_name LIMIT ?";
        $stmt2 = mysqli_prepare($conn, $sqlPrefix);
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, 'si', $q, $remaining);
            mysqli_stmt_execute($stmt2);
            $res2 = mysqli_stmt_get_result($stmt2);
            while ($row = mysqli_fetch_assoc($res2)) {
                $result['commodities'][] = [
                    'commodity_id' => $row['commodity_id'],
                    'commodity_name' => $row['commodity_name'],
                    'category_name' => $row['category_name']
                ];
                $foundIds[] = (int)$row['commodity_id'];
            }
            mysqli_stmt_close($stmt2);
        }
    }

    // 3) Substring matches to fill remaining
    $remaining = $limit - count($result['commodities']);
    if ($remaining > 0) {
        $excludeSql = '';
        if (!empty($foundIds)) {
            $excludeSql = ' AND c.commodity_id NOT IN (' . implode(',', $foundIds) . ') ';
        }
        $sqlSub = "SELECT c.commodity_id, c.commodity_name, cc.category_name
                   FROM commodities c
                   LEFT JOIN commodity_categories cc ON c.category_id = cc.category_id
                   WHERE LOWER(c.commodity_name) LIKE CONCAT('%', LOWER(?), '%') " . $excludeSql . "
                   ORDER BY c.commodity_name LIMIT ?";
        $stmt3 = mysqli_prepare($conn, $sqlSub);
        if ($stmt3) {
            mysqli_stmt_bind_param($stmt3, 'si', $q, $remaining);
            mysqli_stmt_execute($stmt3);
            $res3 = mysqli_stmt_get_result($stmt3);
            while ($row = mysqli_fetch_assoc($res3)) {
                $result['commodities'][] = [
                    'commodity_id' => $row['commodity_id'],
                    'commodity_name' => $row['commodity_name'],
                    'category_name' => $row['category_name']
                ];
            }
            mysqli_stmt_close($stmt3);
        }
    }

    $result['success'] = true;
}
echo json_encode($result);