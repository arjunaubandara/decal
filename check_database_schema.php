<?php
// filepath: h:\Current\decal\check_database_schema.php
// Use this to check database structure

$server = '10.0.0.20';
$username = 'appserver';
$password = 'nlpl1234';
$database = 'nlpl';

$conn = new mysqli($server, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Table Schema for imporderdata_processed</h1>";

// Check table structure
$sql = "DESCRIBE imporderdata_processed";
$result = $conn->query($sql);

if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["Field"] . "</td>";
        echo "<td>" . $row["Type"] . "</td>";
        echo "<td>" . $row["Null"] . "</td>";
        echo "<td>" . $row["Key"] . "</td>";
        echo "<td>" . $row["Default"] . "</td>";
        echo "<td>" . $row["Extra"] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check for triggers
echo "<h1>Triggers on imporderdata_processed</h1>";

$sql = "SHOW TRIGGERS WHERE `table` = 'imporderdata_processed'";
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Trigger</th><th>Event</th><th>Statement</th><th>Timing</th></tr>";
        
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row["Trigger"] . "</td>";
            echo "<td>" . $row["Event"] . "</td>";
            echo "<td>" . $row["Statement"] . "</td>";
            echo "<td>" . $row["Timing"] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "No triggers found.";
    }
} else {
    echo "Error checking triggers: " . $conn->error;
}

$conn->close();
?>