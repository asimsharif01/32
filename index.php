<?php
// index.php — Dashboard
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── KPI queries ───────────────────────────────────────────────────────────
$cur_month_start = date('Y-m-01');
$cur_month_end   = date('Y-m-t');
$cur_year_start  = date('Y-01-01');
$cur_year_end    = date('Y-12-31');
$year            = date('Y');
$prev_month_start = date('Y-m-01', strtotime('-1 month'));
$prev_month_end   = date('Y-m-t',  strtotime('-1 month'));

// Status IDs
function sid($conn, $d) {
    $r = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description='" . mysqli_real_escape_string($conn,$d) . "' LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    return $row ? intval($row['id']) : 0;
}
$s_closed = sid($conn,'Closed');
$s_listed = sid($conn,'Listed');
$s_uc     = sid($conn,'Under Contract');
$s_resc   = sid($conn,'Rescinded');

// Total listings in DB
$total_listings = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings"))['c'];

// Active (Listed + UC)
$active = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id IN ($s_listed,$s_uc)"))['c'];

// Closed this month
$closed_month = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_month_start' AND '$cur_month_end'"))['c'];

// Closed this year
$closed_year = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"))['c'];

// Volume this year
$vol_year_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(final_price) v FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"));
$vol_year = $vol_year_row['v'] ?? 0;

// Volume this month
$vol_month_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(final_price) v FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_month_start' AND '$cur_month_end'"));
$vol_month = $vol_month_row['v'] ?? 0;

// Commission this year
$comm_year_row = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) c
    FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'
"));
$comm_year = $comm_year_row['c'] ?? 0;

// Under contract count
$uc_count = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_uc"))['c'];

// Avg days on market (closed this year)
$dom_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(DATEDIFF(contract_date,date_of_listing)) d FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end' AND contract_date IS NOT NULL AND date_of_listing IS NOT NULL"));
$avg_dom = $dom_row['d'] ? round($dom_row['d'],1) : 0;

// Avg sale price this year
$avg_price_row = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(final_price) a FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"));
$avg_price = $avg_price_row['a'] ?? 0;

