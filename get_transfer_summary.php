<?php
header('Content-Type: application/json');

$source_db = new mysqli("10.0.0.12", "printing", "nlpl1234", "decalOrderData");

if ($source_db->connect_error) {
    die(json_encode(array('error' => "Database connection failed: " . $source_db->connect_error)));
}

// Get count of records processed today
$sql = "SELECT COUNT(*) as processed FROM orderData WHERE imported = 1 AND DATE(upload_date) = CURDATE()";
$result = $source_db->query($sql);
$row = $result->fetch_assoc();

$summary = array(
    'processed' => $row['processed'],
    'status' => 'Completed',
    'timestamp' => date('Y-m-d H:i:s')
);

echo json_encode($summary);
$source_db->close();
?>
