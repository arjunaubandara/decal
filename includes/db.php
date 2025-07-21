<?php
// Database connection configuration
$servername = "10.0.0.20";
$username = "appserver";
$password = "nlpl1234";
$dbname = "nlpl";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    throw $e;
}

/**
 * Sanitize input to prevent SQL injection
 * 
 * @param string $input
 * @return string
 */
function sanitize_input($input) {
    global $conn;
    return $conn->real_escape_string(trim($input));
}
?>