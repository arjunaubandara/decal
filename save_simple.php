<?php
// filepath: h:\Current\decal\save_simple.php
// Ultra-simple script to update planned_delivery and lot_no

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Simple logging to a separate file
function log_it($msg) {
    file_put_contents('save_super_simple.log', date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

log_it("SCRIPT STARTED");
log_it("POST: " . print_r($_POST, true));

try {
    // Check if data exists
    if (!isset($_POST['data'])) {
        throw new Exception("No data provided");
    }
    
    // Database connection
    $server = '10.0.0.20';
    $username = 'appserver';
    $password = 'nlpl1234';
    $database = 'nlpl';
    
    log_it("Connecting to database");
    $conn = mysqli_connect($server, $username, $password, $database);
    
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    log_it("Database connected");
    
    // Parse JSON
    $data = json_decode($_POST['data'], true);
    
    if (!is_array($data)) {
        throw new Exception("Invalid data format");
    }
    
    log_it("Processing " . count($data) . " records");
    $updated = 0;
    
    // Process each record
    foreach ($data as $record) {
        if (empty($record['id'])) continue;
        
        $id = (int)$record['id'];
        $planned_delivery = mysqli_real_escape_string($conn, $record['planned_delivery']);
        $lot_no = mysqli_real_escape_string($conn, $record['lot_no']);
        
        log_it("Updating ID $id: PD=$planned_delivery, Lot=$lot_no");
        
        // Direct update - keep it simple
        $sql = "UPDATE imporderdata_processed 
                SET planned_delivery = '$planned_delivery', 
                    lot_no = '$lot_no' 
                WHERE id = $id";
                
        log_it("SQL: $sql");
        
        if (mysqli_query($conn, $sql)) {
            $affected = mysqli_affected_rows($conn);
            log_it("Success - affected rows: $affected");
            $updated++;
        } else {
            log_it("Error: " . mysqli_error($conn));
        }
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => "Updated $updated records"
    ]);
    
} catch (Exception $e) {
    log_it("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

log_it("SCRIPT COMPLETED");
?>