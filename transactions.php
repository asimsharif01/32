<?php
// transactions.php - Main page to list all transactions with filters and actions
require_once 'db.php';
session_start();
// Fetch distinct values for autocomplete (datalists)
$mls_values = [];
$tn_values = [];
$agent_names = [];
$seller_names = [];
$buyer_names = [];

$res = mysqli_query($conn, "SELECT DISTINCT mls_number FROM listings WHERE mls_number IS NOT NULL AND mls_number != '' ORDER BY mls_number");
while($row = mysqli_fetch_assoc($res)) $mls_values[] = $row['mls_number'];

$res = mysqli_query($conn, "SELECT DISTINCT transaction_number FROM listings WHERE transaction_number IS NOT NULL AND transaction_number != '' ORDER BY transaction_number");
while($row = mysqli_fetch_assoc($res)) $tn_values[] = $row['transaction_number'];

$res = mysqli_query($conn, "SELECT DISTINCT name FROM agents WHERE (is_listing_agent = 1 OR is_selling_agent = 1) AND active = 1 ORDER BY name");
while($row = mysqli_fetch_assoc($res)) $agent_names[] = $row['name'];

$res = mysqli_query($conn, "SELECT DISTINCT seller_name FROM listings WHERE seller_name IS NOT NULL AND seller_name != '' ORDER BY seller_name");
while($row = mysqli_fetch_assoc($res)) $seller_names[] = $row['seller_name'];

