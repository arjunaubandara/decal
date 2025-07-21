<?php
// filepath: h:\Current\decal\nhq_data.php
// Start session for messages
session_start();

// Clear CSV generation flag on direct page access with no referrer
if (!isset($_SERVER['HTTP_REFERER'])) {
    unset($_SESSION['csv_generated']);
    unset($_SESSION['csv_type']);
}

$message = '';
$messageType = 'success';

// Handle CSV generation message
if (isset($_SESSION['csv_generated']) && $_SESSION['csv_generated']) {
    $csvType = isset($_SESSION['csv_type']) ? $_SESSION['csv_type'] : '';
    if ($csvType == 'delivery') {
        $message = "Delivery ACK CSV file has been generated successfully.";
    } else if ($csvType == 'decal') {
        $message = "Decal Master CSV file has been generated successfully.";
    }
    
    // Clear the session flag
    $_SESSION['csv_generated'] = false;
    unset($_SESSION['csv_type']);
}
?>

<div class="container">
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
        <?php echo $message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <div class="row mb-3">
        <div class="col">
            <!-- Changed button order here: Save Changes, Generate Decal Master, Generate Delivery ACK -->
            <button id="saveChanges" onclick="saveData()" class="btn btn-success">Save Changes</button>
            <button id="generateDecalMaster" class="btn btn-success">Generate Decal Master</button>
            <button id="generateDeliveryAck" class="btn btn-info">Generate Delivery ACK</button>
        </div>
    </div>
    
    <div id="statusMessage"></div>
    
    <div class="table-responsive">
        <table id="deliveryTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <!-- Original columns (without the checkbox first) -->
                    <th>Order No</th>
                    <th>Decal Pattern</th>
                    <th>Curve No</th>
                    <th>Order Qty</th>
                    <th>Planned Delivery</th>
                    <th>Lot No</th>
                    <th>Delivery ACK</th>
                    <!-- Checkbox moved to last column -->
                    <th><input type="checkbox" id="selectAll"></th>
                </tr>
            </thead>
            <tbody id="data-body"></tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    function loadTableData() {
        $.ajax({
            url: 'load_table_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var tbody = $('#deliveryTable tbody');
                tbody.empty();
                
                data.forEach(function(row) {
                    tbody.append(
                        '<tr data-id="' + row.id + '">' +
                        '<td>' + (row.order_no || '') + '</td>' +
                        '<td>' + (row.decal_patt || '') + '</td>' +
                        '<td>' + (row.curve_no || '') + '</td>' +
                        '<td>' + (row.order_quantity || '') + '</td>' +
                        '<td><input type="text" class="form-control planned-delivery" value="' + (row.planned_delivery || '') + '"></td>' +
                        '<td><input type="text" class="form-control lot-no" value="' + (row.lot_no || '') + '"></td>' +
                        '<td><input type="checkbox" class="delivery-ack" ' + (row.delivery_confirm == 1 ? 'checked' : '') + '></td>' +
                        '<td><input type="checkbox" class="row-checkbox" value="' + row.id + '"></td>' +
                        '</tr>'
                    );
                });
            },
            error: function(xhr, status, error) {
                $('#statusMessage').html(
                    '<div class="alert alert-danger">Error loading data: ' + error + '</div>'
                );
            }
        });
    }

    // Generate Delivery ACK CSV
    $('#generateDeliveryAck').click(function(e) {
        e.preventDefault();
        var selectedIds = [];
        
        $('.row-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one record');
            return;
        }

        // Save any changes first
        saveData();
        
        // Then generate CSV in a new window/tab
        var csvUrl = 'generate_csv.php?type=delivery&ids=' + selectedIds.join(',');
        var csvWindow = window.open(csvUrl, '_blank');
        
        // Reload current page after 1.5 seconds
        setTimeout(function() {
            window.location.reload();
        }, 1500);
    });

    // Generate Decal Master CSV
    $('#generateDecalMaster').click(function(e) {
        e.preventDefault();
        var selectedIds = [];
        
        $('.row-checkbox:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Please select at least one record');
            return;
        }

        // Save any changes first
        saveData();
        
        // Then generate CSV in a new window/tab
        var csvUrl = 'generate_csv.php?type=decal&ids=' + selectedIds.join(',');
        var csvWindow = window.open(csvUrl, '_blank');
        
        // Reload current page after 1.5 seconds
        // setTimeout(function() {
        //     window.location.reload();
        // }, 1500);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert-dismissible').alert('close');
    }, 5000);
    
    // Initialize
    loadTableData();
});

// Add this function inside your <script> tag but outside any other function

function saveData() {
    console.log("saveData function called");
    
    // Collect data from checked rows
    var updatedData = [];
    $('.row-checkbox:checked').each(function() {
        var $row = $(this).closest('tr');
        var id = $row.data('id');
        
        // Get values from inputs
        var plannedDelivery = $row.find('input.planned-delivery').val();
        var lotNo = $row.find('input.lot-no').val();
        var deliveryConfirm = $row.find('.delivery-ack').is(':checked') ? 1 : 0;
        
        console.log('Adding row ' + id + ': PD=' + plannedDelivery + ', Lot=' + lotNo);
        
        updatedData.push({
            id: id,
            planned_delivery: plannedDelivery,
            lot_no: lotNo,
            delivery_confirm: deliveryConfirm
        });
    });
    
    if (updatedData.length === 0) {
        alert('Please select at least one row');
        return;
    }
    
    console.log('Data to save:', updatedData);
    
    // Show saving message
    $('#statusMessage').html('<div class="alert alert-info">Saving data...</div>');
    
    // Send data to server
    $.ajax({
        url: 'save_data.php',
        type: 'POST',
        dataType: 'json',
        data: { data: updatedData },
        success: function(response) {
            console.log('Save response:', response);
            
            if (response && response.success) {
                $('#statusMessage').html(
                    '<div class="alert alert-success">Data saved successfully.</div>'
                );
            } else {
                $('#statusMessage').html(
                    '<div class="alert alert-danger">Error: ' + 
                    (response && response.message ? response.message : 'Unknown error') + '</div>'
                );
            }
        },
        error: function(xhr, status, error) {
            console.error('Save error:', status, error);
            console.error('Response:', xhr.responseText);
            
            $('#statusMessage').html(
                '<div class="alert alert-danger">Error saving data: ' + status + '</div>'
            );
        }
    });
}
</script>