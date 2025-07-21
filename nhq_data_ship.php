<?php
error_reporting(0);
ini_set('display_errors', 0);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Decal Shipped Data</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }

        /* Force hide DataTables pagination elements */
        .dataTables_paginate,
        .dataTables_length,
        .dataTables_info {
            display: none !important;
        }

        /* Make all rows visible */
        .dataTable tbody tr {
            display: table-row !important;
        }
    </style>
</head>
<body>
    <h2>Generate Shipped Data csv file</h2>
    <form id="data-form">
        <div class="container">
            <div class="row mb-3">
                <div class="col">
                    <button id="generateDeliveryAck" class="btn btn-primary">Generate Shipped Data</button>
                </div>
            </div>
            
            <div id="statusMessage"></div>
            
            <div class="table-responsive">
                <table id="deliveryTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Order No</th>
                            <th>Decal Pattern</th>
                            <th>Curve No</th>
                            <th>Order Qty</th>
                            <th>Planned Delivery</th>
                            <th>Lot No</th>
                            <th>Shipped Qty</th>
                            <th>This Time Ship Qty</th>
                            <th>Shipped</th>
                        </tr>
                    </thead>
                    <tbody id="data-body"></tbody>
                </table>
            </div>
        </div>
    </form>

    <script>
        // Track selected rows and their data across all pages
        var selectedRowsData = {};

        // Load data from the server
        $(document).ready(function() {
            loadTableData();
        });

        function loadTableData() {
            $.ajax({
                url: 'load_table_data_ship.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    var tbody = $('#deliveryTable tbody');
                    tbody.empty();
                    
                    data.forEach(function(row) {
                        var remainingQty = row.order_quantity - (row.shippedsofar || 0);
                        tbody.append(
                            '<tr data-id="' + row.id + '" data-order-qty="' + row.order_quantity + '" data-shipped="' + (row.shippedsofar || 0) + '">' +
                            '<td><input type="checkbox" class="row-checkbox" value="' + row.id + '"></td>' +
                            '<td>' + (row.order_no || '') + '</td>' +
                            '<td>' + (row.decal_patt || '') + '</td>' +
                            '<td>' + (row.curve_no || '') + '</td>' +
                            '<td>' + (row.order_quantity || '') + '</td>' +
                            '<td>' + (row.planned_delivery || '') + '</td>' +
                            '<td>' + (row.lot_no || '') + '</td>' +
                            '<td>' + (row.shippedsofar || 0) + '</td>' +
                            '<td><input type="number" class="form-control ship-qty" value="' + remainingQty + '" max="' + remainingQty + '" min="0"></td>' +
                            '<td><input type="checkbox" class="shipped-checkbox" ' + (row.shipped == 1 ? 'checked' : '') + '></td>' +
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

        $('#selectAll').change(function() {
            $('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        // Save Data Function
        function saveData() {
            var selectedData = [];
            for (var id in selectedRowsData) {
                if (selectedRowsData.hasOwnProperty(id)) {
                    var row = selectedRowsData[id];
                    // Only add if ship_qty > 0
                    if (row.ship_qty > 0) {
                        selectedData.push(row);
                    }
                }
            }
            if (selectedData.length === 0) {
                alert('Please select records and enter shipping quantities');
                return null;
            }
            return selectedData;
        }

        // Generate Shipped Data CSV
        $('#generateDeliveryAck').click(function(e) {
            e.preventDefault();
            
            // Get selected data with error checking
            var selectedData = saveData();
            if (!selectedData) {
                return; // Exit if no valid data
            }

            var selectedIds = selectedData.map(function(item) {
                return item.id;
            });

            console.log('Selected Data:', selectedData);
            console.log('Selected IDs:', selectedIds);

            // First save the data
            $.ajax({
                url: 'save_shipped_data.php',
                type: 'POST',
                dataType: 'json',
                data: { data: selectedData },
                success: function(response) {
                    console.log('Save Response:', response);
                    if (response.success) {
                        // Trigger CSV generation
                        var csvForm = $('<form>', {
                            'action': 'generate_shipped_csv.php',
                            'method': 'POST',
                            'target': '_blank'
                        });

                        $('<input>').attr({
                            type: 'hidden',
                            name: 'ids',
                            value: selectedIds.join(',')
                        }).appendTo(csvForm);

                        // Add debugging information
                        $('<input>').attr({
                            type: 'hidden',
                            name: 'debug',
                            value: '1'
                        }).appendTo(csvForm);

                        // Append and submit form
                        csvForm.appendTo('body');
                        
                        // Log form details before submission
                        console.log('CSV Generation Form:', {
                            action: csvForm.attr('action'),
                            method: csvForm.attr('method'),
                            ids: csvForm.find('input[name="ids"]').val()
                        });

                        csvForm.submit();

                        // Remove form after submission
                        setTimeout(function() {
                            csvForm.remove();
                        }, 1000);

                        loadTableData(); // Reload the table
                    } else {
                        alert('Error: ' + response.message);
                        console.error('Save failed:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error saving data: ' + error);
                    console.error('AJAX Error:', status, error);
                    console.error('Response Text:', xhr.responseText);
                }
            });
        });

        // Initial load
        loadTableData();

        // Auto-save on changes
        $(document).on('change', '.planned-delivery, .lot-no, .ship-qty', function() {
            saveData();
        });

        // When a checkbox is changed, update the global object
        $(document).on('change', '.row-checkbox', function() {
            var tr = $(this).closest('tr');
            var id = tr.data('id');
            if ($(this).is(':checked')) {
                // Save all relevant data for this row
                selectedRowsData[id] = {
                    id: id,
                    ship_qty: parseInt(tr.find('.ship-qty').val(), 10) || 0,
                    current_shipped: parseInt(tr.data('shipped') || 0, 10),
                    order_qty: parseInt(tr.data('order-qty') || 0, 10),
                    shipped: tr.find('.shipped-checkbox').is(':checked') ? 1 : 0
                };
            } else {
                // Remove if unchecked
                delete selectedRowsData[id];
            }
        });

        // Also update ship_qty if changed
        $(document).on('input', '.ship-qty', function() {
            var tr = $(this).closest('tr');
            var id = tr.data('id');
            if (selectedRowsData[id]) {
                selectedRowsData[id].ship_qty = parseInt($(this).val(), 10) || 0;
            }
        });

        $(document).ready(function() {
            // Wait a moment for any existing DataTables to initialize
            setTimeout(function() {
                // Destroy existing DataTable if it exists
                if ($.fn.DataTable.isDataTable('#deliveryTable')) {
                    $('#deliveryTable').DataTable().destroy();
                }
                
                // Reinitialize without paging
                $('#deliveryTable').DataTable({
                    "paging": false,
                    "info": false,
                    "ordering": true,
                    "searching": true
                });
            }, 100);
        });
    </script>

    <script>
    $(document).ready(function() {
        var tableId = '#shippedDataTable';
        var attempts = 0;
        var maxAttempts = 10; // Try for 5 seconds (10 * 500ms)
        var intervalId;

        console.log("NHQ_DATA_SHIP.PHP: Starting script to disable pagination for " + tableId);

        function disableTablePagination() {
            attempts++;
            console.log("NHQ_DATA_SHIP.PHP: Attempt " + attempts + " to disable pagination for " + tableId);

            if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableId)) {
                console.log("NHQ_DATA_SHIP.PHP: " + tableId + " is a DataTable.");
                var dtInstance = $(tableId).DataTable();

                // Check current paging state
                if (dtInstance.settings()[0].oFeatures.bPaginate === false) {
                    console.log("NHQ_DATA_SHIP.PHP: Pagination is already disabled for " + tableId + ". Stopping attempts.");
                    clearInterval(intervalId);
                    return;
                }

                console.log("NHQ_DATA_SHIP.PHP: Pagination is currently enabled. Attempting to disable...");

                // More forceful approach: destroy and re-initialize
                // Preserve other settings if possible, but prioritize disabling pagination
                var existingSettings = dtInstance.settings()[0];
                var newSettings = $.extend(true, {}, existingSettings.oInit); // Clone original init options

                newSettings.paging = false;     // Explicitly disable paging
                newSettings.bPaginate = false;  // For older versions / direct feature flag
                newSettings.info = false;       // Hide "Showing x to y of z entries"
                newSettings.pageLength = -1;    // Show all entries by default

                // Destroy the current instance
                dtInstance.destroy();
                console.log("NHQ_DATA_SHIP.PHP: Destroyed existing DataTable instance for " + tableId);

                // Re-initialize with pagination disabled
                $(tableId).DataTable(newSettings);
                console.log("NHQ_DATA_SHIP.PHP: Re-initialized " + tableId + " with pagination disabled.");
                
                // Verify
                var newDtInstance = $(tableId).DataTable();
                if (newDtInstance.settings()[0].oFeatures.bPaginate === false) {
                     console.log("NHQ_DATA_SHIP.PHP: VERIFIED - Pagination successfully disabled for " + tableId + ". Stopping attempts.");
                     clearInterval(intervalId);
                } else {
                     console.log("NHQ_DATA_SHIP.PHP: VERIFICATION FAILED - Pagination still enabled for " + tableId + ".");
                }

            } else {
                console.log("NHQ_DATA_SHIP.PHP: " + tableId + " is not a DataTable yet or not found on this attempt.");
                // If it's not a DataTable yet, we can try to initialize it directly with paging off
                // This might conflict if another script initializes it later, but worth a try
                // $(tableId).DataTable({ "paging": false, "info": false });
            }

            if (attempts >= maxAttempts) {
                console.log("NHQ_DATA_SHIP.PHP: Reached max attempts for " + tableId + ". Stopping.");
                clearInterval(intervalId);
                // As a last resort, try to hide pagination elements with CSS
                var css = tableId + '_paginate { display: none !important; } ' +
                          tableId + '_length { display: none !important; } ' +
                          tableId + '_info { display: none !important; }';
                var style = document.createElement('style');
                if (style.styleSheet) {
                    style.styleSheet.cssText = css;
                } else {
                    style.appendChild(document.createTextNode(css));
                }
                document.getElementsByTagName('head')[0].appendChild(style);
                console.log("NHQ_DATA_SHIP.PHP: Applied CSS fallback to hide pagination elements for " + tableId);
            }
        }

        // Start trying to disable pagination
        // Run immediately and then set an interval
        disableTablePagination();
        intervalId = setInterval(disableTablePagination, 500); // Try every 500ms
    });
    </script>
</body>
</html>
