<?php
// filepath: h:\Current\decal\download_csv.php

require_once 'includes/auth.php'; // Add authentication check if needed
require_once 'includes/db.php';   // Database connection

$log_id = isset($_GET['log_id']) ? (int)$_GET['log_id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : 'file';

if (!$log_id) {
    die("Missing log ID");
}

// Get CSV info
$stmt = $conn->prepare("SELECT * FROM csv_generation_log WHERE id = ?");
$stmt->bind_param("i", $log_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("CSV record not found");
}

$row = $result->fetch_assoc();
$filename = $row['filename'];
$filepath = $row['filepath'];
$type = $row['type'];

if ($source === 'file' && file_exists($filepath)) {
    // Download the existing file
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    // Regenerate from database
    $stmt = $conn->prepare("SELECT * FROM csv_data_backup WHERE log_id = ? ORDER BY row_num ASC");
    $stmt->bind_param("i", $log_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("No CSV data found in backup");
    }
    
    // Get first row to extract headers
    $firstRow = $result->fetch_assoc();
    $data = json_decode($firstRow['data_json'], true);
    $headers = array_keys($data);
    
    // Reset result pointer
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Output CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    while ($row = $result->fetch_assoc()) {
        $data = json_decode($row['data_json'], true);
        fputcsv($output, array_values($data));
    }
    
    fclose($output);
    exit;
}
?>