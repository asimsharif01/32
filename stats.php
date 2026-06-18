<?php
// stats_report.php — Adjustable date range stats summary
// Covers: Total Closings, Total Listings, Total Volume, Referral Sources, Under Contract
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Date range defaults ───────────────────────────────────────────────────
$from = $_GET['from'] ?? date('Y-01-01');
$to   = $_GET['to']   ?? date('Y-12-31');

$from_esc = mysqli_real_escape_string($conn, $from);
$to_esc   = mysqli_real_escape_string($conn, $to);

// ── Status IDs ────────────────────────────────────────────────────────────
function sid($conn, $d) {
    $r = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description='"
        . mysqli_real_escape_string($conn,$d) . "' LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    return $row ? intval($row['id']) : 0;
}
$s_closed = sid($conn,'Closed');
$s_listed = sid($conn,'Listed');
$s_uc     = sid($conn,'Under Contract');
$s_resc   = sid($conn,'Rescinded');

// ── 1. Total Closings (closed within date range) ──────────────────────────
$total_closings = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
"))['c'];

// ── 2. Total Listings (date_of_listing within date range) ─────────────────
$total_listings = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c FROM listings
    WHERE date_of_listing BETWEEN '$from_esc' AND '$to_esc'
"))['c'];

// ── 3. Total Volume (sum of final_price on closed in date range) ──────────
$total_volume = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(final_price) v FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
"))['v'] ?? 0;

// ── 4. Total Under Contract (currently UC — snapshot, date-filtered
//       by contract_date falling within the range) ─────────────────────────
$total_uc = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c FROM listings
    WHERE status_id=$s_uc
      AND contract_date BETWEEN '$from_esc' AND '$to_esc'
"))['c'];

// Also: currently active UC (no date filter) for context
$uc_active_now = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c FROM listings WHERE status_id=$s_uc
"))['c'];

