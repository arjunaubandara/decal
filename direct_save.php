<?php
// filepath: h:\Current\decal\direct_save.php
// Ultra-minimal save file

// Basic setup - no error output
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Very simple log function
function write_log($msg) {
    $file = 'direct_save.log';
    $date = date('Y-m-d H:i:s');
    file_put_contents($file, "[$date] $msg\n", FILE_APPEND);
}

write_log("STARTED");
write_log("POST: " . print_r($_POST, true));

// Default response
$response = ["success" => false];

try {
    // Get data
    if (empty($_POST['data'])) {
        throw new Exception("No data received");
    }
    
    // Connect to database
    $conn = new mysqli('10.0.0.20', 'appserver', 'nlpl1234', 'nlpl');
    if ($conn->connect_error) {
        throw new Exception("DB connection failed");
    }
    write_log("Database connected");
    
    // Parse JSON
    $raw_data = $_POST['data'];
    write_log("JSON: $raw_data");
    
    $data = json_decode($raw_data, true);
    if (!is_array($data)) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    // Process records
    $updated = 0;
    foreach ($data as $item) {
        if (empty($item['id'])) continue;
        
        $id = (int)$item['id'];
        $pd = $conn->real_escape_string($item['planned_delivery'] ?? '');
        $lot = $conn->real_escape_string($item['lot_no'] ?? '');
        
        write_log("ID: $id, PD: $pd, Lot: $lot");
        
        $sql = "UPDATE imporderdata_processed SET planned_delivery='$pd', lot_no='$lot' WHERE id=$id";
        write_log("SQL: $sql");
        
        if ($conn->query($sql)) {
            $updated++;
            write_log("Update OK");
        } else {
            write_log("Update failed: " . $conn->error);
        }
    }
    
    // Success
    $response = ["success" => true, "updated" => $updated];
    
} catch (Exception $e) {
    write_log("ERROR: " . $e->getMessage());
    $response = ["success" => false, "error" => $e->getMessage()];
}

// Output response
write_log("Response: " . json_encode($response));
echo json_encode($response);
write_log("COMPLETED");
?>