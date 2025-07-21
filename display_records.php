<?php
function displayRecords($conn) {
    $sql = "SELECT * FROM imporderdata WHERE integrated = 0";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<h3>Records Ready for Transfer</h3>";
        echo "<table class='table table-striped'>";
        echo "<thead><tr>
                <th>ID</th>
                <th>Status</th>
                <th>Destination</th>
                <th>Order No</th>
                <th>Decal Pattern</th>
                <th>Curve No</th>
                <th>Order Quantity</th>
                <th>Delivery Date</th>
                <th>Order Date</th>
                <th>Upload Date</th>
              </tr></thead><tbody>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['status']}</td>
                    <td>{$row['dest']}</td>
                    <td>{$row['order_no']}</td>
                    <td>{$row['decal_patt']}</td>
                    <td>{$row['curve_no']}</td>
                    <td>{$row['order_quantity']}</td>
                    <td>{$row['delivery_date']}</td>
                    <td>{$row['order_date']}</td>
                    <td>{$row['upload_date']}</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<div class='alert alert-warning'>No records available for transfer.</div>";
    }
}

// Display records if included directly
if (!defined('INCLUDED')) {
    displayRecords($conn);
}