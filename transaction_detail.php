<?php
require_once 'db.php';
session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit = ($id > 0);

$listing = null;
$seller = null;
$buyer = null;
$milestones = [];
$trustTransactions = [];
$roles = [];

if ($is_edit) {
    $sql = "SELECT * FROM listings WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    $listing = mysqli_fetch_assoc($result);

    $seller_sql = "SELECT c.* FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = $id AND tr.role_type = 'Seller' LIMIT 1";
    $seller_res = mysqli_query($conn, $seller_sql);
    $seller = mysqli_fetch_assoc($seller_res);

    $buyer_sql = "SELECT c.* FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = $id AND tr.role_type = 'Buyer' LIMIT 1";
    $buyer_res = mysqli_query($conn, $buyer_sql);
    $buyer = mysqli_fetch_assoc($buyer_res);

    $milestone_sql = "SELECT * FROM listing_milestones WHERE listing_id = $id";
    $milestone_res = mysqli_query($conn, $milestone_sql);
    while ($row = mysqli_fetch_assoc($milestone_res)) {
        $milestones[$row['milestone_type']] = $row;
    }

    $trust_sql = "SELECT * FROM trust_account_transactions WHERE transaction_number = '".mysqli_real_escape_string($conn, $listing['transaction_number'])."' ORDER BY trans_date";
    $trust_res = mysqli_query($conn, $trust_sql);
    while ($row = mysqli_fetch_assoc($trust_res)) {
        $trustTransactions[] = $row;
    }

    $role_sql = "SELECT tr.role_type, tr.agent_id, c.* FROM transaction_roles tr JOIN contacts c ON tr.contact_id = c.id WHERE tr.listing_id = $id";
    $role_res = mysqli_query($conn, $role_sql);
    while ($role = mysqli_fetch_assoc($role_res)) {
        $roles[$role['role_type']] = $role;
    }
}

