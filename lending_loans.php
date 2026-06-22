<?php
// lending_loans.php - Main page to list all loans with filters and actions
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Distinct values for dropdowns ──────────────────────────────────────────
// Filter dropdowns show ALL consultants/statuses (including inactive) since
// historical loans may reference a consultant who is now inactive.
// (The Add/Edit loan FORM, by contrast, only offers active ones — see
// lending_loan_detail.php.)
$consultants = [];
$res = mysqli_query($conn, "SELECT id, name, active FROM loan_consultants ORDER BY name");
while ($row = mysqli_fetch_assoc($res)) $consultants[] = $row;

$statuses = [];
$res = mysqli_query($conn, "SELECT id, name FROM loan_statuses ORDER BY sort_order, name");
while ($row = mysqli_fetch_assoc($res)) $statuses[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Info — Laser Lending</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title">Loan Info</h5>
        <div>
            <a href="loan_referral_sources.php" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-users me-1"></i> Agents
            </a>
            <a href="lending_loan_detail.php?action=create" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add New
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card p-3">

            <!-- Filters -->
            <div class="filter-row mb-3">
                <form method="GET" id="filterForm">
                    <div class="row g-2 align-items-end">

                        <!-- Trans # -->
                        <div class="col">
                            <input type="text" name="trans_no" class="form-control" placeholder="Trans #"
                                   value="<?= htmlspecialchars($_GET['trans_no'] ?? '') ?>">
                        </div>

                        <!-- Borrower -->
                        <div class="col">
                            <input type="text" name="borrower" class="form-control" placeholder="Borrower"
                                   value="<?= htmlspecialchars($_GET['borrower'] ?? '') ?>">
                        </div>

                        <!-- Loan Consultant -->
                        <div class="col">
                            <select name="consultant_id" class="form-select form-select-sm">
                                <option value="">All Consultants</option>
                                <?php foreach ($consultants as $c):
                                    $sel = (isset($_GET['consultant_id']) && $_GET['consultant_id'] == $c['id']) ? 'selected' : '';
                                    $label = $c['name'] . ($c['active'] ? '' : ' (inactive)');
                                ?>
                                <option value="<?= $c['id'] ?>" <?= $sel ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col">
                            <select name="status_id" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $s):
                                    $sel = (isset($_GET['status_id']) && $_GET['status_id'] == $s['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $s['id'] ?>" <?= $sel ?>><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Buttons -->
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            <a href="lending_loans.php" class="btn btn-light btn-sm">Filter Off</a>
                        </div>

                    </div>
                </form>
            </div>

            <!-- Loans Table -->
            <div class="table-responsive">
                <table id="example" class="display table table-hover table-striped" style="min-width:845px">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Trans #</th>
                            <th>Borrower Name</th>
                            <th>Loan Consultant</th>
                            <th>Loan Processor</th>
                            <th>Status</th>
                            <th>Date Funded</th>
                            <th>Loan Type</th>
                            <th>Purchase/Refinance</th>
                            <th>Brokered/Warehouse</th>
                            <th>Loan Amount</th>
                            <th>Referral Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // ── Build WHERE from filters ──────────────────────────────
                        $where = [];

                        if (!empty($_GET['trans_no'])) {
                            $tn = mysqli_real_escape_string($conn, $_GET['trans_no']);
                            $where[] = "l.transaction_no LIKE '%$tn%'";
                        }
                        if (!empty($_GET['borrower'])) {
                            $b = mysqli_real_escape_string($conn, $_GET['borrower']);
                            $where[] = "l.borrower_name LIKE '%$b%'";
                        }
                        if (!empty($_GET['consultant_id'])) {
                            $cid = intval($_GET['consultant_id']);
                            $where[] = "l.loan_consultant_id = $cid";
                        }
                        if (!empty($_GET['status_id'])) {
                            $sid = intval($_GET['status_id']);
                            $where[] = "l.status_id = $sid";
                        }

                        $where_sql = count($where) ? "WHERE " . implode(" AND ", $where) : "";

                        // ── Main query ────────────────────────────────────────────
                        // Loan Amount = Loan_1_Amount + Loan_2_Amount, matching the
                        // "Total_Loan" calculation used consistently across every
                        // saved Access query found in the source database.
                        $sql = "
                            SELECT
                                l.id,
                                l.transaction_no,
                                l.borrower_name,
                                lc.name   AS consultant_name,
                                lp.name   AS processor_name,
                                ls.name   AS status_name,
                                l.date_closed,
                                lt.name   AS loan_type_name,
                                pt.name   AS purchase_type_name,
                                rt.name   AS role_type_name,
                                (IFNULL(l.loan_1_amount,0) + IFNULL(l.loan_2_amount,0)) AS total_loan,
                                rs.name   AS referral_name
                            FROM loans l
                            LEFT JOIN loan_consultants      lc ON l.loan_consultant_id = lc.id
                            LEFT JOIN loan_processors        lp ON l.loan_processor_id  = lp.id
                            LEFT JOIN loan_statuses          ls ON l.status_id          = ls.id
                            LEFT JOIN loan_types             lt ON l.loan_type_id       = lt.id
                            LEFT JOIN purchase_types         pt ON l.purchase_type_id   = pt.id
                            LEFT JOIN loan_role_types        rt ON l.loan_role_type_id  = rt.id
                            LEFT JOIN loan_referral_sources  rs ON l.referral_source_id = rs.id
                            $where_sql
                            ORDER BY l.created_at DESC
                        ";

                        $result = mysqli_query($conn, $sql);
                        if (!$result) {
                            echo '<tr><td colspan="12" class="text-danger">Query error: ' . htmlspecialchars(mysqli_error($conn)) . '</td></tr>';
                        } else {
                            while ($row = mysqli_fetch_assoc($result)):
                        ?>
                        <tr>
                            <td class="action-btns">
                                <a href="lending_loan_detail.php?id=<?= $row['id'] ?>"
                                    class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-danger btn-sm btn-delete"
                                    data-id="<?= $row['id'] ?>"
                                    data-borrower="<?= htmlspecialchars($row['borrower_name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <td><?= htmlspecialchars($row['transaction_no'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['borrower_name']) ?></td>
                            <td><?= htmlspecialchars($row['consultant_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['processor_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['status_name'] ?? '') ?></td>
                            <td><?= $row['date_closed'] ? date('m/d/Y', strtotime($row['date_closed'])) : '' ?></td>
                            <td><?= htmlspecialchars($row['loan_type_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['purchase_type_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['role_type_name'] ?? '') ?></td>
                            <td>$<?= number_format($row['total_loan'], 2) ?></td>
                            <td><?= htmlspecialchars($row['referral_name'] ?? '') ?></td>
                        </tr>
                        <?php
                            endwhile;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the loan for <strong id="delete_borrower_label"></strong>?</p>
                <p class="text-muted small">This will also delete any associated borrower contact records.</p>
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
        $('#delete_borrower_label').text($(this).data('borrower'));
        $('#deleteModal').modal('show');
    });

    // Confirm delete — redirect to delete handler
    $('#confirmDeleteBtn').click(function () {
        window.location.href = 'lending_delete_loan.php?id=' + $('#delete_id').val();
    });

});
</script>
</body>
</html>