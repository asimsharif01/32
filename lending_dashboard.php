<?php
// lending_dashboard.php — Laser Lending main dashboard
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── View toggle — YTD vs All-Time ──────────────────────────────────────────
$view = ($_GET['view'] ?? 'alltime') === 'alltime' ? 'alltime' : 'ytd';

// ── Date ranges ────────────────────────────────────────────────────────────
$cur_year_start = date('Y-01-01');
$cur_year_end   = date('Y-12-31');
$cur_month_start = date('Y-m-01');
$cur_month_end   = date('Y-m-t');
$year = date('Y');

// SQL fragment for the main KPI date filter
$dateFilter = ($view === 'ytd')
    ? "AND date_closed BETWEEN '$cur_year_start' AND '$cur_year_end'"
    : "";

// ── Status ID lookup ──────────────────────────────────────────────────────
function statusId($conn, $name) {
    $stmt = $conn->prepare("SELECT id FROM loan_statuses WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ? intval($r['id']) : 0;
}
$s_funded    = statusId($conn, 'Funded');
$s_rescinded = statusId($conn, 'Rescinded');
$s_submitted = statusId($conn, 'Submitted');

// ── KPIs ────────────────────────────────────────────────────────────────────
$funded_year = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c FROM loans
    WHERE status_id = $s_funded $dateFilter
"))['c'] ?? 0;

$funded_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c FROM loans
    WHERE status_id = $s_funded AND date_closed BETWEEN '$cur_month_start' AND '$cur_month_end'
"))['c'] ?? 0;

$volume_year = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(IFNULL(loan_1_amount,0) + IFNULL(loan_2_amount,0)) v FROM loans
    WHERE status_id = $s_funded $dateFilter
"))['v'] ?? 0;

$volume_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(IFNULL(loan_1_amount,0) + IFNULL(loan_2_amount,0)) v FROM loans
    WHERE status_id = $s_funded AND date_closed BETWEEN '$cur_month_start' AND '$cur_month_end'
"))['v'] ?? 0;

$revenue_year = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(IFNULL(lo_revenue,0) + IFNULL(processing_fee,0)) v FROM loans
    WHERE status_id = $s_funded $dateFilter
"))['v'] ?? 0;

$active_pipeline = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c FROM loans WHERE status_id = $s_submitted
"))['c'] ?? 0;

$total_loans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM loans"))['c'] ?? 0;

$rescinded_year = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c FROM loans
    WHERE status_id = $s_rescinded $dateFilter
"))['c'] ?? 0;

$avg_loan = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT AVG(IFNULL(loan_1_amount,0) + IFNULL(loan_2_amount,0)) a FROM loans
    WHERE status_id = $s_funded $dateFilter
"))['a'] ?? 0;

// ── Performance chart data ──────────────────────────────────────────────────
if ($view === 'ytd') {
    $chart_labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $chart_funded = array_fill(0, 12, 0);
    $chart_volume = array_fill(0, 12, 0);
    $mc = mysqli_query($conn, "
        SELECT MONTH(date_closed) period, COUNT(*) cnt,
               SUM(IFNULL(loan_1_amount,0)+IFNULL(loan_2_amount,0)) vol
        FROM loans
        WHERE status_id = $s_funded AND date_closed BETWEEN '$cur_year_start' AND '$cur_year_end'
        GROUP BY MONTH(date_closed)
    ");
    while ($r = mysqli_fetch_assoc($mc)) {
        $idx = intval($r['period']) - 1;
        $chart_funded[$idx] = intval($r['cnt']);
        $chart_volume[$idx] = floatval($r['vol']);
    }
} else {
    $yc = mysqli_query($conn, "
        SELECT YEAR(date_closed) yr, COUNT(*) cnt,
               SUM(IFNULL(loan_1_amount,0)+IFNULL(loan_2_amount,0)) vol
        FROM loans
        WHERE status_id = $s_funded AND date_closed IS NOT NULL
        GROUP BY YEAR(date_closed) ORDER BY yr ASC
    ");
    $chart_labels = []; $chart_funded = []; $chart_volume = [];
    while ($r = mysqli_fetch_assoc($yc)) {
        $chart_labels[] = $r['yr'];
        $chart_funded[] = intval($r['cnt']);
        $chart_volume[] = floatval($r['vol']);
    }
}

// ── Referral sources ─────────────────────────────────────────────────────
$referral_data = [];
$rd = mysqli_query($conn, "
    SELECT IFNULL(rs.name,'Unknown') AS src, COUNT(*) cnt
    FROM loans l
    LEFT JOIN loan_referral_sources rs ON l.referral_source_id = rs.id
    WHERE l.status_id = $s_funded $dateFilter
    GROUP BY src ORDER BY cnt DESC LIMIT 6
");
while ($r = mysqli_fetch_assoc($rd)) $referral_data[] = $r;
$total_referrals = array_sum(array_column($referral_data, 'cnt'));

// ── Top consultants by volume ──────────────────────────────────────────────
$top_consultants = [];
$active_consultants_count = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) c FROM loan_consultants WHERE active = 1
"))['c'] ?? 0;
$tc = mysqli_query($conn, "
    SELECT lc.name AS cname,
           COUNT(*) funded_count,
           SUM(IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) volume
    FROM loans l
    JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
    WHERE l.status_id = $s_funded $dateFilter
    GROUP BY lc.name ORDER BY volume DESC LIMIT 5
");
while ($r = mysqli_fetch_assoc($tc)) $top_consultants[] = $r;
$volumes = array_filter(array_column($top_consultants, 'volume'), fn($v) => $v > 0);
$max_volume = $volumes ? max($volumes) : 1;

// ── Recent loans ────────────────────────────────────────────────────────────
$recent = [];
$rr = mysqli_query($conn, "
    SELECT l.id, l.transaction_no, l.borrower_name,
           lc.name AS consultant_name, ls.name AS status_name,
           (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS total_loan,
           l.date_closed, l.date_submitted
    FROM loans l
    LEFT JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
    LEFT JOIN loan_statuses    ls ON l.status_id = ls.id
    ORDER BY l.updated_at DESC LIMIT 8
");
while ($r = mysqli_fetch_assoc($rr)) $recent[] = $r;

// ── Helpers ───────────────────────────────────────────────────────────────
function fmtMoney($v, $abbr = false) {
    if ($abbr) {
        if ($v >= 1000000) return '$' . number_format($v / 1000000, 2) . 'M';
        if ($v >= 1000)    return '$' . number_format($v / 1000, 1) . 'K';
    }
    return '$' . number_format($v, 2);
}
function fmtD($d) { return ($d && $d != '0000-00-00') ? date('M j', strtotime($d)) : '—'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Laser Lending</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- Use Chart.js v3 (more stable and compatible) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <style>
        :root {
            --ink:      #0d1117;
            --surface:  #ffffff;
            --surface2: #f7f8fc;
            --border:   #e8ecf4;
            --border2:  #d1d9ea;
            --navy:     #1e3a5f;
            --navy2:    #2a4f82;
            --red:      #c0392b;
            --red2:     #e74c3c;
            --teal:     #0d9488;
            --gold:     #c9a84c;
            --green:    #059669;
            --amber:    #d97706;
            --text:     #1a202c;
            --text2:    #4a5568;
            --text3:    #718096;
            --shadow:   0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
            --radius:   14px;
            --radius-sm:8px;
        }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DM Sans', sans-serif; background:var(--surface2); color:var(--text); font-size:14px; line-height:1.5; }
        .content-body { padding:0 0 40px; }
        .page-titles { padding:20px 28px 0; margin-bottom:0; }
        .dash { padding:20px 28px; display:flex; flex-direction:column; gap:22px; }

        .dash-header { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:12px; }
        .greeting-line { font-family:'Syne', sans-serif; font-size:26px; font-weight:800; color:var(--navy); letter-spacing:-0.5px; }
        .greeting-sub { font-size:13px; color:var(--text3); margin-top:2px; }
        .year-badge { background:var(--ink); color:#f0c866; font-family:'Syne', sans-serif; font-size:13px; font-weight:700; padding:6px 16px; border-radius:20px; letter-spacing:1px; }

        .kpi-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
        .kpi { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; box-shadow:var(--shadow); position:relative; overflow:hidden; }
        .kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi.red::before   { background:linear-gradient(90deg, var(--red), var(--red2)); }
        .kpi.navy::before  { background:linear-gradient(90deg, var(--navy), var(--navy2)); }
        .kpi.teal::before  { background:linear-gradient(90deg, var(--teal), #14b8a6); }
        .kpi.green::before { background:linear-gradient(90deg, var(--green), #10b981); }
        .kpi-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; }
        .kpi.red   .kpi-icon { background:rgba(192,57,43,.1);  color:var(--red); }
        .kpi.navy  .kpi-icon { background:rgba(30,58,95,.1);   color:var(--navy); }
        .kpi.teal  .kpi-icon { background:rgba(13,148,136,.1); color:var(--teal); }
        .kpi.green .kpi-icon { background:rgba(5,150,105,.1);  color:var(--green); }
        .kpi-val { font-family:'Syne', sans-serif; font-size:28px; font-weight:800; color:var(--ink); letter-spacing:-1px; line-height:1; margin-bottom:4px; }
        .kpi-label { font-size:12px; color:var(--text3); font-weight:500; text-transform:uppercase; letter-spacing:.6px; }
        .kpi-sub { font-size:11.5px; color:var(--text2); margin-top:6px; }
        .kpi-sub span { font-weight:600; }

        .stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
        .stat-pill { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; display:flex; align-items:center; gap:12px; box-shadow:var(--shadow); }
        .stat-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .stat-pill-val { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--ink); }
        .stat-pill-lbl { font-size:11px; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; margin-top:1px; }

        .row-3 { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .row-32 { display:grid; grid-template-columns:1fr 1.6fr; gap:20px; }

        .card2 { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:22px 24px; box-shadow:var(--shadow); }
        .card2-title { font-family:'Syne', sans-serif; font-size:13px; font-weight:700; color:var(--navy); letter-spacing:.4px; text-transform:uppercase; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; }
        .card2-title a { font-family:'DM Sans', sans-serif; font-size:12px; font-weight:500; color:var(--navy2); text-decoration:none; text-transform:none; letter-spacing:0; }

        .chart-wrap { position:relative; height:220px; width:100%; }

        .ref-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
        .ref-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
        .ref-name { flex:1; font-size:12.5px; font-weight:500; }
        .ref-cnt  { font-family:'Syne',sans-serif; font-size:13px; font-weight:700; color:var(--ink); }
        .ref-pct  { font-size:11px; color:var(--text3); min-width:32px; text-align:right; }
        .ref-bar  { height:4px; background:var(--border); border-radius:2px; margin-left:18px; margin-bottom:7px; }
        .ref-bar-fill { height:100%; border-radius:2px; }
        .ref-total { margin-top:10px; padding-top:10px; border-top:1px solid var(--border); font-size:11.5px; color:var(--text3); }

        .ab-row { margin-bottom:14px; }
        .ab-top { display:flex; justify-content:space-between; margin-bottom:5px; }
        .ab-name { font-size:13px; font-weight:600; color:var(--ink); }
        .ab-vol  { font-size:12px; color:var(--text3); }
        .ab-track { height:6px; background:var(--border); border-radius:3px; overflow:hidden; }
        .ab-fill  { height:100%; border-radius:3px; background:linear-gradient(90deg, var(--navy), var(--navy2)); }
        .ab-cnt   { font-size:11px; color:var(--text3); margin-top:3px; }

        .rt-table { width:100%; border-collapse:collapse; }
        .rt-table th { font-size:10.5px; font-weight:600; color:var(--text3); text-transform:uppercase; letter-spacing:.6px; padding:0 10px 10px; text-align:left; white-space:nowrap; border-bottom:1px solid var(--border); }
        .rt-table td { padding:10px; font-size:12.5px; color:var(--text2); border-bottom:1px solid var(--border2); vertical-align:middle; }
        .rt-table tr:last-child td { border-bottom:none; }
        .status-chip { display:inline-block; padding:2px 9px; border-radius:20px; font-size:10.5px; font-weight:600; white-space:nowrap; }
        .sc-funded    { background:#d1fae5; color:#065f46; }
        .sc-submitted { background:#dbeafe; color:#1e40af; }
        .sc-rescinded { background:#fee2e2; color:#991b1b; }
        .sc-default   { background:#f1f5f9; color:#475569; }

        .empty { text-align:center; padding:24px; color:var(--text3); font-size:13px; }

        @media(max-width:1200px){ .kpi-strip,.stat-row{ grid-template-columns:1fr 1fr; } .row-3,.row-32{ grid-template-columns:1fr; } }
        @media(max-width:640px){ .kpi-strip,.stat-row{ grid-template-columns:1fr; } .dash{ padding:14px 16px; } }
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

    <div class="dash-header">
        <div>
            <div class="greeting-line">
                Good <?= (date('H')<12)?'Morning':(date('H')<17?'Afternoon':'Evening') ?>,
                <?= htmlspecialchars(explode(' ', $_SESSION['name'] ?? 'there')[0]) ?> 👋
            </div>
            <div class="greeting-sub"><?= date('l, F j, Y') ?> &nbsp;·&nbsp; Laser Lending</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            <div style="display:flex;background:#fff;border:1px solid var(--border);border-radius:20px;padding:3px;box-shadow:var(--shadow)">
                <a href="?view=ytd" style="text-decoration:none;padding:6px 16px;border-radius:16px;font-size:12px;font-weight:700;font-family:'Syne',sans-serif;<?= $view==='ytd' ? 'background:var(--navy);color:#fff;' : 'color:var(--text3);' ?>">
                    <?= $year ?> YTD
                </a>
                <a href="?view=alltime" style="text-decoration:none;padding:6px 16px;border-radius:16px;font-size:12px;font-weight:700;font-family:'Syne',sans-serif;<?= $view==='alltime' ? 'background:var(--navy);color:#fff;' : 'color:var(--text3);' ?>">
                    All-Time
                </a>
            </div>
        </div>
    </div>

    <?php $period_label = $view === 'ytd' ? 'YTD' : 'All-Time'; ?>

    <!-- KPI strip -->
    <div class="kpi-strip">
        <div class="kpi red">
            <div class="kpi-icon">💰</div>
            <div class="kpi-val"><?= fmtMoney($volume_year, true) ?></div>
            <div class="kpi-label">Total Volume <?= $period_label ?></div>
            <div class="kpi-sub">This month <span><?= fmtMoney($volume_month, true) ?></span></div>
        </div>
        <div class="kpi navy">
            <div class="kpi-icon">🏦</div>
            <div class="kpi-val"><?= $funded_year ?></div>
            <div class="kpi-label">Funded Loans <?= $period_label ?></div>
            <div class="kpi-sub">This month <span><?= $funded_month ?></span></div>
        </div>
        <div class="kpi teal">
            <div class="kpi-icon">📋</div>
            <div class="kpi-val"><?= $active_pipeline ?></div>
            <div class="kpi-label">Active Pipeline</div>
            <div class="kpi-sub">Total loans <span><?= $total_loans ?></span></div>
        </div>
        <div class="kpi green">
            <div class="kpi-icon">💵</div>
            <div class="kpi-val"><?= fmtMoney($revenue_year, true) ?></div>
            <div class="kpi-label">Revenue Earned <?= $period_label ?></div>
            <div class="kpi-sub">Avg loan <span><?= fmtMoney($avg_loan, true) ?></span></div>
        </div>
    </div>

    <!-- Stat pills -->
    <div class="stat-row">
        <div class="stat-pill"><div class="stat-dot" style="background:#1e3a5f"></div><div><div class="stat-pill-val"><?= $total_loans ?></div><div class="stat-pill-lbl">Total Loans</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#0d9488"></div><div><div class="stat-pill-val"><?= $active_pipeline ?></div><div class="stat-pill-lbl">In Pipeline</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#d97706"></div><div><div class="stat-pill-val"><?= $active_consultants_count ?></div><div class="stat-pill-lbl">Active Consultants</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#c0392b"></div><div><div class="stat-pill-val"><?= $rescinded_year ?></div><div class="stat-pill-lbl">Rescinded <?= $period_label ?></div></div></div>
    </div>

    <!-- Monthly chart + Referral sources -->
    <div class="row-3">
        <div class="card2">
            <div class="card2-title">
                <?= $view === 'ytd' ? 'Monthly Performance ' . $year : 'Performance by Year' ?>
                <a href="lending_report.php">View Progress →</a>
            </div>
            <div class="chart-wrap">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <div class="card2">
            <div class="card2-title">Referral Sources <span style="font-weight:400;font-size:11px;color:var(--text3);text-transform:none">Funded <?= $period_label ?></span></div>
            <?php if ($referral_data):
                $ref_colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#c0392b','#7c3aed'];
            ?>
            <?php foreach ($referral_data as $i => $rdrow):
                $pct = $total_referrals > 0 ? round($rdrow['cnt'] / $total_referrals * 100) : 0;
                $col = $ref_colors[$i % count($ref_colors)];
            ?>
            <div class="ref-row">
                <div class="ref-dot" style="background:<?= $col ?>"></div>
                <div class="ref-name"><?= htmlspecialchars($rdrow['src']) ?></div>
                <div class="ref-cnt"><?= $rdrow['cnt'] ?></div>
                <div class="ref-pct"><?= $pct ?>%</div>
            </div>
            <div class="ref-bar"><div class="ref-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
            <?php endforeach; ?>
            <div class="ref-total">Total funded: <strong style="color:var(--ink)"><?= $total_referrals ?></strong> loans</div>
            <?php else: ?>
            <div class="empty">No funded loans yet — referral data will appear here.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Consultants + Recent Loans -->
    <div class="row-32">
        <div class="card2">
            <div class="card2-title">Top Consultants by Volume</div>
            <?php if ($top_consultants):
                $colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#c0392b'];
            ?>
            <?php foreach ($top_consultants as $i => $tcrow): ?>
            <div class="ab-row">
                <div class="ab-top">
                    <span class="ab-name"><?= htmlspecialchars($tcrow['cname']) ?></span>
                    <span class="ab-vol"><?= fmtMoney($tcrow['volume'], true) ?></span>
                </div>
                <div class="ab-track"><div class="ab-fill" style="width:<?= round($tcrow['volume']/$max_volume*100) ?>%;background:<?= $colors[$i] ?>"></div></div>
                <div class="ab-cnt"><?= $tcrow['funded_count'] ?> funded</div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty">No funded loans yet</div>
            <?php endif; ?>
        </div>

        <div class="card2">
            <div class="card2-title">
                Recent Loans
                <a href="lending_loans.php">View All →</a>
            </div>
            <?php if ($recent): ?>
            <div style="overflow-x:auto">
            <table class="rt-table">
                <thead><tr><th>Trans #</th><th>Borrower</th><th>Consultant</th><th>Loan Amount</th><th>Date</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row):
                    $st = strtolower($row['status_name'] ?? '');
                    $chip = $st === 'funded' ? 'sc-funded' : ($st === 'submitted' ? 'sc-submitted' : ($st === 'rescinded' ? 'sc-rescinded' : 'sc-default'));
                    $kd = $row['date_closed'] ?: $row['date_submitted'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['transaction_no'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['borrower_name']) ?></td>
                    <td><?= htmlspecialchars($row['consultant_name'] ?? '—') ?></td>
                    <td style="font-weight:600;color:var(--ink)"><?= fmtMoney($row['total_loan'], true) ?></td>
                    <td><?= fmtD($kd) ?></td>
                    <td><span class="status-chip <?= $chip ?>"><?= htmlspecialchars($row['status_name'] ?? '') ?></span></td>
                    <td><a href="lending_loan_detail.php?id=<?= $row['id'] ?>" style="font-size:11.5px;color:var(--navy2);text-decoration:none;font-weight:600">Edit →</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="empty">No loans yet. <a href="lending_loan_detail.php?action=create">Add the first one →</a></div>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<?php include('footer.php'); ?>

<!-- Chart.js script - using v3 syntax which is more stable -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart is available
    if (typeof Chart === 'undefined') {
        console.error('Chart.js not loaded');
        const wrap = document.querySelector('.chart-wrap');
        if (wrap) {
            wrap.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#c0392b;font-size:14px;padding:20px;text-align:center;">⚠️ Chart library failed to load</div>';
        }
        return;
    }

    // Get data from PHP
    const months = <?= json_encode($chart_labels) ?>;
    const fundedData = <?= json_encode($chart_funded) ?>;
    const volumeData = <?= json_encode($chart_volume) ?>;
    
    console.log('Chart data:', { months, fundedData, volumeData });

    // Check if there's any data to display
    const hasData = fundedData.some(v => v > 0) || volumeData.some(v => v > 0);
    
    if (!hasData || months.length === 0) {
        const wrap = document.querySelector('.chart-wrap');
        if (wrap) {
            wrap.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#718096;font-size:14px;">No data available for this period</div>';
        }
        return;
    }

    // Get canvas element
    const ctx = document.getElementById('monthlyChart');
    if (!ctx) {
        console.error('Canvas element not found');
        return;
    }

    // Destroy existing chart if any
    if (window.myChart) {
        window.myChart.destroy();
        window.myChart = null;
    }

    try {
        // Create chart with v3 syntax
        window.myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Funded Loans',
                        data: fundedData,
                        backgroundColor: 'rgba(30,58,95,0.7)',
                        borderColor: '#1e3a5f',
                        borderWidth: 2,
                        borderRadius: 4,
                        order: 2
                    },
                    {
                        label: 'Volume ($)',
                        type: 'line',
                        data: volumeData,
                        borderColor: '#c0392b',
                        backgroundColor: 'rgba(192,57,43,0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#c0392b',
                        pointBorderColor: '#c0392b',
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        fill: true,
                        tension: 0.3,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 16,
                            font: {
                                family: "'DM Sans', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.parsed.y;
                                if (context.datasetIndex === 1) {
                                    return label + ': $' + value.toLocaleString('en-US');
                                }
                                return label + ': ' + value;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            color: '#e8ecf4'
                        },
                        title: {
                            display: true,
                            text: 'Funded Loans',
                            font: {
                                size: 11
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) return '$' + (value/1000000).toFixed(1) + 'M';
                                if (value >= 1000) return '$' + (value/1000).toFixed(0) + 'K';
                                return '$' + value;
                            }
                        },
                        title: {
                            display: true,
                            text: 'Volume',
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        console.log('Chart created successfully!');
    } catch (error) {
        console.error('Error creating chart:', error);
        const wrap = document.querySelector('.chart-wrap');
        if (wrap) {
            wrap.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#c0392b;font-size:14px;padding:20px;text-align:center;">⚠️ Error: ' + error.message + '</div>';
        }
    }
});
</script>
</body