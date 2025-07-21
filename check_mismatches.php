<?php
date_default_timezone_set("Asia/Colombo"); // Set the timezone

echo "<h2><p style='color: red;'>Check Printing Masters before proceeding to integration.</p></h2>";

$servername = "10.0.0.20";
$username = "appserver";
$password = "nlpl1234";
$dbname = "nlpl";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch non-matching records from curve_master_tbl
$sql2 = "
    SELECT * FROM imporderdata i 
    WHERE NOT EXISTS (
        SELECT 1 FROM curve_master_tbl c 
        WHERE i.decal_patt = c.fg_design_no_cmt AND 
              CAST(i.curve_no AS UNSIGNED) = CAST(c.curve_no_cmt AS UNSIGNED)
    )
";
$result2 = $conn->query($sql2);

// Fetch non-matching records from pigment_master_tbl
$sql3 = "
    SELECT * FROM (SELECT i.*, c.decal_design_no_cmt FROM imporderdata i
    LEFT JOIN curve_master_tbl c on
    i.decal_patt = c.fg_design_no_cmt AND i.curve_no = c.curve_no_cmt) AS ii
    WHERE NOT EXISTS (
        SELECT 1 FROM pigment_master_tbl p 
        WHERE ii.decal_design_no_cmt = p.pattern_pm
    )
";
$result3 = $conn->query($sql3);

// Fetch non-matching records from colours_tbl
$sql4 = "
    SELECT * FROM (SELECT i.*, c.decal_design_no_cmt FROM imporderdata i
    LEFT JOIN curve_master_tbl c on
    i.decal_patt = c.fg_design_no_cmt AND i.curve_no = c.curve_no_cmt) AS ii 
    WHERE NOT EXISTS (
        SELECT 1 FROM colours_tbl d 
        WHERE  ii.decal_design_no_cmt = d.pattern_no_ct
    )
";
$result4 = $conn->query($sql4);

// Fetch non-matching records from fd140_tbl
$sql5 = "
    SELECT * FROM imporderdata i
    WHERE NOT EXISTS (
        SELECT 1 FROM fd140 e 
        WHERE i.decal_patt = e.Design AND 
              CAST(i.curve_no AS UNSIGNED) = CAST(e.Curve AS UNSIGNED)
    )
";
$result5 = $conn->query($sql5);

// Initialize a flag to determine if there are mismatches
$hasMismatches = false;

// Function to display non-matching records
function displayMismatch($result, $title) {
    global $hasMismatches;
    if ($result->num_rows > 0) {
        $hasMismatches = true; // Set flag to true if mismatches exist
        echo "<h4>$title</h4>";
        echo "<table border='1'>";
        echo "<tr>
            <th>Status</th>
            <th>Order No</th>
            <th>Decal Pattern</th>
            <th>Curve No</th>
            <th>Order Quantity</th>
            <th>Delivery Date</th>
            <th>Order Date</th>
        </tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['status']}</td>
                <td>{$row['order_no']}</td>
                <td>{$row['decal_patt']}</td>
                <td>{$row['curve_no']}</td>
                <td>{$row['order_quantity']}</td>
                <td>{$row['delivery_date']}</td>
                <td>{$row['order_date']}</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<h4>$title: All required master data available</h4>";
    }
}

// Display mismatches
displayMismatch($result2, "Need to update below Curve Master data");
displayMismatch($result3, "Need to update below Pigment Master data");
displayMismatch($result4, "Need to update below Colours Master data");
displayMismatch($result5, "Need to update below FD140 (Curve) data");

// Show integration button if no mismatches
if (!$hasMismatches) {
    
    // echo "<form id='integrationForm' method='POST'>
    echo "Proceed to Integration";
    // <button type='submit' name='transfer'>Proceed to Integration</button>
   // </form>";
} else {
    echo "<h3><p style='color: red;'>Create necessary master data before integration.</p></h3>";
}

$conn->close();
// Add this at the end of your mismatch checking logic
$hasMismatches = false; // Initialize flag

// Assuming you already have mismatch checking code that populates results
if (!empty($mismatchResults)) {
    $hasMismatches = true;
}

// Store the flag in session for access across pages
$_SESSION['has_mismatches'] = $hasMismatches;

?>
