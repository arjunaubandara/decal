<?php
// filepath: h:\Current\decal\direct.php
// Ultra-simple direct update script

// Set proper header
header('Content-Type: application/json');

// Log function
function log_entry($msg) {
    file_put_contents('direct_update.log', date('Y-m-d H:i:s') . ' - ' . $msg . "\n", FILE_APPEND);
}

// Begin logging
log_entry("=== SCRIPT START ===");

// Direct hard-coded update for testing
$id = 332;  // The ID from your test
$date = "20250515"; // The date from your test

log_entry("Attempting direct update - ID: $id, Date: $date");

// Database connection - hardcoded for simplicity
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

// Connect with error handling
log_entry("Connecting to database");
$conn = mysqli_connect($server, $username, $password, $database);

if (!$conn) {
    log_entry("Connection failed: " . mysqli_connect_error());
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit;
}
log_entry("Connected successfully");

// Check if record exists first
$check_sql = "SELECT id, planned_delivery FROM imporderdata_processed WHERE id = $id";
log_entry("Checking record: $check_sql");

$check_result = mysqli_query($conn, $check_sql);
if (!$check_result) {
    log_entry("Check query failed: " . mysqli_error($conn));
    echo json_encode(array('success' => false, 'message' => 'Failed to check record'));
    exit;
}

if (mysqli_num_rows($check_result) == 0) {
    log_entry("Record not found for ID: $id");
    echo json_encode(array('success' => false, 'message' => 'Record not found'));
    exit;
}

$row = mysqli_fetch_assoc($check_result);
log_entry("Found record - Current planned_delivery: " . $row['planned_delivery']);

// Try a very basic update
$update_sql = "UPDATE imporderdata_processed SET planned_delivery = '$date' WHERE id = $id";
log_entry("Running update: $update_sql");

try {
    $update_result = mysqli_query($conn, $update_sql);
    
    if ($update_result) {
        $affected = mysqli_affected_rows($conn);
        log_entry("Update successful. Affected rows: $affected");
        echo json_encode(array('success' => true, 'affected' => $affected));
    } else {
        log_entry("Update failed: " . mysqli_error($conn));
        echo json_encode(array('success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)));
    }
} catch (Exception $e) {
    log_entry("Exception: " . $e->getMessage());
    echo json_encode(array('success' => false, 'message' => 'Exception: ' . $e->getMessage()));
}

// Close connection
mysqli_close($conn);
log_entry("Connection closed");
log_entry("=== SCRIPT END ===");
?>