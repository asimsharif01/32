<?php
// transaction_detail.php — View / edit a single transaction (Key Player form)
require_once 'db.php';
session_start();

$id       = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit  = ($id > 0);
$listing  = null;
$milestones = [];

if ($is_edit) {
    // Load the single listing row — everything is inline (denormalised)
    $result  = mysqli_query($conn, "SELECT * FROM listings WHERE id = $id");
    $listing = mysqli_fetch_assoc($result);

    if (!$listing) {
        header('Location: transactions.php');
        exit;
    }

    // Load milestones
    $ms_res = mysqli_query($conn, "SELECT * FROM listing_milestones WHERE listing_id = $id");
    while ($row = mysqli_fetch_assoc($ms_res)) {
        $milestones[$row['milestone_type']] = $row;
    }
}

// Helper — safely output a value from the listing row
function val($data, $field, $default = '') {
    if (!$data) return htmlspecialchars($default);
    return isset($data[$field]) ? htmlspecialchars($data[$field]) : htmlspecialchars($default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit Transaction' : 'New Transaction' ?> — Larson &amp; Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .section-card   { background:#fff; border-radius:8px; padding:20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.1); }
        .section-title  { font-size:1.05rem; font-weight:700; margin-bottom:14px; color:#1e3a5f; border-left:4px solid #1e3a5f; padding-left:10px; }
        .agent-card     { background:#f8f9fc; padding:14px; border-radius:8px; margin-bottom:14px; border:1px solid #e2e8f0; }
        .agent-card h6  { color:#1e3a5f; margin-bottom:10px; font-weight:600; }
        .financial-row  { background:#fef9e6; padding:12px; border-radius:6px; }
        .form-label     { font-size:.75rem; font-weight:600; margin-bottom:.2rem; color:#4a5568; }
        .form-control,
        .form-select    { font-size:.85rem; padding:.25rem .5rem; }
        .calc-field     { background:#e9ecef !important; font-weight:600; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">

    <!-- Page title + action buttons -->
    <div class="page-titles d-flex justify-content-between align-items-center mb-2">
        <h5 class="bc-title"><?= $is_edit ? 'Edit Transaction' : 'New Transaction' ?></h5>
        <div>
            <a href="transactions.php" class="btn btn-light btn-sm me-1">← Back</a>
            <button type="submit" form="transactionForm" class="btn btn-primary btn-sm">Save</button>
            <?php if ($is_edit): ?>
            <a href="generate_report.php?id=<?= $id ?>" target="_blank"
               class="btn btn-info btn-sm ms-2">Key Players Report</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-fluid">
    <form id="transactionForm" action="transactions/save_transaction.php" method="POST">
        <input type="hidden" name="listing_id" value="<?= $id ?>">

        <!-- ══ SECTION 1: Property Information ══════════════════════════════════ -->
        <div class="section-card">
            <div class="section-title">Property Information</div>
            <div class="row g-2">

                <!-- Col 1: IDs + address -->
                <div class="col-md-4">
                    <div class="mb-2">
                        <label class="form-label">MLS Number</label>
                        <input type="text" name="mls_number" class="form-control"
                            value="<?= val($listing, 'mls_number') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Trans. Number</label>
                        <input type="text" name="transaction_number" class="form-control"
                            value="<?= val($listing, 'transaction_number') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Address</label>
                        <input type="text" name="address1" class="form-control"
                            value="<?= val($listing, 'address1') ?>">
                    </div>
                    <div class="row g-1 mb-2">
                        <div class="col-6">
                            <input type="text" name="city" class="form-control"
                                placeholder="City" value="<?= val($listing, 'city') ?>">
                        </div>
                        <div class="col-3">
                            <input type="text" name="state" class="form-control"
                                placeholder="ST" maxlength="2" value="<?= val($listing, 'state', 'UT') ?>">
                        </div>
                        <div class="col-3">
                            <input type="text" name="zip" class="form-control"
                                placeholder="Zip" value="<?= val($listing, 'zip') ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Earnest Money Amount</label>
                        <input type="number" step="0.01" name="earnest_money_amount" class="form-control"
                            value="<?= val($listing, 'earnest_money_amount') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">On Deposit With</label>
                        <input type="text" name="earnest_money_deposit_with" class="form-control"
                            value="<?= val($listing, 'earnest_money_deposit_with') ?>">
                    </div>
                </div>

                <!-- Col 2: Type / prices / dates -->
                <div class="col-md-4">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Property Type</label>
                            <select name="property_type_id" class="form-select">
                                <option value="">Select</option>
                                <?php
                                $pt_res = mysqli_query($conn, "SELECT id, description FROM property_types ORDER BY description");
                                while ($pt = mysqli_fetch_assoc($pt_res)) {
                                    $sel = ($listing['property_type_id'] ?? '') == $pt['id'] ? 'selected' : '';
                                    echo "<option value='{$pt['id']}' $sel>{$pt['description']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" class="form-control"
                                value="<?= val($listing, 'purchase_price') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">UC Price</label>
                            <input type="number" step="0.01" name="uc_price" class="form-control"
                                value="<?= val($listing, 'uc_price') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Financing Type</label>
                            <select name="financing_type_id" class="form-select">
                                <option value="">Select</option>
                                <?php
                                $ft_res = mysqli_query($conn, "SELECT id, description FROM financing_types ORDER BY description");
                                while ($ft = mysqli_fetch_assoc($ft_res)) {
                                    $sel = ($listing['financing_type_id'] ?? '') == $ft['id'] ? 'selected' : '';
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
                                $st_res = mysqli_query($conn, "SELECT id, description FROM sales_statuses ORDER BY description");
                                while ($st = mysqli_fetch_assoc($st_res)) {
                                    $sel = ($listing['status_id'] ?? '') == $st['id'] ? 'selected' : '';
                                    echo "<option value='{$st['id']}' $sel>{$st['description']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Lead Source</label>
                            <select name="lead_source" class="form-select">
                                <option value="">Select</option>
                                <?php
                                $ls_res = mysqli_query($conn, "SELECT description FROM lead_sources WHERE active=1 ORDER BY description");
                                while ($ls = mysqli_fetch_assoc($ls_res)) {
                                    $sel = ($listing['lead_source'] ?? '') == $ls['description'] ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($ls['description']) . "' $sel>"
                                        . htmlspecialchars($ls['description']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">D.O.L (Date of Listing)</label>
                            <input type="date" name="date_of_listing" class="form-control"
                                value="<?= val($listing, 'date_of_listing') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">D.O.E (Date of Expiration)</label>
                            <input type="date" name="date_of_expiration" class="form-control"
                                value="<?= val($listing, 'date_of_expiration') ?>">
                        </div>
                        <div class="col-12 mt-1">
                            <div class="form-check">
                                <input type="checkbox" name="private" class="form-check-input"
                                    value="1" <?= ($listing['private'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label form-label">Private</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Col 3: Final price (closing info) -->
                <div class="col-md-4">
                    <div class="mb-2">
                        <label class="form-label">Final / Closing Price</label>
                        <input type="number" step="0.01" name="final_price" class="form-control"
                            value="<?= val($listing, 'final_price') ?>">
                        <small class="text-muted">Actual price the home closed at</small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Closing Date</label>
                        <input type="date" name="closing_date" class="form-control"
                            value="<?= val($listing, 'closing_date') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Contract Date</label>
                        <input type="date" name="contract_date" class="form-control"
                            value="<?= val($listing, 'contract_date') ?>">
                        <small class="text-muted">Used for Under Contract reports</small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Multiplier</label>
                        <input type="number" step="0.1" name="multiplier" class="form-control"
                            value="<?= val($listing, 'multiplier', '1') ?>">
                        <small class="text-muted">Set to 2 for double credit on split deals</small>
                    </div>
                </div>

            </div>
        </div>


        <!-- ══ SECTION 2: Commission & Financials ══════════════════════════════ -->
        <div class="section-card">
            <div class="section-title">Commission &amp; Financials</div>
            <div class="financial-row">
                <!-- Row 1: inputs that feed the net amount -->
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-2">
                        <label class="form-label">Comm. Price</label>
                        <input type="number" step="0.01" name="commission_price" id="commission_price"
                            class="form-control" value="<?= val($listing, 'commission_price') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Comm %</label>
                        <input type="number" step="0.01" name="commission_pct" id="commission_pct"
                            class="form-control" value="<?= val($listing, 'commission_pct') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Other</label>
                        <input type="number" step="0.01" name="commission_other" id="commission_other"
                            class="form-control" value="<?= val($listing, 'commission_other') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Trans. Fee</label>
                        <input type="number" step="0.01" name="transaction_fee" id="transaction_fee"
                            class="form-control" value="<?= val($listing, 'transaction_fee') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Err. &amp; Omiss.</label>
                        <input type="number" step="0.01" name="errors_omissions" id="errors_omissions"
                            class="form-control" value="<?= val($listing, 'errors_omissions') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Net Amt (calculated)</label>
                        <input type="text" id="net_amt" name="commission_amount"
                            class="form-control calc-field" readonly
                            value="<?= val($listing, 'commission_amount') ?>">
                    </div>
                </div>
                <!-- Row 2: split / fees → check amount -->
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label">Agent Split %</label>
                        <input type="number" step="0.01" name="agent_split" id="agent_split"
                            class="form-control" value="<?= val($listing, 'agent_split') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Proc. Fee</label>
                        <input type="number" step="0.01" name="processing_fee" id="processing_fee"
                            class="form-control" value="<?= val($listing, 'processing_fee') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Other 2</label>
                        <input type="number" step="0.01" name="other2" id="other2"
                            class="form-control" value="<?= val($listing, 'other2') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Check Amt (calculated)</label>
                        <input type="text" id="check_amt" name="check_amount"
                            class="form-control calc-field" readonly
                            value="<?= val($listing, 'check_amount') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Split With</label>
                        <select name="split_with" class="form-select">
                            <option value="">None</option>
                            <?php
                            $sp_res = mysqli_query($conn, "SELECT name FROM agents
                                WHERE (is_listing_agent=1 OR is_selling_agent=1)
                                AND active=1 ORDER BY name");
                            while ($sp = mysqli_fetch_assoc($sp_res)) {
                                $sel = ($listing['split_with'] ?? '') == $sp['name'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($sp['name']) . "' $sel>"
                                    . htmlspecialchars($sp['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>


        <!-- ══ SECTION 3: Buyer & Seller Information ═════════════════════════ -->
        <!-- Stored directly in listings.buyer_* and listings.seller_* columns -->
        <div class="section-card">
            <div class="section-title">Buyer &amp; Seller Information</div>
            <div class="row">

                <!-- Buyer -->
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3" style="color:#1e3a5f">Buyer Info</h6>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="buyer_name" class="form-control"
                            value="<?= val($listing, 'buyer_name') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Home Phone</label>
                        <input type="text" name="buyer_home_phone" class="form-control"
                            value="<?= val($listing, 'buyer_home_phone') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cell Phone 1</label>
                        <input type="text" name="buyer_cell_phone1" class="form-control"
                            value="<?= val($listing, 'buyer_cell_phone1') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cell Phone 2</label>
                        <input type="text" name="buyer_cell_phone2" class="form-control"
                            value="<?= val($listing, 'buyer_cell_phone2') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fax</label>
                        <input type="text" name="buyer_fax" class="form-control"
                            value="<?= val($listing, 'buyer_fax') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email 1</label>
                        <input type="email" name="buyer_email1" class="form-control"
                            value="<?= val($listing, 'buyer_email1') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email 2</label>
                        <input type="email" name="buyer_email2" class="form-control"
                            value="<?= val($listing, 'buyer_email2') ?>">
                    </div>
                </div>

                <!-- Seller -->
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3" style="color:#1e3a5f">Seller Info</h6>
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="seller_name" class="form-control"
                            value="<?= val($listing, 'seller_name') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Home Phone</label>
                        <input type="text" name="seller_home_phone" class="form-control"
                            value="<?= val($listing, 'seller_home_phone') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cell Phone 1</label>
                        <input type="text" name="seller_cell_phone1" class="form-control"
                            value="<?= val($listing, 'seller_cell_phone1') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cell Phone 2</label>
                        <input type="text" name="seller_cell_phone2" class="form-control"
                            value="<?= val($listing, 'seller_cell_phone2') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Fax</label>
                        <input type="text" name="seller_fax" class="form-control"
                            value="<?= val($listing, 'seller_fax') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email 1</label>
                        <input type="email" name="seller_email1" class="form-control"
                            value="<?= val($listing, 'seller_email1') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email 2</label>
                        <input type="email" name="seller_email2" class="form-control"
                            value="<?= val($listing, 'seller_email2') ?>">
                    </div>
                </div>

            </div>
        </div>


        <!-- ══ SECTION 4: Deadlines ══════════════════════════════════════════ -->
        <!-- Stored in listing_milestones table (one row per deadline type)    -->
        <div class="section-card">
            <div class="section-title">Deadlines</div>
            <div class="row g-3">
                <?php
                $milestone_types = [
                    'Date of Contract',
                    'Seller Disclosure',
                    'Due Diligence',
                    'Financing & Appraisal',
                    'Settlement',
                ];
                foreach ($milestone_types as $mt):
                    $ms_date      = $milestones[$mt]['due_date']   ?? '';
                    $ms_completed = $milestones[$mt]['completed']  ?? 0;
                    $ms_na        = $milestones[$mt]['na_flag']    ?? 0;
                ?>
                <div class="col-md-2">
                    <label class="form-label"><?= htmlspecialchars($mt) ?></label>
                    <input type="date" name="milestone[<?= htmlspecialchars($mt) ?>][due_date]"
                        class="form-control" value="<?= htmlspecialchars($ms_date) ?>">
                    <div class="form-check mt-1">
                        <input type="checkbox"
                            name="milestone[<?= htmlspecialchars($mt) ?>][na_flag]"
                            class="form-check-input" value="1" <?= $ms_na ? 'checked' : '' ?>>
                        <label class="form-check-label">N/A</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox"
                            name="milestone[<?= htmlspecialchars($mt) ?>][completed]"
                            class="form-check-input" value="1" <?= $ms_completed ? 'checked' : '' ?>>
                        <label class="form-check-label">Completed</label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- ══ SECTION 5: Key Players (5 agent roles) ═══════════════════════ -->
        <!-- All stored inline in listings table as LO_*, BEO_*, SEO_*,      -->
        <!-- LA_*, SA_* columns (denormalised — matches Access design).        -->
        <div class="section-card">
            <div class="section-title">Key Players</div>
            <div class="row">
                <?php
                $role_config = [
                    'Loan Officer'          => ['prefix' => 'LO',  'flag' => 'is_loan_officer'],
                    'Buyer Escrow Officer'  => ['prefix' => 'BEO', 'flag' => 'is_buyer_escrow'],
                    'Seller Escrow Officer' => ['prefix' => 'SEO', 'flag' => 'is_seller_escrow'],
                    'Listing Agent'         => ['prefix' => 'LA',  'flag' => 'is_listing_agent'],
                    'Selling Agent'         => ['prefix' => 'SA',  'flag' => 'is_selling_agent'],
                ];

                foreach ($role_config as $role_name => $cfg):
                    $p = $cfg['prefix'];  // e.g. "LO"
                ?>
                <div class="col-md-6 mb-3">
                    <div class="agent-card" data-prefix="<?= $p ?>">
                        <h6><?= $role_name ?></h6>
                        <div class="row g-2">

                            <!-- Name dropdown — auto-fills from AgentTb data attributes -->
                            <div class="col-12">
                                <label class="form-label">Name</label>
                                <select class="form-select agent-select" data-prefix="<?= $p ?>">
                                    <option value="">Select <?= $role_name ?></option>
                                    <?php
                                    $ag_sql = "SELECT id, name, company, office_phone, cell_phone,
                                                      fax, email,
                                                      asst_name, asst_office_phone,
                                                      asst_cell_phone1, asst_fax, asst_email
                                               FROM agents
                                               WHERE {$cfg['flag']} = 1 AND active = 1
                                               ORDER BY name";
                                    $ag_res = mysqli_query($conn, $ag_sql);
                                    $current_name = $listing[$p . '_Name'] ?? '';
                                    while ($ag = mysqli_fetch_assoc($ag_res)) {
                                        $sel = ($current_name == $ag['name']) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($ag['name']) . "'
                                                data-company='"    . htmlspecialchars($ag['company'] ?? '') . "'
                                                data-office='"     . htmlspecialchars($ag['office_phone'] ?? '') . "'
                                                data-cell='"       . htmlspecialchars($ag['cell_phone'] ?? '') . "'
                                                data-fax='"        . htmlspecialchars($ag['fax'] ?? '') . "'
                                                data-email='"      . htmlspecialchars($ag['email'] ?? '') . "'
                                                data-asst-name='"  . htmlspecialchars($ag['asst_name'] ?? '') . "'
                                                data-asst-office='" . htmlspecialchars($ag['asst_office_phone'] ?? '') . "'
                                                data-asst-cell='"  . htmlspecialchars($ag['asst_cell_phone1'] ?? '') . "'
                                                data-asst-fax='"   . htmlspecialchars($ag['asst_fax'] ?? '') . "'
                                                data-asst-email='" . htmlspecialchars($ag['asst_email'] ?? '') . "'
                                                $sel>" . htmlspecialchars($ag['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                                <!-- Hidden input carries the actual name to save -->
                                <input type="hidden" name="<?= $p ?>_Name"
                                    class="agent-name-val"
                                    value="<?= val($listing, $p . '_Name') ?>">
                            </div>

                            <div class="col-6">
                                <label class="form-label">Company</label>
                                <input type="text" name="<?= $p ?>_Company" class="form-control agent-company"
                                    value="<?= val($listing, $p . '_Company') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Office Phone</label>
                                <input type="text" name="<?= $p ?>_OfficePhone" class="form-control agent-office"
                                    value="<?= val($listing, $p . '_OfficePhone') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Cell Phone</label>
                                <input type="text" name="<?= $p ?>_CellPhone" class="form-control agent-cell"
                                    value="<?= val($listing, $p . '_CellPhone') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Fax</label>
                                <input type="text" name="<?= $p ?>_Fax" class="form-control agent-fax"
                                    value="<?= val($listing, $p . '_Fax') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" name="<?= $p ?>_Email" class="form-control agent-email"
                                    value="<?= val($listing, $p . '_Email') ?>">
                            </div>

                            <!-- Assistant fields -->
                            <div class="col-12 mt-1">
                                <div class="form-check">
                                    <input type="checkbox"
                                        name="<?= $p ?>_AddAsstFlag"
                                        class="form-check-input asst-toggle"
                                        value="1"
                                        <?= ($listing[$p . '_AddAsstFlag'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label form-label">Show Assistant</label>
                                </div>
                            </div>
                            <div class="col-12 asst-block"
                                style="<?= ($listing[$p . '_AddAsstFlag'] ?? 0) ? '' : 'display:none' ?>">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label">Asst. Name</label>
                                        <input type="text" name="<?= $p ?>_AsstName"
                                            class="form-control agent-asst-name"
                                            value="<?= val($listing, $p . '_AsstName') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Asst. Office Phone</label>
                                        <input type="text" name="<?= $p ?>_AsstOfficePhone"
                                            class="form-control agent-asst-office"
                                            value="<?= val($listing, $p . '_AsstOfficePhone') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Asst. Cell Phone</label>
                                        <input type="text" name="<?= $p ?>_AsstCellPhone1"
                                            class="form-control agent-asst-cell"
                                            value="<?= val($listing, $p . '_AsstCellPhone1') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Asst. Fax</label>
                                        <input type="text" name="<?= $p ?>_AsstFax"
                                            class="form-control agent-asst-fax"
                                            value="<?= val($listing, $p . '_AsstFax') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Asst. Email</label>
                                        <input type="email" name="<?= $p ?>_AsstEmail"
                                            class="form-control agent-asst-email"
                                            value="<?= val($listing, $p . '_AsstEmail') ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- For Report flag — LA and SA only -->
                            <?php if ($p === 'LA' || $p === 'SA'): ?>
                            <div class="col-12 mt-1">
                                <div class="form-check">
                                    <input type="checkbox"
                                        name="<?= $p ?>_ForReport"
                                        class="form-check-input" value="1"
                                        <?= ($listing[$p . '_ForReport'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label form-label">Include in Reports</label>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div><!-- /.row -->
                    </div><!-- /.agent-card -->
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Comments -->
            <div class="row mt-2">
                <div class="col-12">
                    <label class="form-label">Comments</label>
                    <textarea name="comments" class="form-control" rows="4"><?= val($listing, 'comments') ?></textarea>
                </div>
            </div>
        </div>

    </form>
    </div><!-- /.container-fluid -->
</div><!-- /.content-body -->

<?php include('footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {

    // ── Commission calculation ────────────────────────────────────────────
    function calcCommission() {
        var commPrice  = parseFloat($('#commission_price').val())  || 0;
        var commPct    = parseFloat($('#commission_pct').val())    || 0;
        var commOther  = parseFloat($('#commission_other').val())  || 0;
        var transFee   = parseFloat($('#transaction_fee').val())   || 0;
        var errors     = parseFloat($('#errors_omissions').val())  || 0;
        var agentSplit = parseFloat($('#agent_split').val())       || 0;
        var procFee    = parseFloat($('#processing_fee').val())    || 0;
        var other2     = parseFloat($('#other2').val())            || 0;

        var netAmt   = (commPrice * commPct / 100) + commOther + transFee + errors;
        var checkAmt = (netAmt * agentSplit / 100) + procFee + other2;

        $('#net_amt').val(netAmt.toFixed(2));
        $('#check_amt').val(checkAmt.toFixed(2));
    }

    $('#commission_price, #commission_pct, #commission_other, #transaction_fee, ' +
      '#errors_omissions, #agent_split, #processing_fee, #other2')
        .on('input', calcCommission);
    calcCommission();


    // ── Agent dropdown → auto-fill fields + update hidden name input ──────
    $('.agent-select').on('change', function () {
        var opt       = $(this).find(':selected');
        var card      = $(this).closest('.agent-card');
        var agentName = $(this).val();

        // Update hidden name field (this is what gets submitted)
        card.find('.agent-name-val').val(agentName);

        if (!agentName) {
            // Clear all editable fields in this card
            card.find('input[type="text"], input[type="email"]').not('.agent-name-val').val('');
            return;
        }

        card.find('.agent-company').val(opt.data('company')    || '');
        card.find('.agent-office').val(opt.data('office')      || '');
        card.find('.agent-cell').val(opt.data('cell')          || '');
        card.find('.agent-fax').val(opt.data('fax')            || '');
        card.find('.agent-email').val(opt.data('email')        || '');
        card.find('.agent-asst-name').val(opt.data('asst-name')    || '');
        card.find('.agent-asst-office').val(opt.data('asst-office')|| '');
        card.find('.agent-asst-cell').val(opt.data('asst-cell')    || '');
        card.find('.agent-asst-fax').val(opt.data('asst-fax')      || '');
        card.find('.agent-asst-email').val(opt.data('asst-email')  || '');
    });


    // ── Show/hide assistant block ─────────────────────────────────────────
    $('.asst-toggle').on('change', function () {
        var block = $(this).closest('.agent-card').find('.asst-block');
        if ($(this).is(':checked')) {
            block.show();
        } else {
            block.hide();
        }
    });

});
</script>
</body>
</html>