// Monthly closed for sparkline (last 12 months)
$monthly_closed = array_fill(1,12,0);
$monthly_volume = array_fill(1,12,0);
$mc_res = mysqli_query($conn,"
    SELECT MONTH(closing_date) mn, COUNT(*) cnt, SUM(final_price) vol
    FROM listings
    WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'
    GROUP BY MONTH(closing_date)
");
while($r = mysqli_fetch_assoc($mc_res)) {
    $monthly_closed[$r['mn']] = intval($r['cnt']);
    $monthly_volume[$r['mn']] = floatval($r['vol']);
}

// Top 5 agents by volume this year
$top_agents = [];
$ta_res = mysqli_query($conn,"
    SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS aname,
           COUNT(*) closed_count,
           SUM(final_price) volume
    FROM listings
    WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'
      AND (LA_ForReport=1 OR SA_ForReport=1)
    GROUP BY aname
    ORDER BY volume DESC
    LIMIT 5
");
while($r = mysqli_fetch_assoc($ta_res)) $top_agents[] = $r;

// Lead source breakdown (closed this year)
$lead_data = [];
$ld_res = mysqli_query($conn,"
    SELECT IFNULL(NULLIF(lead_source,''),'Unknown') AS src, COUNT(*) cnt
    FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'
    GROUP BY src ORDER BY cnt DESC LIMIT 6
");
while($r = mysqli_fetch_assoc($ld_res)) $lead_data[] = $r;

// Recent 8 transactions
$recent_res = mysqli_query($conn,"
    SELECT l.id, l.mls_number, l.transaction_number, l.address1, l.city,
           l.final_price, l.closing_date, l.contract_date,
           l.LA_Name, l.buyer_name, l.seller_name,
           s.description AS status
    FROM listings l
    LEFT JOIN sales_statuses s ON l.status_id=s.id
    ORDER BY l.updated_at DESC LIMIT 8
");
$recent = [];
while($r = mysqli_fetch_assoc($recent_res)) $recent[] = $r;

// Upcoming settlements (settlement milestone within next 30 days)
$upcoming_res = mysqli_query($conn,"
    SELECT l.id, l.mls_number, l.address1, l.city, l.LA_Name,
           lm.due_date AS settlement_date
    FROM listings l
    JOIN listing_milestones lm ON lm.listing_id=l.id AND lm.milestone_type='Settlement'
    WHERE lm.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
      AND lm.na_flag=0
    ORDER BY lm.due_date ASC LIMIT 5
");
$upcoming = [];
while($r = mysqli_fetch_assoc($upcoming_res)) $upcoming[] = $r;

// Rescinded this year
$resc_year = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_resc AND YEAR(date_of_listing)=$year"))['c'];

// Helpers
function fmt_money($v, $abbr=false) {
    if ($abbr) {
        if ($v >= 1000000) return '$'.number_format($v/1000000,2).'M';
        if ($v >= 1000)    return '$'.number_format($v/1000,1).'K';
    }
    return '$'.number_format($v,2);
}
function fmt_d($d) { return $d && $d!='0000-00-00' ? date('M j', strtotime($d)) : '—'; }
function days_until($d) {
    if (!$d || $d=='0000-00-00') return null;
    return (int)((strtotime($d)-time())/(60*60*24));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Larson &amp; Company</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ── Variables ──────────────────────────────────────────────── */
        :root {
            --ink:      #0d1117;
            --ink2:     #1c2333;
            --ink3:     #2d3748;
            --surface:  #ffffff;
            --surface2: #f7f8fc;
            --border:   #e8ecf4;
            --border2:  #d1d9ea;
            --gold:     #c9a84c;
            --gold2:    #f0c866;
            --navy:     #1e3a5f;
            --navy2:    #2a4f82;
            --teal:     #0d9488;
            --rose:     #e11d48;
            --amber:    #d97706;
            --green:    #059669;
            --text:     #1a202c;
            --text2:    #4a5568;
            --text3:    #718096;
            --shadow:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
            --shadow-lg:0 8px 32px rgba(0,0,0,.10), 0 2px 8px rgba(0,0,0,.06);
            --radius:   14px;
            --radius-sm:8px;
        }

        /* ── Base ───────────────────────────────────────────────────── */
        * { box-sizing:border-box; margin:0; padding:0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--surface2);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }
        .content-body { padding: 0 0 40px; }
        .page-titles   { padding: 20px 28px 0; margin-bottom: 0; }

        /* ── Dashboard wrapper ──────────────────────────────────────── */
        .dash { padding: 20px 28px; display: flex; flex-direction: column; gap: 22px; }

        /* ── Header greeting ────────────────────────────────────────── */
        .dash-header {
            display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px;
        }
        .greeting-line {
            font-family: 'Syne', sans-serif;
            font-size: 26px; font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.5px;
        }
        .greeting-sub { font-size: 13px; color: var(--text3); margin-top: 2px; }
        .year-badge {
            background: var(--ink); color: var(--gold2);
            font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
            padding: 6px 16px; border-radius: 20px; letter-spacing: 1px;
        }

        /* ── KPI strip ──────────────────────────────────────────────── */
        .kpi-strip {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 16px;
        }
        .kpi {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }
        .kpi:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .kpi::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
        }
        .kpi.gold::before   { background: linear-gradient(90deg, var(--gold), var(--gold2)); }
        .kpi.navy::before   { background: linear-gradient(90deg, var(--navy), var(--navy2)); }
        .kpi.teal::before   { background: linear-gradient(90deg, var(--teal), #14b8a6); }
        .kpi.green::before  { background: linear-gradient(90deg, var(--green), #10b981); }
        .kpi.rose::before   { background: linear-gradient(90deg, var(--rose), #fb7185); }
        .kpi.amber::before  { background: linear-gradient(90deg, var(--amber), #fbbf24); }
        .kpi-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; margin-bottom: 14px;
        }
        .kpi.gold  .kpi-icon { background: rgba(201,168,76,.12);  color: var(--gold); }
        .kpi.navy  .kpi-icon { background: rgba(30,58,95,.1);     color: var(--navy); }
        .kpi.teal  .kpi-icon { background: rgba(13,148,136,.1);   color: var(--teal); }
        .kpi.green .kpi-icon { background: rgba(5,150,105,.1);    color: var(--green); }
        .kpi.rose  .kpi-icon { background: rgba(225,29,72,.1);    color: var(--rose); }
        .kpi.amber .kpi-icon { background: rgba(217,119,6,.1);    color: var(--amber); }

        .kpi-val {
            font-family: 'Syne', sans-serif;
            font-size: 28px; font-weight: 800;
            color: var(--ink); letter-spacing: -1px; line-height: 1;
            margin-bottom: 4px;
        }
        .kpi-label { font-size: 12px; color: var(--text3); font-weight: 500; text-transform: uppercase; letter-spacing: .6px; }
        .kpi-sub   { font-size: 11.5px; color: var(--text2); margin-top: 6px; }
        .kpi-sub span { font-weight: 600; }

        /* ── Stat row ────────────────────────────────────────────── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 14px;
        }
        .stat-pill {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: var(--shadow);
        }
        .stat-dot {
            width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        }
        .stat-pill-val { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--ink); }
        .stat-pill-lbl { font-size:11px; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; margin-top:1px; }

        /* ── Two / three column layout ──────────────────────────────── */
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .row-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .row-32{ display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px; }

        /* ── Card ───────────────────────────────────────────────────── */
        .card2 {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 24px;
            box-shadow: var(--shadow);
        }
        .card2-title {
            font-family: 'Syne', sans-serif;
            font-size: 13px; font-weight: 700;
            color: var(--navy); letter-spacing: .4px;
            text-transform: uppercase;
            margin-bottom: 18px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card2-title a {
            font-family: 'DM Sans', sans-serif;
            font-size: 12px; font-weight: 500;
            color: var(--navy2); text-decoration: none; text-transform: none;
            letter-spacing: 0;
        }
        .card2-title a:hover { text-decoration: underline; }

        /* ── Chart containers ───────────────────────────────────────── */
        .chart-wrap { position: relative; height: 220px; }
        .chart-wrap-sm { position: relative; height: 180px; }

        /* ── Recent transactions table ──────────────────────────────── */
        .rt-table { width: 100%; border-collapse: collapse; }
        .rt-table th {
            font-size: 10.5px; font-weight: 600; color: var(--text3);
            text-transform: uppercase; letter-spacing: .6px;
            padding: 0 10px 10px; text-align: left; white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }
        .rt-table td {
            padding: 10px; font-size: 12.5px; color: var(--text2);
            border-bottom: 1px solid var(--border2);
            vertical-align: middle;
        }
        .rt-table tr:last-child td { border-bottom: none; }
        .rt-table tr:hover td { background: var(--surface2); }
        .rt-mls { font-weight: 600; color: var(--ink); font-family: 'Syne', sans-serif; font-size: 12px; }
        .rt-addr { color: var(--text); font-weight: 500; }
        .rt-agent{ color: var(--text3); font-size: 11.5px; }
        .status-chip {
            display: inline-block; padding: 2px 9px; border-radius: 20px;
            font-size: 10.5px; font-weight: 600; white-space: nowrap;
        }
        .sc-closed  { background:#d1fae5; color:#065f46; }
        .sc-listed  { background:#dbeafe; color:#1e40af; }
        .sc-uc      { background:#fef3c7; color:#92400e; }
        .sc-resc    { background:#fee2e2; color:#991b1b; }

        /* ── Top agents ─────────────────────────────────────────────── */
        .agent-bar-row { margin-bottom: 14px; }
        .agent-bar-row:last-child { margin-bottom: 0; }
        .ab-top { display:flex; justify-content:space-between; margin-bottom:5px; }
        .ab-name { font-size:13px; font-weight:600; color:var(--ink); }
        .ab-vol  { font-size:12px; color:var(--text3); }
        .ab-track { height:6px; background:var(--border); border-radius:3px; overflow:hidden; }
        .ab-fill  { height:100%; border-radius:3px;
                    background: linear-gradient(90deg, var(--navy), var(--navy2)); transition: width 1s ease; }
        .ab-cnt   { font-size:11px; color:var(--text3); margin-top:3px; }

        /* ── Lead sources ───────────────────────────────────────────── */
        .lead-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 4px;
        }
        .lead-item {
            display: flex; align-items: center; gap: 9px;
            background: var(--surface2); border-radius: var(--radius-sm);
            padding: 10px 12px;
        }
        .lead-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .lead-name { font-size:12.5px; font-weight:500; color:var(--text); }
        .lead-cnt  { margin-left:auto; font-size:12px; font-weight:700; color:var(--ink); font-family:'Syne',sans-serif; }

        /* ── Upcoming settlements ───────────────────────────────────── */
        .settle-row {
            display:flex; align-items:center; gap:14px;
            padding: 11px 0; border-bottom:1px solid var(--border2);
        }
        .settle-row:last-child { border-bottom:none; }
        .settle-days {
            min-width:46px; height:46px; border-radius:10px;
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            font-family:'Syne',sans-serif;
        }
        .settle-days.urgent   { background:#fee2e2; color:#991b1b; }
        .settle-days.soon     { background:#fef3c7; color:#92400e; }
        .settle-days.upcoming { background:#dbeafe; color:#1e40af; }
        .settle-days-num { font-size:16px; font-weight:800; line-height:1; }
        .settle-days-lbl { font-size:8.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        .settle-addr { font-weight:600; color:var(--ink); font-size:13px; }
        .settle-agent{ font-size:11.5px; color:var(--text3); margin-top:1px; }
        .settle-date { margin-left:auto; font-size:12px; font-weight:600; color:var(--text2); white-space:nowrap; }

        /* ── Empty state ────────────────────────────────────────────── */
        .empty { text-align:center; padding:24px; color:var(--text3); font-size:13px; }

        /* ── Animate in ─────────────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(16px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .kpi      { animation: fadeUp .4s ease both; }
        .stat-pill{ animation: fadeUp .4s ease both; }
        .card2    { animation: fadeUp .5s ease both; }
        .kpi:nth-child(1){ animation-delay:.05s; }
        .kpi:nth-child(2){ animation-delay:.10s; }
        .kpi:nth-child(3){ animation-delay:.15s; }
        .kpi:nth-child(4){ animation-delay:.20s; }
        .stat-pill:nth-child(1){ animation-delay:.22s; }
        .stat-pill:nth-child(2){ animation-delay:.27s; }
        .stat-pill:nth-child(3){ animation-delay:.32s; }
        .stat-pill:nth-child(4){ animation-delay:.37s; }

        @media(max-width:1200px){
            .kpi-strip,.stat-row{ grid-template-columns:1fr 1fr; }
            .row-3,.row-32,.row-2{ grid-template-columns:1fr; }
        }
        @media(max-width:640px){
            .kpi-strip,.stat-row{ grid-template-columns:1fr; }
            .dash{ padding:14px 16px; }
        }
    </style>
</head>
<body>
<?php include('header.php'); ?>

<div class="content-body">
<div class="page-titles">
    <ol class="breadcrumb" style="margin:0">
        <li><h5 class="bc-title">Dashboard</h5></li>
    </ol>
</div>

<div class="dash">

    <!-- ── Greeting ───────────────────────────────────────────────────── -->
    <div class="dash-header">
        <div>
            <div class="greeting-line">
                Good <?= (date('H')<12)?'Morning':(date('H')<17?'Afternoon':'Evening') ?>,
                <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?> 👋
            </div>
            <div class="greeting-sub">
                <?= date('l, F j, Y') ?> &nbsp;·&nbsp; Larson &amp; Company Real Estate
            </div>
        </div>
        <div class="year-badge"><?= $year ?> YTD</div>
    </div>


    <!-- ── KPI strip ──────────────────────────────────────────────────── -->
    <div class="kpi-strip">

        <div class="kpi gold">
            <div class="kpi-icon">💰</div>
            <div class="kpi-val"><?= fmt_money($vol_year, true) ?></div>
            <div class="kpi-label">Total Volume YTD</div>
            <div class="kpi-sub">This month <span><?= fmt_money($vol_month, true) ?></span></div>
        </div>

        <div class="kpi navy">
            <div class="kpi-icon">🏠</div>
            <div class="kpi-val"><?= $closed_year ?></div>
            <div class="kpi-label">Closed Transactions YTD</div>
            <div class="kpi-sub">This month <span><?= $closed_month ?></span></div>
        </div>

        <div class="kpi teal">
            <div class="kpi-icon">📋</div>
            <div class="kpi-val"><?= $active ?></div>
            <div class="kpi-label">Active Listings &amp; UC</div>
            <div class="kpi-sub">Under Contract <span><?= $uc_count ?></span></div>
        </div>

        <div class="kpi green">
            <div class="kpi-icon">💵</div>
            <div class="kpi-val"><?= fmt_money($comm_year, true) ?></div>
            <div class="kpi-label">Commission Earned YTD</div>
            <div class="kpi-sub">Avg sale price <span><?= fmt_money($avg_price, true) ?></span></div>
        </div>

    </div>


    <!-- ── Stat pills ──────────────────────────────────────────────────── -->
    <div class="stat-row">
        <div class="stat-pill">
            <div class="stat-dot" style="background:#1e3a5f"></div>
            <div>
                <div class="stat-pill-val"><?= $total_listings ?></div>
                <div class="stat-pill-lbl">Total Listings</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-dot" style="background:#0d9488"></div>
            <div>
                <div class="stat-pill-val"><?= $uc_count ?></div>
                <div class="stat-pill-lbl">Under Contract</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-dot" style="background:#d97706"></div>
            <div>
                <div class="stat-pill-val"><?= $avg_dom ?></div>
                <div class="stat-pill-lbl">Avg Days on Market</div>
            </div>
        </div>
        <div class="stat-pill">
            <div class="stat-dot" style="background:#e11d48"></div>
            <div>
                <div class="stat-pill-val"><?= $resc_year ?></div>
                <div class="stat-pill-lbl">Rescinded YTD</div>
            </div>
        </div>
    </div>


    <!-- ── Row: Monthly Chart + Lead Sources ─────────────────────────── -->
    <div class="row-3">

        <div class="card2">
            <div class="card2-title">
                Monthly Performance <?= $year ?>
                <a href="reports.php?tab=progress">View Progress →</a>
            </div>
            <div class="chart-wrap">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="card2">
            <div class="card2-title">Lead Sources</div>
            <?php if ($lead_data): ?>
            <div class="chart-wrap-sm">
                <canvas id="leadChart"></canvas>
            </div>
            <?php else: ?>
            <div class="empty">No closed data yet</div>
            <?php endif; ?>
        </div>

    </div>


    <!-- ── Row: Top Agents + Upcoming Settlements ────────────────────── -->
    <div class="row-32">

        <div class="card2">
            <div class="card2-title">
                Upcoming Settlements
                <span style="font-family:'DM Sans';font-size:11px;color:var(--text3);font-weight:400;text-transform:none;letter-spacing:0">Next 30 days</span>
            </div>
            <?php if ($upcoming): ?>
            <?php foreach ($upcoming as $u):
                $days = days_until($u['settlement_date']);
                $cls  = $days <= 7 ? 'urgent' : ($days <= 14 ? 'soon' : 'upcoming');
            ?>
            <div class="settle-row">
                <div class="settle-days <?= $cls ?>">
                    <div class="settle-days-num"><?= $days ?></div>
                    <div class="settle-days-lbl">days</div>
                </div>
                <div>
                    <div class="settle-addr">
                        <?= htmlspecialchars($u['address1'] ?? 'Address N/A') ?>
                        <?= $u['city'] ? ', '.htmlspecialchars($u['city']) : '' ?>
                    </div>
                    <div class="settle-agent">
                        <?= htmlspecialchars($u['LA_Name'] ?? '') ?>
                        &nbsp;·&nbsp; MLS <?= htmlspecialchars($u['mls_number']) ?>
                    </div>
                </div>
                <div class="settle-date"><?= fmt_d($u['settlement_date']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty">🎉 No settlements due in the next 30 days</div>
            <?php endif; ?>
        </div>

        <div class="card2">
            <div class="card2-title">
                Top Agents by Volume
                <a href="reports.php?tab=summary">Full Summary →</a>
            </div>
            <?php if ($top_agents):
                $max_vol = max(array_column($top_agents,'volume')) ?: 1;
                $colors  = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48'];
            ?>
            <?php foreach ($top_agents as $i => $ag): ?>
            <div class="agent-bar-row">
                <div class="ab-top">
                    <span class="ab-name"><?= htmlspecialchars($ag['aname']) ?></span>
                    <span class="ab-vol"><?= fmt_money($ag['volume'],true) ?></span>
                </div>
                <div class="ab-track">
                    <div class="ab-fill"
                         style="width:<?= round($ag['volume']/$max_vol*100) ?>%;background:<?= $colors[$i] ?>"></div>
                </div>
                <div class="ab-cnt"><?= $ag['closed_count'] ?> closed</div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty">No closed transactions yet</div>
            <?php endif; ?>
        </div>

    </div>


    <!-- ── Recent Transactions ───────────────────────────────────────── -->
    <div class="card2">
        <div class="card2-title">
            Recent Transactions
            <a href="transactions.php">View All →</a>
        </div>
        <?php if ($recent): ?>
        <div style="overflow-x:auto">
        <table class="rt-table">
            <thead>
                <tr>
                    <th>MLS / TN</th>
                    <th>Property</th>
                    <th>Agent</th>
                    <th>Buyer</th>
                    <th>Seller</th>
                    <th>Price</th>
                    <th>Key Date</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row):
                $status_cls = match(strtolower($row['status'] ?? '')) {
                    'closed'         => 'sc-closed',
                    'listed'         => 'sc-listed',
                    'under contract' => 'sc-uc',
                    'rescinded'      => 'sc-resc',
                    default          => 'sc-listed',
                };
                $key_date = $row['closing_date'] ?? $row['contract_date'] ?? null;
            ?>
            <tr>
                <td>
                    <div class="rt-mls"><?= htmlspecialchars($row['mls_number'] ?? '—') ?></div>
                    <div class="rt-agent"><?= htmlspecialchars($row['transaction_number'] ?? '') ?></div>
                </td>
                <td>
                    <div class="rt-addr"><?= htmlspecialchars($row['address1'] ?? '—') ?></div>
                    <div class="rt-agent"><?= htmlspecialchars($row['city'] ?? '') ?></div>
                </td>
                <td><?= htmlspecialchars($row['LA_Name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['buyer_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['seller_name'] ?? '—') ?></td>
                <td style="font-weight:600;color:var(--ink)">
                    <?= $row['final_price'] ? fmt_money($row['final_price'],true) : '—' ?>
                </td>
                <td><?= fmt_d($key_date) ?></td>
                <td><span class="status-chip <?= $status_cls ?>"><?= htmlspecialchars($row['status'] ?? '') ?></span></td>
                <td>
                    <a href="transaction_detail.php?id=<?= $row['id'] ?>"
                       style="font-size:11.5px;color:var(--navy2);text-decoration:none;font-weight:600">
                        Edit →
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div class="empty">No transactions yet. <a href="transaction_detail.php?action=create">Create the first one →</a></div>
        <?php endif; ?>
    </div>


</div><!-- /.dash -->
</div><!-- /.content-body -->

<?php include('footer.php'); ?>

<script>
// ── Chart.js defaults ────────────────────────────────────────────────────
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color       = '#718096';

const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// ── Monthly performance — dual axis bar + line ────────────────────────────
const closedData = <?= json_encode(array_values($monthly_closed)) ?>;
const volumeData = <?= json_encode(array_values($monthly_volume)) ?>;

new Chart(document.getElementById('monthlyChart'), {
    data: {
        labels: months,
        datasets: [
            {
                type: 'bar',
                label: 'Closed',
                data: closedData,
                backgroundColor: 'rgba(30,58,95,0.15)',
                borderColor: '#1e3a5f',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y',
            },
            {
                type: 'line',
                label: 'Volume ($)',
                data: volumeData,
                borderColor: '#c9a84c',
                backgroundColor: 'rgba(201,168,76,0.08)',
                borderWidth: 2.5,
                pointBackgroundColor: '#c9a84c',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4,
                yAxisID: 'y2',
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.datasetIndex === 1
                        ? ' $' + ctx.parsed.y.toLocaleString('en-US', {minimumFractionDigits:0})
                        : ' ' + ctx.parsed.y + ' closed'
                }
            }
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                type: 'linear', position: 'left',
                ticks: { stepSize: 1, precision: 0 },
                grid: { color: '#e8ecf4' },
                title: { display: true, text: 'Closed', font: { size: 11 } }
            },
            y2: {
                type: 'linear', position: 'right',
                grid: { drawOnChartArea: false },
                ticks: {
                    callback: v => '$' + (v >= 1000000 ? (v/1000000).toFixed(1)+'M' : (v/1000).toFixed(0)+'K')
                },
                title: { display: true, text: 'Volume', font: { size: 11 } }
            }
        }
    }
});

// ── Lead sources doughnut ─────────────────────────────────────────────────
<?php if ($lead_data): ?>
const leadLabels = <?= json_encode(array_column($lead_data,'src')) ?>;
const leadCounts = <?= json_encode(array_column($lead_data,'cnt')) ?>;
const leadColors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#2a4f82'];

new Chart(document.getElementById('leadChart'), {
    type: 'doughnut',
    data: {
        labels: leadLabels,
        datasets: [{
            data: leadCounts,
            backgroundColor: leadColors,
            borderWidth: 2,
            borderColor: '#fff',
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 10, padding: 12, font: { size: 11 } }
            }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>