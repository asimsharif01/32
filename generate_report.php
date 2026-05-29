<?php
require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid report ID.");
}

// Build the query with all necessary joins
$sql = "
    SELECT 
        l.mls_number,
        l.transaction_number,
        l.address1,
        l.address2,
        l.city,
        l.state,
        l.zip,
        pt.description AS property_type,
        l.uc_price,
        ft.description AS financing_type,
        l.earnest_money_amount,
        l.earnest_money_deposit_with,
        -- Milestones (pivot from listing_milestones)
        MAX(CASE WHEN lm.milestone_type = 'Date of Contract' THEN lm.due_date END) AS contract_date,
        MAX(CASE WHEN lm.milestone_type = 'Seller Disclosure' THEN lm.due_date END) AS sellers_disclosure_date,
        MAX(CASE WHEN lm.milestone_type = 'Due Diligence' THEN lm.due_date END) AS due_diligence_deadline,
        MAX(CASE WHEN lm.milestone_type = 'Financing & Appraisal' THEN lm.due_date END) AS funding_and_appraisal,
        MAX(CASE WHEN lm.milestone_type = 'Settlement' THEN lm.due_date END) AS settlement_deadline_date,
        -- Buyer info (from contacts via transaction_roles)
        buyer_contact.name AS buyer_name,
        buyer_contact.home_phone AS buyer_home_phone,
        buyer_contact.cell_phone AS buyer_cell_phone,
        buyer_contact.fax AS buyer_fax,
        buyer_contact.email AS buyer_email,
        -- Seller info
        seller_contact.name AS seller_name,
        seller_contact.home_phone AS seller_home_phone,
        seller_contact.cell_phone AS seller_cell_phone,
        seller_contact.fax AS seller_fax,
        seller_contact.email AS seller_email,
        -- Denormalized agent fields (directly from listings)
        l.LO_Name, l.LO_Company, l.LO_Email, l.LO_OfficePhone, l.LO_CellPhone, l.LO_Fax,
        l.LO_AsstName, l.LO_AsstOfficePhone, l.LO_AsstFax, l.LO_AsstEmail,
        l.BEO_Name, l.BEO_Company, l.BEO_Email, l.BEO_OfficePhone, l.BEO_CellPhone, l.BEO_Fax,
        l.BEO_AsstName, l.BEO_AsstOfficePhone, l.BEO_AsstFax, l.BEO_AsstEmail,
        l.SEO_Name, l.SEO_Company, l.SEO_Email, l.SEO_OfficePhone, l.SEO_CellPhone, l.SEO_Fax,
        l.SEO_AsstName, l.SEO_AsstOfficePhone, l.SEO_AsstFax, l.SEO_AsstEmail,
        l.LA_Name, l.LA_Company, l.LA_Email, l.LA_OfficePhone, l.LA_CellPhone, l.LA_Fax,
        l.LA_AsstName, l.LA_AsstOfficePhone, l.LA_AsstFax, l.LA_AsstEmail,
        l.SA_Name, l.SA_Company, l.SA_Email, l.SA_OfficePhone, l.SA_CellPhone, l.SA_Fax,
        l.SA_AsstName, l.SA_AsstOfficePhone, l.SA_AsstFax, l.SA_AsstEmail,
        l.comments
    FROM listings l
    LEFT JOIN property_types pt ON l.property_type_id = pt.id
    LEFT JOIN financing_types ft ON l.financing_type_id = ft.id
    LEFT JOIN listing_milestones lm ON l.id = lm.listing_id
    LEFT JOIN transaction_roles tr_buyer ON l.id = tr_buyer.listing_id AND tr_buyer.role_type = 'Buyer'
    LEFT JOIN contacts buyer_contact ON tr_buyer.contact_id = buyer_contact.id
    LEFT JOIN transaction_roles tr_seller ON l.id = tr_seller.listing_id AND tr_seller.role_type = 'Seller'
    LEFT JOIN contacts seller_contact ON tr_seller.contact_id = seller_contact.id
    WHERE l.id = $id
    GROUP BY l.id
";

$result = mysqli_query($conn, $sql);
$listing = mysqli_fetch_assoc($result);
if (!$listing) {
    die("No record found.");
}

// Helper functions
function formatDate($date) {
    if (empty($date)) return '';
    return date('D, M j, Y', strtotime($date));
}

