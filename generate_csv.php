<?php
// filepath: h:\Current\decal\generate_csv.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_GET['type']) || !isset($_GET['ids'])) {
    die("Missing parameters");
}

$type = $_GET['type'];
$ids = explode(',', $_GET['ids']);
$ids = array_map('intval', $ids);
$idList = implode(',', $ids);

if (empty($idList)) {
    die("No valid IDs provided");
}

// Check for debug mode
$debug = isset($_GET['debug']) && $_GET['debug'] == 1;

if ($type == 'delivery') {
    $filename = "Decal_Shipping_Data.csv";
    $sql = "SELECT 
                'A' as Status,
                order_no as `Order no`,
                decal_patt as `Decal Patt`,
                curve_no as `Curve No`,
                planned_delivery as `Answer date`,
                lot_no as `Lot No`,
                order_quantity as `Shipping Quantity`
            FROM imporderdata_processed 
            WHERE id IN ($idList)";
            
    // Update csv_generated status
    $updateSql = "UPDATE imporderdata_processed SET csv_generated = 1 
                  WHERE id IN ($idList)";
    $conn->query($updateSql);
    
} else if ($type == 'decal') {
    $filename = "Decal_master.csv";
    $sql = "SELECT 
                COALESCE(aa.Status, 'N') as Status,
                b.decal_patt as `Decal Patt`,
                b.curve_no as `Curve No`,
                aa.PrintingPattern as `Print Patt`,
                aa.Doughnut as `Doughnut Mark`,
                aa.CombiCurve1 as `Combi Curve No1`,
                aa.CombiCurve2 as `Combi Curve No2`,
                aa.CombiCurve3 as `Combi Curve No3`,
                aa.CombiCurve1Qty as `Attached Quantity1`,
                aa.CombiCurve2Qty as `Attached Quantity2`,
                aa.CombiCurve3Qty as `Attached Quantity3`
            FROM imporderdata_processed b 
            LEFT JOIN (
                SELECT 
                    CASE WHEN Shipped = '1' THEN 'U' ELSE 'N' END AS Status,
                    Design, 
                    Curve, 
                    PrintingPattern, 
                    Doughnut, 
                    CombiCurve1, 
                    CombiCurve2, 
                    CombiCurve3, 
                    CombiCurve1Qty, 
                    CombiCurve2Qty, 
                    CombiCurve3Qty 
                FROM fd140
            ) AS aa ON aa.Design = b.decal_patt AND aa.Curve = b.curve_no
            WHERE b.id IN ($idList)";
}

