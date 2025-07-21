<?php
// filepath: h:\Current\decal\shipped_csv_backup.php

// This file is a standalone patch to fix CSV backup issues
// Include database connection
require_once 'includes/db.php';

// ===== STEP 1: Check directory permissions =====
echo "<h2>CSV Backup Path Check</h2>";

// Check if directory exists and is writable
$backup_base = __DIR__ . DIRECTORY_SEPARATOR . 'csv_backups';
if (!file_exists($backup_base)) {
    if (mkdir($backup_base, 0777, true)) {
        echo "Created base directory: $backup_base<br>";
    } else {
        echo "FAILED to create base directory!<br>";
        echo "Current permissions: " . substr(sprintf('%o', fileperms(dirname(__DIR__))), -4) . "<br>";
    }
} else {
    echo "Base directory exists: $backup_base<br>";
    echo "Is writable: " . (is_writable($backup_base) ? 'Yes' : 'No') . "<br>";
}

// Create month directory
$month_dir = $backup_base . DIRECTORY_SEPARATOR . date('Y-m');
if (!file_exists($month_dir)) {
    if (mkdir($month_dir, 0777, true)) {
        echo "Created month directory: $month_dir<br>";
    } else {
        echo "FAILED to create month directory!<br>";
    }
} else {
    echo "Month directory exists: $month_dir<br>";
    echo "Is writable: " . (is_writable($month_dir) ? 'Yes' : 'No') . "<br>";
}

// ===== STEP 2: Check database tables =====
echo "<h2>Database Tables Check</h2>";

// Create tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS csv_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    generated_by VARCHAR(50) DEFAULT 'system',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    order_ids TEXT NOT NULL,
    success TINYINT(1) DEFAULT 1
)");

if ($conn->error) {
    echo "Error creating log table: " . $conn->error . "<br>";
} else {
    echo "CSV generation log table OK<br>";
}

$conn->query("CREATE TABLE IF NOT EXISTS csv_data_backup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    row_num INT NOT NULL,
    data_json TEXT NOT NULL
)");

if ($conn->error) {
    echo "Error creating data backup table: " . $conn->error . "<br>";
} else {
    echo "CSV data backup table OK<br>";
}

