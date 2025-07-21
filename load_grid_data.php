<?php
// Prevent PHP from displaying errors directly
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(array('error' => 'Connection failed: ' . $conn->connect_error));
    exit;
}

$sql = "SELECT id, status, dest, order_no, decal_patt, curve_no, order_quantity, delivery_date, planned_delivery 
FROM imporderdata_processed 
WHERE processed = 0";

$result = $conn->query($sql);
$data = array();

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>
