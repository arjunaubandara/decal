<?php
$source_db = new mysqli("10.0.0.12", "printing", "nlpl1234", "decalOrderData");
$target_db = new mysqli("10.0.0.20", "appserver", "nlpl1234", "nlpl");

if ($source_db->connect_error || $target_db->connect_error) {
    die("Database connection failed: " . $source_db->connect_error . " / " . $target_db->connect_error);
}

$sql = "SELECT * FROM orderData WHERE imported = 0";
$result = $source_db->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Extract values into variables
        $status = $row['status'];
        $dest = 'IMR';
        $order_no = $row['order_no'];
        $decal_patt = $row['decal_patt'];
        $curve_no = (string)$row['curve_no']; // Explicitly cast to string
        $order_quantity = $row['order_quantity'];
        $delivery_date = $row['delivery_date'];
        $order_date = $row['order_date'];
        $upload_date = $row['upload_date'];

        // Use variables in bind_param, note curve_no is now 's' for string
        $stmt = $target_db->prepare("INSERT INTO impOrderData (status, dest, order_no, decal_patt, curve_no, order_quantity, delivery_date, order_date, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $status, $dest, $order_no, $decal_patt, $curve_no, $order_quantity, $delivery_date, $order_date, $upload_date);
        
        if (!$stmt->execute()) {
            echo "Error inserting record: " . $stmt->error;
            continue;
        }

        // Update the 'imported' status
        $id = $row['id'];
        $update = $source_db->prepare("UPDATE orderData SET imported = 1 WHERE id = ?");
        $update->bind_param("i", $id);
        $update->execute();
    }
    echo "<h2>New data imported successfully!</h2>";
} else {
    echo "<h2>No new data to import.</h2>";
}

// Close database connections
$source_db->close();
$target_db->close();
?>
