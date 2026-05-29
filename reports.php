<?php
// reports.php — All reports matching Access: Company/Progress/Listings/Summary
require_once 'db.php';
session_start();

// ── Parameters ────────────────────────────────────────────────────────────
$from_date  = $_GET['from_date'] ?? date('Y-01-01');
$to_date    = $_GET['to_date']   ?? date('Y-12-31');
$year       = intval($_GET['year'] ?? date('Y'));
$agent_id   = intval($_GET['agent_id'] ?? 0);
$active_tab = $_GET['tab'] ?? 'company';   // company | progress | listings | summary

// Resolve agent name from id
$agent_name = '';
if ($agent_id > 0) {
    $ar = mysqli_query($conn, "SELECT name FROM agents WHERE id = $agent_id LIMIT 1");
    if ($row = mysqli_fetch_assoc($ar)) $agent_name = $row['name'];
}

$from_esc = mysqli_real_escape_string($conn, $from_date);
$to_esc   = mysqli_real_escape_string($conn, $to_date);
$an_esc   = mysqli_real_escape_string($conn, $agent_name);

// ── Agent filter helper ───────────────────────────────────────────────────
// Matches Access logic:
//   - LA_ForReport=1 and LA_Name = agent  (listing agent side)
//   - SA_ForReport=1 and SA_Name = agent  (selling agent side)
//   - split_with = agent
// When no agent selected → company-wide (no name filter, but still LA/SA_ForReport)
function agentWhere($an_esc, $alias = 'l') {
    if ($an_esc === '') {
        // Company-wide: include any record where LA or SA is flagged for report
        return "($alias.LA_ForReport = 1 OR $alias.SA_ForReport = 1)";
    }
    return "(
        ($alias.LA_Name = '$an_esc' AND $alias.LA_ForReport = 1)
        OR ($alias.SA_Name = '$an_esc' AND $alias.SA_ForReport = 1)
        OR ($alias.split_with = '$an_esc')
    )";
}

// ── Effective agent label for display ────────────────────────────────────
$agent_label = $agent_name ?: 'Larson & Company';

// ── Resolved status IDs (avoids repeated subqueries) ─────────────────────
function getStatusId($conn, $desc) {
    $r = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description = '" .
        mysqli_real_escape_string($conn, $desc) . "' LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    return $row ? intval($row['id']) : 0;
}
$sid_listed   = getStatusId($conn, 'Listed');
$sid_closed   = getStatusId($conn, 'Closed');
$sid_uc       = getStatusId($conn, 'Under Contract');
$sid_rescind  = getStatusId($conn, 'Rescinded');

$aw = agentWhere($an_esc, 'l');

// ══════════════════════════════════════════════════════════════════════════
// TAB: COMPANY / AGENT — Sales Summary  (rptSalesSummary / rptCompanySummary)
// ══════════════════════════════════════════════════════════════════════════

