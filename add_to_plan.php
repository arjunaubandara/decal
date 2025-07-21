<?php
// Prevent any HTML error output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Source database connection
$sourceServer = '10.0.0.20';
$sourceUsername = 'appserver';
$sourcePassword = 'nlpl1234';
$sourceDatabase = 'nlpl';

// Destination database connection
$destServer = '127.0.0.1';
$destUsername = 'root';
$destPassword = '';
$destDatabase = 'packing_system';

function outputJSON($data) {
    echo json_encode($data);
    exit;
}

// Connect to source database
$sourceConn = new mysqli($sourceServer, $sourceUsername, $sourcePassword, $sourceDatabase);
if ($sourceConn->connect_error) {
    outputJSON(array(
        'status' => 'error',
        'message' => 'Source connection failed: ' . $sourceConn->connect_error
    ));
}

// Connect to destination database
$destConn = new mysqli($destServer, $destUsername, $destPassword, $destDatabase);
if ($destConn->connect_error) {
    outputJSON(array(
        'status' => 'error',
        'message' => 'Destination connection failed: ' . $destConn->connect_error
    ));
}

// Start transaction in both databases
$sourceConn->autocommit(FALSE);
$destConn->autocommit(FALSE);

// Get records to copy
$selectSql = "SELECT dest, decal_patt, curve_no, order_quantity, planned_delivery, order_no 
              FROM imporderdata_processed 
              WHERE processed = 1 AND planned = 0";

$result = $sourceConn->query($selectSql);

if (!$result) {
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error selecting records: ' . $sourceConn->error
    ));
}

$recordsProcessed = 0;

// Prepare insert statement
$insertSql = "INSERT INTO tbl_imari_dec_final 
              (dec_patt, dec_curve, item, ORDEST, ORNYDATE, qty, order_no, sts) 
              VALUES (?, ?, '', ?, ?, ?, ?, '0')";

$insertStmt = $destConn->prepare($insertSql);
if (!$insertStmt) {
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error preparing insert statement: ' . $destConn->error
    ));
}

$success = true;
while ($row = $result->fetch_assoc()) {
    $insertStmt->bind_param("ssssis", 
        $row['decal_patt'],
        $row['curve_no'],
        $row['dest'],
        $row['planned_delivery'],
        $row['order_quantity'],
        $row['order_no']
    );

    if (!$insertStmt->execute()) {
        $success = false;
        break;
    }

    $recordsProcessed++;
}

if (!$success) {
    $sourceConn->rollback();
    $destConn->rollback();
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error inserting record: ' . $insertStmt->error
    ));
}

// Update processed records
$updateSql = "UPDATE imporderdata_processed SET planned = 1 
              WHERE processed = 1 AND planned = 0";

if (!$sourceConn->query($updateSql)) {
    $sourceConn->rollback();
    $destConn->rollback();
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error updating records: ' . $sourceConn->error
    ));
}

// Commit transactions
$sourceConn->commit();
$destConn->commit();

// Close resources
$insertStmt->close();
$sourceConn->close();
$destConn->close();

outputJSON(array(
    'status' => 'success',
    'message' => 'Successfully processed ' . $recordsProcessed . ' records',
    'records' => $recordsProcessed
));
?>