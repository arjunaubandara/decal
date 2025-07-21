<?php
// filepath: h:\Current\decal\save_data.php
// Ultra-defensive, PHP 5.3.3 compatible, with extra logging after escaping

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

function log_it($msg) {
    $file = 'minimal_save.log';
    file_put_contents($file, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

log_it("SCRIPT START");
log_it("POST DATA: " . print_r($_POST, true));

$response = array('success' => false, 'message' => 'No processing performed');

$db = mysqli_connect('10.0.0.20', 'appserver', 'nlpl1234', 'nlpl');
if (!$db) {
    log_it("DB CONNECTION FAILED");
    $response['message'] = "Database connection failed";
} else {
    log_it("DB CONNECTED");

    // Set charset to utf8 for PHP 5.3.3 compatibility
    mysqli_set_charset($db, 'utf8');
    log_it("CHARSET SET TO utf8");

    if (isset($_POST['data']) && is_array($_POST['data'])) {
        $updated = 0;
        foreach ($_POST['data'] as $item) {
            log_it("PROCESSING: " . print_r($item, true));
            $id = isset($item['id']) ? intval($item['id']) : 0;
            if ($id <= 0) {
                log_it("INVALID ID: $id");
                continue;
            }

            $pd_raw = isset($item['planned_delivery']) ? $item['planned_delivery'] : '';
            $lot_raw = isset($item['lot_no']) ? $item['lot_no'] : '';
            log_it("RAW PD: " . var_export($pd_raw, true));
            log_it("RAW LOT: " . var_export($lot_raw, true));

            // Defensive escaping
            $planned_delivery = mysqli_real_escape_string($db, strval($pd_raw));
            $lot_no = mysqli_real_escape_string($db, strval($lot_raw));
            log_it("AFTER ESCAPE PD: " . $planned_delivery);
            log_it("AFTER ESCAPE LOT: " . $lot_no);

            log_it("ID=$id, PD=$planned_delivery, LOT=$lot_no");

            $sql = "UPDATE imporderdata_processed SET planned_delivery = '$planned_delivery', lot_no = '$lot_no' WHERE id = $id";
            log_it("SQL: $sql");

            $result = mysqli_query($db, $sql);
            if ($result) {
                $affected = mysqli_affected_rows($db);
                log_it("UPDATE SUCCESS ($affected rows)");
                $updated += ($affected > 0) ? 1 : 0;
            } else {
                log_it("UPDATE FAILED: " . mysqli_error($db));
            }
        }
        $response = array(
            'success' => true,
            'updated' => $updated,
            'message' => "Updated $updated records"
        );
    } else {
        log_it("INVALID DATA FORMAT");
        $response['message'] = "Invalid data format";
    }
    mysqli_close($db);
    log_it("DB CLOSED");
}

$json_response = json_encode($response);
log_it("SENDING RESPONSE: " . $json_response);
echo $json_response;
log_it("SCRIPT COMPLETE");
?>