// ── 5. Referral Sources (lead_source on closed in date range) ────────────
$lead_data = [];
$ld_res = mysqli_query($conn,"
    SELECT IFNULL(NULLIF(lead_source,''),'Unknown') AS src,
           COUNT(*) cnt
    FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
    GROUP BY src
    ORDER BY cnt DESC
");
while ($r = mysqli_fetch_assoc($ld_res)) $lead_data[] = $r;
$total_closed_with_source = array_sum(array_column($lead_data,'cnt'));

// ── Extra useful stats ────────────────────────────────────────────────────
$rescinded = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT COUNT(*) c FROM listings
    WHERE status_id=$s_resc
      AND date_of_listing BETWEEN '$from_esc' AND '$to_esc'
"))['c'];

$avg_price = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT AVG(final_price) a FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
"))['a'] ?? 0;

$avg_dom = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT AVG(DATEDIFF(contract_date,date_of_listing)) d
    FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND contract_date IS NOT NULL AND date_of_listing IS NOT NULL
"))['d'] ?? 0;

// ── Point 9: Days on Market detail — distribution breakdown ──────────────
$dom_detail = [];
$dom_res = mysqli_query($conn,"
    SELECT
        CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS agent,
        l.address1, l.city, l.county,
        l.mls_number,
        l.date_of_listing,
        l.contract_date,
        DATEDIFF(l.contract_date, l.date_of_listing) AS days_on_market,
        l.final_price
    FROM listings l
    WHERE l.status_id = $s_closed
      AND l.closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND l.contract_date IS NOT NULL
      AND l.date_of_listing IS NOT NULL
    ORDER BY days_on_market ASC
");
while ($r = mysqli_fetch_assoc($dom_res)) $dom_detail[] = $r;

// ── Point 10: City/County breakdown ──────────────────────────────────────
$city_data   = [];
$county_data = [];

$city_res = mysqli_query($conn,"
    SELECT
        IFNULL(NULLIF(city,''),'Unknown')   AS location,
        COUNT(*)                             AS closings,
        SUM(final_price)                     AS volume
    FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
    GROUP BY location
    ORDER BY closings DESC
");
while ($r = mysqli_fetch_assoc($city_res)) $city_data[] = $r;

$county_res = mysqli_query($conn,"
    SELECT
        IFNULL(NULLIF(county,''),'Not Set')  AS location,
        COUNT(*)                              AS closings,
        SUM(final_price)                      AS volume
    FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
    GROUP BY location
    ORDER BY closings DESC
");
while ($r = mysqli_fetch_assoc($county_res)) $county_data[] = $r;

// Top agents in the period
$top_agents = [];
$ta_res = mysqli_query($conn,"
    SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS aname,
           COUNT(*) closed_count,
           SUM(final_price) volume
    FROM listings
    WHERE status_id=$s_closed
      AND closing_date BETWEEN '$from_esc' AND '$to_esc'
      AND (LA_ForReport=1 OR SA_ForReport=1)
    GROUP BY aname
    ORDER BY volume DESC
    LIMIT 10
");
while ($r = mysqli_fetch_assoc($ta_res)) $top_agents[] = $r;

// ── Helpers ───────────────────────────────────────────────────────────────
function h($v)  { return htmlspecialchars($v ?? ''); }
function money($v) { return '$' . number_format((float)($v??0), 2); }
function moneyAbbr($v) {
    if ($v >= 1000000) return '$' . number_format($v/1000000, 2) . 'M';
    if ($v >= 1000)    return '$' . number_format($v/1000, 1) . 'K';
    return '$' . number_format($v, 2);
}
function fmtDate($d) { return $d ? date('m/d/Y', strtotime($d)) : '—'; }
$lead_colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#7c3aed','#0891b2','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats Report — Larson &amp; Company</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <style>
        body { background:#f0f2f5; font-family:'Segoe UI',Arial,sans-serif; font-size:13px; }

        /* ── Screen layout ─────────────────────────────────────────── */
        .page-wrap { margin:10px auto; padding:0 10px; }

        /* ── Filter card ───────────────────────────────────────────── */
        .filter-card {
            background:#fff; border-radius:10px; padding:18px 22px;
            box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:20px;
            display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
        }
        .filter-card label { font-size:.72rem; font-weight:700; color:#4a5568; display:block; margin-bottom:4px; }
        .filter-card input[type=date] {
            font-size:.85rem; padding:.3rem .5rem; border:1px solid #c8d4e8;
            border-radius:6px; color:#1a202c;
        }
        .btn-filter {
            background:#1e3a5f; color:#fff; border:none; padding:8px 20px;
            border-radius:6px; font-size:.85rem; font-weight:600; cursor:pointer;
        }
        .btn-filter:hover { background:#2a4f82; }
        .btn-print-pg {
            background:#fff; color:#1e3a5f; border:1px solid #1e3a5f;
            padding:8px 18px; border-radius:6px; font-size:.85rem;
            font-weight:600; cursor:pointer; margin-left:auto;
        }
        .btn-print-pg:hover { background:#f0f4ff; }

        /* ── Report card ───────────────────────────────────────────── */
        .rpt-card {
            background:#fff; border-radius:10px; padding:26px 30px;
            box-shadow:0 1px 4px rgba(0,0,0,.08);
        }
        /* ── Report header ─────────────────────────────────────────── */
        .rpt-header {
            display:flex; justify-content:space-between; align-items:flex-start;
            border-bottom:2px solid #1e3a5f; padding-bottom:12px; margin-bottom:22px;
        }
        .rpt-header h1 { font-size:20px; font-weight:700; color:#0f2b3d; }
        .rpt-header p  { font-size:12px; color:#4b5563; margin-top:3px; }
        .co-block { text-align:right; font-size:12px; color:#4b5563; }
        .co-name  { font-size:15px; font-weight:700; color:#1e3a5f; }

        /* ── 5 KPI boxes ───────────────────────────────────────────── */
        .kpi5 { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:24px; }
        .kpi-box {
            border-radius:10px; padding:16px 14px; text-align:center;
            border:1px solid #e2e8f0; position:relative; overflow:hidden;
        }
        .kpi-box::before { content:''; position:absolute; top:0; left:0; right:0; height:4px; }
        .kpi-box.blue::before   { background:linear-gradient(90deg,#1e3a5f,#2a4f82); }
        .kpi-box.teal::before   { background:linear-gradient(90deg,#0d9488,#14b8a6); }
        .kpi-box.gold::before   { background:linear-gradient(90deg,#c9a84c,#f0c866); }
        .kpi-box.amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .kpi-box.purple::before { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .kpi-num { font-size:28px; font-weight:800; color:#0d1117; letter-spacing:-1px; line-height:1; margin-bottom:5px; }
        .kpi-lbl { font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; letter-spacing:.5px; }
        .kpi-sub { font-size:11px; color:#4a5568; margin-top:5px; }

        /* ── Two column ────────────────────────────────────────────── */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }

        /* ── Section ───────────────────────────────────────────────── */
        .sec-title {
            font-size:11px; font-weight:700; color:#1e3a5f; text-transform:uppercase;
            letter-spacing:.5px; border-left:4px solid #1e3a5f; padding-left:9px;
            margin-bottom:12px;
        }

        /* ── Referral rows ─────────────────────────────────────────── */
        .ref-row { display:flex; align-items:center; gap:9px; margin-bottom:5px; }
        .ref-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .ref-name { flex:1; font-size:12.5px; font-weight:500; }
        .ref-cnt  { font-size:13px; font-weight:700; color:#0d1117; }
        .ref-pct  { font-size:11px; color:#718096; min-width:34px; text-align:right; }
        .ref-bar  { height:5px; border-radius:3px; margin-left:19px; margin-bottom:7px; background:#e8ecf4; }
        .ref-bar-fill { height:100%; border-radius:3px; transition:width .8s ease; }
        .ref-total { font-size:11.5px; color:#718096; margin-top:10px; padding-top:10px; border-top:1px solid #f0f2f5; }

        /* ── Agent table ───────────────────────────────────────────── */
        table { width:100%; border-collapse:collapse; }
        th { font-size:10px; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.5px; padding:0 8px 8px; text-align:left; border-bottom:2px solid #e2e8f0; }
        td { font-size:12px; padding:8px; border-bottom:1px solid #f5f5f5; }
        tr:last-child td { border-bottom:none; }
        .ab-track { height:5px; background:#e8ecf4; border-radius:3px; overflow:hidden; margin-top:3px; }
        .ab-fill  { height:100%; border-radius:3px; }

        /* ── Location table ────────────────────────────────────────── */
        .loc-tabs { display:flex; gap:6px; margin-bottom:12px; }
        .loc-tab  { padding:4px 12px; border-radius:16px; font-size:.75rem; font-weight:600; border:1px solid #c8d4e8; cursor:pointer; color:#1e3a5f; background:#f5f7fb; }
        .loc-tab.active { background:#1e3a5f; color:#fff; border-color:#1e3a5f; }
        .loc-panel { display:none; }
        .loc-panel.active { display:block; }

        /* ── DOM table ─────────────────────────────────────────────── */
        .dom-fast   { color:#059669; font-weight:700; }
        .dom-medium { color:#d97706; font-weight:700; }
        .dom-slow   { color:#e11d48; font-weight:700; }

        /* ── Quick stats row ───────────────────────────────────────── */
        .quick-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
        .qs-item { background:#f8f9fc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; }
        .qs-num  { font-size:20px; font-weight:700; color:#0d1117; }
        .qs-lbl  { font-size:11px; color:#718096; text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }

        /* print handled by print_stats.php */
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
    <div class="page-titles d-flex justify-content-between align-items-center">
        <h5 class="bc-title">Stats Report</h5>
    </div>

    <div class="page-wrap">
       <div class="d-flex" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">

    <!-- ── Date range filter ─────────────────────────────────────── -->
    <form method="GET" class="filter-card no-print" id="filterForm"
        style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;width:100%;">

        <div>
            <label>From Date</label>
            <input type="text" name="from" id="input_from"
                value="<?= h($from) ?>"
                placeholder="Select date" readonly
                style="cursor:pointer;background:#fff;min-width:130px">
        </div>

        <div>
            <label>To Date</label>
            <input type="text" name="to" id="input_to"
                value="<?= h($to) ?>"
                placeholder="Select date" readonly
                style="cursor:pointer;background:#fff;min-width:130px">
        </div>

        <div>
            <button type="submit" class="btn-filter">Apply Dates</button>
        </div>

        <!-- Quick range buttons -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:flex-end">
            <?php
            $quick = [
                'This Year'   => [date('Y-01-01'), date('Y-12-31')],
                'Last Year'   => [date('Y-01-01',strtotime('-1 year')), date('Y-12-31',strtotime('-1 year'))],
                'This Month'  => [date('Y-m-01'), date('Y-m-t')],
                'Last Month'  => [date('Y-m-01',strtotime('-1 month')), date('Y-m-t',strtotime('-1 month'))],
                'Last 90 Days'=> [date('Y-m-d',strtotime('-90 days')), date('Y-m-d')],
            ];

            foreach ($quick as $label => [$qf,$qt]):
                $active = ($from===$qf && $to===$qt)
                    ? 'background:#1e3a5f;color:#fff;'
                    : '';
            ?>
                <a href="?from=<?= $qf ?>&to=<?= $qt ?>"
                    style="font-size:.75rem;padding:5px 10px;border-radius:16px;border:1px solid #c8d4e8;text-decoration:none;color:#1e3a5f;<?= $active ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn-print-pg"
            onclick="window.open('print_stats.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>','_blank')">
            🖨️ Print / Save as PDF
        </button>

    </form>

</div>

        <!-- ── Report content ────────────────────────────────────────── -->
        <div class="rpt-card">

            <!-- Header (visible on print) -->
            <div class="rpt-header">
                <div>
                    <h1>Stats Report</h1>
                    <p><?= fmtDate($from) ?> — <?= fmtDate($to) ?></p>
                </div>
                <div class="co-block">
                    <div class="co-name">LARSON &amp; COMPANY</div>
                    <div>pros@larsonandcompany.com</div>
                    <div>www.larsonandcompany.com</div>
                </div>
            </div>

            <!-- ── 5 Key Stats ──────────────────────────────────────── -->
            <div class="kpi5">

                <div class="kpi-box blue">
                    <div class="kpi-num"><?= $total_closings ?></div>
                    <div class="kpi-lbl">Total Closings</div>
                    <div class="kpi-sub"><?= fmtDate($from) ?> – <?= fmtDate($to) ?></div>
                </div>

                <div class="kpi-box teal">
                    <div class="kpi-num"><?= $total_listings ?></div>
                    <div class="kpi-lbl">Total Listings</div>
                    <div class="kpi-sub">Listed in period</div>
                </div>

                <div class="kpi-box gold">
                    <div class="kpi-num" style="font-size:22px"><?= moneyAbbr($total_volume) ?></div>
                    <div class="kpi-lbl">Total Volume</div>
                    <div class="kpi-sub"><?= money($total_volume) ?></div>
                </div>

                <div class="kpi-box amber">
                    <div class="kpi-num"><?= $total_uc ?></div>
                    <div class="kpi-lbl">Under Contract</div>
                    <div class="kpi-sub"><?= $uc_active_now ?> active now</div>
                </div>

                <div class="kpi-box purple">
                    <div class="kpi-num"><?= count($lead_data) ?></div>
                    <div class="kpi-lbl">Referral Sources</div>
                    <div class="kpi-sub">Across <?= $total_closings ?> closings</div>
                </div>

            </div>

            <!-- ── Quick stats ──────────────────────────────────────── -->
            <div class="quick-stats">
                <div class="qs-item">
                    <div class="qs-num"><?= moneyAbbr($avg_price) ?></div>
                    <div class="qs-lbl">Avg Sale Price</div>
                </div>
                <div class="qs-item">
                    <div class="qs-num"><?= $avg_dom ? number_format($avg_dom,1) : '—' ?></div>
                    <div class="qs-lbl">Avg Days on Market</div>
                </div>
                <div class="qs-item">
                    <div class="qs-num"><?= $rescinded ?></div>
                    <div class="qs-lbl">Rescinded in Period</div>
                </div>
            </div>


            <!-- ── Two column: Referral Sources + Top Agents ──────── -->
            <div class="two-col">

                <!-- Referral Sources -->
                <div>
                    <div class="sec-title">Referral Sources</div>
                    <?php if ($lead_data): ?>
                    <?php foreach ($lead_data as $i => $ld):
                        $pct = $total_closed_with_source > 0
                            ? round($ld['cnt'] / $total_closed_with_source * 100) : 0;
                        $col = $lead_colors[$i % count($lead_colors)];
                    ?>
                    <div class="ref-row">
                        <div class="ref-dot" style="background:<?= $col ?>"></div>
                        <div class="ref-name"><?= h($ld['src']) ?></div>
                        <div class="ref-cnt"><?= $ld['cnt'] ?></div>
                        <div class="ref-pct"><?= $pct ?>%</div>
                    </div>
                    <div class="ref-bar">
                        <div class="ref-bar-fill"
                             style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="ref-total">
                        Total closings tracked: <strong><?= $total_closed_with_source ?></strong>
                    </div>
                    <?php else: ?>
                    <p style="color:#718096;font-size:12px">No closed transactions in this period.</p>
                    <?php endif; ?>
                </div>

                <!-- Top Agents -->
                <div>
                    <div class="sec-title">Closings by Agent</div>
                    <?php if ($top_agents):
                        $max_v = max(array_column($top_agents,'volume')) ?: 1;
                        $ac = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#7c3aed','#0891b2','#059669','#dc2626','#2563eb'];
                    ?>
                    <table>
                        <thead><tr>
                            <th>Agent</th>
                            <th style="text-align:right">Closings</th>
                            <th style="text-align:right">Volume</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($top_agents as $i => $ag): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:#0d1117"><?= h($ag['aname']) ?></div>
                                <div class="ab-track">
                                    <div class="ab-fill" style="width:<?= ($max_v > 0 ? round(($ag['volume'] / $max_v) * 100) : 0) ?>%;
background:<?= !empty($ac) ? $ac[$i % count($ac)] : '#ccc' ?>"></div>
                                </div>
                            </td>
                            <td style="text-align:right;font-weight:700"><?= $ag['closed_count'] ?></td>
                            <td style="text-align:right;font-weight:600;color:#1e3a5f"><?= moneyAbbr($ag['volume']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="color:#718096;font-size:12px">No closings in this period.</p>
                    <?php endif; ?>
                </div>

            </div>


            <!-- ══ POINT 9: Average Days on Market ════════════════════ -->
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f0f2f5">
                <div class="sec-title">Average Days on Market
                    <span style="font-weight:400;font-size:10px;color:#718096;text-transform:none;letter-spacing:0;margin-left:8px">(Date of Listing → Contract Date)</span>
                </div>
                <div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap">
                    <div class="qs-item" style="flex:1;min-width:130px">
                        <div class="qs-num"><?= $avg_dom ? number_format($avg_dom,1) : '—' ?></div>
                        <div class="qs-lbl">Avg Days on Market</div>
                    </div>
                    <?php
                    $dom_fast   = count(array_filter($dom_detail, fn($r) => $r['days_on_market'] <= 14));
                    $dom_medium = count(array_filter($dom_detail, fn($r) => $r['days_on_market'] > 14 && $r['days_on_market'] <= 30));
                    $dom_slow   = count(array_filter($dom_detail, fn($r) => $r['days_on_market'] > 30));
                    ?>
                    <div class="qs-item" style="flex:1;min-width:130px">
                        <div class="qs-num dom-fast"><?= $dom_fast ?></div>
                        <div class="qs-lbl">Under 14 Days</div>
                    </div>
                    <div class="qs-item" style="flex:1;min-width:130px">
                        <div class="qs-num dom-medium"><?= $dom_medium ?></div>
                        <div class="qs-lbl">15–30 Days</div>
                    </div>
                    <div class="qs-item" style="flex:1;min-width:130px">
                        <div class="qs-num dom-slow"><?= $dom_slow ?></div>
                        <div class="qs-lbl">Over 30 Days</div>
                    </div>
                </div>
                <?php if ($dom_detail): ?>
                <table>
                    <thead><tr>
                        <th>Address</th><th>City</th><th>Agent</th>
                        <th>List Date</th><th>Contract Date</th>
                        <th style="text-align:right">Days</th>
                        <th style="text-align:right">Sale Price</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($dom_detail as $row):
                        $dv = intval($row['days_on_market']);
                        $dc = $dv <= 14 ? 'dom-fast' : ($dv <= 30 ? 'dom-medium' : 'dom-slow');
                    ?>
                    <tr>
                        <td><?= h($row['address1']) ?></td>
                        <td><?= h($row['city']) ?></td>
                        <td><?= h($row['agent']) ?></td>
                        <td><?= $row['date_of_listing'] ? date('m/d/Y',strtotime($row['date_of_listing'])) : '—' ?></td>
                        <td><?= $row['contract_date']   ? date('m/d/Y',strtotime($row['contract_date']))   : '—' ?></td>
                        <td style="text-align:right" class="<?= $dc ?>"><?= $dv ?></td>
                        <td style="text-align:right"><?= $row['final_price'] ? moneyAbbr($row['final_price']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#718096;font-size:12px">No closed transactions with both list date and contract date in this period.</p>
                <?php endif; ?>
            </div>


            <!-- ══ POINT 10: City / County Breakdown ══════════════════ -->
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f0f2f5">
                <div class="sec-title">Closings by Location</div>
                <?php
                $loc_colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#7c3aed','#0891b2','#059669'];
                ?>
                <div class="two-col">

                    <!-- By City -->
                    <div>
                        <div style="font-size:11px;font-weight:700;color:#4a5568;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px">By City</div>
                        <?php if ($city_data):
                            $max_city = max(array_column($city_data,'closings')) ?: 1; ?>
                        <table>
                            <thead><tr>
                                <th>City</th>
                                <th style="text-align:right">Closings</th>
                                <th style="text-align:right">Volume</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($city_data as $i => $row):
                                $col = $loc_colors[$i % count($loc_colors)];
                                $pct = round($row['closings']/$max_city*100);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;color:#0d1117"><?= h($row['location']) ?></div>
                                    <div style="height:4px;background:#e8ecf4;border-radius:2px;margin-top:3px;overflow:hidden">
                                        <div style="height:100%;background:<?= $col ?>;width:<?= $pct ?>%;border-radius:2px"></div>
                                    </div>
                                </td>
                                <td style="text-align:right;font-weight:700"><?= $row['closings'] ?></td>
                                <td style="text-align:right;font-size:11px;color:#4a5568"><?= moneyAbbr($row['volume']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="color:#718096;font-size:12px">No data.</p>
                        <?php endif; ?>
                    </div>

                    <!-- By County -->
                    <div>
                        <div style="font-size:11px;font-weight:700;color:#4a5568;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px">By County</div>
                        <?php
                        $has_county = !empty(array_filter($county_data, fn($r) => $r['location'] !== 'Not Set'));
                        if ($has_county):
                            $max_county = max(array_column($county_data,'closings')) ?: 1; ?>
                        <table>
                            <thead><tr>
                                <th>County</th>
                                <th style="text-align:right">Closings</th>
                                <th style="text-align:right">Volume</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($county_data as $i => $row):
                                $col = $loc_colors[$i % count($loc_colors)];
                                $pct = round($row['closings']/$max_county*100);
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;color:<?= $row['location']==='Not Set'?'#718096':'#0d1117' ?>"><?= h($row['location']) ?></div>
                                    <div style="height:4px;background:#e8ecf4;border-radius:2px;margin-top:3px;overflow:hidden">
                                        <div style="height:100%;background:<?= $col ?>;width:<?= $pct ?>%;border-radius:2px"></div>
                                    </div>
                                </td>
                                <td style="text-align:right;font-weight:700"><?= $row['closings'] ?></td>
                                <td style="text-align:right;font-size:11px;color:#4a5568"><?= moneyAbbr($row['volume']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div style="background:#fffbe6;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;font-size:12px;color:#92400e">
                            ⚠️ No county data yet. Add the <strong>County</strong> field when entering new transactions and it will appear here.
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div><!-- /.rpt-card -->
    </div><!-- /.page-wrap -->
</div><!-- /.content-body -->

<?php include('footer.php'); ?>

<!-- Flatpickr — replaces native date picker, no calendar-stays-open bug -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Flatpickr closes automatically on date select and never blocks form submission
flatpickr('#input_from', {
    dateFormat: 'Y-m-d',        // matches PHP date format used in queries
    defaultDate: '<?= h($from) ?>',
    allowInput: false,          // readonly — no typing, click only
    disableMobile: true,        // use Flatpickr on mobile too (not native picker)
    onChange: function(selectedDates, dateStr) {
        // Auto-advance focus to To Date after selecting From Date
        document.getElementById('input_to')._flatpickr.open();
    }
});

flatpickr('#input_to', {
    dateFormat: 'Y-m-d',
    defaultDate: '<?= h($to) ?>',
    allowInput: false,
    disableMobile: true,
    onClose: function() {
        // Calendar closed — safe to submit now
        // Nothing needed, user clicks Apply Dates normally
    }
});
</script>
</body>
</html>