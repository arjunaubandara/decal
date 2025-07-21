<?php
header('Content-Type: application/json');
date_default_timezone_set("Asia/Colombo");

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "decalOrderData";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(array('error' => "Connection failed: " . $conn->connect_error)));
}

// Get today's orders
$sql = "SELECT status, order_no, decal_patt, curve_no, order_quantity, delivery_date, order_date 
        FROM orderData 
        WHERE DATE(upload_date) = CURDATE() 
        ORDER BY order_no ASC";

$result = $conn->query($sql);
$orders = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $orders[] = array(
            'status' => $row['status'],
            'order_no' => $row['order_no'],
            'decal_patt' => $row['decal_patt'],
            'curve_no' => $row['curve_no'],
            'order_quantity' => $row['order_quantity'],
            'delivery_date' => $row['delivery_date'],
            'order_date' => $row['order_date']
        );
    }
}

echo json_encode($orders);
$conn->close();
?>
