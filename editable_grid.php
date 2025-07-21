<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editable Grid</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <h2>View / Update Planned Delivery</h2>
    <div class="container">
        <div class="row mb-3">
            <div class="col">
                <button id="saveChanges" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
        <div class="table-responsive">
            <table id="deliveryGrid" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Pattern</th>
                        <th>Curve</th>
                        <th>Order Qty</th>
                        <th>Requested Delivery</th>
                        <th>Planned Delivery</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <script type="text/javascript">
    $(document).ready(function() {
        loadGridData();

        function loadGridData() {
            $.ajax({
                url: 'load_grid_data.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && !response.error) {
                        populateGrid(response);
                    } else {
                        alert('Error loading data: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error loading data: ' + error);
                    console.log(xhr.responseText); // This will help debug the actual response
                }
            });
        }

        function populateGrid(data) {
            var tbody = $('#deliveryGrid tbody');
            tbody.empty();
            
            if (data && data.length > 0) {
                for (var i = 0; i < data.length; i++) {
                    var row = data[i];
                    var tr = $('<tr>');
                    tr.append('<td>' + (row.order_no || '') + '</td>');
                    tr.append('<td>' + (row.decal_patt || '') + '</td>');
                    tr.append('<td>' + (row.curve_no || '') + '</td>');
                    tr.append('<td>' + (row.order_quantity || '') + '</td>');
                    tr.append('<td>' + (row.delivery_date || '') + '</td>');
                    tr.append('<td contenteditable="true" data-id="' + row.id + '">' + (row.planned_delivery || '') + '</td>');
                    tr.append('<td>' + (row.processed == 1 ? 'Processed' : 'Pending') + '</td>');
                    tbody.append(tr);
                }
            }
        }

        var updates = [];
        
        $('#deliveryGrid').on('blur', 'td[contenteditable="true"]', function() {
            var id = $(this).data('id');
            var planned_delivery = $(this).text().trim();
            
            updates.push({
                id: id,
                planned_delivery: planned_delivery
            });
        });

        $('#saveChanges').click(function() {
            if (updates.length === 0) {
                alert('No changes to save');
                return;
            }

            $.ajax({
                url: 'save_grid_data.php',
                method: 'POST',
                dataType: 'json',
                data: { updates: updates },
                success: function(response) {
                    if (response.success) {
                        alert('Changes saved successfully');
                        updates = [];
                        loadGridData();
                    } else {
                        alert('Error: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error saving changes: ' + error);
                    console.log(xhr.responseText);
                }
            });
        });
    });
    </script>
</body>
</html>
