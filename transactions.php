<?php
require_once 'db.php';
session_start();
?>

<?php include('header.php'); // your existing header ?>

<div class="content-body">
    <div class="page-titles">
        <h5 class="bc-title">Transactions</h5>
        <div>
            <button type="button" class="btn btn-primary btn-sm ms-2" data-bs-toggle="modal"
                data-bs-target="#addListingModal">+ Add New Listing</button>
            <a href="transaction_detail.php?action=create" class="btn btn-success btn-sm ms-2">+ Create Key Player</a>
        </div>
    </div>
    <div class="container-fluid">
        

        <!-- Listings Table -->
        <div class="card p-3">
            <!-- Filters -->
        <div class="filter-row">
            <form method="GET" id="filterForm">
                <div class="row g-2 align-items-end mb-2">

                    <div class="col">
                        <input type="text" name="mls" class="form-control " placeholder="MLS #">
                    </div>

                    <div class="col">
                        <input type="text" name="tn" class="form-control " placeholder="TN">
                    </div>

                    <div class="col">
                        <input type="text" name="agent" class="form-control " placeholder="Agent">
                    </div>

                    <div class="col">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Status</option>
                            <?php
                    $status_res = mysqli_query($conn, "SELECT id, description FROM sales_statuses");
                    while($st = mysqli_fetch_assoc($status_res)) {
                        echo "<option value='{$st['id']}'>{$st['description']}</option>";
                    }
                    ?>
                        </select>
                    </div>

                    <div class="col">
                        <input type="text" name="seller" class="form-control " placeholder="Seller">
                    </div>

                    <div class="col">
                        <input type="text" name="buyer" class="form-control " placeholder="Buyer">
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                        <a href="transactions.php" class="btn btn-light btn-sm">Reset</a>
                    </div>

                </div>
            </form>
        </div>
            <div class="table-responsive">
                <table id="example" class="display table" style="min-width: 845px">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>MLS #</th>
                            <th>TN</th>
                            <th>Purchase Price</th>
                            <th>Seller Name</th>
                            <th>Buyer Name</th>
                            <th>Listing Agent</th>
                            <th>Selling Agent</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Build filter conditions
                        $where = [];
                        $params = [];
                        if (!empty($_GET['mls'])) {
                            $mls = mysqli_real_escape_string($conn, $_GET['mls']);
                            $where[] = "l.mls_number LIKE '%$mls%'";
                        }
                        if (!empty($_GET['tn'])) {
                            $tn = mysqli_real_escape_string($conn, $_GET['tn']);
                            $where[] = "l.transaction_number LIKE '%$tn%'";
                        }
                        if (!empty($_GET['agent'])) {
                            $agent = mysqli_real_escape_string($conn, $_GET['agent']);
                            $where[] = "(la.name LIKE '%$agent%' OR sa.name LIKE '%$agent%')";
                        }
                        if (!empty($_GET['status'])) {
                            $status_id = intval($_GET['status']);
                            $where[] = "l.status_id = $status_id";
                        }
                        if (!empty($_GET['seller'])) {
                            $seller = mysqli_real_escape_string($conn, $_GET['seller']);
                            $where[] = "EXISTS (SELECT 1 FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = l.id AND tr.role_type = 'Seller' AND c.name LIKE '%$seller%')";
                        }
                        if (!empty($_GET['buyer'])) {
                            $buyer = mysqli_real_escape_string($conn, $_GET['buyer']);
                            $where[] = "EXISTS (SELECT 1 FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = l.id AND tr.role_type = 'Buyer' AND c.name LIKE '%$buyer%')";
                        }
                        if (!empty($_GET['address'])) {
                            $addr = mysqli_real_escape_string($conn, $_GET['address']);
                            $where[] = "(l.address1 LIKE '%$addr%' OR l.address2 LIKE '%$addr%' OR l.city LIKE '%$addr%')";
                        }
                        $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

                        $sql = "
                            SELECT 
                                l.id,
                                l.mls_number,
                                l.transaction_number,
                                l.purchase_price,
                                (SELECT c.name FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = l.id AND tr.role_type = 'Seller' LIMIT 1) AS seller_name,
                                (SELECT c.name FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = l.id AND tr.role_type = 'Buyer' LIMIT 1) AS buyer_name,
                                la.name AS listing_agent,
                                sa.name AS selling_agent,
                                s.description AS status
                            FROM listings l
                            LEFT JOIN agents la ON la.id = (SELECT contact_id FROM transaction_roles WHERE listing_id = l.id AND role_type = 'Listing Agent' LIMIT 1)
                            LEFT JOIN agents sa ON sa.id = (SELECT contact_id FROM transaction_roles WHERE listing_id = l.id AND role_type = 'Selling Agent' LIMIT 1)
                            LEFT JOIN sales_statuses s ON l.status_id = s.id
                            $where_sql
                            ORDER BY l.created_at DESC
                        ";
                        $result = mysqli_query($conn, $sql);
                        while($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td class="action-btns">
                                <a href="transaction_detail.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm"><i
                                        class="fas fa-edit"></i></a>
                                <button class="btn btn-danger btn-sm btn-delete" data-id="<?= $row['id'] ?>"
                                    data-mls="<?= htmlspecialchars($row['mls_number']) ?>"><i
                                        class="fas fa-trash"></i></button>
                            </td>
                            <td><?= htmlspecialchars($row['mls_number']) ?></td>
                            <td><?= htmlspecialchars($row['transaction_number']) ?></td>
                            <td>$<?= number_format($row['purchase_price'], 2) ?></td>
                            <td><?= htmlspecialchars($row['seller_name']) ?></td>
                            <td><?= htmlspecialchars($row['buyer_name']) ?></td>
                            <td><?= htmlspecialchars($row['listing_agent']) ?></td>
                            <td><?= htmlspecialchars($row['selling_agent']) ?></td>
                            <td><?= htmlspecialchars($row['status']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add New Listing Modal -->
<div class="modal fade" id="addListingModal" tabindex="-1" aria-labelledby="addListingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addListingModalLabel">New Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="transactions/add.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Listing Agent</label>
                            <select name="listing_agent_id" class="form-select" required>
                                <option value="">Select Agent</option>
                                <?php
                                $agent_sql = "SELECT id, name FROM agents WHERE is_listing_agent = 1 ORDER BY name";
                                $agent_res = mysqli_query($conn, $agent_sql);
                                while($agent = mysqli_fetch_assoc($agent_res)) {
                                    echo '<option value="'.$agent['id'].'">'.htmlspecialchars($agent['name']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">MLS Number</label>
                            <input type="text" name="mls_number" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">D.O.L (Date of Listing)</label>
                            <input type="date" name="date_of_listing" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">D.O.E (Date of Expiration)</label>
                            <input type="date" name="date_of_expiration" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Seller Name</label>
                            <input type="text" name="seller_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <select name="city" class="form-select">
                                <option value="">Select City</option>
                                <?php
                                $city_sql = "SELECT DISTINCT city FROM listings WHERE city IS NOT NULL AND city != '' UNION SELECT 'Salt Lake City' UNION SELECT 'Draper' UNION SELECT 'Sandy' ORDER BY city";
                                $city_res = mysqli_query($conn, $city_sql);
                                while($city_row = mysqli_fetch_assoc($city_res)) {
                                    echo '<option value="'.htmlspecialchars($city_row['city']).'">'.htmlspecialchars($city_row['city']).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Listed">Listed</option>
                                <option value="Under Contract">Under Contract</option>
                                <option value="Closed">Closed</option>
                                <option value="Rescinded">Rescinded</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this listing?</p>
                <input type="hidden" id="delete_id">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>


<script>
$(document).ready(function() {
    $('#listingsTable').DataTable({
        pageLength: 25,
        order: [
            [1, 'desc']
        ]
    });

    // Delete button click
    $('.btn-delete').click(function() {
        let id = $(this).data('id');
        $('#delete_id').val(id);
        $('#deleteModal').modal('show');
    });

    $('#confirmDeleteBtn').click(function() {
        let id = $('#delete_id').val();
        window.location.href = 'transactions/delete.php?id=' + id;
    });
});
</script>
</body>

</html>