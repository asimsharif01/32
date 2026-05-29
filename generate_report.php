<?php
// generate_report.php — Key Players Summary Report
// Opens in a new tab from transaction_detail.php
// All data read from the single listings row (denormalised) + listing_milestones pivot
require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid listing ID.");
}

// ── Main query ────────────────────────────────────────────────────────────
// Buyer/seller come from inline listings columns (not contacts/transaction_roles).
// Milestone dates are pivoted from listing_milestones via MAX(CASE WHEN ...).
// listings.contract_date is the editable field; milestone pivot aliases use
// different names to avoid collision.
$sql = "
    SELECT
        l.id,
        l.mls_number,
        l.transaction_number,
        l.address1,
        l.address2,
        l.city,
        l.state,
        l.zip,
        pt.description  AS property_type,
        l.uc_price,
        ft.description  AS financing_type,
        l.earnest_money_amount,
        l.earnest_money_deposit_with,

        -- ── Buyer (inline in listings) ──────────────────────────────────
        l.buyer_name,
        l.buyer_home_phone,
        l.buyer_cell_phone1,
        l.buyer_cell_phone2,
        l.buyer_fax,
        l.buyer_email1,
        l.buyer_email2,

        -- ── Seller (inline in listings) ─────────────────────────────────
        l.seller_name,
        l.seller_home_phone,
        l.seller_cell_phone1,
        l.seller_cell_phone2,
        l.seller_fax,
        l.seller_email1,
        l.seller_email2,

        -- ── Deadline dates (pivoted from listing_milestones) ─────────────
        -- Aliased with ms_ prefix to avoid colliding with listings columns
        MAX(CASE WHEN lm.milestone_type = 'Date of Contract'      THEN lm.due_date END) AS ms_contract_date,
        MAX(CASE WHEN lm.milestone_type = 'Seller Disclosure'     THEN lm.due_date END) AS ms_seller_disclosure,
        MAX(CASE WHEN lm.milestone_type = 'Due Diligence'         THEN lm.due_date END) AS ms_due_diligence,
        MAX(CASE WHEN lm.milestone_type = 'Financing & Appraisal' THEN lm.due_date END) AS ms_financing_appraisal,
        MAX(CASE WHEN lm.milestone_type = 'Settlement'            THEN lm.due_date END) AS ms_settlement,

        -- Also bring the na_flag for each milestone so we can show N/A
        MAX(CASE WHEN lm.milestone_type = 'Date of Contract'      THEN lm.na_flag END) AS ms_contract_na,
        MAX(CASE WHEN lm.milestone_type = 'Seller Disclosure'     THEN lm.na_flag END) AS ms_seller_disclosure_na,
        MAX(CASE WHEN lm.milestone_type = 'Due Diligence'         THEN lm.na_flag END) AS ms_due_diligence_na,
        MAX(CASE WHEN lm.milestone_type = 'Financing & Appraisal' THEN lm.na_flag END) AS ms_financing_appraisal_na,
        MAX(CASE WHEN lm.milestone_type = 'Settlement'            THEN lm.na_flag END) AS ms_settlement_na,

        -- ── Loan Officer ────────────────────────────────────────────────
        l.LO_Name,  l.LO_Company, l.LO_Email,
        l.LO_OfficePhone, l.LO_CellPhone, l.LO_Fax,
        l.LO_AsstName, l.LO_AsstOfficePhone, l.LO_AsstCellPhone1,
        l.LO_AsstFax, l.LO_AsstEmail,

        -- ── Buyer Escrow Officer ────────────────────────────────────────
        l.BEO_Name, l.BEO_Company, l.BEO_Email,
        l.BEO_OfficePhone, l.BEO_CellPhone, l.BEO_Fax,
        l.BEO_AsstName, l.BEO_AsstOfficePhone, l.BEO_AsstCellPhone1,
        l.BEO_AsstFax, l.BEO_AsstEmail,

        -- ── Seller Escrow Officer ───────────────────────────────────────
        l.SEO_Name, l.SEO_Company, l.SEO_Email,
        l.SEO_OfficePhone, l.SEO_CellPhone, l.SEO_Fax,
        l.SEO_AsstName, l.SEO_AsstOfficePhone, l.SEO_AsstCellPhone1,
        l.SEO_AsstFax, l.SEO_AsstEmail,

        -- ── Listing Agent ───────────────────────────────────────────────
        l.LA_Name, l.LA_Company, l.LA_Email,
        l.LA_OfficePhone, l.LA_CellPhone, l.LA_Fax,
        l.LA_AsstName, l.LA_AsstOfficePhone, l.LA_AsstCellPhone1,
        l.LA_AsstFax, l.LA_AsstEmail,

        -- ── Selling Agent ───────────────────────────────────────────────
        l.SA_Name, l.SA_Company, l.SA_Email,
        l.SA_OfficePhone, l.SA_CellPhone, l.SA_Fax,
        l.SA_AsstName, l.SA_AsstOfficePhone, l.SA_AsstCellPhone1,
        l.SA_AsstFax, l.SA_AsstEmail,

        l.comments

    FROM listings l
    LEFT JOIN property_types  pt ON l.property_type_id  = pt.id
    LEFT JOIN financing_types ft ON l.financing_type_id = ft.id
    LEFT JOIN listing_milestones lm ON lm.listing_id = l.id
    WHERE l.id = $id
    GROUP BY l.id
