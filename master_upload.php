<?php
date_default_timezone_set("Asia/Colombo"); // Set the timezone

$servername = "10.0.0.20";
$username = "appserver";
$password = "nlpl1234";
$dbname = "nlpl";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create the table if it doesn't exist
$table_creation_query = "
    CREATE TABLE IF NOT EXISTS decalMasterNhq (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status CHAR(1),
        decal_patt TEXT,
        curve_no TEXT,
        print_patt TEXT,
        doughnut_mark CHAR(1),
        combi_curve_1 TEXT,
        combi_curve_2 TEXT,
        combi_curve_3 TEXT,
        attached_qty_1 INT,
        attached_qty_2 INT,
        attached_qty_3 INT,
        upload_date DATE
    )
";
$conn->query($table_creation_query);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"]["tmp_name"];

    if (($handle = fopen($file, "r")) !== false) {
        // Skip the header row
        fgetcsv($handle);

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $status = $data[0];
            $decal_patt = $data[1];
            $curve_no = $data[2];
            $print_patt = $data[3];
            $doughnut_mark = $data[4];
            $combi_curve_1 = $data[5];
            $combi_curve_2 = $data[6];
            $combi_curve_3 = $data[7];
            $attached_qty_1 = (int)$data[8];
            $attached_qty_2 = (int)$data[9];
            $attached_qty_3 = (int)$data[10];
            $upload_date = date('Y-m-d');

            // Check for duplicates
            $check_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM decalMasterNhq WHERE status = ? AND decal_patt = ? AND curve_no = ?");
            $check_stmt->bind_param("sss", $status, $decal_patt, $curve_no);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $row = $check_result->fetch_assoc();

            if ($row['cnt'] == 0) {
                // Insert new record
                $stmt = $conn->prepare("
                    INSERT INTO decalMasterNhq 
                    (status, decal_patt, curve_no, print_patt, doughnut_mark, combi_curve_1, combi_curve_2, combi_curve_3, attached_qty_1, attached_qty_2, attached_qty_3, upload_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssssssiiis",
                    $status,
                    $decal_patt,
                    $curve_no,
                    $print_patt,
                    $doughnut_mark,
                    $combi_curve_1,
                    $combi_curve_2,
                    $combi_curve_3,
                    $attached_qty_1,
                    $attached_qty_2,
                    $attached_qty_3,
                    $upload_date
                );
                $stmt->execute();
            }
        }

        fclose($handle);
        echo "Data uploaded successfully!";
    } else {
        echo "Error opening the file.";
    }
}

// Fetch and display data uploaded today
$current_date = date('Y-m-d');
$sql = "SELECT * FROM decalMasterNhq WHERE upload_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<h2>Uploaded Data for " . $current_date . "</h2>";
    echo "<button onclick='window.print()'>Print</button>";
    echo "<table border='1'>";
    echo "<tr>
        <th>Status</th>
        <th>Decal Pattern</th>
        <th>Curve No</th>
        <th>Print Pattern</th>
        <th>Doughnut Mark</th>
        <th>Combi Curve 1</th>
        <th>Combi Curve 2</th>
        <th>Combi Curve 3</th>
        <th>Attached Qty 1</th>
        <th>Attached Qty 2</th>
        <th>Attached Qty 3</th>
        <th>Upload Date</th>
    </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['status']}</td>
            <td>{$row['decal_patt']}</td>
            <td>{$row['curve_no']}</td>
            <td>{$row['print_patt']}</td>
            <td>{$row['doughnut_mark']}</td>
            <td>{$row['combi_curve_1']}</td>
            <td>{$row['combi_curve_2']}</td>
            <td>{$row['combi_curve_3']}</td>
            <td>{$row['attached_qty_1']}</td>
            <td>{$row['attached_qty_2']}</td>
            <td>{$row['attached_qty_3']}</td>
            <td>{$row['upload_date']}</td>
        </tr>";
    }
    echo "</table>";
} else {
    echo "No data uploaded today.";
}

$conn->close();
?>
