<?php
// filepath: h:\Current\decal\generate_shipped_csv.php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include backup functions
require_once 'includes/csv_backup.php';
require_once 'includes/csv_db_backup.php';

// Log file for tracking errors (Windows path)
$log_file = 'C:\\wamp\\www\\Production\\decal\\generate_shipped_csv_error.log';

// Custom error logging function
function custom_error_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Ensure directory exists and is writable
    $dir = dirname($log_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Append to log file
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Improved error handling function
function handle_ajax_error($message, $error = null) {
    $log_message = $message;
    if ($error) {
        $log_message .= ": " . $error;
    }
    custom_error_log($log_message);
    
    // For AJAX requests, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'message' => $message)); // Fixed array syntax
    } else {
        // For direct browser requests generating CSV, don't break the file download
        // Just log the error but continue with CSV generation
    }
}

// Capture any fatal errors
function fatal_error_handler() {
    $error = error_get_last();
    if ($error !== NULL) {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];
        
        custom_error_log("FATAL ERROR: [$errno] $errstr in $errfile on line $errline");
        
        // Output a user-friendly error message
        header('Content-Type: text/plain');
        echo "An unexpected error occurred. Please check the server logs.";
        exit(1);
    }
}
register_shutdown_function('fatal_error_handler');

try {
    $server = '10.0.0.20';
    $username = 'appserver';
    $password = 'nlpl1234';
    $database = 'nlpl';

    $conn = new mysqli($server, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // If data is posted from the frontend, use it directly to generate the CSV
    if (isset($_POST['data'])) {
        $data = json_decode($_POST['data'], true);
        if (!is_array($data)) {
            throw new Exception('Invalid data format');
        }
        // Prepare CSV rows from posted data
        $csvRows = array();
        foreach ($data as $row) {
            $csvRows[] = array(
                'S',
                isset($row['order_no']) ? $row['order_no'] : '',
                isset($row['decal_patt']) ? $row['decal_patt'] : '',
                isset($row['curve_no']) ? $row['curve_no'] : '',
                isset($row['planned_delivery']) ? $row['planned_delivery'] : '',
                isset($row['lot_no']) ? $row['lot_no'] : '',
                isset($row['ship_qty']) ? $row['ship_qty'] : ''
            );
        }
        
        // Get IDs for tracking
        $ids = array();
        foreach ($data as $row) {
            if (isset($row['id'])) {
                $ids[] = intval($row['id']);
            }
        }
        
        // Create backup of the data before directly outputting
        if (!empty($ids) && !empty($csvRows)) {
            $headers = array('Status', 'Order No', 'Decal Patt', 'Curve No', 'Answer Date', 'Lot No', 'This Time Shipped Qty');
            
            // Use backup functions to save CSV history
            $backup_dir = __DIR__ . '/csv_backups/' . date('Y-m');
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            
            $timestamp = date('Y-m-d_His');
            $filepath = $backup_dir . '/shipped_data_' . $timestamp . '.csv';
            
            // Save to filesystem
            $file = fopen($filepath, 'w');
            fputcsv($file, $headers);
            foreach ($csvRows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            
            // Log to database
            $logId = log_csv_to_database('shipped', $ids, $headers, $csvRows, $filepath);
            
            // Store in session
            session_start();
            $_SESSION['last_csv_log_id'] = $logId;
            $_SESSION['csv_generated'] = true;
            $_SESSION['csv_type'] = 'shipped';
        }
        
        // Output CSV directly
        ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=Decal_Shipping_Data.csv');
        $output = fopen('php://output', 'w');
        $headers = array('Status','Order No','Decal Patt','Curve No','Answer Date','Lot No','This Time Shipped Qty');
        fputcsv($output, $headers);
        foreach ($csvRows as $csv_row) {
            fputcsv($output, $csv_row);
        }
        fclose($output);
        exit;
    }

    // Check both POST and GET for IDs
    $ids_param = isset($_POST['ids']) ? $_POST['ids'] : (isset($_GET['ids']) ? $_GET['ids'] : null);

    custom_error_log("Received IDs: " . print_r($ids_param, true));

    if (empty($ids_param)) {
        throw new Exception("No records selected");
    }

    $ids = explode(',', $ids_param);
    $ids = array_map('intval', $ids);
    $idList = implode(',', $ids);

    custom_error_log("Processed ID List: " . $idList);

    // Filename without date
    $filename = "Decal_Shipping_Data.csv";

    // Prepare SQL to get data and check for full shipment
    $sql = "SELECT 
                'S' as `Status`,
                i.order_no as `Order No`,
                i.decal_patt as `Decal Patt`,
                i.curve_no as `Curve No`,
                i.planned_delivery as `Answer Date`,
                i.lot_no as `Lot No`,
                l.ship_qty as `This Time Shipped Qty`
            FROM imporderdata_processed i
            JOIN (
                SELECT order_id, MAX(ship_qty) as ship_qty
                FROM shipped_quantities_log
                WHERE order_id IN ($idList)
                GROUP BY order_id
            ) l ON i.id = l.order_id
            WHERE i.id IN ($idList)
            ORDER BY i.order_no";

    custom_error_log("Executing SQL: " . $sql);

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    // Prepare arrays to track updates
    $fullyShippedIds = array();
    $partialShippedIds = array();
    $csvRows = array();

    // Collect CSV rows and track shipment status
    while ($row = $result->fetch_assoc()) {
        $csvRows[] = array(
            $row['Status'],
            isset($row['Order No']) ? $row['Order No'] : '',
            isset($row['Decal Patt']) ? $row['Decal Patt'] : '',
            isset($row['Curve No']) ? $row['Curve No'] : '',
            isset($row['Answer Date']) ? $row['Answer Date'] : '',
            isset($row['Lot No']) ? $row['Lot No'] : '',
            isset($row['This Time Shipped Qty']) ? $row['This Time Shipped Qty'] : ''
        );
    }

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
            custom_error_log("Server filepath: " . $server_filepath);
            
            // Check if tables exist and create if needed
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
            
            $conn->query("CREATE TABLE IF NOT EXISTS csv_data_backup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                log_id INT NOT NULL,
                row_num INT NOT NULL,
                data_json TEXT NOT NULL
            )");
            
            // Insert log record directly instead of using the function
            $idList = implode(',', $ids);
            
            $stmt = $conn->prepare("INSERT INTO csv_generation_log 
                                  (type, filename, filepath, order_ids)
                                  VALUES ('shipped', ?, ?, ?)");
                                  
            if (!$stmt) {
                throw new Exception("Failed to prepare log statement: " . $conn->error);
            }
            
            $stmt->bind_param("sss", $backup_filename, $server_filepath, $idList);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute log statement: " . $stmt->error);
            }
            
            $logId = $conn->insert_id;
            custom_error_log("Log inserted with ID: " . $logId);
            
            // Insert data records
            $stmtData = $conn->prepare("INSERT INTO csv_data_backup 
                                       (log_id, row_num, data_json) 
                                       VALUES (?, ?, ?)");
                                       
            if (!$stmtData) {
                throw new Exception("Failed to prepare data statement: " . $conn->error);
            }
            
            foreach ($csvRows as $i => $row) {
                // Create associative array for each row
                $rowData = array();
                foreach ($headers as $j => $header) {
                    $rowData[$header] = isset($row[$j]) ? $row[$j] : '';
                }
                
                $json = json_encode($rowData);
                $rowNum = $i + 1;
                
                $stmtData->bind_param("iis", $logId, $rowNum, $json);
                $stmtData->execute();
            }
            
            // Store in session
            @session_start();
            $_SESSION['last_csv_log_id'] = $logId;
            $_SESSION['csv_generated'] = true;
            $_SESSION['csv_type'] = 'shipped';
            
            custom_error_log("CSV backup created and logged successfully");
        } catch (Exception $e) {
            custom_error_log("ERROR creating CSV backup: " . $e->getMessage());
            // Continue with CSV generation even if backup fails
        }
    }

    custom_error_log("Fully Shipped IDs: " . print_r($fullyShippedIds, true));
    custom_error_log("Partial Shipped IDs: " . print_r($partialShippedIds, true));
    
    // CRITICAL FIX: Make sure lot_no is preserved
    $updatePreserveFields = "UPDATE imporderdata_processed 
                         SET shipped = 1,
                             csv_generated = 1,
                             lot_no = lot_no,
                             delivery_confirm = delivery_confirm
                         WHERE id IN ($idList)";
                         
    if (!$conn->query($updatePreserveFields)) {
        handle_ajax_error("Error updating records", $conn->error);
        // But don't throw exception - let CSV generation continue
    } else {
        custom_error_log("Successfully preserved fields with: " . $updatePreserveFields);
    }
    
    // Update fd140 table
    $updateFd140Sql = "UPDATE fd140 f
                       JOIN imporderdata_processed i ON 
                           f.Design = i.decal_patt AND 
                           f.Curve = i.curve_no
                       SET f.Shipped = 1
                       WHERE i.id IN ($idList)";
                       
    if (!$conn->query($updateFd140Sql)) {
        handle_ajax_error("Error updating fd140", $conn->error);
        // But don't throw exception
    }
    
    // Continue with CSV generation...
    

    // Generate CSV
    ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Write headers
    $headers = array(
        'Status', 
        'Order No', 
        'Decal Patt', 
        'Curve No', 
        'Answer Date', 
        'Lot No', 
        'This Time Shipped Qty'
    );
    fputcsv($output, $headers);

    // Write data rows
    foreach ($csvRows as $csv_row) {
        fputcsv($output, $csv_row);
    }

    fclose($output);
    $conn->close();
    exit;

} catch (Exception $e) {
    // Log the full error details
    custom_error_log("Caught Exception: " . $e->getMessage());
    custom_error_log("Trace: " . $e->getTraceAsString());

    // Output a user-friendly error message
    header('Content-Type: text/plain');
    echo "An error occurred: " . $e->getMessage();
    exit(1);
}
?>