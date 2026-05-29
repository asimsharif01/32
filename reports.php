<?php
require_once 'db.php';
session_start();

// Get report parameters from GET (or POST)
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-01');
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-t');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$agent_id = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;

// Helper to get agent name (for filter)
$agent_name = '';
if ($agent_id > 0) {
    $agent_res = mysqli_query($conn, "SELECT name FROM agents WHERE id = $agent_id");
    if ($row = mysqli_fetch_assoc($agent_res)) {
        $agent_name = $row['name'];
    }
}

// Helper to build WHERE clauses for agent filter (based on LA_Name/SA_Name and split logic)
function agentFilter($conn, $agent_name, $table_alias = 'l') {
    if (empty($agent_name)) return '1=1';
    return "(
        ($table_alias.LA_Name = '$agent_name' AND $table_alias.LA_ForReport = 1) OR 
        ($table_alias.SA_Name = '$agent_name' AND $table_alias.SA_ForReport = 1) OR 
        $table_alias.split_with = '$agent_name'
    )";
}

// Helper to calculate adjusted count/volume for splits
// split_with not empty -> half; otherwise full.
// We'll use CASE in SQL.

// ----------------------------
// 1. SALES SUMMARY
// ----------------------------
// 1a. LISTED section
$listed_sql = "
    SELECT 
        l.mls_number,
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        seller_contact.name AS seller_name,
        l.address1 AS address,
        l.city,
        l.purchase_price AS price,
        l.uc_price,
        l.date_of_listing AS dol,
        l.date_of_expiration AS doe,
        s.description AS status
    FROM listings l
    LEFT JOIN transaction_roles tr_seller ON l.id = tr_seller.listing_id AND tr_seller.role_type = 'Seller'
    LEFT JOIN contacts seller_contact ON tr_seller.contact_id = seller_contact.id
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE s.description = 'Listed'
        AND l.date_of_listing BETWEEN '$from_date' AND '$to_date'
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    ORDER BY l.date_of_listing
";
$listed_result = mysqli_query($conn, $listed_sql);

// 1b. CLOSED section
$closed_sql = "
    SELECT 
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        buyer_contact.name AS buyer_name,
        seller_contact.name AS seller_name,
        l.address1,
        l.city,
        l.final_price,
        l.closing_date,
        DATEDIFF(l.closing_date, l.date_of_listing) AS days_on_market,
        IFNULL(l.final_price / NULLIF(l.uc_price, 0) * 100, 0) AS pp_uc_pct,
        l.lead_source
    FROM listings l
    LEFT JOIN transaction_roles tr_buyer ON l.id = tr_buyer.listing_id AND tr_buyer.role_type = 'Buyer'
    LEFT JOIN contacts buyer_contact ON tr_buyer.contact_id = buyer_contact.id
    LEFT JOIN transaction_roles tr_seller ON l.id = tr_seller.listing_id AND tr_seller.role_type = 'Seller'
    LEFT JOIN contacts seller_contact ON tr_seller.contact_id = seller_contact.id
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE s.description = 'Closed'
        AND l.closing_date BETWEEN '$from_date' AND '$to_date'
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    ORDER BY l.closing_date
";
$closed_result = mysqli_query($conn, $closed_sql);

// 1c. UNDER CONTRACT section (use contract_date from milestones)
$uc_sql = "
    SELECT 
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        buyer_contact.name AS buyer_name,
        seller_contact.name AS seller_name,
        l.address1,
        l.city,
        l.purchase_price AS price,
        lm.due_date AS contract_date,
        'Under Contract' AS status
    FROM listings l
    LEFT JOIN transaction_roles tr_buyer ON l.id = tr_buyer.listing_id AND tr_buyer.role_type = 'Buyer'
    LEFT JOIN contacts buyer_contact ON tr_buyer.contact_id = buyer_contact.id
    LEFT JOIN transaction_roles tr_seller ON l.id = tr_seller.listing_id AND tr_seller.role_type = 'Seller'
    LEFT JOIN contacts seller_contact ON tr_seller.contact_id = seller_contact.id
    LEFT JOIN listing_milestones lm ON l.id = lm.listing_id AND lm.milestone_type = 'Date of Contract'
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE s.description = 'Under Contract'
        AND lm.due_date BETWEEN '$from_date' AND '$to_date'
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    ORDER BY lm.due_date
";
$uc_result = mysqli_query($conn, $uc_sql);

