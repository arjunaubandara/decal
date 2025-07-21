<?php
// filepath: h:\Current\decal\update_dates.php
// Robust date update script based on our working test

header('Content-Type: application/json');

function log_entry($msg) {
    file_put_contents('date_updates.log', date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND);
}

log_entry("=== UPDATE DATES SCRIPT START ===");
log_entry("POST data: " . print_r($_POST, true));

// Database connection
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$response = array('success' => false, 'message' => '', 'updated' => 0);

try {
    // Connect to database
    $conn = mysqli_connect($server, $username, $password, $database);
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    log_entry("Database connected");
    
    // Check for data
    if (!isset($_POST['records'])) {
        throw new Exception("No records data provided");
    }
    
    // Parse records
    $records = json_decode($_POST['records'], true);
    if ($records === null) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    log_entry("Processing " . count($records) . " records");
    $updated = 0;
    
    // Process each record
    foreach ($records as $record) {
        if (empty($record['id']) || !isset($record['date'])) {
            log_entry("Skipping record - missing id or date");
            continue;
        }
        
        $id = (int)$record['id'];
        $date = mysqli_real_escape_string($conn, $record['date']);
        
        log_entry("Processing ID: $id, Date: $date");
        
        // Update query - based on our working direct.php example
        $sql = "UPDATE imporderdata_processed SET planned_delivery = '$date' WHERE id = $id";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            $affected = mysqli_affected_rows($conn);
            $updated += $affected;
            log_entry("Update successful for ID: $id, Affected: $affected");
        } else {
            log_entry("Error updating ID $id: " . mysqli_error($conn));
            throw new Exception("Error updating ID $id: " . mysqli_error($conn));
        }
    }
    
    // Success even if no rows were changed (they might already have the right value)
    $response['success'] = true;
    $response['updated'] = $updated;
    $response['message'] = $updated > 0 ? "$updated records updated" : "Records already up to date";
    
} catch (Exception $e) {
    log_entry("ERROR: " . $e->getMessage());
    $response['message'] = $e->getMessage();
}

// Close connection if it exists
if (isset($conn) && $conn) {
    mysqli_close($conn);
    log_entry("Database connection closed");
}

// Send response
log_entry("Sending response: " . json_encode($response));
echo json_encode($response);
log_entry("=== UPDATE DATES SCRIPT END ===");
?>