<?php
header('Content-Type: application/json');

// Database connection details
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(array('error' => 'Database connection failed: ' . $conn->connect_error));
    exit;
}

$sql = "SELECT 
            id,
            generated_at AS upload_date, 
            filename AS file_name, 
            order_ids,
            type AS csv_type
        FROM csv_generation_log
        ORDER BY generated_at DESC";

$result = $conn->query($sql);
$historyData = array();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['filepath'] = 'download_csv.php?log_id=' . $row['id'] . '&source=file';
        
        if (!empty($row['order_ids'])) {
            $order_ids_array = explode(',', $row['order_ids']);
            $row['records_added'] = count(array_filter($order_ids_array)); 
        } else {
            $row['records_added'] = 0;
        }
        $historyData[] = $row;
    }
    $result->free();
} else {
    echo json_encode(array('error' => 'Error fetching CSV history: ' . $conn->error, 'sql' => $sql));
    $conn->close();
    exit;
}

$conn->close();
echo json_encode($historyData);
?>