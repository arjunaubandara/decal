<?php
// filepath: h:\Current\decal\csv_history.php

// Check if JSON output is requested
$is_json_request = (isset($_GET['format']) && $_GET['format'] === 'json');

if ($is_json_request) {
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
            $row['records_added'] = count(explode(',', $row['order_ids'])); // Count records from order_ids
            $historyData[] = $row;
        }
        $result->free();
    } else {
        echo json_encode(array('error' => 'Error fetching CSV history: ' . $conn->error));
        $conn->close();
        exit;
    }
    $conn->close();
    echo json_encode($historyData);
    exit; // IMPORTANT: Exit after sending JSON to prevent HTML output

} else {
    // --- HTML Rendering Part (for direct viewing) ---
    // require_once 'includes/auth.php'; // Add authentication check if needed
    require_once 'includes/db.php';   // Database connection for HTML page

    // Pagination for HTML page
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    // Filters for HTML page
    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

    // Build WHERE clause for HTML page
    $where = array();
    if (!empty($type_filter)) {
        $where[] = "type = '$type_filter'";
    }
    if (!empty($date_from)) {
        $where[] = "generated_at >= '$date_from 00:00:00'";
    }
    if (!empty($date_to)) {
        $where[] = "generated_at <= '$date_to 23:59:59'";
    }
    $where_clause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

    // Count total records for HTML page
    $count_sql = "SELECT COUNT(*) as total FROM csv_generation_log $where_clause";
    $count_result = $conn->query($count_sql); // $conn here is from includes/db.php
    $count_row = $count_result->fetch_assoc();
    $total_rows = $count_row['total'];

    // Get paginated results for HTML page
    $sql_html = "SELECT * FROM csv_generation_log 
            $where_clause
            ORDER BY generated_at DESC
            LIMIT $offset, $per_page";
    $result_html = $conn->query($sql_html); // $conn here is from includes/db.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSV History</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>CSV Generation History</h2>
        
        <!-- Filter Form -->
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="delivery" <?php echo $type_filter == 'delivery' ? 'selected' : ''; ?>>Delivery ACK</option>
                        <option value="decal" <?php echo $type_filter == 'decal' ? 'selected' : ''; ?>>Decal Master</option>
                        <option value="shipped" <?php echo $type_filter == 'shipped' ? 'selected' : ''; ?>>Shipped Data</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="csv_history.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
        
        <!-- Results Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Generated</th>
                        <th>Filename</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_html && $result_html->num_rows > 0): ?>
                        <?php while($row = $result_html->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                    <?php 
                                    switch($row['type']) {
                                        case 'delivery': echo 'Delivery ACK'; break;
                                        case 'decal': echo 'Decal Master'; break;
                                        case 'shipped': echo 'Shipped Data'; break;
                                        default: echo htmlspecialchars($row['type']);
                                    }
                                    ?>
                                </td>
                                <td><?php echo $row['generated_at']; ?></td>
                                <td><?php echo htmlspecialchars($row['filename']); ?></td>
                                <td>
                                    <a href="download_csv.php?log_id=<?php echo $row['id']; ?>&source=file" class="btn btn-sm btn-primary">Download File</a>
                                    <a href="download_csv.php?log_id=<?php echo $row['id']; ?>&source=db" class="btn btn-sm btn-success">Regenerate from DB</a>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="showOrderIds('<?php echo htmlspecialchars($row['order_ids']); ?>')">View Order IDs</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center">No records found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_rows > $per_page): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <?php 
                    $total_pages = ceil($total_rows / $per_page);
                    $params = $_GET;
                    
                    for ($i = 1; $i <= $total_pages; $i++) {
                        unset($params['format']); // Ensure format=json is not in pagination links
                        $params['page'] = $i;
                        $query_string = http_build_query($params);
                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
                        echo '<a class="page-link" href="?'.$query_string.'">'.$i.'</a>';
                        echo '</li>';
                    }
                    ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    
    <!-- Modal for Order IDs -->
    <div class="modal fade" id="orderIdsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order IDs</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="orderIdsList"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function showOrderIds(ids) {
            $('#orderIdsList').html(ids.split(',').join('<br>'));
            $('#orderIdsModal').modal('show');
        }
    </script>
</body>
</html>
<?php
} // End of HTML rendering part
?>