// Execute the query
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// Debug mode - show the query and results instead of generating CSV
if ($debug) {
    echo "<h2>Debug Information</h2>";
    echo "<h3>SQL Query:</h3>";
    echo "<pre>$sql</pre>";
    
    echo "<h3>Results:</h3>";
    if ($result->num_rows == 0) {
        echo "<p>No results found.</p>";
    } else {
        echo "<p>Found {$result->num_rows} rows.</p>";
        echo "<table border='1'>";
        $row = $result->fetch_assoc();
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";
        
        // Reset the result pointer
        $result->data_seek(0);
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    exit;
}

// Only start session if not in debug mode
session_start();
$_SESSION['csv_generated'] = true;
$_SESSION['csv_type'] = $type; // Save which type was generated
$_SESSION['return_to'] = 'delivery-ack';

// Set content type as plain text
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename=' . $filename);

if ($result->num_rows > 0) {
    // Get the headers from first row
    $firstRow = $result->fetch_assoc();
    
    // Output simplified header row (no quotes)
    if ($type == 'delivery') {
        echo "Status,Order no,Decal Patt,Curve No,Answer date,Lot No,Shipping Quantity\r\n";
    } else {
        echo "Status,Decal Patt,Curve No,Print Patt,Doughnut Mark,Combi Curve No1,Combi Curve No2,Combi Curve No3,Attached Quantity1,Attached Quantity2,Attached Quantity3\r\n";
    }
    
    // Reset pointer
    $result->data_seek(0);
    
    // Write data rows without any quotes
    while ($row = $result->fetch_assoc()) {
        $values = array();
        foreach ($row as $value) {
            // Sanitize the value - replace commas with spaces to prevent CSV corruption
            $value = str_replace(',', ' ', $value);
            $value = str_replace("\n", ' ', $value);
            $value = str_replace("\r", '', $value);
            $values[] = $value;
        }
        echo implode(',', $values) . "\r\n";
    }
} else {
    // Output headers even if no data
    if ($type == 'delivery') {
        echo "Status,Order no,Decal Patt,Curve No,Answer date,Lot No,Shipping Quantity\r\n";
    } else {
        echo "Status,Decal Patt,Curve No,Print Patt,Doughnut Mark,Combi Curve No1,Combi Curve No2,Combi Curve No3,Attached Quantity1,Attached Quantity2,Attached Quantity3\r\n";
    }
}

// Include backup functions
require_once 'includes/csv_backup.php';
require_once 'includes/csv_db_backup.php';

// After preparing the CSV data but before outputting it
if ($result->num_rows > 0) {
    // Create arrays for headers and data
    $headers = array();
    $data = array();
    
    // Set headers based on type
    if ($type == 'delivery') {
        $headers = array('Status', 'Order no', 'Decal Patt', 'Curve No', 'Answer date', 'Lot No', 'Shipping Quantity');
    } else {
        $headers = array('Status', 'Decal Patt', 'Curve No', 'Print Patt', 'Doughnut Mark', 
                         'Combi Curve No1', 'Combi Curve No2', 'Combi Curve No3',
                         'Attached Quantity1', 'Attached Quantity2', 'Attached Quantity3');
    }
    
    // Collect data rows (copy from result)
    $result->data_seek(0); // Reset cursor to beginning
    while ($row = $result->fetch_assoc()) {
        $values = array();
        foreach ($row as $value) {
            // Use your existing sanitization logic
            $value = str_replace(',', ' ', $value);
            $value = str_replace("\n", ' ', $value);
            $value = str_replace("\r", '', $value);
            $values[] = $value;
        }
        $data[] = $values;
    }
    
    // Save backups (new functionality)
    $filepath = save_csv_backup($type, $headers, $data);
    $logId = log_csv_to_database($type, $ids, $headers, $data, $filepath);
    
    // Store the log ID in session for possible retrieval
    $_SESSION['last_csv_log_id'] = $logId;
    
    // Reset result pointer for your existing CSV output code
    $result->data_seek(0);
}

// Close connection
$conn->close();

// Update database after CSV generation
if (isset($_GET['type']) && isset($_GET['ids'])) {
    // Create log
    $csv_log = 'csv_updates.log';
    $log = function($msg) use ($csv_log) {
        file_put_contents($csv_log, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
    };
    
    $log("CSV Generation completed, updating database");
    
    $type = $_GET['type'];
    $ids = explode(',', $_GET['ids']);
    $id_list = implode(',', array_map('intval', $ids));
    
    $log("Type: $type, IDs: $id_list");
    
    // New database connection
    $conn = new mysqli($server, $username, $password, $database);
    
    if (!$conn->connect_error) {
        if ($type == 'delivery') {
            // For Delivery ACK CSV ONLY
            $sql = "UPDATE imporderdata_processed 
                   SET delivery_confirm = 1, 
                       csv_generated = 1 
                   WHERE id IN ($id_list)";
            $log("Running SQL: $sql");
            $result = $conn->query($sql);
            $log("Update result: " . ($result ? "Success" : "Failed - " . $conn->error));
        } else if ($type == 'decal') {
            // For Decal Master CSV: DO NOT update database!
            $log("No database update for decal master CSV.");
        }
        
        // Set session variables
        $_SESSION['csv_generated'] = true;
        $_SESSION['csv_type'] = $type;
        
        $conn->close();
        $log("Database connection closed");
    } else {
        $log("Database connection failed: " . $conn->connect_error);
    }
}
?>