// 1d. Header totals (Volume and Commission) – simplified: Volume = SUM(final_price) with split adjustment
$totals_sql = "
    SELECT 
        SUM(CASE WHEN l.split_with IS NOT NULL AND l.split_with != '' THEN l.final_price * 0.5 ELSE l.final_price END) AS total_volume,
        SUM(
            ( (l.commission_price * l.commission_pct / 100) + l.commission_other + l.transaction_fee + l.errors_omissions ) 
            * l.agent_split / 100 + l.processing_fee + l.other2
        ) AS commission_paid
    FROM listings l
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Closed')
        AND l.closing_date BETWEEN '$from_date' AND '$to_date'
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
";
$totals_result = mysqli_query($conn, $totals_sql);
$totals = mysqli_fetch_assoc($totals_result);
$total_volume = number_format($totals['total_volume'] ?? 0, 2);
$commission_paid = number_format($totals['commission_paid'] ?? 0, 2);

// ----------------------------
// 2. PROGRESS REPORT (monthly counts + YTD)
// ----------------------------
// Months array for display
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];

// 2a. Under Contract per month (using contract_date)
$uc_monthly = [];
$uc_res = mysqli_query($conn, "
    SELECT MONTH(lm.due_date) AS month_num, 
           COUNT(*) AS cnt
    FROM listings l
    LEFT JOIN listing_milestones lm ON l.id = lm.listing_id AND lm.milestone_type = 'Date of Contract'
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Under Contract')
        AND YEAR(lm.due_date) = $year
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    GROUP BY MONTH(lm.due_date)
");
while($row = mysqli_fetch_assoc($uc_res)) {
    $uc_monthly[$row['month_num']] = $row['cnt'];
}

// 2b. Closed per month (using closing_date)
$closed_monthly = [];
$cl_res = mysqli_query($conn, "
    SELECT MONTH(l.closing_date) AS month_num, 
           COUNT(*) AS cnt
    FROM listings l
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Closed')
        AND YEAR(l.closing_date) = $year
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    GROUP BY MONTH(l.closing_date)
");
while($row = mysqli_fetch_assoc($cl_res)) {
    $closed_monthly[$row['month_num']] = $row['cnt'];
}

// 2c. Listed per month (using date_of_listing)
$listed_monthly = [];
$list_res = mysqli_query($conn, "
    SELECT MONTH(l.date_of_listing) AS month_num, 
           COUNT(*) AS cnt
    FROM listings l
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Listed')
        AND YEAR(l.date_of_listing) = $year
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    GROUP BY MONTH(l.date_of_listing)
");
while($row = mysqli_fetch_assoc($list_res)) {
    $listed_monthly[$row['month_num']] = $row['cnt'];
}

// 2d. YTD Closed, Listed, Volume, Commission (for selected agent or company)
$ytd_sql = "
    SELECT 
        SUM(CASE WHEN l.split_with IS NOT NULL AND l.split_with != '' THEN l.final_price * 0.5 ELSE l.final_price END) AS ytd_volume,
        COUNT(CASE WHEN l.split_with IS NOT NULL AND l.split_with != '' THEN 0.5 ELSE 1 END) AS ytd_closed,
        COUNT(CASE WHEN l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Listed') AND YEAR(l.date_of_listing) = $year THEN 1 END) AS ytd_listed,
        COUNT(CASE WHEN l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Rescinded') AND YEAR(l.date_of_listing) = $year THEN 1 END) AS ytd_rescinded
    FROM listings l
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Closed')
        AND YEAR(l.closing_date) = $year
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
";
$ytd_res = mysqli_query($conn, $ytd_sql);
$ytd = mysqli_fetch_assoc($ytd_res);
$ytd_closed = number_format($ytd['ytd_closed'] ?? 0, 0);
$ytd_listed = number_format($ytd['ytd_listed'] ?? 0, 0);
$ytd_rescinded = number_format($ytd['ytd_rescinded'] ?? 0, 0);
$ytd_volume = number_format($ytd['ytd_volume'] ?? 0, 2);
// For YTD Commission rank – simplified: just show commission sum (no rank in this version)
$rank_sql = "
    SELECT 
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent_name,
        SUM(
            ( (l.commission_price * l.commission_pct / 100) + l.commission_other + l.transaction_fee + l.errors_omissions ) 
            * l.agent_split / 100 + l.processing_fee + l.other2
        ) AS total_comm
    FROM listings l
    WHERE l.status_id = (SELECT id FROM sales_statuses WHERE description = 'Closed')
        AND YEAR(l.closing_date) = $year
    GROUP BY agent_name
    ORDER BY total_comm DESC
";
$rank_res = mysqli_query($conn, $rank_sql);
$rank_position = 1;
$agent_commission = 0;
while($r = mysqli_fetch_assoc($rank_res)) {
    if($r['agent_name'] == $agent_name || empty($agent_name)) {
        $agent_commission = number_format($r['total_comm'] ?? 0, 2);
        break;
    }
    $rank_position++;
}
$rank_display = $rank_position . (($rank_position == 1) ? 'st' : (($rank_position == 2) ? 'nd' : (($rank_position == 3) ? 'rd' : 'th')));

// ----------------------------
// 3. LISTINGS REPORT (grouped by month and status)
// ----------------------------
$listings_sql = "
    SELECT 
        CASE 
            WHEN s.description = 'Closed' THEN l.closing_date
            WHEN s.description = 'Under Contract' THEN lm.due_date
            ELSE l.date_of_listing
        END AS key_date,
        MONTH(
            CASE 
                WHEN s.description = 'Closed' THEN l.closing_date
                WHEN s.description = 'Under Contract' THEN lm.due_date
                ELSE l.date_of_listing
            END
        ) AS month_num,
        YEAR(
            CASE 
                WHEN s.description = 'Closed' THEN l.closing_date
                WHEN s.description = 'Under Contract' THEN lm.due_date
                ELSE l.date_of_listing
            END
        ) AS year_num,
        s.description AS status,
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        l.mls_number,
        l.purchase_price AS price,
        seller_contact.name AS seller_name,
        l.address1,
        l.city
    FROM listings l
    LEFT JOIN listing_milestones lm ON l.id = lm.listing_id AND lm.milestone_type = 'Date of Contract'
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    LEFT JOIN transaction_roles tr_seller ON l.id = tr_seller.listing_id AND tr_seller.role_type = 'Seller'
    LEFT JOIN contacts seller_contact ON tr_seller.contact_id = seller_contact.id
    WHERE s.description IN ('Closed', 'Under Contract', 'Listed')
        AND (
            (s.description = 'Closed' AND l.closing_date BETWEEN '$from_date' AND '$to_date')
            OR (s.description = 'Under Contract' AND lm.due_date BETWEEN '$from_date' AND '$to_date')
            OR (s.description = 'Listed' AND l.date_of_listing BETWEEN '$from_date' AND '$to_date')
        )
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    ORDER BY key_date
";
$listings_result = mysqli_query($conn, $listings_sql);

// Group listings by month & status for display
$grouped_listings = [];
while($row = mysqli_fetch_assoc($listings_result)) {
    $month_key = $months[$row['month_num']-1] . ' ' . $row['year_num'];
    $grouped_listings[$month_key][$row['status']][] = $row;
}

// ----------------------------
// 4. AGENT SALES SUMMARY
// ----------------------------
$agent_summary_sql = "
    SELECT 
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        SUM(CASE WHEN l.split_with IS NOT NULL AND l.split_with != '' THEN l.final_price * 0.5 ELSE l.final_price END) AS total_volume,
        SUM(
            ( (l.commission_price * l.commission_pct / 100) + l.commission_other + l.transaction_fee + l.errors_omissions ) 
            * l.agent_split / 100 + l.processing_fee + l.other2
        ) AS commission_paid
    FROM listings l
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE s.description = 'Closed'
        AND l.closing_date BETWEEN '$from_date' AND '$to_date'
        AND (" . agentFilter($conn, $agent_name, 'l') . ")
    GROUP BY agent
    ORDER BY agent
";
$agent_summary_result = mysqli_query($conn, $agent_summary_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Larson & Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .report-section { margin-bottom: 40px; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: white; }
        .report-title { font-size: 1.3rem; font-weight: bold; margin-bottom: 15px; color: #1e3a5f; border-left: 4px solid #1e3a5f; padding-left: 10px; }
        .sub-section { margin-top: 20px; }
        .sub-title { font-size: 1.1rem; font-weight: bold; margin-bottom: 10px; background: #eef2ff; padding: 5px 10px; }
        table { font-size: 0.85rem; }
        th { background: #f8f9fc; }
        .total-row { font-weight: bold; background: #f1f3f5; }
        .progress-grid { display: flex; gap: 20px; flex-wrap: wrap; }
        .progress-panel { flex: 1; min-width: 200px; border: 1px solid #dee2e6; padding: 10px; border-radius: 6px; }
        .progress-panel h5 { background: #e9ecef; padding: 5px; margin-top: 0; }
        .ytd-box { background: #f8f9fa; padding: 10px; margin-top: 15px; border-radius: 6px; }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles">
        <h5 class="bc-title">Reports Menu</h5>
    </div>
    <div class="container-fluid">
        <!-- Parameter Form -->
        <div class="card p-3 mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="from_date" class="form-control" value="<?= $from_date ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="to_date" class="form-control" value="<?= $to_date ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <input type="number" name="year" class="form-control" value="<?= $year ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Agent</label>
                    <select name="agent_id" class="form-select">
                        <option value="0">-- All Agents --</option>
                        <?php
                        $agent_list = mysqli_query($conn, "SELECT id, name FROM agents WHERE include_in_reports = 1 ORDER BY name");
                        while($ag = mysqli_fetch_assoc($agent_list)) {
                            $selected = ($agent_id == $ag['id']) ? 'selected' : '';
                            echo "<option value='{$ag['id']}' $selected>" . htmlspecialchars($ag['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Report: Sales Summary -->
        <div class="report-section">
            <div class="report-title">Sales Summary</div>
            <p class="text-muted">Between: <?= date('m/d/Y', strtotime($from_date)) ?> and <?= date('m/d/Y', strtotime($to_date)) ?></p>
            <div class="row mb-3">
                <div class="col-md-6">
                    <strong>Total Volume:</strong> $<?= $total_volume ?><br>
                    <strong>Commission Paid:</strong> $<?= $commission_paid ?>
                </div>
            </div>

            <!-- LISTED -->
            <div class="sub-section">
                <div class="sub-title">LISTED (<?= mysqli_num_rows($listed_result) ?>)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>#</th><th>MLS #</th><th>Listing Agent</th><th>Seller Name</th><th>Address</th><th>City</th><th>Price</th><th>UC Price</th><th>D.O.L</th><th>D.O.E</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php $i=1; while($row = mysqli_fetch_assoc($listed_result)): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['mls_number']) ?></td>
                                <td><?= htmlspecialchars($row['agent']) ?></td>
                                <td><?= htmlspecialchars($row['seller_name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td>$<?= number_format($row['price'],2) ?></td>
                                <td>$<?= number_format($row['uc_price'],2) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['dol'])) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['doe'])) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($listed_result)==0): ?><tr><td colspan="11">No records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- CLOSED -->
            <div class="sub-section">
                <div class="sub-title">CLOSED (<?= mysqli_num_rows($closed_result) ?>)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>#</th><th>Agent</th><th>Buyer Name</th><th>Seller Name</th><th>Address</th><th>City</th><th>Final Price</th><th>Close</th><th>Days On Market</th><th>PP/UC Pct</th><th>Lead Source</th></tr></thead>
                        <tbody>
                            <?php $i=1; while($row = mysqli_fetch_assoc($closed_result)): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['agent']) ?></td>
                                <td><?= htmlspecialchars($row['buyer_name']) ?></td>
                                <td><?= htmlspecialchars($row['seller_name']) ?></td>
                                <td><?= htmlspecialchars($row['address1']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td>$<?= number_format($row['final_price'],2) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['closing_date'])) ?></td>
                                <td><?= $row['days_on_market'] ?></td>
                                <td><?= number_format($row['pp_uc_pct'],2) ?></td>
                                <td><?= htmlspecialchars($row['lead_source']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($closed_result)==0): ?><tr><td colspan="11">No records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- UNDER CONTRACT -->
            <div class="sub-section">
                <div class="sub-title">UNDER CONTRACT (<?= mysqli_num_rows($uc_result) ?>)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>#</th><th>Agent</th><th>Buyer Name</th><th>Seller Name</th><th>Address</th><th>City</th><th>Price</th><th>Contract Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php $i=1; while($row = mysqli_fetch_assoc($uc_result)): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['agent']) ?></td>
                                <td><?= htmlspecialchars($row['buyer_name']) ?></td>
                                <td><?= htmlspecialchars($row['seller_name']) ?></td>
                                <td><?= htmlspecialchars($row['address1']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td>$<?= number_format($row['price'],2) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['contract_date'])) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if(mysqli_num_rows($uc_result)==0): ?><tr><td colspan="9">No records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Report: Progress Report -->
        <div class="report-section">
            <div class="report-title">Progress Report</div>
            <p class="text-muted">Between: <?= date('m/d/Y', strtotime($from_date)) ?> and <?= date('m/d/Y', strtotime($to_date)) ?></p>
            <div class="progress-grid">
                <div class="progress-panel">
                    <h5>Under Contract</h5>
                    <?php foreach($months as $idx => $m): $month_num = $idx+1; $count = $uc_monthly[$month_num] ?? 0; ?>
                        <?php if($count > 0): ?>
                            <div><strong><?= $m ?></strong></div>
                            <div>- <?= htmlspecialchars($agent_name ?: 'Team Larson') ?>: <?= $count ?></div>
                            <div><strong>Total: <?= $count ?></strong></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="progress-panel">
                    <h5>Closed</h5>
                    <?php foreach($months as $idx => $m): $month_num = $idx+1; $count = $closed_monthly[$month_num] ?? 0; ?>
                        <?php if($count > 0): ?>
                            <div><strong><?= $m ?></strong></div>
                            <div>- <?= htmlspecialchars($agent_name ?: 'Team Larson') ?>: <?= $count ?></div>
                            <div><strong>Total: <?= $count ?></strong></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="progress-panel">
                    <h5>Listed</h5>
                    <?php foreach($months as $idx => $m): $month_num = $idx+1; $count = $listed_monthly[$month_num] ?? 0; ?>
                        <?php if($count > 0): ?>
                            <div><strong><?= $m ?></strong></div>
                            <div>- <?= htmlspecialchars($agent_name ?: 'Team Larson') ?>: <?= $count ?></div>
                            <div><strong>Total: <?= $count ?></strong></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ytd-box">
                <strong>YEAR TO DATE (<?= $year ?>)</strong><br>
                Closed: <?= $ytd_closed ?> &nbsp;|&nbsp;
                Listed: <?= $ytd_listed ?> &nbsp;|&nbsp;
                Rescinded: <?= $ytd_rescinded ?> &nbsp;|&nbsp;
                Volume: $<?= $ytd_volume ?> &nbsp;|&nbsp;
                Commission: $<?= $agent_commission ?> &nbsp;|&nbsp;
                Rank: <?= $rank_display ?>
            </div>
        </div>

        <!-- Report: Listings Report (Company / Agent) -->
        <div class="report-section">
            <div class="report-title">Listings Report</div>
            <p class="text-muted">Between: <?= date('m/d/Y', strtotime($from_date)) ?> and <?= date('m/d/Y', strtotime($to_date)) ?>. For: <?= $agent_name ?: 'Larson & Company' ?></p>
            <?php foreach($grouped_listings as $month => $status_groups): ?>
                <div class="sub-section">
                    <div class="sub-title"><?= $month ?> (<?= array_sum(array_map('count', $status_groups)) ?>)</div>
                    <?php foreach($status_groups as $status => $rows): ?>
                        <div class="ms-3 mt-2"><strong><?= $status ?></strong></div>
                        <table class="table table-sm table-bordered">
                            <thead><tr><th>#</th><th>Listing Agent</th><th>MLS #</th><th>Price</th><th>Seller Name</th><th>Address</th><th>City</th></tr></thead>
                            <tbody>
                                <?php $i=1; foreach($rows as $row): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($row['agent']) ?></td>
                                    <td><?= htmlspecialchars($row['mls_number']) ?></td>
                                    <td>$<?= number_format($row['price'],2) ?></td>
                                    <td><?= htmlspecialchars($row['seller_name']) ?></td>
                                    <td><?= htmlspecialchars($row['address1']) ?></td>
                                    <td><?= htmlspecialchars($row['city']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <?php if(empty($grouped_listings)): ?><p>No listings found for the selected criteria.</p><?php endif; ?>
        </div>

        <!-- Report: Agent Sales Summary -->
        <div class="report-section">
            <div class="report-title">Agent Sales Summary</div>
            <p class="text-muted">Between: <?= date('m/d/Y', strtotime($from_date)) ?> and <?= date('m/d/Y', strtotime($to_date)) ?></p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Agent</th><th>Total Volume</th><th>Commission Paid</th></tr></thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($agent_summary_result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['agent']) ?></td>
                            <td>$<?= number_format($row['total_volume'],2) ?></td>
                            <td>$<?= number_format($row['commission_paid'],2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($agent_summary_result)==0): ?><tr><td colspan="3">No closed transactions found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>
</body>
</html>