<?php
// filepath: h:\Current\decal\diagnostic_save.php
// Ultra-simple diagnostic script to update a single record

// Basic setup
header('Content-Type: application/json');
error_reporting(E_ALL);

// Log everything
function debug_log($msg) {
    file_put_contents('diagnostic.log', date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

debug_log("=== DIAGNOSTIC SAVE STARTED ===");
debug_log("REQUEST: " . print_r($_REQUEST, true));

// Database connection
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

try {
    // Connect to database
    debug_log("Connecting to database");
    $conn = new mysqli($server, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    debug_log("Database connected");
    
    // Get the POST values
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $planned_delivery = isset($_POST['planned_delivery']) ? $_POST['planned_delivery'] : '';
    $lot_no = isset($_POST['lot_no']) ? $_POST['lot_no'] : '';
    
    debug_log("Values received - ID: $id, Planned Delivery: $planned_delivery, Lot No: $lot_no");
    
    // Check if ID is valid
    if ($id <= 0) {
        throw new Exception("Invalid ID: $id");
    }
    
    // Create direct update statement
    $sql = "UPDATE imporderdata_processed 
            SET planned_delivery = '" . $conn->real_escape_string($planned_delivery) . "', 
                lot_no = '" . $conn->real_escape_string($lot_no) . "' 
            WHERE id = $id";
    
    debug_log("SQL: $sql");
    
    // Execute query
    $result = $conn->query($sql);
    
    if ($result) {
        $affected = $conn->affected_rows;
        debug_log("Update successful - Affected rows: $affected");
        echo json_encode([
            'success' => true,
            'message' => "Update successful - Affected rows: $affected",
            'sql' => $sql
        ]);
    } else {
        debug_log("Update failed: " . $conn->error);
        throw new Exception("Update failed: " . $conn->error);
    }
    
    // Show table structure
    debug_log("Checking table structure...");
    $structure_sql = "DESCRIBE imporderdata_processed";
    $structure_result = $conn->query($structure_sql);
    
    if ($structure_result) {
        $columns = [];
        while ($row = $structure_result->fetch_assoc()) {
            $columns[] = $row;
        }
        debug_log("Table columns: " . print_r($columns, true));
    }
    
} catch (Exception $e) {
    debug_log("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
    debug_log("Database connection closed");
}

debug_log("=== DIAGNOSTIC SAVE COMPLETED ===");
?>