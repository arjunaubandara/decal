<?php
$master_check = $conn->query("SELECT order_no FROM masterData");

$master_orders = [];
while ($row = $master_check->fetch_assoc()) {
    $master_orders[] = $row['order_no'];
}

$sql = "SELECT * FROM orderData WHERE imported = 1";
$result = $conn->query($sql);

$missing = [];
while ($row = $result->fetch_assoc()) {
    if (!in_array($row['order_no'], $master_orders)) {
        $missing[] = $row;
    }
}

if (count($missing) > 0) {
    echo "<h2>Missing Master Data</h2>";
    foreach ($missing as $order) {
        echo "Order No: {$order['order_no']}<br>";
    }
} else {
    echo "All imported orders are in master data.";
}
?>
