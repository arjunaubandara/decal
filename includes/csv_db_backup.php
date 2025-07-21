<?php
// filepath: h:\Current\decal\includes\csv_db_backup.php

/**
 * Store CSV generation data in the database
 * 
 * @param string $type CSV type ('delivery', 'decal', 'shipped')
 * @param array $ids Order IDs included in the CSV
 * @param array $headers CSV headers
 * @param array $data CSV data rows
 * @param string $filepath Path to the stored file
 * @return int Log ID for referencing
 */
function log_csv_to_database($type, $ids, $headers, $data, $filepath) {
    global $conn;
    
    // Check if tables exist
    $result = $conn->query("SHOW TABLES LIKE 'csv_generation_log'");
    if ($result->num_rows == 0) {
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
        
        $conn->query("CREATE TABLE IF NOT EXISTS csv_data_backup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_id INT NOT NULL,
            row_num INT NOT NULL,
            data_json TEXT NOT NULL,
            FOREIGN KEY (log_id) REFERENCES csv_generation_log(id)
        )");
    }
    
    // Insert into generation log
    $filename = basename($filepath);
    $idList = implode(',', $ids);
    
    $stmt = $conn->prepare("INSERT INTO csv_generation_log 
                           (type, filename, filepath, order_ids)
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $type, $filename, $filepath, $idList);
    $stmt->execute();
    
    $logId = $conn->insert_id;
    
    // Store each row with headers as JSON
    $stmtData = $conn->prepare("INSERT INTO csv_data_backup 
                              (log_id, row_num, data_json) 
                              VALUES (?, ?, ?)");
    
    foreach ($data as $i => $row) {
        // Create associative array combining headers with data
        $rowData = array();
        foreach ($headers as $j => $header) {
            $rowData[$header] = isset($row[$j]) ? $row[$j] : '';
        }
        
        $json = json_encode($rowData);
        $rowNum = $i + 1;
        
        $stmtData->bind_param("iis", $logId, $rowNum, $json);
        $stmtData->execute();
    }
    
    $stmtData->close();
    return $logId;
}
?>