// Add FK if needed
$result = $conn->query("SELECT * 
                      FROM information_schema.TABLE_CONSTRAINTS 
                      WHERE CONSTRAINT_TYPE = 'FOREIGN KEY' 
                      AND TABLE_NAME = 'csv_data_backup'");
                      
if ($result->num_rows == 0) {
    $conn->query("ALTER TABLE csv_data_backup 
                ADD CONSTRAINT fk_log_id 
                FOREIGN KEY (log_id) REFERENCES csv_generation_log(id)");
    
    if ($conn->error) {
        echo "Warning: " . $conn->error . " (FK constraint not added)<br>";
    } else {
        echo "Foreign key constraint added<br>";
    }
}

// ===== STEP 3: Test file writing =====
echo "<h2>File Writing Test</h2>";

$test_file = $month_dir . DIRECTORY_SEPARATOR . 'test_file.txt';
$success = file_put_contents($test_file, "This is a test file created at " . date('Y-m-d H:i:s'));

if ($success !== false) {
    echo "Successfully wrote test file: $test_file<br>";
    echo "File size: " . filesize($test_file) . " bytes<br>";
    
    // Test reading the file back
    $content = file_get_contents($test_file);
    echo "File content: " . htmlspecialchars($content) . "<br>";
    
    // Clean up
    unlink($test_file);
    echo "Test file removed<br>";
} else {
    echo "FAILED to write test file!<br>";
    echo "PHP error: " . error_get_last()['message'] . "<br>";
}

// ===== STEP 4: Update generate_shipped_csv.php =====
echo "<h2>Patching generate_shipped_csv.php</h2>";

// Create the patch
$patch = <<<'PATCH'
// Around line 200 in generate_shipped_csv.php
// ADD CSV BACKUP FUNCTIONALITY HERE
if (count($csvRows) > 0) {
    $headers = array('Status', 'Order No', 'Decal Patt', 'Curve No', 'Answer Date', 'Lot No', 'This Time Shipped Qty');
    
    try {
        // Create backup directory with server-friendly path
        $backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'csv_backups' . DIRECTORY_SEPARATOR . date('Y-m');
        custom_error_log("Backup directory: " . $backup_dir);
        
        if (!file_exists($backup_dir)) {
            if (!mkdir($backup_dir, 0777, true)) {
                throw new Exception("Failed to create directory: $backup_dir");
            }
        }
        
        // Generate unique filename with timestamp
        $timestamp = date('Y-m-d_His');
        $backup_filename = 'shipped_data_' . $timestamp . '.csv';
        $filepath = $backup_dir . DIRECTORY_SEPARATOR . $backup_filename;
        
        custom_error_log("Writing to file: " . $filepath);
        
        // Write CSV backup file
        $file = @fopen($filepath, 'w');
        if (!$file) {
            throw new Exception("Could not open file for writing: $filepath");
        }
        
        fputcsv($file, $headers);
        foreach ($csvRows as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        
        if (!file_exists($filepath)) {
            throw new Exception("File was not created: $filepath");
        }
        
        // Log to database using full server path
        $server_filepath = realpath($filepath);
        $logId = log_csv_to_database('shipped', $ids, $headers, $csvRows, $server_filepath);
        
        custom_error_log("CSV backup created and logged with ID: $logId");
        
        // Store in session
        @session_start();
        $_SESSION['last_csv_log_id'] = $logId;
        $_SESSION['csv_generated'] = true;
        $_SESSION['csv_type'] = 'shipped';
    } catch (Exception $e) {
        custom_error_log("ERROR creating CSV backup: " . $e->getMessage());
        // Continue with CSV generation even if backup fails
    }
}
PATCH;

echo "Generated patch code. Please copy and replace the CSV backup section in generate_shipped_csv.php<br>";

// ===== STEP 5: Apply DIRECT fix =====
echo "<h2>Direct Table Test</h2>";

// Test direct insertion into the tables
$test_filename = 'test_shipped_' . date('YmdHis') . '.csv';
$test_filepath = $month_dir . DIRECTORY_SEPARATOR . $test_filename;
$test_order_ids = '123,456,789';

// Write a simple test CSV
file_put_contents($test_filepath, "Status,ID,Test\nS,123,TestData");

$stmt = $conn->prepare("INSERT INTO csv_generation_log 
                       (type, filename, filepath, order_ids)
                       VALUES ('test', ?, ?, ?)");
                       
if (!$stmt) {
    echo "Prepare statement failed: " . $conn->error . "<br>";
} else {
    $stmt->bind_param("sss", $test_filename, $test_filepath, $test_order_ids);
    
    if ($stmt->execute()) {
        $log_id = $conn->insert_id;
        echo "Test record inserted with ID: $log_id<br>";
        
        // Insert a test data row
        $data_stmt = $conn->prepare("INSERT INTO csv_data_backup 
                                   (log_id, row_num, data_json) 
                                   VALUES (?, 1, ?)");
                                   
        if (!$data_stmt) {
            echo "Data prepare failed: " . $conn->error . "<br>";
        } else {
            $json = json_encode(array('Status' => 'S', 'ID' => '123', 'Test' => 'TestData'));
            $data_stmt->bind_param("is", $log_id, $json);
            
            if ($data_stmt->execute()) {
                echo "Test data record inserted successfully<br>";
            } else {
                echo "Data execute failed: " . $data_stmt->error . "<br>";
            }
        }
    } else {
        echo "Execute failed: " . $stmt->error . "<br>";
    }
}

echo "<h2>Conclusion</h2>";
echo "Run this diagnosis tool to identify issues with your CSV backup system.<br>";
echo "Apply the patch code to generate_shipped_csv.php to fix the CSV backup for Shipped Data.<br>";
echo "Check the CSV history page after generating a new shipped data CSV.";

// For PHP 5.3 compatibility
$error = error_get_last();
echo "PHP error: " . (isset($error['message']) ? $error['message'] : 'Unknown error') . "<br>";
?>