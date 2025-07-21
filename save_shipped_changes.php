<?php
// filepath: h:\Current\decal\save_shipped_changes.php
// Simple script to save changes to planned delivery dates

// For error logging
$log_file = 'shipped_changes_log.txt';
function log_message($msg) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $msg\n", FILE_APPEND);
}

// Set proper content type for AJAX response
header('Content-Type: application/json');

// Track execution
log_message("Request received");

try {
    // Check for changes data
    if (!isset($_POST['changes'])) {
        throw new Exception('No changes data received');
    }
    
    // Log raw data
    log_message("Raw changes data: " . $_POST['changes']);
    
    // Parse the JSON data - PHP 5.3 compatible
    $changes = json_decode($_POST['changes'], true);
    if ($changes === null) {
        throw new Exception('Invalid JSON data');
    }
    
    log_message("Parsed " . count($changes) . " rows of changes");
    
    // Database connection
    $server = '10.0.0.20';
    $username = 'appserver';
    $password = 'nlpl1234';
    $database = 'nlpl';

    $conn = new mysqli($server, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    log_message("Database connected");
    
    // For transaction safety
    $conn->autocommit(FALSE);
    $updated = 0;
    
    // Process each change manually without prepared statements
    foreach ($changes as $change) {
        if (empty($change['id'])) {
            log_message("Skipping row with missing ID");
            continue;
        }
        
        $id = (int)$change['id'];
        $planned_delivery = isset($change['planned_delivery']) ? $change['planned_delivery'] : '';
        $lot_no = isset($change['lot_no']) ? $change['lot_no'] : '';
        
        log_message("Processing ID: $id, Delivery: $planned_delivery, Lot: $lot_no");
        
        // Manual escaping
        $planned_delivery = $conn->real_escape_string($planned_delivery);
        $lot_no = $conn->real_escape_string($lot_no);
        
        // Very basic update query
        $sql = "UPDATE imporderdata_processed SET";
        $set_parts = array();
        
        if (!empty($planned_delivery)) {
            $set_parts[] = " planned_delivery = '$planned_delivery'";
        }
        
        if (!empty($lot_no)) {
            $set_parts[] = " lot_no = '$lot_no'";
        }
        
        if (empty($set_parts)) {
            log_message("No fields to update for ID: $id");
            continue;
        }
        
        $sql .= implode(",", $set_parts);
        $sql .= " WHERE id = $id";
        
        log_message("Running SQL: $sql");
        
        if ($conn->query($sql)) {
            $updated++;
            log_message("Update successful for ID: $id");
        } else {
            log_message("SQL Error for ID $id: " . $conn->error);
            throw new Exception("Failed to update ID $id: " . $conn->error);
        }
    }
    
    // Commit all changes
    $conn->commit();
    log_message("Transaction committed - $updated records updated");
    
    // Close connection
    $conn->close();
    log_message("Database connection closed");
    
    // Return success
    echo json_encode(array(
        'success' => true,
        'message' => "$updated records updated successfully",
        'updated' => $updated
    ));
    
} catch (Exception $e) {
    log_message("ERROR: " . $e->getMessage());
    
    // Rollback if needed
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
        log_message("Transaction rolled back and connection closed");
    }
    
    // Return error
    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}

log_message("Script execution completed");
?>