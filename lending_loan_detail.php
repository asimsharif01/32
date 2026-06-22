<?php
// lending_loan_detail.php — Add/Edit a single loan
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

$action  = $_GET['action'] ?? 'edit';
$loan_id = intval($_GET['id'] ?? 0);
$is_create = ($action === 'create' || $loan_id <= 0);

// ── Load existing loan (edit mode) ─────────────────────────────────────────
$loan = [];
$borrowers = [];
if (!$is_create) {
    $stmt = $conn->prepare("SELECT * FROM loans WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $loan_id);
    $stmt->execute();
    $loan = $stmt->get_result()->fetch_assoc();
    if (!$loan) {
        header('Location: lending_loans.php?error=' . urlencode('Loan not found.'));
        exit;
    }

    $stmt2 = $conn->prepare("SELECT * FROM loan_borrowers WHERE loan_id = ? ORDER BY id");
    $stmt2->bind_param('i', $loan_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) $borrowers[] = $row;
}

// ── Dropdown data ───────────────────────────────────────────────────────────
// Active-only for NEW selections, but always include the currently-assigned
// value even if it's now inactive (so editing an old loan doesn't silently
// change who's assigned).
function dropdownOptions($conn, $table, $currentId = null) {
    $sql = "SELECT id, name FROM `$table` WHERE active = 1";
    if ($currentId) {
        $sql .= " OR id = " . intval($currentId);
    }
    $sql .= " ORDER BY name";
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

$consultants = dropdownOptions($conn, 'loan_consultants', $loan['loan_consultant_id'] ?? null);
$processors  = dropdownOptions($conn, 'loan_processors',  $loan['loan_processor_id']  ?? null);
$statuses    = dropdownOptions($conn, 'loan_statuses',    $loan['status_id']          ?? null);
$loanTypes   = dropdownOptions($conn, 'loan_types',       $loan['loan_type_id']       ?? null);
$purchTypes  = dropdownOptions($conn, 'purchase_types',   $loan['purchase_type_id']   ?? null);
$roleTypes   = dropdownOptions($conn, 'loan_role_types',  $loan['loan_role_type_id']  ?? null);
$referrals   = dropdownOptions($conn, 'loan_referral_sources', $loan['referral_source_id'] ?? null);

// ── Helper ───────────────────────────────────────────────────────────────
function v($arr, $key, $default = '') {
    return htmlspecialchars($arr[$key] ?? $default);
}
function selected($val, $current) {
    return ($val == $current) ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_create ? 'Add Loan' : 'Edit Loan' ?> — Laser Lending</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
    .section-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 20px 22px;
        margin-bottom: 18px;
    }

    .section-title {
        font-size: 10px !important;
        font-weight: 700;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: .4px;
        border-left: 4px solid #1e3a5f;
        padding-left: 10px;
        margin-bottom: 2px !important;
    }

    .borrower-block {
        border: 1px solid #e8ecf4;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 12px;
        background: #f8f9fc;
        position: relative;
    }

    .borrower-block .remove-borrower {
        position: absolute;
        top: 10px;
        right: 10px;
    }
    </style>
</head>

<body>
    <?php include('header.php'); ?>
    <style>
    .section-card {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 5px;
        background: #fff;
    }
    </style>
    <div class="content-body">
        <div class="page-titles d-flex justify-content-between align-items-center">
            <h5 class="bc-title"><?= $is_create ? 'Add New Loan' : 'Edit Loan' ?></h5>
            <a href="lending_loans.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to Loan Info
            </a>
        </div>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="container-fluid">
            <form action="lending_save_loan.php" method="POST" id="loanForm">
                <input type="hidden" name="action" value="<?= $is_create ? 'create' : 'edit' ?>">
                <?php if (!$is_create): ?>
                <input type="hidden" name="id" value="<?= $loan_id ?>">
                <?php endif; ?>

                <!-- ═══ Loan Identifiers ═══ -->
                <div class="section-card">
                    <div class="section-title">Loan Identifiers</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Transaction #</label>
                            <input type="text" name="transaction_no" class="form-control" maxlength="10"
                                value="<?= v($loan, 'transaction_no') ?>">
                        </div>
                        <?php if (!$is_create): ?>
                        <div class="col-md-4">
                            <label class="form-label">Loan ID</label>
                            <input type="text" class="form-control" value="<?= $loan_id ?>" disabled>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-4">
                            <div class="section-title">Borrower</div>

                            <label class="form-label">Borrower Name <span class="text-danger">*</span></label>
                            <input type="text" name="borrower_name" class="form-control" required maxlength="50"
                                value="<?= v($loan, 'borrower_name') ?>"
                                placeholder="e.g. Call, Zachary or Ellis, Mark &amp; Cheryl">
                            <small class="text-muted">This is the primary name shown in the loan list and
                                reports.</small>

                            <label class="form-label">Borrower Contact Details <small class="text-muted">(optional —
                                    supports co-borrowers)</small></label>
                            <div id="borrowerBlocks">
                                <?php if ($borrowers): foreach ($borrowers as $i => $b): ?>
                                <div class="borrower-block">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger remove-borrower">&times;</button>
                                    <input type="hidden" name="borrower_ids[]" value="<?= $b['id'] ?>">
                                    <div class="row g-2">
                                        <div class="col-md-6"><input type="text" name="borrower_address[]"
                                                class="form-control" placeholder="Address"
                                                value="<?= v($b, 'address') ?>"></div>
                                        <div class="col-md-3"><input type="text" name="borrower_city[]"
                                                class="form-control" placeholder="City" value="<?= v($b, 'city') ?>">
                                        </div>
                                        <div class="col-md-1"><input type="text" name="borrower_state[]"
                                                class="form-control" placeholder="State" value="<?= v($b, 'state') ?>">
                                        </div>
                                        <div class="col-md-2"><input type="text" name="borrower_zip[]"
                                                class="form-control" placeholder="Zip" value="<?= v($b, 'zip') ?>">
                                        </div>
                                        <div class="col-md-3 mt-2"><input type="text" name="borrower_phone[]"
                                                class="form-control" placeholder="Phone" value="<?= v($b, 'phone') ?>">
                                        </div>
                                        <div class="col-md-4 mt-2"><input type="email" name="borrower_email[]"
                                                class="form-control" placeholder="Email" value="<?= v($b, 'email') ?>">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addBorrowerBtn">
                                <i class="fas fa-plus me-1"></i> Add Borrower Contact
                            </button>

                        </div>
                    </div>
                </div>



        <!-- ═══ Loan Details ═══ -->
        <div class="section-card">
            <div class="section-title">Loan Details</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Loan Type</label>
                    <select name="loan_type_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($loanTypes as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['loan_type_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase / Refinance</label>
                    <select name="purchase_type_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($purchTypes as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['purchase_type_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Brokered / Warehouse</label>
                    <select name="loan_role_type_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($roleTypes as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['loan_role_type_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($statuses as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['status_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Referral Source / Agent</label>
                    <select name="referral_source_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($referrals as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['referral_source_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Manage this list under System Listings → Referral Sources /
                        Agents.</small>
                </div>
            </div>
        </div>

        <!-- ═══ Personnel ═══ -->
        <div class="section-card">
            <div class="section-title">Personnel</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Loan Consultant</label>
                    <select name="loan_consultant_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($consultants as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['loan_consultant_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loan Processor</label>
                    <select name="loan_processor_id" class="form-select">
                        <option value="">— Select —</option>
                        <?php foreach ($processors as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= selected($t['id'], $loan['loan_processor_id'] ?? null) ?>>
                            <?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- ═══ Amounts ═══ -->
        <div class="section-card">
            <div class="section-title">Loan Amounts</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Loan 1 Amount</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="loan_1_amount" class="form-control money-input"
                            value="<?= v($loan, 'loan_1_amount') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Loan 2 Amount</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="loan_2_amount" class="form-control money-input"
                            value="<?= v($loan, 'loan_2_amount') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">LO Revenue</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="lo_revenue" class="form-control money-input"
                            value="<?= v($loan, 'lo_revenue') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Processing Fee</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="processing_fee" class="form-control money-input"
                            value="<?= v($loan, 'processing_fee') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Dates ═══ -->
        <div class="section-card">
            <div class="section-title">Dates</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Submitted</label>
                    <input type="text" name="date_submitted" class="form-control flatpickr-date"
                        value="<?= v($loan, 'date_submitted') ?>" placeholder="Select date" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Approved Date</label>
                    <input type="text" name="approved_date" class="form-control flatpickr-date"
                        value="<?= v($loan, 'approved_date') ?>" placeholder="Select date" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Welcome Docs</label>
                    <input type="text" name="date_welcome_docs" class="form-control flatpickr-date"
                        value="<?= v($loan, 'date_welcome_docs') ?>" placeholder="Select date" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Docs Date</label>
                    <input type="text" name="to_docs_date" class="form-control flatpickr-date"
                        value="<?= v($loan, 'to_docs_date') ?>" placeholder="Select date" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Closed / Funded</label>
                    <input type="text" name="date_closed" class="form-control flatpickr-date"
                        value="<?= v($loan, 'date_closed') ?>" placeholder="Select date" readonly>
                </div>
            </div>
        </div>

        <!-- ═══ Check Tracking ═══ -->
        <div class="section-card">
            <div class="section-title">Check Tracking</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Lender Check</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="lender_check" class="form-control money-input"
                            value="<?= v($loan, 'lender_check') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Title Co. Check</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="title_co_check" class="form-control money-input"
                            value="<?= v($loan, 'title_co_check') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">PLM Check</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="plm_check" class="form-control money-input"
                            value="<?= v($loan, 'plm_check') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Loan Consultant Check</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input type="text" name="loan_consultant_check" class="form-control money-input"
                            value="<?= v($loan, 'loan_consultant_check') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Notes ═══ -->
        <div class="section-card">
            <div class="section-title">Notes</div>
            <textarea name="notes" class="form-control" rows="3"><?= v($loan, 'notes') ?></textarea>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Loan</button>
            <a href="lending_loans.php" class="btn btn-light">Cancel</a>
        </div>

        </form>
    </div>
    </div>

    <?php include('footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    // ── Flatpickr — no native-calendar-stays-open bug ─────────────────────────
    document.querySelectorAll('.flatpickr-date').forEach(function(el) {
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: false,
            disableMobile: true
        });
    });

    // ── Money inputs — strip $ and commas on blur for clean display ──────────
    document.querySelectorAll('.money-input').forEach(function(el) {
        el.addEventListener('blur', function() {
            var num = parseFloat(this.value.replace(/[^0-9.\-]/g, ''));
            this.value = isNaN(num) ? '' : num.toFixed(2);
        });
    });

    // ── Repeatable borrower contact blocks ────────────────────────────────────
    document.getElementById('addBorrowerBtn').addEventListener('click', function() {
        var div = document.createElement('div');
        div.className = 'borrower-block';
        div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger remove-borrower">&times;</button>
        <div class="row g-2">
            <div class="col-md-6"><input type="text" name="borrower_address[]" class="form-control" placeholder="Address"></div>
            <div class="col-md-3"><input type="text" name="borrower_city[]" class="form-control" placeholder="City"></div>
            <div class="col-md-1"><input type="text" name="borrower_state[]" class="form-control" placeholder="State"></div>
            <div class="col-md-2"><input type="text" name="borrower_zip[]" class="form-control" placeholder="Zip"></div>
            <div class="col-md-3 mt-2"><input type="text" name="borrower_phone[]" class="form-control" placeholder="Phone"></div>
            <div class="col-md-4 mt-2"><input type="email" name="borrower_email[]" class="form-control" placeholder="Email"></div>
        </div>
    `;
        document.getElementById('borrowerBlocks').appendChild(div);
    });

    document.getElementById('borrowerBlocks').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-borrower')) {
            e.target.closest('.borrower-block').remove();
        }
    });
    </script>
</body>

</html>