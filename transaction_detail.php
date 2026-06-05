<?php
// transaction_detail.php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

$id         = isset($_GET['id']) ? intval($_GET['id']) : 0;
$is_edit    = ($id > 0);
$listing    = null;
$milestones = [];

if ($is_edit) {
    $result  = mysqli_query($conn, "SELECT * FROM listings WHERE id = $id");
    $listing = mysqli_fetch_assoc($result);
    if (!$listing) { header('Location: transactions.php'); exit; }

    // Load milestones — na_flag may not exist on older schemas; handle gracefully
    $ms_res = mysqli_query($conn, "SELECT * FROM listing_milestones WHERE listing_id = $id");
    if ($ms_res) {
        while ($row = mysqli_fetch_assoc($ms_res)) {
            $milestones[$row['milestone_type']] = $row;
        }
    }
}

// Safe HTML output
function val($data, $field, $default = '') {
    if (!$data) return htmlspecialchars($default);
    return htmlspecialchars($data[$field] ?? $default);
}
// Raw unescaped (for textarea inner content)
function raw($data, $field, $default = '') {
    if (!$data) return $default;
    return $data[$field] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? 'Edit Transaction' : 'New Transaction' ?> — Larson &amp; Company</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <style>
        body { background:#f0f2f5; font-size:.875rem; }

        /* ── Cards ──────────────────────────────────────── */
        .sc {
            background:#fff; border-radius:8px; padding:16px 18px;
            margin-bottom:14px; box-shadow:0 1px 4px rgba(0,0,0,.07);
        }
        .sc-title {
            font-size:.82rem; font-weight:700; color:#1e3a5f;
            border-left:4px solid #1e3a5f; padding-left:9px;
            margin-bottom:12px; text-transform:uppercase; letter-spacing:.3px;
        }

        /* ── Form labels & controls ──────────────────────── */
        .form-label  { font-size:.68rem; font-weight:600; color:#4a5568; margin-bottom:.12rem; }
        .form-control, .form-select { font-size:.8rem; padding:.22rem .4rem; }
        .calc-field  { background:#e8f4e8 !important; font-weight:700; color:#1a5c1a; }
        .fin-bg      { background:#fef9e6; border-radius:6px; padding:10px 12px; }

        /* ── Pill tabs (compact) ─────────────────────────── */
        .nav-pills-sm { display:flex; flex-wrap:nowrap; gap:4px; margin-bottom:10px; }
        .nav-pills-sm .nav-link {
            font-size:.72rem; font-weight:600; padding:3px 11px;
            border-radius:20px; color:#1e3a5f;
            border:1px solid #c8d4e8; background:#f5f7fb;
            white-space:nowrap; cursor:pointer;
        }
        .nav-pills-sm .nav-link.active {
            background:#1e3a5f; color:#fff; border-color:#1e3a5f;
        }

        /* ── Agent card inside tab ───────────────────────── */
        .agent-inner { background:#f8f9fc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; }
        .asst-section { border-top:1px dashed #c8d4e8; margin-top:8px; padding-top:8px; }

        /* ── Deadline items ──────────────────────────────── */
        .dl-item {
            background:#f8f9fc; border:1px solid #e2e8f0;
            border-radius:6px; padding:8px 10px; margin-bottom:7px;
        }
        .dl-item .dl-label {
            font-size:.67rem; font-weight:700; color:#1e3a5f;
            text-transform:uppercase; letter-spacing:.3px;
            display:block; margin-bottom:4px;
        }
        .dl-checks { display:flex; gap:12px; margin-top:4px; }
        .dl-checks .form-check-label { font-size:.67rem; }

        /* ── Name text input under dropdown ─────────────── */
        .name-manual {
            font-size:.75rem; border-color:#b8c8d8; margin-top:4px;
            background:#fff;
        }
        .name-manual::placeholder { color:#a0aec0; font-style:italic; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center mb-2">
        <h5 class="bc-title"><?= $is_edit ? 'Edit Transaction' : 'New Transaction' ?></h5>
        <div class="d-flex gap-2">
            <a href="transactions.php" class="btn btn-light btn-sm">← Back</a>
            <?php if ($is_edit): ?>
            <a href="generate_report.php?id=<?= $id ?>" target="_blank"
               class="btn btn-outline-info btn-sm">Key Players Report</a>
            <?php endif; ?>
            <button type="submit" form="txForm" class="btn btn-primary btn-sm">💾 Save</button>
        </div>
    </div>

<div class="container-fluid">
<form id="txForm" action="transactions/save_transaction.php" method="POST">
<input type="hidden" name="listing_id" value="<?= $id ?>">

<!-- ══ ROW 1: Property Info  |  Deadlines ══════════════════════════════ -->
<div class="row g-3 mb-0">

    <!-- LEFT 8 cols: Property Info + Commission -->
    <div class="col-xl-8 col-lg-7">

        <!-- Property Info -->
        <div class="sc">
            <div class="sc-title">Property Information</div>
            <div class="row g-2">
                <div class="col-3"><label class="form-label">MLS Number</label>
                    <input type="text" name="mls_number" class="form-control" value="<?= val($listing,'mls_number') ?>"></div>
                <div class="col-3"><label class="form-label">Trans. Number</label>
                    <input type="text" name="transaction_number" class="form-control" value="<?= val($listing,'transaction_number') ?>"></div>
                <div class="col-3"><label class="form-label">Property Type</label>
                    <select name="property_type_id" class="form-select"><option value="">Select</option>
                    <?php $r=mysqli_query($conn,"SELECT id,description FROM property_types ORDER BY description");
                    while($row=mysqli_fetch_assoc($r)){$s=($listing['property_type_id']??'')==$row['id']?'selected':'';
                    echo "<option value='{$row['id']}' $s>".htmlspecialchars($row['description'])."</option>";}?>
                    </select></div>
                <div class="col-3"><label class="form-label">Sales Status</label>
                    <select name="status_id" class="form-select"><option value="">Select</option>
                    <?php $r=mysqli_query($conn,"SELECT id,description FROM sales_statuses ORDER BY description");
                    while($row=mysqli_fetch_assoc($r)){$s=($listing['status_id']??'')==$row['id']?'selected':'';
                    echo "<option value='{$row['id']}' $s>".htmlspecialchars($row['description'])."</option>";}?>
                    </select></div>

                <div class="col-5"><label class="form-label">Address</label>
                    <input type="text" name="address1" class="form-control" value="<?= val($listing,'address1') ?>"></div>
                <div class="col-3"><label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= val($listing,'city') ?>"></div>
                <div class="col-1"><label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" maxlength="2" value="<?= val($listing,'state','UT') ?>"></div>
                <div class="col-3"><label class="form-label">Zip</label>
                    <input type="text" name="zip" class="form-control" value="<?= val($listing,'zip') ?>"></div>

                <div class="col-3"><label class="form-label">Purchase Price</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= val($listing,'purchase_price') ?>"></div>
                <div class="col-3"><label class="form-label">UC Price</label>
                    <input type="number" step="0.01" name="uc_price" class="form-control" value="<?= val($listing,'uc_price') ?>"></div>
                <div class="col-3"><label class="form-label">Financing Type</label>
                    <select name="financing_type_id" class="form-select"><option value="">Select</option>
                    <?php $r=mysqli_query($conn,"SELECT id,description FROM financing_types ORDER BY description");
                    while($row=mysqli_fetch_assoc($r)){$s=($listing['financing_type_id']??'')==$row['id']?'selected':'';
                    echo "<option value='{$row['id']}' $s>".htmlspecialchars($row['description'])."</option>";}?>
                    </select></div>

                <div class="col-3"><label class="form-label">Lead Source</label>
                    <select name="lead_source" class="form-select"><option value="">Select</option>
                    <?php $r=mysqli_query($conn,"SELECT description FROM lead_sources WHERE active=1 ORDER BY description");
                    while($row=mysqli_fetch_assoc($r)){$s=($listing['lead_source']??'')==$row['description']?'selected':'';
                    echo "<option value='".htmlspecialchars($row['description'])."' $s>".htmlspecialchars($row['description'])."</option>";}?>
                    </select></div>
                <div class="col-3"><label class="form-label">D.O.L (Date of Listing)</label>
                    <input type="date" name="date_of_listing" class="form-control" value="<?= val($listing,'date_of_listing') ?>"></div>
                <div class="col-3"><label class="form-label">D.O.E (Expiration)</label>
                    <input type="date" name="date_of_expiration" class="form-control" value="<?= val($listing,'date_of_expiration') ?>"></div>
                <div class="col-3"><label class="form-label">Closing Date</label>
                    <input type="date" name="closing_date" class="form-control" value="<?= val($listing,'closing_date') ?>"></div>

                <div class="col-3"><label class="form-label">Contract Date</label>
                    <input type="date" name="contract_date" class="form-control" value="<?= val($listing,'contract_date') ?>"></div>
                <div class="col-3"><label class="form-label">Earnest Money</label>
                    <input type="number" step="0.01" name="earnest_money_amount" class="form-control" value="<?= val($listing,'earnest_money_amount') ?>"></div>
                <div class="col-4"><label class="form-label">On Deposit With</label>
                    <input type="text" name="earnest_money_deposit_with" class="form-control" value="<?= val($listing,'earnest_money_deposit_with') ?>"></div>
                <div class="col-2 d-flex align-items-end pb-1">
                    <div class="form-check">
                        <input type="checkbox" name="private" class="form-check-input" value="1"
                            <?= ($listing['private']??0)?'checked':'' ?>>
                        <label class="form-check-label form-label">Private</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission -->
        <div class="sc">
            <div class="sc-title">Commission &amp; Financials</div>
            <div class="fin-bg">
                <div class="row g-2 mb-2">
                     <!-- Final Price — first column matching Access layout -->
                    <div class="col"><label class="form-label">Final Price</label>
                        <input type="number" step="0.01" id="fp" name="final_price" class="form-control"
                            value="<?= ($listing['final_price']??'') ?>"
                            placeholder="Actual close price"></div>
                    <div class="col"><label class="form-label">Comm. Price</label>
                        <input type="number" step="0.01" name="commission_price" id="commission_price" class="form-control" value="<?= val($listing,'commission_price') ?>"></div>
                    <div class="col"><label class="form-label">Comm %</label>
                        <input type="number" step="0.01" name="commission_pct" id="commission_pct" class="form-control" value="<?= val($listing,'commission_pct') ?>"></div>
                    <div class="col"><label class="form-label">Other</label>
                        <input type="number" step="0.01" name="commission_other" id="commission_other" class="form-control" value="<?= val($listing,'commission_other') ?>"></div>
                    <div class="col"><label class="form-label">Trans. Fee</label>
                        <input type="number" step="0.01" name="transaction_fee" id="transaction_fee" class="form-control" value="<?= val($listing,'transaction_fee') ?>"></div>
                    <div class="col"><label class="form-label">Err. &amp; Omiss.</label>
                        <input type="number" step="0.01" name="errors_omissions" id="errors_omissions" class="form-control" value="<?= val($listing,'errors_omissions') ?>"></div>
                    <div class="col"><label class="form-label">Net Amt</label>
                        <input type="text" id="net_amt" name="commission_amount" class="form-control calc-field" readonly value="<?= val($listing,'commission_amount') ?>"></div>
                </div>
                <div class="row g-2">
                    <div class="col"><label class="form-label">Agent Split %</label>
                        <input type="number" step="0.01" name="agent_split" id="agent_split" class="form-control" value="<?= val($listing,'agent_split') ?>"></div>
                    <div class="col"><label class="form-label">Proc. Fee</label>
                        <input type="number" step="0.01" name="processing_fee" id="processing_fee" class="form-control" value="<?= val($listing,'processing_fee') ?>"></div>
                    <div class="col"><label class="form-label">Other 2</label>
                        <input type="number" step="0.01" name="other2" id="other2" class="form-control" value="<?= val($listing,'other2') ?>"></div>
                    <div class="col"><label class="form-label">Check Amt</label>
                        <input type="text" id="check_amt" name="check_amount" class="form-control calc-field" readonly value="<?= val($listing,'check_amount') ?>"></div>
                    <div class="col"><label class="form-label">Multiplier</label>
                        <input type="number" step="0.1" name="multiplier" class="form-control" value="<?= val($listing,'multiplier','1') ?>"></div>
                    <div class="col"><label class="form-label">Split With</label>
                        <select name="split_with" class="form-select"><option value="">None</option>
                        <?php $r=mysqli_query($conn,"SELECT name FROM agents WHERE (is_listing_agent=1 OR is_selling_agent=1) AND active=1 ORDER BY name");
                        while($row=mysqli_fetch_assoc($r)){$s=($listing['split_with']??'')==$row['name']?'selected':'';
                        echo "<option value='".htmlspecialchars($row['name'])."' $s>".htmlspecialchars($row['name'])."</option>";}?>
                        </select></div>
                </div>
            </div>
        </div>

    </div><!-- /col-xl-8 -->

    <!-- RIGHT 4 cols: Deadlines -->
    <div class="col-xl-4 col-lg-5">
        <div class="sc" style="position:sticky;top:10px">
            <div class="sc-title">Deadlines</div>
            <?php
            $mtypes = ['Date of Contract','Seller Disclosure','Due Diligence','Financing & Appraisal','Settlement'];
            foreach ($mtypes as $mt):
                $ms   = $milestones[$mt] ?? [];
                $mdate= $ms['due_date']  ?? '';
                $mcomp= $ms['completed'] ?? 0;
                $mna  = $ms['na_flag']   ?? 0;
                $is_settlement = ($mt === 'Settlement');
            ?>
            <div class="dl-item<?= $is_settlement ? ' border-warning' : '' ?>">
                <span class="dl-label"><?= htmlspecialchars($mt) ?></span>
                <input type="date"
                    name="milestone[<?= htmlspecialchars($mt) ?>][due_date]"
                    class="form-control<?= $is_settlement ? ' border-warning fw-bold' : '' ?>"
                    value="<?= htmlspecialchars($mdate) ?>">
                <div class="dl-checks">
                    <div class="form-check form-check-inline mb-0">
                        <input type="checkbox" class="form-check-input"
                            name="milestone[<?= htmlspecialchars($mt) ?>][na_flag]"
                            value="1" id="na_<?= md5($mt) ?>"
                            <?= $mna?'checked':'' ?>>
                        <label class="form-check-label" for="na_<?= md5($mt) ?>">N/A</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input type="checkbox" class="form-check-input"
                            name="milestone[<?= htmlspecialchars($mt) ?>][completed]"
                            value="1" id="comp_<?= md5($mt) ?>"
                            <?= $mcomp?'checked':'' ?>>
                        <label class="form-check-label" for="comp_<?= md5($mt) ?>">Done</label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /row 1 -->


<!-- ══ ROW 2: Buyer/Seller tabs  |  Key Players tabs ══════════════════ -->
<div class="row g-3">

    <!-- Buyer & Seller pill tabs (left 5) -->
    <div class="col-xl-5 col-lg-5">
        <div class="sc">
            <div class="sc-title">Buyer &amp; Seller</div>
            <ul class="nav-pills-sm" role="tablist">
                <li><button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabBuyer">🧑 Buyer</button></li>
                <li><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#tabSeller">🏠 Seller</button></li>
            </ul>
            <div class="tab-content">

                <!-- Buyer -->
                <div class="tab-pane fade show active" id="tabBuyer" role="tabpanel">
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label">Name</label>
                            <input type="text" name="buyer_name" class="form-control" value="<?= val($listing,'buyer_name') ?>"></div>
                        <div class="col-6"><label class="form-label">Home Phone</label>
                            <input type="text" name="buyer_home_phone" class="form-control" value="<?= val($listing,'buyer_home_phone') ?>"></div>
                        <div class="col-6"><label class="form-label">Cell Phone 1</label>
                            <input type="text" name="buyer_cell_phone1" class="form-control" value="<?= val($listing,'buyer_cell_phone1') ?>"></div>
                        <div class="col-6"><label class="form-label">Cell Phone 2</label>
                            <input type="text" name="buyer_cell_phone2" class="form-control" value="<?= val($listing,'buyer_cell_phone2') ?>"></div>
                        <div class="col-6"><label class="form-label">Fax</label>
                            <input type="text" name="buyer_fax" class="form-control" value="<?= val($listing,'buyer_fax') ?>"></div>
                        <div class="col-6"><label class="form-label">Email 1</label>
                            <input type="email" name="buyer_email1" class="form-control" value="<?= val($listing,'buyer_email1') ?>"></div>
                        <div class="col-6"><label class="form-label">Email 2</label>
                            <input type="email" name="buyer_email2" class="form-control" value="<?= val($listing,'buyer_email2') ?>"></div>
                    </div>
                </div>

                <!-- Seller -->
                <div class="tab-pane fade" id="tabSeller" role="tabpanel">
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label">Name</label>
                            <input type="text" name="seller_name" class="form-control" value="<?= val($listing,'seller_name') ?>"></div>
                        <div class="col-6"><label class="form-label">Home Phone</label>
                            <input type="text" name="seller_home_phone" class="form-control" value="<?= val($listing,'seller_home_phone') ?>"></div>
                        <div class="col-6"><label class="form-label">Cell Phone 1</label>
                            <input type="text" name="seller_cell_phone1" class="form-control" value="<?= val($listing,'seller_cell_phone1') ?>"></div>
                        <div class="col-6"><label class="form-label">Cell Phone 2</label>
                            <input type="text" name="seller_cell_phone2" class="form-control" value="<?= val($listing,'seller_cell_phone2') ?>"></div>
                        <div class="col-6"><label class="form-label">Fax</label>
                            <input type="text" name="seller_fax" class="form-control" value="<?= val($listing,'seller_fax') ?>"></div>
                        <div class="col-6"><label class="form-label">Email 1</label>
                            <input type="email" name="seller_email1" class="form-control" value="<?= val($listing,'seller_email1') ?>"></div>
                        <div class="col-6"><label class="form-label">Email 2</label>
                            <input type="email" name="seller_email2" class="form-control" value="<?= val($listing,'seller_email2') ?>"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Key Players pill tabs (right 7) -->
    <div class="col-xl-7 col-lg-7">
        <div class="sc">
            <div class="sc-title">Key Players</div>

            <?php
            $roles = [
                'LO'  => ['label'=>'Loan Officer',          'icon'=>'💰','flag'=>'is_loan_officer'],
                'BEO' => ['label'=>'Buyer Escrow Officer',  'icon'=>'🏦','flag'=>'is_buyer_escrow'],
                'SEO' => ['label'=>'Seller Escrow Officer', 'icon'=>'📋','flag'=>'is_seller_escrow'],
                'LA'  => ['label'=>'Listing Agent',         'icon'=>'🏠','flag'=>'is_listing_agent'],
                'SA'  => ['label'=>'Selling Agent',         'icon'=>'🤝','flag'=>'is_selling_agent'],
            ];
            ?>

            <ul class="nav-pills-sm" role="tablist">
                <?php $first=true; foreach($roles as $p=>$rc): ?>
                <li>
                    <button type="button" class="nav-link <?= $first?'active':'' ?>"
                        data-bs-toggle="pill" data-bs-target="#kp_<?= $p ?>">
                        <?= $rc['icon'] ?> <?= $p ?>
                    </button>
                </li>
                <?php $first=false; endforeach; ?>
            </ul>

            <div class="tab-content">
            <?php $first=true; foreach($roles as $p=>$rc):
                $cur_name = $listing[$p.'_Name'] ?? '';
            ?>
            <div class="tab-pane fade <?= $first?'show active':'' ?>" id="kp_<?= $p ?>" role="tabpanel">
                <div class="agent-inner">
                    <div class="row g-2">

                        <div class="col-12">
                            <label class="form-label"><?= $rc['label'] ?> — Select Saved Agent</label>
                            <!-- Dropdown: only shows agents in our agents table -->
                            <select class="form-select agent-select" data-prefix="<?= $p ?>">
                                <option value="">— Select from saved agents —</option>
                                <?php
                                $ar = mysqli_query($conn,"SELECT id,name,company,office_phone,
                                    cell_phone,fax,email,asst_name,asst_office_phone,
                                    asst_cell_phone1,asst_fax,asst_email
                                    FROM agents WHERE {$rc['flag']}=1 AND active=1 ORDER BY name");
                                while ($ag = mysqli_fetch_assoc($ar)):
                                    // Select this option if name matches current value
                                    $sel = ($cur_name === $ag['name']) ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($ag['name']) ?>"
                                    data-company="<?= htmlspecialchars($ag['company']??'') ?>"
                                    data-office="<?= htmlspecialchars($ag['office_phone']??'') ?>"
                                    data-cell="<?= htmlspecialchars($ag['cell_phone']??'') ?>"
                                    data-fax="<?= htmlspecialchars($ag['fax']??'') ?>"
                                    data-email="<?= htmlspecialchars($ag['email']??'') ?>"
                                    data-asst-name="<?= htmlspecialchars($ag['asst_name']??'') ?>"
                                    data-asst-office="<?= htmlspecialchars($ag['asst_office_phone']??'') ?>"
                                    data-asst-cell="<?= htmlspecialchars($ag['asst_cell_phone1']??'') ?>"
                                    data-asst-fax="<?= htmlspecialchars($ag['asst_fax']??'') ?>"
                                    data-asst-email="<?= htmlspecialchars($ag['asst_email']??'') ?>"
                                    <?= $sel ?>
                                ><?= htmlspecialchars($ag['name']) ?></option>
                                <?php endwhile; ?>
                            </select>

                            <!--
                                ★ KEY FIX for "name not showing":
                                This is a VISIBLE text input (not hidden).
                                - For agents IN the agents table: dropdown auto-fills this
                                - For external agents (Corbett Simon, JeNee Hutchinson etc.)
                                  who are NOT in the agents table: the name shows here directly
                                  because it comes from the listings row, not from the dropdown.
                                This field is what actually gets submitted and saved.
                            -->
                            <input type="text"
                                name="<?= $p ?>_Name"
                                class="form-control name-manual agent-name-val"
                                placeholder="Or type name for external agents not in the list above"
                                value="<?= val($listing, $p.'_Name') ?>">
                        </div>

                        <div class="col-6">
                            <label class="form-label">Company</label>
                            <input type="text" name="<?= $p ?>_Company" class="form-control agent-company"
                                value="<?= val($listing,$p.'_Company') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Office Phone</label>
                            <input type="text" name="<?= $p ?>_OfficePhone" class="form-control agent-office"
                                value="<?= val($listing,$p.'_OfficePhone') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Cell Phone</label>
                            <input type="text" name="<?= $p ?>_CellPhone" class="form-control agent-cell"
                                value="<?= val($listing,$p.'_CellPhone') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Fax</label>
                            <input type="text" name="<?= $p ?>_Fax" class="form-control agent-fax"
                                value="<?= val($listing,$p.'_Fax') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="<?= $p ?>_Email" class="form-control agent-email"
                                value="<?= val($listing,$p.'_Email') ?>">
                        </div>

                        <?php if ($p==='LA' || $p==='SA'): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="<?= $p ?>_ForReport"
                                    class="form-check-input" value="1"
                                    <?= ($listing[$p.'_ForReport']??1)?'checked':'' ?>>
                                <label class="form-check-label form-label">Include in Reports</label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Assistant toggle -->
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="<?= $p ?>_AddAsstFlag"
                                    class="form-check-input asst-toggle" value="1"
                                    <?= ($listing[$p.'_AddAsstFlag']??0)?'checked':'' ?>>
                                <label class="form-check-label form-label">Show Assistant</label>
                            </div>
                        </div>

                        <div class="col-12 asst-section"
                            style="<?= ($listing[$p.'_AddAsstFlag']??0)?'':'display:none' ?>">
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label">Asst. Name</label>
                                    <input type="text" name="<?= $p ?>_AsstName" class="form-control agent-asst-name"
                                        value="<?= val($listing,$p.'_AsstName') ?>"></div>
                                <div class="col-6"><label class="form-label">Asst. Office</label>
                                    <input type="text" name="<?= $p ?>_AsstOfficePhone" class="form-control agent-asst-office"
                                        value="<?= val($listing,$p.'_AsstOfficePhone') ?>"></div>
                                <div class="col-6"><label class="form-label">Asst. Cell</label>
                                    <input type="text" name="<?= $p ?>_AsstCellPhone1" class="form-control agent-asst-cell"
                                        value="<?= val($listing,$p.'_AsstCellPhone1') ?>"></div>
                                <div class="col-6"><label class="form-label">Asst. Fax</label>
                                    <input type="text" name="<?= $p ?>_AsstFax" class="form-control agent-asst-fax"
                                        value="<?= val($listing,$p.'_AsstFax') ?>"></div>
                                <div class="col-12"><label class="form-label">Asst. Email</label>
                                    <input type="email" name="<?= $p ?>_AsstEmail" class="form-control agent-asst-email"
                                        value="<?= val($listing,$p.'_AsstEmail') ?>"></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <?php $first=false; endforeach; ?>
            </div><!-- /tab-content -->

            <!-- Comments sits below the Key Players tabs -->
            <div class="mt-3">
                <label class="form-label">Comments</label>
                <!--
                    ★ KEY FIX for "comments not showing":
                    val() runs htmlspecialchars() which HTML-encodes the value.
                    For a textarea, the content goes BETWEEN the tags, not in an attribute.
                    raw() returns the plain string, then we wrap in htmlspecialchars()
                    directly here so special chars display correctly but don't break HTML.
                -->
                <textarea name="comments" class="form-control" rows="3"><?= htmlspecialchars(raw($listing,'comments')) ?></textarea>
            </div>

        </div>
    </div>

</div><!-- /row 2 -->
</form>
</div><!-- /container -->
</div><!-- /content-body -->

<?php include('footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {

    // ── Commission calculation ────────────────────────────────────────
    function calcComm() {
        var cp  = parseFloat($('#commission_price').val())  || 0;
        var pct = parseFloat($('#commission_pct').val())    || 0;
        var co  = parseFloat($('#commission_other').val())  || 0;
        var tf  = parseFloat($('#transaction_fee').val())   || 0;
        var eo  = parseFloat($('#errors_omissions').val())  || 0;
        var sp  = parseFloat($('#agent_split').val())       || 0;
        var pf  = parseFloat($('#processing_fee').val())    || 0;
        var o2  = parseFloat($('#other2').val())            || 0;
        var net = (cp * pct / 100) + co + tf + eo;
        $('#net_amt').val(net.toFixed(2));
        $('#check_amt').val(((net * sp / 100) + pf + o2).toFixed(2));
    }
    $('#commission_price,#commission_pct,#commission_other,#transaction_fee,' +
      '#errors_omissions,#agent_split,#processing_fee,#other2').on('input', calcComm);
    calcComm();

    // ── Agent dropdown autofill ────────────────────────────────────────
    // When a saved agent is picked:
    //   1. Copy name into the text input below (which is what gets SAVED)
    //   2. Fill company, phone, email etc.
    // When the text input is edited manually (external agent):
    //   - Nothing special needed; it saves as typed.
    $(document).on('change', '.agent-select', function () {
        var $opt  = $(this).find(':selected');
        var $card = $(this).closest('.agent-inner');
        var name  = $(this).val();

        // Update the text input below — this is what actually gets submitted
        $card.find('.agent-name-val').val(name);

        if (!name) {
            $card.find('.agent-company,.agent-office,.agent-cell,.agent-fax,.agent-email').val('');
            $card.find('.agent-asst-name,.agent-asst-office,.agent-asst-cell,.agent-asst-fax,.agent-asst-email').val('');
            return;
        }

        $card.find('.agent-company').val($opt.data('company')     || '');
        $card.find('.agent-office').val($opt.data('office')       || '');
        $card.find('.agent-cell').val($opt.data('cell')           || '');
        $card.find('.agent-fax').val($opt.data('fax')             || '');
        $card.find('.agent-email').val($opt.data('email')         || '');
        $card.find('.agent-asst-name').val($opt.data('asst-name') || '');
        $card.find('.agent-asst-office').val($opt.data('asst-office') || '');
        $card.find('.agent-asst-cell').val($opt.data('asst-cell') || '');
        $card.find('.agent-asst-fax').val($opt.data('asst-fax')   || '');
        $card.find('.agent-asst-email').val($opt.data('asst-email')|| '');
    });

    // ── Assistant toggle ───────────────────────────────────────────────
    $(document).on('change', '.asst-toggle', function () {
        $(this).closest('.agent-inner').find('.asst-section')
               .toggle($(this).is(':checked'));
    });

});
</script>
</body>
</html>