";

$result  = mysqli_query($conn, $sql);
if (!$result) {
    die('Query error: ' . mysqli_error($conn));
}
$r = mysqli_fetch_assoc($result);
if (!$r) {
    die("No record found for ID $id.");
}

// ── Helpers ───────────────────────────────────────────────────────────────
function h($v) {
    return htmlspecialchars($v ?? '');
}

// Format date as "Fri, Jan 4, 2008" — matches Access report format
function fmtDate($date, $na_flag = 0) {
    if ($na_flag) return 'N/A';
    if (empty($date)) return '';
    return date('D, M j, Y', strtotime($date));
}

function fmtMoney($v) {
    if ($v === null || $v === '') return '';
    return '$' . number_format((float)$v, 2);
}

// Render a single agent card — only shown if the agent name is set
function agentCard($title, $prefix, $r) {
    $name = $r[$prefix . '_Name'] ?? '';
    if (empty($name)) return; // skip empty roles (matches Access behaviour)

    $asst = $r[$prefix . '_AsstName'] ?? '';
    ?>
    <div class="agent-card">
        <div class="agent-card-title"><?= h($title) ?></div>
        <div class="agent-row"><span class="al">Name:</span><span class="av"><?= h($name) ?></span></div>
        <?php if (!empty($r[$prefix . '_Company'])): ?>
        <div class="agent-row"><span class="al">Company:</span><span class="av"><?= h($r[$prefix . '_Company']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_OfficePhone'])): ?>
        <div class="agent-row"><span class="al">Office:</span><span class="av"><?= h($r[$prefix . '_OfficePhone']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_CellPhone'])): ?>
        <div class="agent-row"><span class="al">Cell:</span><span class="av"><?= h($r[$prefix . '_CellPhone']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_Fax'])): ?>
        <div class="agent-row"><span class="al">Fax:</span><span class="av"><?= h($r[$prefix . '_Fax']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_Email'])): ?>
        <div class="agent-row"><span class="al">Email:</span><span class="av"><?= h($r[$prefix . '_Email']) ?></span></div>
        <?php endif; ?>

        <?php if (!empty($asst)): ?>
        <div class="asst-divider"></div>
        <div class="agent-row"><span class="al">Assistant:</span><span class="av"><?= h($asst) ?></span></div>
        <?php if (!empty($r[$prefix . '_AsstOfficePhone'])): ?>
        <div class="agent-row"><span class="al">Asst Phone:</span><span class="av"><?= h($r[$prefix . '_AsstOfficePhone']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_AsstCellPhone1'])): ?>
        <div class="agent-row"><span class="al">Asst Cell:</span><span class="av"><?= h($r[$prefix . '_AsstCellPhone1']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_AsstFax'])): ?>
        <div class="agent-row"><span class="al">Asst Fax:</span><span class="av"><?= h($r[$prefix . '_AsstFax']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($r[$prefix . '_AsstEmail'])): ?>
        <div class="agent-row"><span class="al">Asst Email:</span><span class="av"><?= h($r[$prefix . '_AsstEmail']) ?></span></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Key Players Summary — TN <?= h($r['transaction_number']) ?></title>
    <style>
        /* ── Reset ─────────────────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            padding: 30px;
            font-size: 13px;
            color: #1e293b;
        }

        /* ── Wrapper ───────────────────────────────────────────────────── */
        .report-wrap {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,.12);
            padding: 32px 38px;
        }

        /* ── Print button ──────────────────────────────────────────────── */
        .no-print {
            text-align: right;
            margin-bottom: 16px;
        }
        .btn-print {
            background: #1e3a5f;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-print:hover { background: #0f2b3d; }

        /* ── Report header ─────────────────────────────────────────────── */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .report-title-block h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f2b3d;
        }
        .report-title-block p {
            font-size: 12px;
            color: #4b5563;
            margin-top: 2px;
        }
        .company-block {
            text-align: right;
            font-size: 12px;
            color: #4b5563;
        }
        .company-block .co-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e3a5f;
        }

        /* ── Section ───────────────────────────────────────────────────── */
        .section {
            margin-bottom: 22px;
            break-inside: avoid;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            background: #eef2ff;
            padding: 5px 12px;
            border-left: 4px solid #1e3a5f;
            margin-bottom: 12px;
            color: #1e3a5f;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        /* ── Two-column info rows ──────────────────────────────────────── */
        .two-col { display: flex; gap: 30px; flex-wrap: wrap; }
        .col      { flex: 1; min-width: 240px; }

        .info-row {
            display: flex;
            margin-bottom: 5px;
            font-size: 12.5px;
        }
        .il {                       /* info label */
            width: 145px;
            font-weight: 600;
            color: #4b5563;
            flex-shrink: 0;
        }
        .iv { flex: 1; }            /* info value */

        /* Settlement highlight — matches Access report style */
        .settlement-label { background: #fef9e6; font-weight: 700; }
        .settlement-value { background: #fef9e6; padding: 1px 6px; border-radius: 3px; font-weight: 600; }

        /* ── Agent cards grid ──────────────────────────────────────────── */
        .agent-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 16px;
        }
        .agent-card {
            background: #f9fafb;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .agent-card-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e3a5f;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .agent-row {
            font-size: 12px;
            margin-bottom: 3px;
            display: flex;
        }
        .al {                        /* agent label */
            width: 85px;
            font-weight: 600;
            color: #4b5563;
            flex-shrink: 0;
        }
        .av { flex: 1; }             /* agent value */
        .asst-divider {
            border-top: 1px dashed #cbd5e1;
            margin: 7px 0;
        }

        /* ── Comments ──────────────────────────────────────────────────── */
        .comments-box {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 12px;
            font-size: 12px;
            white-space: pre-wrap;
            min-height: 60px;
        }

        /* ── Print styles ──────────────────────────────────────────────── */
        @media print {
            body            { background: #fff; padding: 0; }
            .report-wrap    { padding: 10px; box-shadow: none; border-radius: 0; }
            .no-print       { display: none !important; }
            .agent-grid     { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="report-wrap">

    <!-- Print button (hidden on print) -->
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <!-- ── Report Header ─────────────────────────────────────────────── -->
    <div class="report-header">
        <div class="report-title-block">
            <h1>Key Players Summary</h1>
            <p>Transaction # <?= h($r['transaction_number']) ?></p>
        </div>
        <div class="company-block">
            <div class="co-name">LARSON &amp; COMPANY</div>
            <div>Real Estate</div>
            <div>pros@larsonandcompany.com</div>
            <div>www.larsonandcompany.com</div>
        </div>
    </div>


    <!-- ── PROPERTY INFORMATION ──────────────────────────────────────── -->
    <div class="section">
        <div class="section-title">Property Information</div>
        <div class="two-col">
            <div class="col">
                <div class="info-row">
                    <span class="il">Address:</span>
                    <span class="iv">
                        <?= h($r['address1']) ?>
                        <?= !empty($r['address2']) ? ', ' . h($r['address2']) : '' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="il">City, State Zip:</span>
                    <span class="iv"><?= h($r['city']) ?>, <?= h($r['state']) ?> <?= h($r['zip']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Transaction #:</span>
                    <span class="iv"><?= h($r['transaction_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">MLS #:</span>
                    <span class="iv"><?= h($r['mls_number']) ?></span>
                </div>
            </div>
            <div class="col">
                <div class="info-row">
                    <span class="il">Property Type:</span>
                    <span class="iv"><?= h($r['property_type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">UC Price:</span>
                    <span class="iv"><?= fmtMoney($r['uc_price']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Financing:</span>
                    <span class="iv"><?= h($r['financing_type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Earnest Money:</span>
                    <span class="iv"><?= fmtMoney($r['earnest_money_amount']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Deposit With:</span>
                    <span class="iv"><?= h($r['earnest_money_deposit_with']) ?></span>
                </div>
            </div>
        </div>
    </div>


    <!-- ── DEADLINES ─────────────────────────────────────────────────── -->
    <div class="section">
        <div class="section-title">Deadlines</div>
        <div class="two-col">
            <div class="col">
                <div class="info-row">
                    <span class="il">Date of Contract:</span>
                    <span class="iv"><?= fmtDate($r['ms_contract_date'], $r['ms_contract_na']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Seller Disclosure:</span>
                    <span class="iv"><?= fmtDate($r['ms_seller_disclosure'], $r['ms_seller_disclosure_na']) ?></span>
                </div>
                <div class="info-row">
                    <span class="il">Due Diligence:</span>
                    <span class="iv"><?= fmtDate($r['ms_due_diligence'], $r['ms_due_diligence_na']) ?></span>
                </div>
            </div>
            <div class="col">
                <div class="info-row">
                    <span class="il">Financing &amp; Appraisal:</span>
                    <span class="iv"><?= fmtDate($r['ms_financing_appraisal'], $r['ms_financing_appraisal_na']) ?></span>
                </div>
                <!-- Settlement highlighted — matches Access report box style -->
                <div class="info-row">
                    <span class="il settlement-label">SETTLEMENT:</span>
                    <span class="iv settlement-value"><?= fmtDate($r['ms_settlement'], $r['ms_settlement_na']) ?></span>
                </div>
            </div>
        </div>
    </div>


    <!-- ── BUYER AND SELLER INFORMATION ──────────────────────────────── -->
    <div class="section">
        <div class="section-title">Buyer and Seller Information</div>
        <div class="two-col">

            <!-- Buyer — reads from listings.buyer_* columns -->
            <div class="col">
                <div class="agent-card-title" style="font-size:12px;font-weight:700;color:#1e3a5f;margin-bottom:8px;">Buyer</div>
                <div class="info-row"><span class="il">Name:</span><span class="iv"><?= h($r['buyer_name']) ?></span></div>
                <div class="info-row"><span class="il">Home Phone:</span><span class="iv"><?= h($r['buyer_home_phone']) ?></span></div>
                <div class="info-row"><span class="il">Cell Phone 1:</span><span class="iv"><?= h($r['buyer_cell_phone1']) ?></span></div>
                <?php if (!empty($r['buyer_cell_phone2'])): ?>
                <div class="info-row"><span class="il">Cell Phone 2:</span><span class="iv"><?= h($r['buyer_cell_phone2']) ?></span></div>
                <?php endif; ?>
                <div class="info-row"><span class="il">Fax:</span><span class="iv"><?= h($r['buyer_fax']) ?></span></div>
                <div class="info-row"><span class="il">Email:</span><span class="iv"><?= h($r['buyer_email1']) ?></span></div>
                <?php if (!empty($r['buyer_email2'])): ?>
                <div class="info-row"><span class="il">Email 2:</span><span class="iv"><?= h($r['buyer_email2']) ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Seller — reads from listings.seller_* columns -->
            <div class="col">
                <div class="agent-card-title" style="font-size:12px;font-weight:700;color:#1e3a5f;margin-bottom:8px;">Seller</div>
                <div class="info-row"><span class="il">Name:</span><span class="iv"><?= h($r['seller_name']) ?></span></div>
                <div class="info-row"><span class="il">Home Phone:</span><span class="iv"><?= h($r['seller_home_phone']) ?></span></div>
                <div class="info-row"><span class="il">Cell Phone 1:</span><span class="iv"><?= h($r['seller_cell_phone1']) ?></span></div>
                <?php if (!empty($r['seller_cell_phone2'])): ?>
                <div class="info-row"><span class="il">Cell Phone 2:</span><span class="iv"><?= h($r['seller_cell_phone2']) ?></span></div>
                <?php endif; ?>
                <div class="info-row"><span class="il">Fax:</span><span class="iv"><?= h($r['seller_fax']) ?></span></div>
                <div class="info-row"><span class="il">Email:</span><span class="iv"><?= h($r['seller_email1']) ?></span></div>
                <?php if (!empty($r['seller_email2'])): ?>
                <div class="info-row"><span class="il">Email 2:</span><span class="iv"><?= h($r['seller_email2']) ?></span></div>
                <?php endif; ?>
            </div>

        </div>
    </div>


    <!-- ── KEY PLAYERS (5 agent roles) ───────────────────────────────── -->
    <!-- Order matches Access report: LO → BEO → SEO → LA → SA          -->
    <div class="section">
        <div class="section-title">Key Players</div>
        <div class="agent-grid">
            <?php
            agentCard('Loan Officer',          'LO',  $r);
            agentCard('Buyer Escrow Officer',  'BEO', $r);
            agentCard('Seller Escrow Officer', 'SEO', $r);
            agentCard('Listing Agent',         'LA',  $r);
            agentCard('Selling Agent',         'SA',  $r);
            ?>
        </div>
    </div>


    <!-- ── COMMENTS (only shown when not empty) ──────────────────────── -->
    <?php if (!empty($r['comments'])): ?>
    <div class="section">
        <div class="section-title">Comments</div>
        <div class="comments-box"><?= nl2br(h($r['comments'])) ?></div>
    </div>
    <?php endif; ?>

</div><!-- /.report-wrap -->
</body>
</html>