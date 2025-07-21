<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Error logging function
function logError($message) {
    $log_file = 'C:\\wamp\\www\\Production\\decal\\home_summary_error.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    logError("Connection failed: " . $conn->connect_error);
    die(json_encode(array('error' => 'Connection failed: ' . $conn->connect_error)));
}

try {
    // Queries
    $queries = array(
        'not_planned' => "SELECT COUNT(order_no) as count 
                          FROM imporderdata_processed 
                          WHERE processed = 1 AND planned = 0",
        
        'pending_ack' => "SELECT COUNT(order_no) as count 
                          FROM imporderdata_processed 
                          WHERE processed = 1 AND planned = 1 AND delivery_confirm = 0",
        
        'to_be_shipped' => "SELECT COUNT(order_no) as count 
                            FROM imporderdata_processed 
                            WHERE processed = 1 AND planned = 1 AND delivery_confirm = 1 AND shipped = 0",
        
        'total_orders' => "SELECT COUNT(order_no) as count 
                           FROM imporderdata_processed 
                           WHERE processed = 1"
    );

    $summary = array();

    foreach ($queries as $key => $query) {
        logError("Executing query for $key: $query");
        
        $result = $conn->query($query);
        
        if (!$result) {
            logError("Query failed for $key: " . $conn->error);
            $summary[$key] = 0;
        } else {
            $row = $result->fetch_assoc();
            $summary[$key] = intval($row['count']);
            logError("Result for $key: " . $summary[$key]);
        }
    }

    // Additional debugging: check table contents
    $debug_query = "SELECT processed, planned, delivery_confirm, shipped, COUNT(order_no) as count 
                    FROM imporderdata_processed 
                    GROUP BY processed, planned, delivery_confirm, shipped";
    $debug_result = $conn->query($debug_query);
    
    if ($debug_result) {
        logError("Debug Query Results:");
        while ($debug_row = $debug_result->fetch_assoc()) {
            logError(print_r($debug_row, true));
        }
    }

    echo json_encode($summary);

} catch (Exception $e) {
    logError("Exception: " . $e->getMessage());
    echo json_encode(array('error' => $e->getMessage()));
}

$conn->close();
?>
