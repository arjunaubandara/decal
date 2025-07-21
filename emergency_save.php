<?php
// filepath: h:\Current\decal\emergency_save.php
// Simplified version with no room for error

// Set content type first
header('Content-Type: application/json');

// Log to a specific file
$log_file = 'emergency_debug.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - SCRIPT STARTED\n", FILE_APPEND);
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Always return a valid JSON response
try {
    // Database connection
    $server = '10.0.0.20';
    $username = 'appserver';
    $password = 'nlpl1234';
    $database = 'nlpl';
    
    $conn = mysqli_connect($server, $username, $password, $database);
    if (!$conn) {
        throw new Exception("Cannot connect to database");
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - DB Connected\n", FILE_APPEND);
    
    // Check for data
    if (!isset($_POST['data'])) {
        throw new Exception("No data received");
    }
    
    // Parse JSON data
    $data = json_decode($_POST['data'], true);
    if (!is_array($data)) {
        throw new Exception("Invalid JSON data");
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Decoded " . count($data) . " records\n", FILE_APPEND);
    
    // Process each record
    $updated = 0;
    foreach ($data as $item) {
        if (empty($item['id'])) continue;
        
        $id = (int)$item['id'];
        $planned_delivery = mysqli_real_escape_string($conn, $item['planned_delivery'] ?? '');
        $lot_no = mysqli_real_escape_string($conn, $item['lot_no'] ?? '');
        
        $sql = "UPDATE imporderdata_processed SET 
                planned_delivery = '$planned_delivery', 
                lot_no = '$lot_no' 
                WHERE id = $id";
        
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Running SQL: $sql\n", FILE_APPEND);
        
        if (mysqli_query($conn, $sql)) {
            $affected = mysqli_affected_rows($conn);
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - Updated rows: $affected\n", FILE_APPEND);
            $updated += $affected > 0 ? 1 : 0;
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " - SQL Error: " . mysqli_error($conn) . "\n", FILE_APPEND);
        }
    }
    
    // Always send a success response
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => "Updated $updated records"
    ]);
    
} catch (Exception $e) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

file_put_contents($log_file, date('Y-m-d H:i:s') . " - SCRIPT COMPLETED\n", FILE_APPEND);
?>