function val($data, $field, $default = '') {
    return isset($data[$field]) ? htmlspecialchars($data[$field]) : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit Transaction' : 'Create Key Player' ?> - Larson & Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .section-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .section-title { font-size: 1.1rem; font-weight: bold; margin-bottom: 15px; color: #1e3a5f; border-left: 4px solid #1e3a5f; padding-left: 10px; }
        .mb-custom { margin-bottom: 0.75rem; }
        .agent-card { background: #f8f9fc; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        .agent-card h6 { color: #1e3a5f; margin-bottom: 12px; font-weight: 600; }
        .financial-row { background: #fef9e6; padding: 12px; border-radius: 6px; margin: 10px 0; }
        .trust-panel { background: #fff; border: 1px solid #cbd5e0; border-radius: 6px; padding: 8px; font-size: 0.8rem; }
        .trust-panel table { margin: 0; font-size: 0.75rem; }
        .form-label { font-size: 0.75rem; font-weight: 600; margin-bottom: 0.2rem; color: #4a5568; }
        .form-control, .form-select { font-size: 0.85rem; padding: 0.25rem 0.5rem; }
        .btn-sm { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title"><?= $is_edit ? 'Edit Transaction' : 'New Transaction' ?></h5>
        <div>
            <button type="submit" form="transactionForm" class="btn btn-primary btn-sm">Save</button>
            <?php if ($is_edit): ?>
            <button type="button" class="btn btn-info btn-sm ms-2" id="keyPlayersReportBtn">Key Players Report</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="container-fluid">
        <form id="transactionForm" action="transactions/save_transaction.php" method="POST">
            <input type="hidden" name="listing_id" value="<?= $id ?>">

            <!-- Property Information Section (3 columns) -->
            <div class="section-card">
                <div class="section-title">Property Information</div>
                <div class="row">
                    <!-- LEFT COLUMN -->
                    <div class="col-md-3">
                        <div class="mb-custom">
                            <label class="form-label">MLS Number</label>
                            <input type="text" name="mls_number" class="form-control" value="<?= val($listing, 'mls_number') ?>">
                        </div>
                        <div class="mb-custom">
                            <label class="form-label">Trans. Number</label>
                            <input type="text" name="transaction_number" class="form-control" value="<?= val($listing, 'transaction_number') ?>">
                        </div>
                        <div class="mb-custom">
                            <label class="form-label">Address</label>
                            <input type="text" name="address1" class="form-control" value="<?= val($listing, 'address1') ?>">
                        </div>
                        <div class="row">
                            <div class="col-7"><input type="text" name="city" class="form-control" placeholder="City" value="<?= val($listing, 'city') ?>"></div>
                            <div class="col-3"><input type="text" name="state" class="form-control" placeholder="State" value="<?= val($listing, 'state', 'UT') ?>"></div>
                            <div class="col-2"><input type="text" name="zip" class="form-control" placeholder="Zip" value="<?= val($listing, 'zip') ?>"></div>
                        </div>
                        <div class="mb-custom">
                            <label class="form-label">Earnest Money</label>
                            <input type="number" step="0.01" name="earnest_money_amount" class="form-control" value="<?= val($listing, 'earnest_money_amount') ?>">
                        </div>
                        <div class="mb-custom">
                            <label class="form-label">On Deposit With</label>
                            <input type="text" name="earnest_money_deposit_with" class="form-control" value="<?= val($listing, 'earnest_money_deposit_with') ?>">
                        </div>
                    </div>

                    <!-- CENTER COLUMN -->
                    <div class="col-md-5">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Property Type</label>
                                <select name="property_type_id" class="form-select">
                                    <option value="">Select</option>
                                    <?php
                                    $pt_res = mysqli_query($conn, "SELECT id, description FROM property_types");
                                    while($pt = mysqli_fetch_assoc($pt_res)) {
                                        $sel = ($listing['property_type_id'] == $pt['id']) ? 'selected' : '';
                                        echo "<option value='{$pt['id']}' $sel>{$pt['description']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= val($listing, 'purchase_price') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">UC Price</label>
                                <input type="number" step="0.01" name="uc_price" class="form-control" value="<?= val($listing, 'uc_price') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Financing Type</label>
                                <select name="financing_type_id" class="form-select">
                                    <option value="">Select</option>
                                    <?php
                                    $ft_res = mysqli_query($conn, "SELECT id, description FROM financing_types");
                                    while($ft = mysqli_fetch_assoc($ft_res)) {
                                        $sel = ($listing['financing_type_id'] == $ft['id']) ? 'selected' : '';
                                        echo "<option value='{$ft['id']}' $sel>{$ft['description']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Sales Status</label>
                                <select name="status_id" class="form-select">
                                    <option value="">Select</option>
                                    <?php
                                    $st_res = mysqli_query($conn, "SELECT id, description FROM sales_statuses");
                                    while($st = mysqli_fetch_assoc($st_res)) {
                                        $sel = ($listing['status_id'] == $st['id']) ? 'selected' : '';
                                        echo "<option value='{$st['id']}' $sel>{$st['description']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Lead Source</label>
                                <input type="text" name="lead_source" class="form-control" value="<?= val($listing, 'lead_source') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">D.O.L (Date of Listing)</label>
                                <input type="date" name="date_of_listing" class="form-control" value="<?= val($listing, 'date_of_listing') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">D.O.E (Date of Expiration)</label>
                                <input type="date" name="date_of_expiration" class="form-control" value="<?= val($listing, 'date_of_expiration') ?>">
                            </div>
                            <div class="col-12">
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="private" class="form-check-input" value="1" <?= ($listing['private'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Private</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN (Trust Account Panel) -->
                    <div class="col-md-4">
                        <div class="trust-panel">
                            <div class="fw-bold mb-2">Trust Account</div>
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>TN</th><th>Date</th><th>Debit</th><th>Credit</th></tr></thead>
                                <tbody>
                                    <?php if (!empty($trustTransactions)): ?>
                                        <?php foreach($trustTransactions as $tt): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tt['transaction_number']) ?></td>
                                            <td><?= htmlspecialchars($tt['trans_date']) ?></td>
                                            <td>$<?= number_format($tt['debit'],2) ?></td>
                                            <td>$<?= number_format($tt['credit'],2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center">No trust transactions</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <?php 
                                $totalDebit = array_sum(array_column($trustTransactions, 'debit'));
                                $totalCredit = array_sum(array_column($trustTransactions, 'credit'));
                                ?>
                                <tfoot><tr><th colspan="2">Totals</th><th>$<?= number_format($totalDebit,2) ?></th><th>$<?= number_format($totalCredit,2) ?></th></tr></tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial / Commission Row -->
            <div class="section-card">
                <div class="section-title">Commission & Financials</div>
                <div class="financial-row">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2"><input type="number" step="0.01" name="final_price" id="final_price" class="form-control" placeholder="Final Price" value="<?= val($listing, 'final_price') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="commission_price" id="commission_price" class="form-control" placeholder="Comm. Price" value="<?= val($listing, 'commission_price') ?>"></div>
                        <div class="col-md-1"><input type="number" step="0.01" name="commission_pct" id="commission_pct" class="form-control" placeholder="Comm %" value="<?= val($listing, 'commission_pct') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="commission_other" id="commission_other" class="form-control" placeholder="Other" value="<?= val($listing, 'commission_other') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="transaction_fee" id="transaction_fee" class="form-control" placeholder="Trans. Fee" value="<?= val($listing, 'transaction_fee') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="errors_omissions" id="errors_omissions" class="form-control" placeholder="Err. & Omiss." value="<?= val($listing, 'errors_omissions') ?>"></div>
                    </div>
                    <div class="row g-2 mt-2 align-items-center">
                        <div class="col-md-2"><label class="form-label">Net Amt</label><input type="text" id="net_amt" class="form-control bg-light" readonly></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="agent_split" id="agent_split" class="form-control" placeholder="Split %" value="<?= val($listing, 'agent_split') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="processing_fee" id="processing_fee" class="form-control" placeholder="Proc. Fee" value="<?= val($listing, 'processing_fee') ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="other2" id="other2" class="form-control" placeholder="Other" value="<?= val($listing, 'other2') ?>"></div>
                        <div class="col-md-2"><label class="form-label">Check Amt</label><input type="text" id="check_amt" class="form-control bg-light" readonly></div>
                        <div class="col-md-2">
                            <select name="split_with" class="form-select">
                                <option value="">Split With</option>
                                <?php
                                $split_res = mysqli_query($conn, "SELECT name FROM agents WHERE is_listing_agent=1 OR is_selling_agent=1 ORDER BY name");
                                while($sp = mysqli_fetch_assoc($split_res)) {
                                    $sel = ($listing['split_with'] == $sp['name']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($sp['name'])."' $sel>".htmlspecialchars($sp['name'])."</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Buyer & Seller Info (2 columns) -->
            <div class="section-card">
                <div class="section-title">Buyer & Seller Information</div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Buyer Info</h6>
                        <div class="mb-2"><input type="text" name="buyer_name" class="form-control" placeholder="Name" value="<?= val($buyer, 'name') ?>"></div>
                        <div class="mb-2"><input type="text" name="buyer_home_phone" class="form-control" placeholder="Home Phone" value="<?= val($buyer, 'home_phone') ?>"></div>
                        <div class="mb-2"><input type="text" name="buyer_cell_phone" class="form-control" placeholder="Cell Phone" value="<?= val($buyer, 'cell_phone') ?>"></div>
                        <div class="mb-2"><input type="text" name="buyer_fax" class="form-control" placeholder="Fax" value="<?= val($buyer, 'fax') ?>"></div>
                        <div class="mb-2"><input type="email" name="buyer_email" class="form-control" placeholder="Email" value="<?= val($buyer, 'email') ?>"></div>
                    </div>
                    <div class="col-md-6">
                        <h6>Seller Info</h6>
                        <div class="mb-2"><input type="text" name="seller_name" class="form-control" placeholder="Name" value="<?= val($seller, 'name') ?>"></div>
                        <div class="mb-2"><input type="text" name="seller_home_phone" class="form-control" placeholder="Home Phone" value="<?= val($seller, 'home_phone') ?>"></div>
                        <div class="mb-2"><input type="text" name="seller_cell_phone" class="form-control" placeholder="Cell Phone" value="<?= val($seller, 'cell_phone') ?>"></div>
                        <div class="mb-2"><input type="text" name="seller_fax" class="form-control" placeholder="Fax" value="<?= val($seller, 'fax') ?>"></div>
                        <div class="mb-2"><input type="email" name="seller_email" class="form-control" placeholder="Email" value="<?= val($seller, 'email') ?>"></div>
                    </div>
                </div>
            </div>

            <!-- Deadlines -->
            <div class="section-card">
                <div class="section-title">Deadlines</div>
                <div class="row">
                    <?php
                    $milestone_types = ['Date of Contract', 'Seller Disclosure', 'Due Diligence', 'Financing & Appraisal', 'Settlement'];
                    foreach ($milestone_types as $mt):
                        $date = isset($milestones[$mt]['due_date']) ? $milestones[$mt]['due_date'] : '';
                        $completed = isset($milestones[$mt]['completed']) ? $milestones[$mt]['completed'] : 0;
                    ?>
                    <div class="col-md-2 mb-3">
                        <label class="form-label"><?= $mt ?></label>
                        <input type="date" name="milestone[<?= $mt ?>][due_date]" class="form-control" value="<?= $date ?>">
                        <div class="form-check mt-1">
                            <input type="checkbox" name="milestone[<?= $mt ?>][completed]" class="form-check-input" value="1" <?= $completed ? 'checked' : '' ?>>
                            <label class="form-check-label">Completed</label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Key Players (5 roles) -->
           <!-- Key Players (5 roles) - Denormalized directly into listings columns -->
<div class="section-card">
    <div class="section-title">Key Players</div>
    <div class="row">
        <?php
        // Define each role with its column prefix and agent role flag
        $role_config = [
            'Loan Officer' => ['prefix' => 'LO', 'flag' => 'is_loan_officer'],
            'Buyer Escrow Officer' => ['prefix' => 'BEO', 'flag' => 'is_buyer_escrow'],
            'Seller Escrow Officer' => ['prefix' => 'SEO', 'flag' => 'is_seller_escrow'],
            'Listing Agent' => ['prefix' => 'LA', 'flag' => 'is_listing_agent'],
            'Selling Agent' => ['prefix' => 'SA', 'flag' => 'is_selling_agent']
        ];
        foreach ($role_config as $role_name => $cfg):
            $prefix = $cfg['prefix'];
            $flag = $cfg['flag'];
            // Retrieve current values from $listing (if editing)
            $current_name = val($listing, $prefix.'_Name');
            $current_company = val($listing, $prefix.'_Company');
            $current_email = val($listing, $prefix.'_Email');
            $current_office = val($listing, $prefix.'_OfficePhone');
            $current_cell = val($listing, $prefix.'_CellPhone');
            $current_fax = val($listing, $prefix.'_Fax');
            $current_asst_name = val($listing, $prefix.'_AsstName');
            $current_asst_office = val($listing, $prefix.'_AsstOfficePhone');
            $current_asst_fax = val($listing, $prefix.'_AsstFax');
            $current_asst_email = val($listing, $prefix.'_AsstEmail');
        ?>
        <div class="col-md-6 mb-4 agent-card" data-role="<?= $prefix ?>">
            <h6><?= $role_name ?></h6>
            <div class="row">
                <div class="col-12 mb-2">
                    <label class="form-label">Name</label>
                    <select class="form-select agent-select" data-prefix="<?= $prefix ?>">
                        <option value="">Select Agent</option>
                        <?php
                        $sql = "SELECT id, name, company, office_phone, cell_phone, fax, email, asst_name, asst_office_phone, asst_fax, asst_email 
                                FROM agents WHERE $flag = 1 AND active = 1 ORDER BY name";
                        $res = mysqli_query($conn, $sql);
                        while($ag = mysqli_fetch_assoc($res)) {
                            $selected = ($current_name == $ag['name']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($ag['name'])."' data-company='".htmlspecialchars($ag['company'])."' 
                                    data-office='".htmlspecialchars($ag['office_phone'])."' data-cell='".htmlspecialchars($ag['cell_phone'])."'
                                    data-fax='".htmlspecialchars($ag['fax'])."' data-email='".htmlspecialchars($ag['email'])."'
                                    data-asst-name='".htmlspecialchars($ag['asst_name'])."' data-asst-office='".htmlspecialchars($ag['asst_office_phone'])."'
                                    data-asst-fax='".htmlspecialchars($ag['asst_fax'])."' data-asst-email='".htmlspecialchars($ag['asst_email'])."'
                                    $selected>".htmlspecialchars($ag['name'])."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_Company" class="form-control agent-company" placeholder="Company" value="<?= $current_company ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_OfficePhone" class="form-control agent-office" placeholder="Office Phone" value="<?= $current_office ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_CellPhone" class="form-control agent-cell" placeholder="Cell" value="<?= $current_cell ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_Fax" class="form-control agent-fax" placeholder="Fax" value="<?= $current_fax ?>"></div>
                <div class="col-12 mb-2"><input type="email" name="<?= $prefix ?>_Email" class="form-control agent-email" placeholder="Email" value="<?= $current_email ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_AsstName" class="form-control agent-asst-name" placeholder="Assistant Name" value="<?= $current_asst_name ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_AsstOfficePhone" class="form-control agent-asst-office" placeholder="Asst Office Phone" value="<?= $current_asst_office ?>"></div>
                <div class="col-6 mb-2"><input type="text" name="<?= $prefix ?>_AsstFax" class="form-control agent-asst-fax" placeholder="Asst Fax" value="<?= $current_asst_fax ?>"></div>
                <div class="col-6 mb-2"><input type="email" name="<?= $prefix ?>_AsstEmail" class="form-control agent-asst-email" placeholder="Asst Email" value="<?= $current_asst_email ?>"></div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="<?= $prefix ?>_AddAsstFlag" class="form-check-input" value="1" <?= ($listing[$prefix.'_AddAsstFlag'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label">Add Assistant</label>
                    </div>
                </div>
                <?php if ($prefix == 'LA' || $prefix == 'SA'): ?>
                <div class="col-12 mt-2">
                    <div class="form-check">
                        <input type="checkbox" name="<?= $prefix ?>_ForReport" class="form-check-input" value="1" <?= ($listing[$prefix.'_ForReport'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">Include in Reports</label>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row mt-3">
        <div class="col-12">
            <label class="form-label">Comments</label>
            <textarea name="comments" class="form-control" rows="4"><?= val($listing, 'comments') ?></textarea>
        </div>
    </div>
</div>

            <!-- Misc Panel: Multiplier & Folder Link -->
            <div class="section-card d-none">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Multiplier</label>
                        <input type="number" step="0.1" name="multiplier" class="form-control" value="<?= val($listing, 'multiplier', '1') ?>">
                        <small class="text-muted">Change this number to 2 for double credit</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Folder Link</label>
                        <div class="input-group">
                            <input type="text" name="folder_path" class="form-control" value="<?= val($listing, 'folder_path') ?>">
                            <button type="button" class="btn btn-outline-secondary" id="viewFolderBtn">View Folder</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include('footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Commission calculation (unchanged)
    function calculateCommission() {
        let commPrice = parseFloat($('#commission_price').val()) || 0;
        let commPct = parseFloat($('#commission_pct').val()) || 0;
        let commOther = parseFloat($('#commission_other').val()) || 0;
        let transFee = parseFloat($('#transaction_fee').val()) || 0;
        let errors = parseFloat($('#errors_omissions').val()) || 0;
        let agentSplit = parseFloat($('#agent_split').val()) || 0;
        let procFee = parseFloat($('#processing_fee').val()) || 0;
        let other2 = parseFloat($('#other2').val()) || 0;

        let netAmt = (commPrice * commPct / 100) + commOther + transFee + errors;
        let checkAmt = (netAmt * agentSplit / 100) + procFee + other2;

        $('#net_amt').val(netAmt.toFixed(2));
        $('#check_amt').val(checkAmt.toFixed(2));
    }

    $('#commission_price, #commission_pct, #commission_other, #transaction_fee, #errors_omissions, #agent_split, #processing_fee, #other2').on('input', calculateCommission);
    calculateCommission();

    // Agent autofill for denormalized fields
    $('.agent-select').change(function() {
        let selectedOption = $(this).find(':selected');
        let prefix = $(this).data('prefix');
        let container = $(this).closest('.agent-card');
        
        if ($(this).val() == "") {
            // Clear all fields in this agent card
            container.find('input:not(.agent-name-hidden)').val('');
            return;
        }
        
        // Populate the fields using data attributes from the option
        container.find('.agent-company').val(selectedOption.data('company') || '');
        container.find('.agent-office').val(selectedOption.data('office') || '');
        container.find('.agent-cell').val(selectedOption.data('cell') || '');
        container.find('.agent-fax').val(selectedOption.data('fax') || '');
        container.find('.agent-email').val(selectedOption.data('email') || '');
        container.find('.agent-asst-name').val(selectedOption.data('asst-name') || '');
        container.find('.agent-asst-office').val(selectedOption.data('asst-office') || '');
        container.find('.agent-asst-fax').val(selectedOption.data('asst-fax') || '');
        container.find('.agent-asst-email').val(selectedOption.data('asst-email') || '');
    });

    // View Folder button
    $('#viewFolderBtn').click(function() {
        let folderPath = $('input[name="folder_path"]').val();
        if (folderPath) {
            window.open(folderPath, '_blank');
        } else {
            alert('No folder path set');
        }
    });

    $('#keyPlayersReportBtn').click(function() {
        window.open('generate_report.php?id=<?= $id ?>', '_blank');
    });
});
</script>
</body>
</html>