function formatMoney($amount) {
    if (empty($amount)) return '';
    return '$' . number_format((float)$amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Key Players Summary - <?= htmlspecialchars($listing['transaction_number']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; padding: 30px; }
        .report-container { max-width: 1100px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 30px 35px; }
        .report-header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #1e3a5f; padding-bottom: 15px; }
        .logo { font-size: 28px; font-weight: 700; color: #1e3a5f; }
        .logo-sub { font-size: 14px; color: #4b5563; margin-top: 5px; }
        h1 { font-size: 22px; margin: 15px 0 5px; color: #0f2b3d; }
        .company-contact { font-size: 12px; color: #4b5563; }
        .section { margin-bottom: 25px; break-inside: avoid; }
        .section-title { font-size: 16px; font-weight: 700; background: #eef2ff; padding: 6px 12px; border-left: 4px solid #1e3a5f; margin-bottom: 12px; color: #1e3a5f; }
        .two-columns { display: flex; gap: 30px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 250px; }
        .info-row { display: flex; margin-bottom: 6px; font-size: 13px; }
        .info-label { width: 130px; font-weight: 600; color: #4b5563; flex-shrink: 0; }
        .info-value { flex: 1; color: #1e293b; }
        .agent-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; margin-top: 10px; }
        .agent-card { background: #f9fafb; border-radius: 8px; padding: 12px 15px; border: 1px solid #e2e8f0; }
        .agent-card h3 { font-size: 14px; font-weight: 700; color: #1e3a5f; margin-bottom: 10px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; }
        .agent-row { font-size: 12px; margin-bottom: 4px; }
        .agent-label { font-weight: 600; color: #4b5563; display: inline-block; width: 90px; }
        hr { margin: 8px 0; border-color: #e2e8f0; }
        .comments-box { background: #fefce8; padding: 12px; border-radius: 6px; font-size: 12px; white-space: pre-wrap; }
        @media print { body { background: white; padding: 0; } .report-container { padding: 15px; box-shadow: none; } .no-print { display: none; } }
        button { background: #1e3a5f; color: white; border: none; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-size: 14px; margin-bottom: 20px; }
        button:hover { background: #0f2b3d; }
    </style>
</head>
<body>
<div class="report-container">
    <div class="no-print" style="text-align: right; margin-bottom: 10px;">
        <button onclick="window.print();">🖨️ Print / Save as PDF</button>
    </div>

    <div class="report-header">
        <div class="logo">LARSON & COMPANY</div>
        <div class="logo-sub">Real Estate | Mortgage | Title</div>
        <h1>Key Players Summary</h1>
        <div class="company-contact">pros@larsonandcompany.com | www.larsonandcompany.com</div>
    </div>

    <!-- Property Information -->
    <div class="section">
        <div class="section-title">PROPERTY INFORMATION</div>
        <div class="two-columns">
            <div class="col">
                <div class="info-row"><span class="info-label">Address:</span><span class="info-value"><?= htmlspecialchars($listing['address1'] ?? '') ?><?= !empty($listing['address2']) ? ', ' . htmlspecialchars($listing['address2']) : '' ?></span></div>
                <div class="info-row"><span class="info-label">City, State Zip:</span><span class="info-value"><?= htmlspecialchars($listing['city'] ?? '') ?>, <?= htmlspecialchars($listing['state'] ?? '') ?> <?= htmlspecialchars($listing['zip'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Transaction #:</span><span class="info-value"><?= htmlspecialchars($listing['transaction_number'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">MLS #:</span><span class="info-value"><?= htmlspecialchars($listing['mls_number'] ?? '') ?></span></div>
            </div>
            <div class="col">
                <div class="info-row"><span class="info-label">Property Type:</span><span class="info-value"><?= htmlspecialchars($listing['property_type'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">UC Price:</span><span class="info-value"><?= formatMoney($listing['uc_price']) ?></span></div>
                <div class="info-row"><span class="info-label">Financing:</span><span class="info-value"><?= htmlspecialchars($listing['financing_type'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Earnest Money:</span><span class="info-value"><?= formatMoney($listing['earnest_money_amount']) ?></span></div>
                <div class="info-row"><span class="info-label">Deposit With:</span><span class="info-value"><?= htmlspecialchars($listing['earnest_money_deposit_with'] ?? '') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Deadlines -->
    <div class="section">
        <div class="section-title">DEADLINES</div>
        <div class="two-columns">
            <div class="col">
                <div class="info-row"><span class="info-label">Date of Contract:</span><span class="info-value"><?= formatDate($listing['contract_date']) ?></span></div>
                <div class="info-row"><span class="info-label">Seller Disclosure:</span><span class="info-value"><?= formatDate($listing['sellers_disclosure_date']) ?></span></div>
                <div class="info-row"><span class="info-label">Due Diligence:</span><span class="info-value"><?= formatDate($listing['due_diligence_deadline']) ?></span></div>
            </div>
            <div class="col">
                <div class="info-row"><span class="info-label">Financing & Appraisal:</span><span class="info-value"><?= formatDate($listing['funding_and_appraisal']) ?></span></div>
                <div class="info-row"><span class="info-label" style="background:#fef9e6; font-weight:bold;">SETTLEMENT:</span><span class="info-value" style="background:#fef9e6; padding:2px 6px; border-radius:4px;"><?= formatDate($listing['settlement_deadline_date']) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Buyer & Seller Information -->
    <div class="section">
        <div class="section-title">BUYER AND SELLER INFORMATION</div>
        <div class="two-columns">
            <div class="col">
                <div class="info-row"><span class="info-label">Buyer Name:</span><span class="info-value"><?= htmlspecialchars($listing['buyer_name'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Home Phone:</span><span class="info-value"><?= htmlspecialchars($listing['buyer_home_phone'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Cell Phone:</span><span class="info-value"><?= htmlspecialchars($listing['buyer_cell_phone'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Fax:</span><span class="info-value"><?= htmlspecialchars($listing['buyer_fax'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Email:</span><span class="info-value"><?= htmlspecialchars($listing['buyer_email'] ?? '') ?></span></div>
            </div>
            <div class="col">
                <div class="info-row"><span class="info-label">Seller Name:</span><span class="info-value"><?= htmlspecialchars($listing['seller_name'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Home Phone:</span><span class="info-value"><?= htmlspecialchars($listing['seller_home_phone'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Cell Phone:</span><span class="info-value"><?= htmlspecialchars($listing['seller_cell_phone'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Fax:</span><span class="info-value"><?= htmlspecialchars($listing['seller_fax'] ?? '') ?></span></div>
                <div class="info-row"><span class="info-label">Email:</span><span class="info-value"><?= htmlspecialchars($listing['seller_email'] ?? '') ?></span></div>
            </div>
        </div>
    </div>

    <!-- Key Players (Agent Cards) -->
    <div class="section">
        <div class="section-title">KEY PLAYERS</div>
        <div class="agent-grid">
            <?php if (!empty($listing['LO_Name'])): ?>
            <div class="agent-card"><h3>LOAN OFFICER</h3>
                <div class="agent-row"><span class="agent-label">Name:</span> <?= htmlspecialchars($listing['LO_Name']) ?></div>
                <div class="agent-row"><span class="agent-label">Company:</span> <?= htmlspecialchars($listing['LO_Company']) ?></div>
                <div class="agent-row"><span class="agent-label">Office:</span> <?= htmlspecialchars($listing['LO_OfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Cell:</span> <?= htmlspecialchars($listing['LO_CellPhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Fax:</span> <?= htmlspecialchars($listing['LO_Fax']) ?></div>
                <div class="agent-row"><span class="agent-label">Email:</span> <?= htmlspecialchars($listing['LO_Email']) ?></div>
                <?php if (!empty($listing['LO_AsstName'])): ?>
                <hr><div class="agent-row"><span class="agent-label">Assistant:</span> <?= htmlspecialchars($listing['LO_AsstName']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Phone:</span> <?= htmlspecialchars($listing['LO_AsstOfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Fax:</span> <?= htmlspecialchars($listing['LO_AsstFax']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Email:</span> <?= htmlspecialchars($listing['LO_AsstEmail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($listing['BEO_Name'])): ?>
            <div class="agent-card"><h3>BUYER ESCROW OFFICER</h3>
                <div class="agent-row"><span class="agent-label">Name:</span> <?= htmlspecialchars($listing['BEO_Name']) ?></div>
                <div class="agent-row"><span class="agent-label">Company:</span> <?= htmlspecialchars($listing['BEO_Company']) ?></div>
                <div class="agent-row"><span class="agent-label">Office:</span> <?= htmlspecialchars($listing['BEO_OfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Cell:</span> <?= htmlspecialchars($listing['BEO_CellPhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Fax:</span> <?= htmlspecialchars($listing['BEO_Fax']) ?></div>
                <div class="agent-row"><span class="agent-label">Email:</span> <?= htmlspecialchars($listing['BEO_Email']) ?></div>
                <?php if (!empty($listing['BEO_AsstName'])): ?>
                <hr><div class="agent-row"><span class="agent-label">Assistant:</span> <?= htmlspecialchars($listing['BEO_AsstName']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Phone:</span> <?= htmlspecialchars($listing['BEO_AsstOfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Fax:</span> <?= htmlspecialchars($listing['BEO_AsstFax']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Email:</span> <?= htmlspecialchars($listing['BEO_AsstEmail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($listing['SEO_Name'])): ?>
            <div class="agent-card"><h3>SELLER ESCROW OFFICER</h3>
                <div class="agent-row"><span class="agent-label">Name:</span> <?= htmlspecialchars($listing['SEO_Name']) ?></div>
                <div class="agent-row"><span class="agent-label">Company:</span> <?= htmlspecialchars($listing['SEO_Company']) ?></div>
                <div class="agent-row"><span class="agent-label">Office:</span> <?= htmlspecialchars($listing['SEO_OfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Cell:</span> <?= htmlspecialchars($listing['SEO_CellPhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Fax:</span> <?= htmlspecialchars($listing['SEO_Fax']) ?></div>
                <div class="agent-row"><span class="agent-label">Email:</span> <?= htmlspecialchars($listing['SEO_Email']) ?></div>
                <?php if (!empty($listing['SEO_AsstName'])): ?>
                <hr><div class="agent-row"><span class="agent-label">Assistant:</span> <?= htmlspecialchars($listing['SEO_AsstName']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Phone:</span> <?= htmlspecialchars($listing['SEO_AsstOfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Fax:</span> <?= htmlspecialchars($listing['SEO_AsstFax']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Email:</span> <?= htmlspecialchars($listing['SEO_AsstEmail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($listing['LA_Name'])): ?>
            <div class="agent-card"><h3>LISTING AGENT</h3>
                <div class="agent-row"><span class="agent-label">Name:</span> <?= htmlspecialchars($listing['LA_Name']) ?></div>
                <div class="agent-row"><span class="agent-label">Company:</span> <?= htmlspecialchars($listing['LA_Company']) ?></div>
                <div class="agent-row"><span class="agent-label">Office:</span> <?= htmlspecialchars($listing['LA_OfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Cell:</span> <?= htmlspecialchars($listing['LA_CellPhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Fax:</span> <?= htmlspecialchars($listing['LA_Fax']) ?></div>
                <div class="agent-row"><span class="agent-label">Email:</span> <?= htmlspecialchars($listing['LA_Email']) ?></div>
                <?php if (!empty($listing['LA_AsstName'])): ?>
                <hr><div class="agent-row"><span class="agent-label">Assistant:</span> <?= htmlspecialchars($listing['LA_AsstName']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Phone:</span> <?= htmlspecialchars($listing['LA_AsstOfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Fax:</span> <?= htmlspecialchars($listing['LA_AsstFax']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Email:</span> <?= htmlspecialchars($listing['LA_AsstEmail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($listing['SA_Name'])): ?>
            <div class="agent-card"><h3>SELLING AGENT</h3>
                <div class="agent-row"><span class="agent-label">Name:</span> <?= htmlspecialchars($listing['SA_Name']) ?></div>
                <div class="agent-row"><span class="agent-label">Company:</span> <?= htmlspecialchars($listing['SA_Company']) ?></div>
                <div class="agent-row"><span class="agent-label">Office:</span> <?= htmlspecialchars($listing['SA_OfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Cell:</span> <?= htmlspecialchars($listing['SA_CellPhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Fax:</span> <?= htmlspecialchars($listing['SA_Fax']) ?></div>
                <div class="agent-row"><span class="agent-label">Email:</span> <?= htmlspecialchars($listing['SA_Email']) ?></div>
                <?php if (!empty($listing['SA_AsstName'])): ?>
                <hr><div class="agent-row"><span class="agent-label">Assistant:</span> <?= htmlspecialchars($listing['SA_AsstName']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Phone:</span> <?= htmlspecialchars($listing['SA_AsstOfficePhone']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Fax:</span> <?= htmlspecialchars($listing['SA_AsstFax']) ?></div>
                <div class="agent-row"><span class="agent-label">Asst Email:</span> <?= htmlspecialchars($listing['SA_AsstEmail']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Comments -->
    <?php if (!empty($listing['comments'])): ?>
    <div class="section">
        <div class="section-title">COMMENTS</div>
        <div class="comments-box"><?= nl2br(htmlspecialchars($listing['comments'])) ?></div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>