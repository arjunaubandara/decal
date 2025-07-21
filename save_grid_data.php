<?php
header('Content-Type: application/json');

// Database connection parameters
$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

// Create a connection to the database
$conn = new mysqli($server, $username, $password, $database);

// Check if the connection was successful
if ($conn->connect_error) {
    die(json_encode(array('error' => 'Database connection failed: ' . $conn->connect_error)));
}

// Check if the updates are received from the front-end
if (isset($_POST['updates'])) {
    $updates = $_POST['updates'];
    
    // Loop through each update and perform the update query
    foreach ($updates as $update) {
        $id = $update['id'];
        $planned_delivery = $update['planned_delivery'];

        // Update the planned_delivery field for each row
        $sql = "UPDATE imporderdata_processed SET planned_delivery = ?, processed = 1 WHERE id = ?";

        // Prepare the statement to prevent SQL injection
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $planned_delivery, $id);

        if (!$stmt->execute()) {
            // If query fails, send an error response
            echo json_encode(array('error' => 'Error updating record with ID ' . $id . ': ' . $stmt->error));
            exit;
        }
    }

    // If everything is successful, send a success response
    echo json_encode(array('success' => 'Data updated successfully!'));
} else {
    // If no updates are received, send an error response
    echo json_encode(array('error' => 'No updates received.'));
}

//Close the database connection
$conn->close();
?>
