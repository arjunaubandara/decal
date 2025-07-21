<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$log_file = 'C:\\wamp\\www\\Production\\decal\\save_shipped_data_error.log';

function custom_error_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Ensure directory exists
    $dir = dirname($log_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    custom_error_log("Connection failed: " . $conn->connect_error);
    die(json_encode(array('error' => 'Connection failed: ' . $conn->connect_error)));
}

$response = array('success' => false, 'message' => '');

// Log raw POST data for debugging
custom_error_log("Raw POST data: " . print_r($_POST, true));

if (isset($_POST['data'])) {
    $data = $_POST['data'];
    
    custom_error_log("Received data: " . print_r($data, true));
    
    $conn->autocommit(FALSE);
    
    try {
        // Prepare a statement to log shipped quantities
        $log_stmt = $conn->prepare("INSERT INTO shipped_quantities_log (order_id, ship_qty, current_shipped, order_qty, timestamp) VALUES (?, ?, ?, ?, NOW())");
        
        foreach ($data as $row) {
            // Log each row for debugging
            custom_error_log("Processing row: " . print_r($row, true));
            
            $id = isset($row['id']) ? intval($row['id']) : 0;
            $ship_qty = isset($row['ship_qty']) ? intval($row['ship_qty']) : 0;
            $current_shipped = isset($row['current_shipped']) ? intval($row['current_shipped']) : 0;
            $total_shipped = $current_shipped + $ship_qty;
            $order_qty = isset($row['order_qty']) ? intval($row['order_qty']) : 0;
            
            custom_error_log("Processed values - ID: $id, Ship Qty: $ship_qty, Current Shipped: $current_shipped, Total Shipped: $total_shipped, Order Qty: $order_qty");
            
            // Allow shipping quantity to exceed order quantity as per new requirement
            // if ($total_shipped > $order_qty) {
            //     throw new Exception("Shipping quantity exceeds order quantity");
            // }
            
            // Log shipped quantity
            $log_stmt->bind_param("iiii", $id, $ship_qty, $current_shipped, $order_qty);
            $log_stmt->execute();
            
            // Update imporderdata_processed
            $stmt = $conn->prepare("UPDATE imporderdata_processed 
                                  SET shippedsofar = ?,
                                      shipped = CASE WHEN ? >= order_quantity THEN 1 ELSE 0 END,
                                      csv_generated = CASE WHEN ? >= order_quantity THEN 2 ELSE csv_generated END
                                  WHERE id = ?");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("iiii", $total_shipped, $total_shipped, $total_shipped, $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // If fully shipped, update fd140
            if ($total_shipped >= $order_qty) {
                $stmt2 = $conn->prepare("UPDATE fd140 
                                       SET Shipped = 1 
                                       WHERE Design = (SELECT decal_patt FROM imporderdata_processed WHERE id = ?)
                                       AND Curve = (SELECT curve_no FROM imporderdata_processed WHERE id = ?)");
                
                if (!$stmt2) {
                    throw new Exception("Prepare fd140 failed: " . $conn->error);
                }
                
                $stmt2->bind_param("ii", $id, $id);
                if (!$stmt2->execute()) {
                    throw new Exception("Execute fd140 failed: " . $stmt2->error);
                }
            }
        }
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Data saved successfully';
        
    } catch (Exception $e) {
        $conn->rollback();
        custom_error_log("Exception: " . $e->getMessage());
        $response['message'] = $e->getMessage();
    }
    
} else {
    $response['message'] = 'No data received';
    custom_error_log('No data received in POST');
}

echo json_encode($response);
$conn->close();
?>