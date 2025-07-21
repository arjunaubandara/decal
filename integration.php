<?php
// At the very top, before anything else
error_log("=== Starting integration.php ===");

// Set error handling to catch everything
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Enable error reporting and logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Log request details
error_log("POST data: " . print_r($_POST, true));
error_log("Server variables: " . print_r($_SERVER, true));

// Function to send JSON response
function sendJsonResponse($status, $message, $data = null) {
    try {
        if (headers_sent($filename, $linenum)) {
            error_log("Headers already sent in $filename on line $linenum");
            throw new Exception("Headers already sent");
        }
        
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        $response = array(
            'status' => $status,
            'message' => $message,
            'timestamp' => date('c')
        );
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        $json = json_encode($response);
        if ($json === false) {
            throw new Exception("JSON encode failed: " . json_last_error_msg());
        }
        
        echo $json;
        exit;
    } catch (Exception $e) {
        error_log("Error in sendJsonResponse: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
}

try {
    // Database connection with error checking
    $conn = @new mysqli("10.0.0.20", "appserver", "nlpl1234", "nlpl");
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    error_log("Database connection successful");

    // Check if this is an AJAX request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer'])) {
        // Start transaction using older syntax
        $conn->autocommit(FALSE);

        // Select records to transfer
        $sql = "SELECT * FROM imporderdata WHERE integrated = 0";
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $transferred = 0;
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Prepare the insert statement
                $lot_no = ''; // Use an empty string as default.
                $stmt = $conn->prepare("INSERT INTO imporderdata_processed 
                    (status, dest, order_no, decal_patt, curve_no, order_quantity, 
                    delivery_date, order_date, upload_date, planned_delivery, lot_no) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                // Bind parameters
                if (!$stmt->bind_param("sssssisssss",
                    $row['status'],
                    $row['dest'],
                    $row['order_no'],
                    $row['decal_patt'],
                    $row['curve_no'],
                    $row['order_quantity'],
                    $row['delivery_date'],
                    $row['order_date'],
                    $row['upload_date'],
                    $row['delivery_date'],
                    $lot_no
                )) {
                    throw new Exception("Binding parameters failed: " . $stmt->error);
                }

                // Execute the insert
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }

                // Update the original record
                $update_stmt = $conn->prepare("UPDATE imporderdata SET integrated = 1 WHERE id = ?");
                if (!$update_stmt) {
                    throw new Exception("Update prepare failed: " . $conn->error);
                }

                if (!$update_stmt->bind_param("i", $row['id'])) {
                    throw new Exception("Update binding failed: " . $update_stmt->error);
                }

                if (!$update_stmt->execute()) {
                    throw new Exception("Update execute failed: " . $update_stmt->error);
                }

                $transferred++;
            }

            // Commit the transaction
            if (!$conn->commit()) {
                throw new Exception("Commit failed");
            }

            sendJsonResponse('success', "Successfully transferred $transferred records", 
                array('transferred' => $transferred));
        } else {
            $conn->rollback();
            sendJsonResponse('warning', 'No records available for transfer');
        }
    } else {
        // Regular page load - display records
        header('Content-Type: text/html');
        displayRecords($conn);
    }

} catch (Exception $e) {
    error_log("Fatal error in integration.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($conn) && $conn->ping()) {
        try {
            $conn->rollback();
        } catch (Exception $rollbackError) {
            error_log("Rollback failed: " . $rollbackError->getMessage());
        }
    }
    
    sendJsonResponse('error', "An error occurred: " . $e->getMessage());
}

// Ensure connection is closed
if (isset($conn)) {
    try {
        $conn->close();
    } catch (Exception $e) {
        error_log("Error closing connection: " . $e->getMessage());
    }
}

error_log("=== Ending integration.php ===");

// Function to display available records for integration
function displayRecords($conn)
{
    $sql = "SELECT * FROM imporderdata WHERE integrated = 0";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<h3>Records Ready for Transfer</h3>";
        echo "<table border='1'>";
        echo "<tr>
                <th>ID</th>
                <th>Status</th>
                <th>Destination</th>
                <th>Order No</th>
                <th>Decal Pattern</th>
                <th>Curve No</th>
                <th>Order Quantity</th>
                <th>Delivery Date</th>
                <th>Order Date</th>
                <th>Upload Date</th>
              </tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['status']}</td>
                    <td>{$row['dest']}</td>
                    <td>{$row['order_no']}</td>
                    <td>{$row['decal_patt']}</td>
                    <td>{$row['curve_no']}</td>
                    <td>{$row['order_quantity']}</td>
                    <td>{$row['delivery_date']}</td>
                    <td>{$row['order_date']}</td>
                    <td>{$row['upload_date']}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No records available for Transfer.</p>";
    }
}
?>
