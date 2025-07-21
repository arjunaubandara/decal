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

// Insert records into minus report before updating sts, so only '0' are included
$insertSql = "INSERT INTO tbl_minus_report_dec_final (dec_patt, dec_curve, item, ORDEST, ORNYDATE, qty)
SELECT dec_patt, dec_curve, item, ORDEST, ORNYDATE, qty
FROM tbl_imari_dec_final WHERE sts = '0'";
$insertResult = $destConn->query($insertSql);
if (!$insertResult) {
    $destConn->rollback();
    $sourceConn->rollback();
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error inserting minus report: ' . $destConn->error
    ));
}

// Update all relevant records (set sts = '1' where sts = '0')
$updateSql = "UPDATE tbl_imari_dec_final SET sts = '1' WHERE sts = '0'";
$updateResult = $destConn->query($updateSql);
if (!$updateResult) {
    $destConn->rollback();
    $sourceConn->rollback();
    outputJSON(array(
        'status' => 'error',
        'message' => 'Error updating records: ' . $destConn->error
    ));
}

$recordsProcessed = $destConn->affected_rows;

// Commit transactions
$sourceConn->commit();
$destConn->commit();

// Close resources
$sourceConn->close();
$destConn->close();

outputJSON(array(
    'status' => 'success',
    'message' => 'Successfully updated plan and inserted minus report.',
    'records' => $recordsProcessed
));
?>