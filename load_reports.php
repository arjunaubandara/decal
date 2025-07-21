<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(array('error' => 'Connection failed: ' . $conn->connect_error)));
}

$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';

try {
    switch ($report_type) {
        case 'not_planned':
            $sql = "SELECT 
                        order_no, 
                        decal_patt, 
                        curve_no, 
                        order_quantity, 
                        order_date, 
                        delivery_date 
                    FROM imporderdata_processed 
                    WHERE processed = 1 AND planned = 0";
            break;
        
        case 'pending_ack':
            $sql = "SELECT 
                        order_no, 
                        decal_patt, 
                        curve_no, 
                        order_quantity, 
                        order_date, 
                        delivery_date 
                    FROM imporderdata_processed 
                    WHERE processed = 1 AND planned = 1 AND delivery_confirm = 0";
            break;
        
        case 'to_be_shipped':
            $sql = "SELECT 
                        order_no, 
                        decal_patt, 
                        curve_no, 
                        order_quantity, 
                        order_date, 
                        planned_delivery, 
                        lot_no, 
                        shippedsofar 
                    FROM imporderdata_processed 
                    WHERE processed = 1 AND planned = 1 AND delivery_confirm = 1 AND shipped = 0";
            break;
        
        default:
            throw new Exception('Invalid report type');
    }

    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode(array('error' => $e->getMessage()));
}

$conn->close();
?>
