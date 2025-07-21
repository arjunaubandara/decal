<?php
// filepath: h:\Current\decal\update_planned_delivery.php
// Create or update this file with the following code

header('Content-Type: application/json');

// Basic error logging
function log_error($message) {
    file_put_contents('update_date_errors.log', date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}

// Database connection
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);
if ($conn->connect_error) {
    log_error("Connection failed: " . $conn->connect_error);
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit;
}

// Log all incoming data
log_error("REQUEST: " . print_r($_POST, true));

// Get parameters
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$date = isset($_POST['date']) ? $_POST['date'] : '';
$lot_no = isset($_POST['lot_no']) ? $_POST['lot_no'] : '';

// Simple validation
if ($id <= 0) {
    log_error("Invalid ID: $id");
    echo json_encode(array('success' => false, 'message' => 'Invalid ID'));
    exit;
}

// Escape values to prevent SQL injection
$date = $conn->real_escape_string($date);
$lot_no = $conn->real_escape_string($lot_no);

// Use a very simple update query - UPDATE ONLY THE DATE, NOT THE LOT_NO
$sql = "UPDATE imporderdata_processed SET planned_delivery = '$date' WHERE id = $id";

log_error("SQL: $sql");
$result = $conn->query($sql);

if ($result) {
    // Success
    $affected = $conn->affected_rows;
    log_error("SUCCESS: Updated ID $id. Affected rows: $affected");
    echo json_encode(array('success' => true, 'affected' => $affected));
} else {
    // Failure
    log_error("ERROR: Query failed: " . $conn->error);
    echo json_encode(array('success' => false, 'message' => 'Database update failed: ' . $conn->error));
}

$conn->close();
?>