<?php
if (!function_exists('hasMismatches')) {
    function hasMismatches($conn) {
        // Check curve master mismatches
        $sql1 = "SELECT COUNT(*) as count FROM imporderdata i 
                WHERE NOT EXISTS (
                    SELECT 1 FROM curve_master_tbl c 
                    WHERE i.decal_patt = c.fg_design_no_cmt AND 
                          CAST(i.curve_no AS UNSIGNED) = CAST(c.curve_no_cmt AS UNSIGNED)
                )";
        
        // Check pigment master mismatches
        $sql2 = "SELECT COUNT(*) as count FROM (
                    SELECT i.* FROM imporderdata i
                    LEFT JOIN curve_master_tbl c on i.decal_patt = c.fg_design_no_cmt 
                    AND i.curve_no = c.curve_no_cmt
                ) AS ii
                WHERE NOT EXISTS (
                    SELECT 1 FROM pigment_master_tbl p 
                    WHERE ii.decal_patt = p.pattern_pm
                )";
        
        // Check colors master mismatches
        $sql3 = "SELECT COUNT(*) as count FROM (
                    SELECT i.* FROM imporderdata i
                    LEFT JOIN curve_master_tbl c on i.decal_patt = c.fg_design_no_cmt 
                    AND i.curve_no = c.curve_no_cmt
                ) AS ii 
                WHERE NOT EXISTS (
                    SELECT 1 FROM colours_tbl d 
                    WHERE ii.decal_patt = d.pattern_no_ct
                )";
        
        // Check FD140 mismatches
        $sql4 = "SELECT COUNT(*) as count FROM imporderdata i
                WHERE NOT EXISTS (
                    SELECT 1 FROM fd140 e 
                    WHERE i.decal_patt = e.Design AND 
                          CAST(i.curve_no AS UNSIGNED) = CAST(e.Curve AS UNSIGNED)
                )";
        
        try {
            $result1 = $conn->query($sql1);
            $result2 = $conn->query($sql2);
            $result3 = $conn->query($sql3);
            $result4 = $conn->query($sql4);
            
            if ($result1 && $result2 && $result3 && $result4) {
                $row1 = $result1->fetch_assoc();
                $row2 = $result2->fetch_assoc();
                $row3 = $result3->fetch_assoc();
                $row4 = $result4->fetch_assoc();
                
                return ($row1['count'] > 0 || $row2['count'] > 0 || 
                        $row3['count'] > 0 || $row4['count'] > 0);
            }
        } catch (Exception $e) {
            error_log("Error checking mismatches: " . $e->getMessage());
        }
        
        return true; // Return true if any query fails (safer option)
    }
}

if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($status, $message, $data = null) {
        header('Content-Type: application/json');
        $response = array(
            'status' => $status,
            'message' => $message
        );
        if ($data !== null) {
            $response['data'] = $data;
        }
        echo json_encode($response);
        exit;
    }
}

if (!function_exists('displayMismatch')) {
    function displayMismatch($result, $title) {
        if ($result->num_rows > 0) {
            echo "<h3>$title</h3>";
            echo "<table class='table table-striped'>";
            echo "<tr>
                <th>Status</th>
                <th>Order No</th>
                <th>Decal Pattern</th>
                <th>Curve No</th>
                <th>Order Quantity</th>
                <th>Delivery Date</th>
                <th>Order Date</th>
            </tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>
                    <td>" . htmlspecialchars($row['status']) . "</td>
                    <td>" . htmlspecialchars($row['order_no']) . "</td>
                    <td>" . htmlspecialchars($row['decal_patt']) . "</td>
                    <td>" . htmlspecialchars($row['curve_no']) . "</td>
                    <td>" . htmlspecialchars($row['order_quantity']) . "</td>
                    <td>" . htmlspecialchars($row['delivery_date']) . "</td>
                    <td>" . htmlspecialchars($row['order_date']) . "</td>
                </tr>";
            }
            echo "</table>";
            return true;
        } else {
            echo "<h3>$title: All required master data available</h3>";
            return false;
        }
    }
}
?>