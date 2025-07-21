<?php
// filepath: h:\Current\decal\includes\csv_backup.php

/**
 * Save a backup copy of generated CSV on the server
 * 
 * @param string $type CSV type ('delivery', 'decal', 'shipped')
 * @param array $headers CSV headers
 * @param array $data CSV data rows
 * @return string The path to the saved file
 */
function save_csv_backup($type, $headers, $data) {
    // Create backup directory if it doesn't exist
    $backup_dir = __DIR__ . '/../csv_backups/' . date('Y-m');
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Generate unique filename with timestamp and type
    $timestamp = date('Y-m-d_His');
    $filename = "{$type}_data_{$timestamp}.csv";
    $filepath = $backup_dir . '/' . $filename;
    
    // Write CSV file
    $file = fopen($filepath, 'w');
    fputcsv($file, $headers);
    foreach ($data as $row) {
        fputcsv($file, $row);
    }
    fclose($file);
    
    return $filepath;
}
?>