<?php
// filepath: h:\Current\decal\update_dates.php
// Ultra-simple version to fix parsererror

// Important: Turn off errors so they don't pollute the JSON output
error_reporting(0);
ini_set('display_errors', 0); 

// Set proper content type
header('Content-Type: application/json');

// Simple log function
function log_msg($text) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('update_log.txt', "[$timestamp] $text\n", FILE_APPEND);
}

// Buffer output to prevent any unwanted characters
ob_start();

log_msg("Script started");

try {
    // Database connection
    $server = '10.0.0.20';
    $username = 'appserver';
    $password = 'nlpl1234';
    $database = 'nlpl';
    
    // Create connection
    log_msg("Connecting to database...");
    $conn = new mysqli($server, $username, $password, $database);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Process records
    log_msg("POST data received: " . json_encode($_POST));
    
    if (!isset($_POST['records'])) {
        throw new Exception("No records data provided");
    }
    
    // Decode JSON
    $records = json_decode($_POST['records'], true);
    if ($records === null) {
        throw new Exception("Invalid JSON data");
    }
    
    $updated = 0;
    
    // Process each record
    foreach ($records as $record) {
        $id = isset($record['id']) ? (int)$record['id'] : 0;
        $date = isset($record['date']) ? trim($record['date']) : '';
        
        if ($id <= 0 || empty($date)) {
            continue;
        }
        
        // Safe date value
        $date = str_replace("'", "", $date);
        
        // SQL query
        $sql = "UPDATE imporderdata_processed SET planned_delivery = '$date' WHERE id = $id";
        log_msg("Running SQL: $sql");
        
        if ($conn->query($sql)) {
            $updated += $conn->affected_rows;
        }
    }
    
    // Prepare clean response
    $response = array(
        'success' => true,
        'updated' => $updated,
        'message' => "$updated records updated"
    );
    
} catch (Exception $e) {
    log_msg("ERROR: " . $e->getMessage());
    $response = array(
        'success' => false,
        'message' => $e->getMessage()
    );
}

// Close database if open
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// Clear output buffer
ob_end_clean();

// Output clean JSON
echo json_encode($response);
log_msg("Script completed");
?>