$res = mysqli_query($conn, "SELECT DISTINCT buyer_name FROM listings WHERE buyer_name IS NOT NULL AND buyer_name != '' ORDER BY buyer_name");
while($row = mysqli_fetch_assoc($res)) $buyer_names[] = $row['buyer_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions — Larson &amp; Company</title>
     <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles">
        <h5 class="bc-title">Transactions</h5>
        <div>
            <button type="button" class="btn btn-primary btn-sm ms-2"
                data-bs-toggle="modal" data-bs-target="#addListingModal">
                + Add New Listing
            </button>
            <a href="transaction_detail.php?action=create" class="btn btn-success btn-sm ms-2">
                + Create Key Player
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card p-3">

            <!-- Filters -->
         <div class="filter-row mb-3">
    <form method="GET" id="filterForm">
        <div class="row g-2 align-items-end">
            <!-- MLS # -->
            <div class="col">
                <input type="text" name="mls" class="form-control" list="mlsList"
                       placeholder="MLS #" value="<?= htmlspecialchars($_GET['mls'] ?? '') ?>">
                <datalist id="mlsList">
                    <?php foreach ($mls_values as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Transaction Number -->
            <div class="col">
                <input type="text" name="tn" class="form-control" list="tnList"
                       placeholder="TN" value="<?= htmlspecialchars($_GET['tn'] ?? '') ?>">
                <datalist id="tnList">
                    <?php foreach ($tn_values as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Agent Filter -->
            <div class="col">
                <input type="text" name="agent" class="form-control" list="agentList"
                       placeholder="Agent" value="<?= htmlspecialchars($_GET['agent'] ?? '') ?>">
                <datalist id="agentList">
                    <?php foreach ($agent_names as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Status (unchanged – dropdown) -->
            <div class="col">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <?php
                    $status_res = mysqli_query($conn, "SELECT id, description FROM sales_statuses ORDER BY description");
                    while ($st = mysqli_fetch_assoc($status_res)) {
                        $sel = (isset($_GET['status']) && $_GET['status'] == $st['id']) ? 'selected' : '';
                        echo "<option value='{$st['id']}' $sel>{$st['description']}</option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Seller -->
            <div class="col">
                <input type="text" name="seller" class="form-control" list="sellerList"
                       placeholder="Seller" value="<?= htmlspecialchars($_GET['seller'] ?? '') ?>">
                <datalist id="sellerList">
                    <?php foreach ($seller_names as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Buyer -->
            <div class="col">
                <input type="text" name="buyer" class="form-control" list="buyerList"
                       placeholder="Buyer" value="<?= htmlspecialchars($_GET['buyer'] ?? '') ?>">
                <datalist id="buyerList">
                    <?php foreach ($buyer_names as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Buttons -->
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="transactions.php" class="btn btn-light btn-sm">Reset</a>
            </div>
        </div>
    </form>
</div>

            <!-- Listings Table -->
            <div class="table-responsive">
                <table id="example" class="display table table-hover table-striped" style="min-width:845px">
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
                        // ── Build WHERE from filters ──────────────────────────────
                        // All buyer/seller/agent data is now inline in listings table
                        // No joins to dropped tables (transaction_roles, contacts)
                        $where = [];

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
                            // Search inline LA_Name and SA_Name columns
                            $where[] = "(l.LA_Name LIKE '%$agent%' OR l.SA_Name LIKE '%$agent%')";
                        }
                        if (!empty($_GET['status'])) {
                            $status_id = intval($_GET['status']);
                            $where[] = "l.status_id = $status_id";
                        }
                        if (!empty($_GET['seller'])) {
                            $seller = mysqli_real_escape_string($conn, $_GET['seller']);
                            // seller_name is now an inline column in listings
                            $where[] = "l.seller_name LIKE '%$seller%'";
                        }
                        if (!empty($_GET['buyer'])) {
                            $buyer = mysqli_real_escape_string($conn, $_GET['buyer']);
                            // buyer_name is now an inline column in listings
                            $where[] = "l.buyer_name LIKE '%$buyer%'";
                        }

                        $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

                        // ── Main query ────────────────────────────────────────────
                        // No joins needed — all data is inline in listings
                        $sql = "
                            SELECT
                                l.id,
                                l.mls_number,
                                l.transaction_number,
                                l.purchase_price,
                                l.seller_name,
                                l.buyer_name,
                                l.LA_Name   AS listing_agent,
                                l.SA_Name   AS selling_agent,
                                s.description AS status
                            FROM listings l
                            LEFT JOIN sales_statuses s ON l.status_id = s.id
                            $where_sql
                            ORDER BY l.created_at DESC
                        ";

                        $result = mysqli_query($conn, $sql);
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td class="action-btns">
                                <a href="transaction_detail.php?id=<?= $row['id'] ?>"
                                    class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-danger btn-sm btn-delete"
                                    data-id="<?= $row['id'] ?>"
                                    data-mls="<?= htmlspecialchars($row['mls_number']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($row['mls_number']) ?></td>
                            <td><?= htmlspecialchars($row['transaction_number']) ?></td>
                            <td>$<?= number_format($row['purchase_price'], 2) ?></td>
                            <td><?= htmlspecialchars($row['seller_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['buyer_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['listing_agent'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['selling_agent'] ?? '') ?></td>
                            <td class="status"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- ══ Add New Listing Modal ═══════════════════════════════════════════════ -->
<!-- This is the quick-add "listing only" modal (minimal fields).           -->
<!-- Full transaction detail (key players, commission, etc.) is done        -->
<!-- via transaction_detail.php after the listing is created.               -->
<div class="modal fade" id="addListingModal" tabindex="-1"
    aria-labelledby="addListingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addListingModalLabel">New Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="transactions/add.php" method="POST">
                <div class="modal-body">
                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Listing Agent</label>
                            <select name="listing_agent_name" class="form-select" required>
                                <option value="">Select Agent</option>
                                <?php
                                // Pull agents flagged as listing agents
                                $agent_sql = "SELECT id, name FROM agents
                                              WHERE is_listing_agent = 1 AND active = 1
                                              ORDER BY name";
                                $agent_res = mysqli_query($conn, $agent_sql);
                                while ($agent = mysqli_fetch_assoc($agent_res)) {
                                   echo '<option value="' . $agent['id'] . '">'
                                        . htmlspecialchars($agent['name']) . '</option>';
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
                            <label class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" name="price" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Seller Name</label>
                            <input type="text" name="seller_name" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="UT" maxlength="2">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php
                                $st_res = mysqli_query($conn, "SELECT id, description FROM sales_statuses ORDER BY description");
                                while ($st = mysqli_fetch_assoc($st_res)) {
                                    $sel = ($st['description'] === 'Listed') ? 'selected' : '';
                                    echo "<option value='{$st['description']}' $sel>{$st['description']}</option>";
                                }
                                ?>
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


<!-- ══ Delete Confirmation Modal ══════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete listing <strong id="delete_mls_label"></strong>?</p>
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
$(document).ready(function () {

 

    // Delete button — show modal
    $('.btn-delete').click(function () {
        $('#delete_id').val($(this).data('id'));
        $('#delete_mls_label').text($(this).data('mls'));
        $('#deleteModal').modal('show');
    });

    // Confirm delete — redirect to delete handler
    $('#confirmDeleteBtn').click(function () {
        window.location.href = 'transactions/delete.php?id=' + $('#delete_id').val();
    });

});
</script>
<script>
$(document).ready(function() {
    // Debounce function to avoid too many requests
    let debounceTimer;
    let activeInput = null;

    // Function to fetch suggestions
    function fetchSuggestions(input, field, term) {
        if (term.length < 1) {
            hideSuggestions(input);
            return;
        }
        $.ajax({
            url: 'ajax_search.php',
            type: 'GET',
            data: { field: field, term: term },
            dataType: 'json',
            success: function(data) {
                showSuggestions(input, data);
            },
            error: function() {
                hideSuggestions(input);
            }
        });
    }

    function showSuggestions(input, suggestions) {
        let container = input.siblings('.autocomplete-suggestions');
        container.empty();
        if (suggestions.length === 0) {
            container.hide();
            return;
        }
        $.each(suggestions, function(i, val) {
            let item = $('<div class="autocomplete-suggestion"></div>').text(val);
            item.on('click', function() {
                input.val(val);
                hideSuggestions(input);
                input.trigger('change');
            });
            container.append(item);
        });
        // Position container below input
        let pos = input.offset();
        container.css({
            top: pos.top + input.outerHeight(),
            left: pos.left,
            minWidth: input.outerWidth()
        }).show();
        activeInput = input;
    }

    function hideSuggestions(input) {
        let container = input.siblings('.autocomplete-suggestions');
        container.hide();
        if (activeInput === input) activeInput = null;
    }

    // Attach events to all autocomplete inputs
    $('.autocomplete-input').on('input', function() {
        let $this = $(this);
        let term = $this.val();
        let field = $this.data('autocomplete');
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            fetchSuggestions($this, field, term);
        }, 300);
    });

    // Hide suggestions when clicking outside
    $(document).on('click', function(e) {
        if (activeInput && !activeInput.is(e.target) && !activeInput.siblings('.autocomplete-suggestions').is(e.target) && !$(e.target).closest('.autocomplete-suggestions').length) {
            hideSuggestions(activeInput);
        }
    });

    // Ensure suggestions close when the form is submitted
    $('#filterForm').on('submit', function() {
        if (activeInput) hideSuggestions(activeInput);
    });
});
</script>