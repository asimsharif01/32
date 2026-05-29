<?php include('header.php'); ?>

<!--**********************************
            Content body start
***********************************-->
<div class="content-body">
    <div class="page-titles">
        <h5 class="bc-title">Logs</h5>
    </div>
    <div class="container-fluid">
        <!-- row -->
        <div class="row">
            <div class="col-xl-12">
                <div class="row">
                    <div class="card p-3">
                        <div class="table-responsive">
                                                    <?php
include 'db.php'; // Include the database connection file

// Query to fetch logs with a limit of 1000
$query = "SELECT log, created_at FROM logs ORDER BY created_at DESC LIMIT 1000";
$result = $conn->query($query);
?>

                            <table id="example" class="display table" style="min-width: 845px">
                                <thead>
                                    <tr>
                                        <th>Logs</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>

 <?php
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            
           
            echo "<td>" . $row['created_at'] . "</td>";
            echo "<td>" . htmlspecialchars($row['log']) . "</td>";
            echo "</tr>";
        }
        ?>

                                        </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--**********************************
            Content body end
 ***********************************-->

<?php include('footer.php'); ?>