// Header totals
$totals_res = mysqli_query($conn, "
    SELECT
        SUM(CASE WHEN l.split_with <> '' AND l.split_with IS NOT NULL
                 THEN l.final_price * 0.5 ELSE l.final_price END)      AS total_volume,
        SUM(
            ((l.commission_price * l.commission_pct / 100)
              + l.commission_other
              + l.transaction_fee
              + l.errors_omissions)
            * l.agent_split / 100
            + l.processing_fee
            + l.other2
        )                                                               AS commission_paid
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND l.closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
");
$totals = mysqli_fetch_assoc($totals_res);
$total_volume    = $totals['total_volume']    ?? 0;
$commission_paid = $totals['commission_paid'] ?? 0;

// LISTED section — filter by date_of_listing, status = Listed
$listed_res = mysqli_query($conn, "
    SELECT
        l.mls_number,
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        l.seller_name,
        l.address1,
        l.city,
        l.purchase_price                                                AS price,
        l.uc_price,
        l.date_of_listing                                               AS dol,
        l.date_of_expiration                                            AS doe,
        s.description                                                   AS status
    FROM listings l
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE l.status_id = $sid_listed
      AND l.date_of_listing BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
    ORDER BY l.date_of_listing
");

// CLOSED section — filter by closing_date
$closed_res = mysqli_query($conn, "
    SELECT
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        l.buyer_name,
        l.seller_name,
        l.address1,
        l.city,
        l.final_price,
        l.closing_date,
        DATEDIFF(l.contract_date, l.date_of_listing)                   AS days_on_market,
        CASE WHEN l.uc_price > 0
             THEN ROUND(l.final_price / l.uc_price * 100, 2)
             ELSE 0 END                                                 AS pp_uc_pct,
        l.lead_source
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND l.closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
    ORDER BY l.closing_date
");

// CLOSED averages for footer row
$avg_res = mysqli_query($conn, "
    SELECT
        AVG(l.final_price)                                                   AS avg_price,
        AVG(DATEDIFF(l.contract_date, l.date_of_listing))                    AS avg_dom,
        AVG(CASE WHEN l.uc_price > 0
                 THEN l.final_price / l.uc_price * 100 ELSE NULL END)        AS avg_pp_uc
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND l.closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
");
$avgs = mysqli_fetch_assoc($avg_res);

// UNDER CONTRACT section — filter by contract_date (stored in listings.contract_date)
$uc_res = mysqli_query($conn, "
    SELECT
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        l.buyer_name,
        l.seller_name,
        l.address1,
        l.city,
        l.purchase_price                                                AS price,
        l.contract_date,
        s.description                                                   AS status
    FROM listings l
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE l.status_id = $sid_uc
      AND l.contract_date BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
    ORDER BY l.contract_date
");

// ══════════════════════════════════════════════════════════════════════════
// TAB: PROGRESS REPORT  (rptProgressSummary)
// ══════════════════════════════════════════════════════════════════════════

$months_names = ['January','February','March','April','May','June',
                 'July','August','September','October','November','December'];

// Under Contract per month (by contract_date year)
$uc_monthly = [];
$r = mysqli_query($conn, "
    SELECT MONTH(l.contract_date) AS mn, COUNT(*) AS cnt
    FROM listings l
    WHERE l.status_id = $sid_uc
      AND YEAR(l.contract_date) = $year
      AND $aw
    GROUP BY MONTH(l.contract_date)
    ORDER BY mn
");
while ($row = mysqli_fetch_assoc($r)) $uc_monthly[$row['mn']] = $row['cnt'];

// Closed per month (by closing_date year)
$closed_monthly = [];
$r = mysqli_query($conn, "
    SELECT MONTH(l.closing_date) AS mn, COUNT(*) AS cnt
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND YEAR(l.closing_date) = $year
      AND $aw
    GROUP BY MONTH(l.closing_date)
    ORDER BY mn
");
while ($row = mysqli_fetch_assoc($r)) $closed_monthly[$row['mn']] = $row['cnt'];

// Listed per month (by date_of_listing year) + rescinded count
$listed_monthly    = [];
$rescinded_monthly = [];
$r = mysqli_query($conn, "
    SELECT
        MONTH(l.date_of_listing)  AS mn,
        SUM(l.status_id = $sid_listed)   AS listed_cnt,
        SUM(l.status_id = $sid_rescind)  AS resc_cnt
    FROM listings l
    WHERE YEAR(l.date_of_listing) = $year
      AND (l.status_id = $sid_listed OR l.status_id = $sid_rescind)
      AND $aw
    GROUP BY MONTH(l.date_of_listing)
    ORDER BY mn
");
while ($row = mysqli_fetch_assoc($r)) {
    $listed_monthly[$row['mn']]    = $row['listed_cnt'];
    $rescinded_monthly[$row['mn']] = $row['resc_cnt'];
}

// YTD totals
$ytd_res = mysqli_query($conn, "
    SELECT
        COUNT(*)                                                             AS ytd_closed,
        SUM(CASE WHEN l.split_with <> '' AND l.split_with IS NOT NULL
                 THEN l.final_price * 0.5 ELSE l.final_price END)           AS ytd_volume,
        SUM(
            ((l.commission_price * l.commission_pct / 100)
              + l.commission_other + l.transaction_fee + l.errors_omissions)
            * l.agent_split / 100 + l.processing_fee + l.other2
        )                                                                    AS ytd_commission
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND YEAR(l.closing_date) = $year
      AND $aw
");
$ytd = mysqli_fetch_assoc($ytd_res);

$ytd_listed_res = mysqli_query($conn, "
    SELECT
        SUM(l.status_id = $sid_listed)   AS ytd_listed,
        SUM(l.status_id = $sid_rescind)  AS ytd_rescinded
    FROM listings l
    WHERE YEAR(l.date_of_listing) = $year
      AND (l.status_id = $sid_listed OR l.status_id = $sid_rescind)
      AND $aw
");
$ytd_l = mysqli_fetch_assoc($ytd_listed_res);

// Commission rank for selected agent
$rank_position   = 1;
$rank_display    = '—';
if ($agent_name !== '') {
    $rank_res = mysqli_query($conn, "
        SELECT
            CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS aname,
            SUM(
                ((l.commission_price * l.commission_pct / 100)
                  + l.commission_other + l.transaction_fee + l.errors_omissions)
                * l.agent_split / 100 + l.processing_fee + l.other2
            ) AS total_comm
        FROM listings l
        WHERE l.status_id = $sid_closed
          AND YEAR(l.closing_date) = $year
          AND (l.LA_ForReport = 1 OR l.SA_ForReport = 1)
        GROUP BY aname
        ORDER BY total_comm DESC
    ");
    $pos = 1;
    while ($r = mysqli_fetch_assoc($rank_res)) {
        if ($r['aname'] === $agent_name) {
            $rank_position = $pos;
            break;
        }
        $pos++;
    }
    $sfx = match(true) {
        $rank_position === 1 => 'st',
        $rank_position === 2 => 'nd',
        $rank_position === 3 => 'rd',
        default              => 'th',
    };
    $rank_display = $rank_position . $sfx;
}

// ══════════════════════════════════════════════════════════════════════════
// TAB: LISTINGS REPORT  (rptListings / rptListings_Agent)
// ══════════════════════════════════════════════════════════════════════════
// Key date per status matches Access: Closed→closing_date, UC→contract_date, Listed→date_of_listing
$listings_res = mysqli_query($conn, "
    SELECT
        CASE
            WHEN l.status_id = $sid_closed THEN l.closing_date
            WHEN l.status_id = $sid_uc     THEN l.contract_date
            ELSE l.date_of_listing
        END                                                             AS key_date,
        CASE
            WHEN l.status_id = $sid_closed THEN MONTH(l.closing_date)
            WHEN l.status_id = $sid_uc     THEN MONTH(l.contract_date)
            ELSE MONTH(l.date_of_listing)
        END                                                             AS month_num,
        CASE
            WHEN l.status_id = $sid_closed THEN YEAR(l.closing_date)
            WHEN l.status_id = $sid_uc     THEN YEAR(l.contract_date)
            ELSE YEAR(l.date_of_listing)
        END                                                             AS year_num,
        s.description                                                   AS status,
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        l.mls_number,
        l.purchase_price                                                AS price,
        l.seller_name,
        l.address1,
        l.city
    FROM listings l
    LEFT JOIN sales_statuses s ON l.status_id = s.id
    WHERE l.status_id IN ($sid_closed, $sid_uc, $sid_listed)
      AND (
            (l.status_id = $sid_closed AND l.closing_date     BETWEEN '$from_esc' AND '$to_esc')
         OR (l.status_id = $sid_uc     AND l.contract_date    BETWEEN '$from_esc' AND '$to_esc')
         OR (l.status_id = $sid_listed AND l.date_of_listing  BETWEEN '$from_esc' AND '$to_esc')
      )
      AND $aw
    ORDER BY key_date, s.description
");

// Group: month-year → status → rows
$grouped_listings = [];
while ($row = mysqli_fetch_assoc($listings_res)) {
    $mk = $months_names[$row['month_num'] - 1] . ' ' . $row['year_num'];
    $grouped_listings[$mk][$row['status']][] = $row;
}

// ══════════════════════════════════════════════════════════════════════════
// TAB: AGENT SALES SUMMARY  (rptSaleSummary)
// ══════════════════════════════════════════════════════════════════════════
$summary_res = mysqli_query($conn, "
    SELECT
        CASE WHEN l.SA_ForReport = 1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
        SUM(CASE WHEN l.split_with <> '' AND l.split_with IS NOT NULL
                 THEN l.final_price * 0.5 ELSE l.final_price END)       AS total_volume,
        SUM(
            ((l.commission_price * l.commission_pct / 100)
              + l.commission_other + l.transaction_fee + l.errors_omissions)
            * l.agent_split / 100 + l.processing_fee + l.other2
        )                                                                AS commission_paid
    FROM listings l
    WHERE l.status_id = $sid_closed
      AND l.closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND $aw
    GROUP BY agent
    ORDER BY agent
");

// ── Helpers ───────────────────────────────────────────────────────────────
function h($v) { return htmlspecialchars($v ?? ''); }
function money($v) { return '$' . number_format((float)($v ?? 0), 2); }
function fmtD($d) {
    if (empty($d) || $d === '0000-00-00') return '';
    return date('m/d/Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Larson &amp; Company</title>
     <!-- FAVICONS ICON -->
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .rpt-card  { background:#fff; border-radius:8px; padding:22px; margin-bottom:28px; box-shadow:0 1px 4px rgba(0,0,0,.08); }
        .rpt-title { font-size:1.2rem; font-weight:700; color:#1e3a5f; border-left:4px solid #1e3a5f; padding-left:10px; margin-bottom:4px; }
        .rpt-meta  { font-size:.82rem; color:#6c757d; margin-bottom:14px; }
        .sub-hdr   { font-size:1rem; font-weight:700; background:#eef2ff; padding:5px 12px;
                     border-left:3px solid #3b5bdb; margin:16px 0 8px; color:#1e3a5f; }
        .total-row td { font-weight:700; background:#f1f3f5; }
        table      { font-size:.83rem; }
        th         { background:#f8f9fc; white-space:nowrap; }
        .prog-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
        .prog-panel{ border:1px solid #dee2e6; border-radius:6px; padding:12px; }
        .prog-panel h6 { background:#e9ecef; padding:5px 8px; margin:-12px -12px 10px; border-radius:6px 6px 0 0; font-size:.9rem; font-weight:700; color:#1e3a5f; }
        .prog-month { font-weight:700; color:#1e3a5f; margin-top:10px; margin-bottom:2px; font-size:.85rem; }
        .prog-row   { display:flex; justify-content:space-between; font-size:.83rem; padding:2px 4px; }
        .prog-total { display:flex; justify-content:space-between; font-size:.83rem; font-weight:700; border-top:1px solid #dee2e6; padding-top:3px; margin-top:2px; }
        .ytd-box    { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:14px 18px; margin-top:18px; }
        .ytd-box .ytd-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:10px; }
        .ytd-stat   { background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:10px; text-align:center; }
        .ytd-num    { font-size:1.5rem; font-weight:700; color:#1e3a5f; }
        .ytd-lbl    { font-size:.72rem; color:#6c757d; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
        .totals-box { background:#f0f4ff; border:1px solid #c5d1f8; border-radius:6px; padding:12px 18px; display:flex; gap:40px; margin-bottom:16px; }
        .totals-box .t-val { font-size:1.3rem; font-weight:700; color:#1e3a5f; }
        .totals-box .t-lbl { font-size:.75rem; color:#6c757d; }
        .avg-row td { font-style:italic; color:#555; background:#fafbfc; }
        @media(max-width:900px){ .prog-grid{grid-template-columns:1fr;} .ytd-box .ytd-grid{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title">Reports</h5>
    </div>

    <div class="container-fluid">

        <!-- ── Parameter form ──────────────────────────────────────────── -->
        <div class="card p-3 mb-3">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?= h($active_tab) ?>">

                <div class="col-md-2">
                    <label class="form-label form-label-sm">From</label>
                    <input type="date" name="from_date" class="form-control"
                        value="<?= h($from_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm">To</label>
                    <input type="date" name="to_date" class="form-control"
                        value="<?= h($to_date) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm">Year</label>
                    <input type="number" name="year" class="form-control"
                        value="<?= $year ?>" min="2000" max="2099">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Agent
                        <small class="text-muted">(blank = company-wide)</small>
                    </label>
                    <select name="agent_id" class="form-select form-select-sm">
                        <option value="0">— All Agents (Company) —</option>
                        <?php
                        $al = mysqli_query($conn, "SELECT id, name FROM agents
                                                   WHERE include_in_reports = 1 AND active = 1
                                                   ORDER BY name");
                        while ($ag = mysqli_fetch_assoc($al)) {
                            $sel = ($agent_id == $ag['id']) ? 'selected' : '';
                            echo "<option value='{$ag['id']}' $sel>" . h($ag['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-filter me-1"></i>Apply
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?tab=<?= h($active_tab) ?>" class="btn btn-light btn-sm w-100">Reset</a>
                </div>
            </form>
        </div>

        <!-- ── Report Tabs ──────────────────────────────────────────────── -->
        <ul class="nav nav-tabs mb-3" id="reportTabs">
            <?php
            $tabs = [
                'company'  => 'Company / Sales Summary',
                'progress' => 'Progress Report',
                'listings' => 'Listings Report',
                'summary'  => 'Agent Sales Summary',
            ];
            foreach ($tabs as $slug => $label):
                $active_class = ($active_tab === $slug) ? 'active' : '';
                $url = '?' . http_build_query(array_merge($_GET, ['tab' => $slug]));
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $active_class ?>" href="<?= $url ?>">
                    <?= h($label) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 1 — COMPANY / SALES SUMMARY  (rptSalesSummary)
               Matches: Sales Summary report in Access with sections
               LISTED, CLOSED, UNDER CONTRACT
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php if ($active_tab === 'company'): ?>
        <div class="rpt-card">
            <div class="rpt-title">Sales Summary</div>
            <div class="rpt-meta">
                Between: <?= fmtD($from_date) ?> and <?= fmtD($to_date) ?> &nbsp;·&nbsp;
                <?= h($agent_label) ?>
            </div>

            <!-- Header totals box -->
            <div class="totals-box">
                <div>
                    <div class="t-lbl">Total Volume</div>
                    <div class="t-val"><?= money($total_volume) ?></div>
                </div>
                <div>
                    <div class="t-lbl">Commission Paid</div>
                    <div class="t-val"><?= money($commission_paid) ?></div>
                </div>
            </div>

            <!-- LISTED -->
            <?php $listed_count = mysqli_num_rows($listed_res); ?>
            <div class="sub-hdr">LISTED (<?= $listed_count ?>)</div>
            <?php if ($listed_count): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr>
                        <th>#</th><th>MLS #</th><th>Listing Agent</th>
                        <th>Seller Name</th><th>Address</th><th>City</th>
                        <th>Price</th><th>UC Price</th><th>D.O.L</th><th>D.O.E</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($listed_res)): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= h($row['mls_number']) ?></td>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= h($row['seller_name']) ?></td>
                        <td><?= h($row['address1']) ?></td>
                        <td><?= h($row['city']) ?></td>
                        <td><?= money($row['price']) ?></td>
                        <td><?= $row['uc_price'] ? money($row['uc_price']) : '' ?></td>
                        <td><?= fmtD($row['dol']) ?></td>
                        <td><?= fmtD($row['doe']) ?></td>
                        <td><?= h($row['status']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><p class="text-muted ps-2">No listed properties in this period.</p><?php endif; ?>

            <!-- CLOSED -->
            <?php $closed_count = mysqli_num_rows($closed_res); ?>
            <div class="sub-hdr">CLOSED (<?= $closed_count ?>)</div>
            <?php if ($closed_count): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr>
                        <th>#</th><th>Agent</th><th>Buyer Name</th><th>Seller Name</th>
                        <th>Address</th><th>City</th><th>Final Price</th><th>Close Date</th>
                        <th>Days On Market</th><th>PP/UC %</th><th>Lead Source</th>
                    </tr></thead>
                    <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($closed_res)): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= h($row['buyer_name']) ?></td>
                        <td><?= h($row['seller_name']) ?></td>
                        <td><?= h($row['address1']) ?></td>
                        <td><?= h($row['city']) ?></td>
                        <td><?= money($row['final_price']) ?></td>
                        <td><?= fmtD($row['closing_date']) ?></td>
                        <td><?= $row['days_on_market'] !== null ? intval($row['days_on_market']) : '' ?></td>
                        <td><?= $row['pp_uc_pct'] ? number_format($row['pp_uc_pct'], 2) : '' ?></td>
                        <td><?= h($row['lead_source']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <!-- Average row — matches Access report footer -->
                    <tr class="avg-row">
                        <td colspan="6" class="text-end pe-2">Average</td>
                        <td><?= money($avgs['avg_price']) ?></td>
                        <td></td>
                        <td><?= $avgs['avg_dom'] !== null ? number_format($avgs['avg_dom'], 1) : '' ?></td>
                        <td><?= $avgs['avg_pp_uc'] !== null ? number_format($avgs['avg_pp_uc'], 3) : '' ?></td>
                        <td></td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <?php else: ?><p class="text-muted ps-2">No closed transactions in this period.</p><?php endif; ?>

            <!-- UNDER CONTRACT -->
            <?php $uc_count = mysqli_num_rows($uc_res); ?>
            <div class="sub-hdr">UNDER CONTRACT (<?= $uc_count ?>)</div>
            <?php if ($uc_count): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr>
                        <th>#</th><th>Agent</th><th>Buyer Name</th><th>Seller Name</th>
                        <th>Address</th><th>City</th><th>Price</th><th>Contract Date</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                    <?php $i = 1; while ($row = mysqli_fetch_assoc($uc_res)): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= h($row['buyer_name']) ?></td>
                        <td><?= h($row['seller_name']) ?></td>
                        <td><?= h($row['address1']) ?></td>
                        <td><?= h($row['city']) ?></td>
                        <td><?= money($row['price']) ?></td>
                        <td><?= fmtD($row['contract_date']) ?></td>
                        <td><?= h($row['status']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><p class="text-muted ps-2">No under-contract properties in this period.</p><?php endif; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 2 — PROGRESS REPORT  (rptProgressSummary)
               Matches Access: 3 panels (UC / Closed / Listed) by month
               + Year-to-Date summary box at bottom
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'progress'): ?>
        <div class="rpt-card">
            <div class="rpt-title">Progress Report</div>
            <div class="rpt-meta">
                Between: <?= fmtD($from_date) ?> and <?= fmtD($to_date) ?> &nbsp;·&nbsp;
                <?= h($agent_label) ?>
            </div>

            <div class="prog-grid">

                <!-- Under Contract panel -->
                <div class="prog-panel">
                    <h6>Under Contract</h6>
                    <?php foreach ($months_names as $idx => $mn):
                        $mn_num = $idx + 1;
                        $cnt = $uc_monthly[$mn_num] ?? 0;
                        if (!$cnt) continue; ?>
                    <div class="prog-month"><?= $mn ?></div>
                    <div class="prog-row">
                        <span><?= h($agent_label) ?></span>
                        <span><?= $cnt ?></span>
                    </div>
                    <div class="prog-total">
                        <span>Total</span><span><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!array_sum($uc_monthly)): ?>
                        <p class="text-muted" style="font-size:.8rem;margin-top:8px">No data</p>
                    <?php endif; ?>
                </div>

                <!-- Closed panel -->
                <div class="prog-panel">
                    <h6>Closed</h6>
                    <?php foreach ($months_names as $idx => $mn):
                        $mn_num = $idx + 1;
                        $cnt = $closed_monthly[$mn_num] ?? 0;
                        if (!$cnt) continue; ?>
                    <div class="prog-month"><?= $mn ?></div>
                    <div class="prog-row">
                        <span><?= h($agent_label) ?></span>
                        <span><?= $cnt ?></span>
                    </div>
                    <div class="prog-total">
                        <span>Total</span><span><?= $cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!array_sum($closed_monthly)): ?>
                        <p class="text-muted" style="font-size:.8rem;margin-top:8px">No data</p>
                    <?php endif; ?>
                </div>

                <!-- Listed + Rescinded panel -->
                <div class="prog-panel">
                    <h6>Listed <span style="font-weight:400;font-size:.78rem;color:#6c757d">/ Resc.</span></h6>
                    <?php foreach ($months_names as $idx => $mn):
                        $mn_num  = $idx + 1;
                        $lcnt = $listed_monthly[$mn_num]    ?? 0;
                        $rcnt = $rescinded_monthly[$mn_num] ?? 0;
                        if (!$lcnt && !$rcnt) continue; ?>
                    <div class="prog-month"><?= $mn ?></div>
                    <div class="prog-row">
                        <span><?= h($agent_label) ?></span>
                        <span><?= $lcnt ?> / <?= $rcnt ?></span>
                    </div>
                    <div class="prog-total">
                        <span>Total</span>
                        <span><?= $lcnt ?> / <?= $rcnt ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!array_sum($listed_monthly) && !array_sum($rescinded_monthly)): ?>
                        <p class="text-muted" style="font-size:.8rem;margin-top:8px">No data</p>
                    <?php endif; ?>
                </div>

            </div><!-- /.prog-grid -->

            <!-- Year To Date box — matches Access bottom summary -->
            <div class="ytd-box">
                <div style="font-weight:700;font-size:.9rem;color:#1e3a5f;margin-bottom:4px">
                    YEAR TO DATE (<?= $year ?>)
                </div>
                <div class="ytd-grid">
                    <div class="ytd-stat">
                        <div class="ytd-num"><?= intval($ytd['ytd_closed'] ?? 0) ?></div>
                        <div class="ytd-lbl">Closed</div>
                    </div>
                    <div class="ytd-stat">
                        <div class="ytd-num"><?= intval($ytd_l['ytd_listed'] ?? 0) ?></div>
                        <div class="ytd-lbl">Listed</div>
                    </div>
                    <div class="ytd-stat">
                        <div class="ytd-num"><?= intval($ytd_l['ytd_rescinded'] ?? 0) ?></div>
                        <div class="ytd-lbl">Rescinded</div>
                    </div>
                    <div class="ytd-stat">
                        <div class="ytd-num" style="font-size:1rem"><?= money($ytd['ytd_volume'] ?? 0) ?></div>
                        <div class="ytd-lbl">Volume</div>
                    </div>
                    <div class="ytd-stat">
                        <div class="ytd-num" style="font-size:1rem"><?= money($ytd['ytd_commission'] ?? 0) ?></div>
                        <div class="ytd-lbl">Commission</div>
                    </div>
                    <?php if ($agent_name): ?>
                    <div class="ytd-stat">
                        <div class="ytd-num"><?= h($rank_display) ?></div>
                        <div class="ytd-lbl">Rank</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 3 — LISTINGS REPORT  (rptListings)
               Matches Access: grouped by Month → then by Status
               Shows count per month header e.g. "January (5)"
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'listings'): ?>
        <div class="rpt-card">
            <div class="rpt-title">Listings Report</div>
            <div class="rpt-meta">
                Between: <?= fmtD($from_date) ?> and <?= fmtD($to_date) ?> &nbsp;·&nbsp;
                For: <?= h($agent_label) ?>
            </div>

            <?php if (empty($grouped_listings)): ?>
                <p class="text-muted">No listings found for the selected criteria.</p>
            <?php endif; ?>

            <?php foreach ($grouped_listings as $month_key => $status_groups): ?>
            <?php
                $month_total = array_sum(array_map('count', $status_groups));
            ?>
            <div class="sub-hdr">
                <?= h($month_key) ?>
                <span style="font-weight:400;font-size:.85rem">(<?= $month_total ?>)</span>
            </div>

            <?php
            // Render in Access order: Closed first, then Under Contract, then Listed
            $status_order = ['Closed', 'Under Contract', 'Listed'];
            foreach ($status_order as $st):
                if (!isset($status_groups[$st])) continue;
                $rows = $status_groups[$st];
            ?>
            <div class="ms-2 mb-1 fw-bold" style="font-size:.85rem;color:#333"><?= h($st) ?></div>
            <div class="table-responsive mb-2">
                <table class="table table-sm table-bordered">
                    <thead><tr>
                        <th>#</th><th>Listing Agent</th><th>MLS #</th>
                        <th>Price</th><th>Seller Name</th><th>Address</th><th>City</th>
                    </tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= h($row['mls_number']) ?></td>
                        <td><?= money($row['price']) ?></td>
                        <td><?= h($row['seller_name']) ?></td>
                        <td><?= h($row['address1']) ?></td>
                        <td><?= h($row['city']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 4 — AGENT SALES SUMMARY  (rptSaleSummary)
               Total volume + commission per agent for the period
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'summary'): ?>
        <div class="rpt-card">
            <div class="rpt-title">Agent Sales Summary</div>
            <div class="rpt-meta">
                Between: <?= fmtD($from_date) ?> and <?= fmtD($to_date) ?>
            </div>

            <!-- Same header totals box as Sales Summary -->
            <div class="totals-box">
                <div>
                    <div class="t-lbl"><?= h($agent_label) ?> — Total Volume</div>
                    <div class="t-val"><?= money($total_volume) ?></div>
                </div>
                <div>
                    <div class="t-lbl">Commission Paid</div>
                    <div class="t-val"><?= money($commission_paid) ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr>
                        <th>Agent</th>
                        <th>Total Volume</th>
                        <th>Commission Paid</th>
                    </tr></thead>
                    <tbody>
                    <?php
                    $has_rows = false;
                    while ($row = mysqli_fetch_assoc($summary_res)):
                        $has_rows = true;
                    ?>
                    <tr>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= money($row['total_volume']) ?></td>
                        <td><?= money($row['commission_paid']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_rows): ?>
                    <tr><td colspan="3" class="text-muted">No closed transactions found for this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.container-fluid -->
</div><!-- /.content-body -->

<?php include('footer.php'); ?>
</body>
</html>