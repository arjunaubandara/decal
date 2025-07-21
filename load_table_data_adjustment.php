<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(array('error' => 'Connection failed: ' . $conn->connect_error)));
}

$sql = "SELECT id, order_no, decal_patt, curve_no, 
               order_quantity, planned_delivery, lot_no, 
               shipped, shippedsofar, 
               (order_quantity - COALESCE(shippedsofar, 0)) as remaining_qty 
        FROM imporderdata_processed 
        WHERE shipped = 0 AND csv_generated = 1 AND shippedsofar > 0";

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
