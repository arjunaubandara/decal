<?php
// filepath: h:\Current\decal\adjust_orders.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't output errors directly

header('Content-Type: application/json'); // Ensure JSON response

// Database connection
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$response = array(
    'success' => false,
    'message' => 'No action taken'
);

try {
    // Check if IDs were provided
    if (!isset($_POST['ids']) || empty($_POST['ids'])) {
        throw new Exception('No records selected for adjustment');
    }
    
    // Connect to database
    $conn = new mysqli($server, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Sanitize input IDs
    $ids = $_POST['ids'];
    $idList = array();
    
    foreach ($ids as $id) {
        $idList[] = (int)$id; // Force integer conversion for safety
    }
    
    // Create a safe comma-separated list
    $idString = implode(',', $idList);
    
    if (empty($idString)) {
        throw new Exception('Invalid ID selection');
    }
    
    // Update the shipped status and mark as adjusted
    $sql = "UPDATE imporderdata_processed 
            SET shipped = 1
            WHERE id IN ($idString)";
    
    if ($conn->query($sql) === TRUE) {
        $affectedRows = $conn->affected_rows;
        $response['success'] = true;
        $response['message'] = "Successfully adjusted $affectedRows record(s)";
        $response['affected'] = $affectedRows;
    } else {
        throw new Exception("Database error: " . $conn->error);
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    // Return error as JSON
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

// Output JSON response
echo json_encode($response);
?>
