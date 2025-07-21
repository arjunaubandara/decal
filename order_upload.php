<?php
date_default_timezone_set("Asia/Colombo");

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "decalOrderData";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"]["tmp_name"];
    
    if (($handle = fopen($file, "r")) !== false) {
        // Skip the header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $status = $data[0];
            $order_no = $data[1];
            $decal_patt = $data[2];
            $curve_no = $data[3];
            $order_quantity = (int)$data[4];
            $delivery_date = $data[5];
            $order_date = $data[6];
            $upload_date = date('Y-m-d');
			
			// Check for duplicate data
			$check_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orderData WHERE status = ? AND order_no = ? AND decal_patt = ? AND curve_no = ? AND order_quantity = ? AND order_date = ?");
			$check_stmt->bind_param("ssssis", $status, $order_no, $decal_patt, $curve_no, $order_quantity, $order_date);
			$check_stmt->execute();
			$check_result = $check_stmt->get_result();
			$row = $check_result->fetch_assoc();

			if ($row['cnt'] == 0) {
            // No duplicate found, proceed to insert
            $stmt = $conn->prepare("INSERT INTO orderData (status, order_no, decal_patt, curve_no, order_quantity, delivery_date, order_date, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssisss", $status, $order_no, $decal_patt, $curve_no, $order_quantity, $delivery_date, $order_date, $upload_date);
            $stmt->execute();
			}
        }

        fclose($handle);
        echo "Data uploaded successfully!";
		$sql = "SELECT * FROM orderData WHERE upload_date = CURDATE()";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>Uploaded Data for " . date('Y-m-d') . "</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Status</th><th>Order No</th><th>Decal Pattern</th><th>Curve No</th><th>Order Quantity</th><th>Delivery Date</th><th>Order Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['status']}</td><td>{$row['order_no']}</td><td>{$row['decal_patt']}</td><td>{$row['curve_no']}</td><td>{$row['order_quantity']}</td><td>{$row['delivery_date']}</td><td>{$row['order_date']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "No data uploaded today.";
}

    } else {
        echo "Error opening the file.";
    }
}

$conn->close();
?>
