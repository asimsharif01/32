<?php
// print_dashboard.php — Clean standalone printable dashboard summary
// Opens in a new tab from the dashboard Export button.
// No nav, no sidebar — just the KPI data formatted for A4 landscape.
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Same queries as index.php ─────────────────────────────────────────────
$cur_month_start = date('Y-m-01');
$cur_month_end   = date('Y-m-t');
$cur_year_start  = date('Y-01-01');
$cur_year_end    = date('Y-12-31');
$year            = date('Y');

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

$total_listings = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings"))['c'];
$active         = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id IN ($s_listed,$s_uc)"))['c'];
$uc_count       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_uc"))['c'];
$closed_year    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"))['c'];
$closed_month   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_month_start' AND '$cur_month_end'"))['c'];
$resc_year      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_resc AND YEAR(date_of_listing)=$year"))['c'];

$vol_year  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(final_price) v FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"))['v'] ?? 0;
$vol_month = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(final_price) v FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_month_start' AND '$cur_month_end'"))['v'] ?? 0;
$comm_year = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"))['c'] ?? 0;
$avg_price = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(final_price) a FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end'"))['a'] ?? 0;
$avg_dom_r = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(DATEDIFF(contract_date,date_of_listing)) d FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end' AND contract_date IS NOT NULL AND date_of_listing IS NOT NULL"));
$avg_dom   = $avg_dom_r['d'] ? round($avg_dom_r['d'],1) : 0;

// Monthly closed/volume
$monthly_closed = array_fill(1,12,0);
$monthly_volume = array_fill(1,12,0);
$mc = mysqli_query($conn,"SELECT MONTH(closing_date) mn,COUNT(*) cnt,SUM(final_price) vol FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end' GROUP BY MONTH(closing_date)");
while($r=mysqli_fetch_assoc($mc)){ $monthly_closed[$r['mn']]=intval($r['cnt']); $monthly_volume[$r['mn']]=floatval($r['vol']); }

// Lead / referral sources
$lead_data = [];
$ld = mysqli_query($conn,"SELECT IFNULL(NULLIF(lead_source,''),'Unknown') AS src, COUNT(*) cnt FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end' GROUP BY src ORDER BY cnt DESC LIMIT 8");
while($r=mysqli_fetch_assoc($ld)) $lead_data[] = $r;
$total_leads = array_sum(array_column($lead_data,'cnt'));

// Top agents
$top_agents = [];
$ta = mysqli_query($conn,"SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS aname, COUNT(*) closed_count, SUM(final_price) volume FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$cur_year_start' AND '$cur_year_end' AND (LA_ForReport=1 OR SA_ForReport=1) GROUP BY aname ORDER BY volume DESC LIMIT 5");
while($r=mysqli_fetch_assoc($ta)) $top_agents[] = $r;
$max_vol = $top_agents ? max(array_column($top_agents,'volume')) : 1;

// Upcoming settlements
$upcoming = [];
$up = mysqli_query($conn,"SELECT l.id,l.mls_number,l.address1,l.city,l.LA_Name,lm.due_date AS settlement_date FROM listings l JOIN listing_milestones lm ON lm.listing_id=l.id AND lm.milestone_type='Settlement' WHERE lm.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND lm.na_flag=0 ORDER BY lm.due_date ASC LIMIT 5");
while($r=mysqli_fetch_assoc($up)) $upcoming[] = $r;

// Recent transactions
$recent = [];
$rr = mysqli_query($conn,"SELECT l.id,l.mls_number,l.transaction_number,l.address1,l.city,l.final_price,l.closing_date,l.contract_date,l.LA_Name,l.buyer_name,l.seller_name,s.description AS status FROM listings l LEFT JOIN sales_statuses s ON l.status_id=s.id ORDER BY l.updated_at DESC LIMIT 10");
while($r=mysqli_fetch_assoc($rr)) $recent[] = $r;

function money($v,$abbr=false){
    if($abbr){if($v>=1000000) return '$'.number_format($v/1000000,2).'M'; if($v>=1000) return '$'.number_format($v/1000,1).'K';}
    return '$'.number_format($v,2);
}
function fmtD($d){ return ($d&&$d!='0000-00-00') ? date('M j, Y',strtotime($d)) : '—'; }
function daysUntil($d){ if(!$d||$d=='0000-00-00') return null; return (int)((strtotime($d)-time())/(60*60*24)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Summary — Larson &amp; Company</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; padding:20px; color:#1e293b; font-size:13px; }

        .wrap { max-width:1100px; margin:0 auto; background:#fff; border-radius:10px; padding:24px 28px; box-shadow:0 4px 20px rgba(0,0,0,.1); }

        /* ── Print tip bar ─────────────────────────── */
        .no-print { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .print-tip { font-size:11px;color:#6b7280;background:#f3f4f6;padding:6px 12px;border-radius:6px; }
        .btn-print { background:#1e3a5f;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600; }
        .btn-print:hover { background:#2a4f82; }

        /* ── Report header ─────────────────────────── */
        .rpt-header { display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e3a5f;padding-bottom:12px;margin-bottom:20px; }
        .rpt-header h1 { font-size:20px;font-weight:700;color:#0f2b3d; }
        .rpt-header p  { font-size:11.5px;color:#4b5563;margin-top:3px; }
        .co-block { text-align:right;font-size:11.5px;color:#4b5563; }
        .co-name  { font-size:15px;font-weight:700;color:#1e3a5f; }

        /* ── KPI grid ──────────────────────────────── */
        .kpi-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px; }
        .kpi-box  { border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;position:relative;overflow:hidden; }
        .kpi-box::before { content:'';position:absolute;top:0;left:0;right:0;height:3px; }
        .kpi-box.gold::before  { background:linear-gradient(90deg,#c9a84c,#f0c866); }
        .kpi-box.navy::before  { background:linear-gradient(90deg,#1e3a5f,#2a4f82); }
        .kpi-box.teal::before  { background:linear-gradient(90deg,#0d9488,#14b8a6); }
        .kpi-box.green::before { background:linear-gradient(90deg,#059669,#10b981); }
        .kpi-lbl { font-size:10px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px; }
        .kpi-val { font-size:22px;font-weight:800;color:#0d1117;letter-spacing:-0.5px;line-height:1; }
        .kpi-sub { font-size:11px;color:#4a5568;margin-top:4px; }
        .kpi-sub strong { font-weight:700; }

        /* ── Stat pills ────────────────────────────── */
        .stat-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px; }
        .stat-pill { border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;display:flex;align-items:center;gap:10px; }
        .stat-dot  { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
        .stat-val  { font-size:18px;font-weight:700;color:#0d1117; }
        .stat-lbl  { font-size:10px;color:#718096;text-transform:uppercase;letter-spacing:.4px; }

        /* ── Two-column row ────────────────────────── */
        .row2 { display:grid;grid-template-columns:1.6fr 1fr;gap:14px;margin-bottom:16px; }
        .row2b{ display:grid;grid-template-columns:1fr 1.4fr;gap:14px;margin-bottom:16px; }
        .card { border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px; }
        .card-title { font-size:11px;font-weight:700;color:#1e3a5f;text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid #f0f2f5; }

        /* ── Monthly chart ─────────────────────────── */
        .chart-wrap { position:relative;height:160px; }

        /* ── Referral sources ──────────────────────── */
        .ref-row { display:flex;align-items:center;gap:8px;margin-bottom:6px; }
        .ref-dot  { width:9px;height:9px;border-radius:50%;flex-shrink:0; }
        .ref-name { flex:1;font-size:11.5px;font-weight:500; }
        .ref-cnt  { font-size:12px;font-weight:700;color:#0d1117; }
        .ref-pct  { font-size:10.5px;color:#718096;min-width:30px;text-align:right; }
        .ref-bar  { height:3px;border-radius:2px;margin-left:17px;margin-bottom:6px; }
        .ref-total{ font-size:11px;color:#718096;margin-top:8px;padding-top:8px;border-top:1px solid #f0f2f5; }

        /* ── Settlements ───────────────────────────── */
        .settle-row { display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f5f5f5; }
        .settle-row:last-child { border-bottom:none; }
        .settle-badge { min-width:38px;height:38px;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center; }
        .settle-badge.urgent  { background:#fee2e2;color:#991b1b; }
        .settle-badge.soon    { background:#fef3c7;color:#92400e; }
        .settle-badge.soon2   { background:#dbeafe;color:#1e40af; }
        .settle-num { font-size:14px;font-weight:800;line-height:1; }
        .settle-lbl { font-size:8px;font-weight:700;text-transform:uppercase; }
        .settle-addr{ font-size:12px;font-weight:600;color:#0d1117; }
        .settle-info{ font-size:10.5px;color:#718096; }
        .settle-date{ margin-left:auto;font-size:11px;font-weight:600;color:#4a5568;white-space:nowrap; }

        /* ── Agent bars ────────────────────────────── */
        .ab-row  { margin-bottom:10px; }
        .ab-top  { display:flex;justify-content:space-between;margin-bottom:3px; }
        .ab-name { font-size:12px;font-weight:600;color:#0d1117; }
        .ab-vol  { font-size:11px;color:#718096; }
        .ab-track{ height:5px;background:#e8ecf4;border-radius:3px;overflow:hidden; }
        .ab-fill { height:100%;border-radius:3px; }
        .ab-cnt  { font-size:10.5px;color:#718096;margin-top:2px; }

        /* ── Recent transactions ───────────────────── */
        table  { width:100%;border-collapse:collapse; }
        th { font-size:9.5px;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:.5px;padding:0 8px 7px;text-align:left;border-bottom:2px solid #e2e8f0; }
        td { font-size:11px;padding:7px 8px;border-bottom:1px solid #f5f5f5;vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        .chip { display:inline-block;padding:2px 7px;border-radius:12px;font-size:10px;font-weight:600; }
        .chip-closed { background:#d1fae5;color:#065f46; }
        .chip-listed { background:#dbeafe;color:#1e40af; }
        .chip-uc     { background:#fef3c7;color:#92400e; }
        .chip-resc   { background:#fee2e2;color:#991b1b; }

        /* ── Print ─────────────────────────────────── */
        @page { size:A4 landscape; margin:8mm 10mm; }
        @media print {
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            body { background:#fff; padding:0; }
            .wrap { padding:0; box-shadow:none; border-radius:0; max-width:100%; }
            .no-print { display:none !important; }
            table { font-size:9px; }
            th,td { padding:4px 5px; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <!-- Print tip + button -->
    <div class="no-print">
        <div class="print-tip">
            💡 Set paper to <strong>A4 Landscape</strong>, margins <strong>Minimum</strong>, enable <strong>Background graphics</strong>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <!-- Header -->
    <div class="rpt-header">
        <div>
            <h1>Dashboard Summary</h1>
            <p><?= date('l, F j, Y') ?> &nbsp;·&nbsp; Year to Date <?= $year ?></p>
        </div>
        <div class="co-block">
            <div class="co-name">LARSON &amp; COMPANY</div>
            <div>pros@larsonandcompany.com</div>
            <div>www.larsonandcompany.com</div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-box gold">
            <div class="kpi-lbl">Total Volume YTD</div>
            <div class="kpi-val"><?= money($vol_year,true) ?></div>
            <div class="kpi-sub">This month <strong><?= money($vol_month,true) ?></strong></div>
        </div>
        <div class="kpi-box navy">
            <div class="kpi-lbl">Closed Transactions YTD</div>
            <div class="kpi-val"><?= $closed_year ?></div>
            <div class="kpi-sub">This month <strong><?= $closed_month ?></strong></div>
        </div>
        <div class="kpi-box teal">
            <div class="kpi-lbl">Active Listings &amp; UC</div>
            <div class="kpi-val"><?= $active ?></div>
            <div class="kpi-sub">Under Contract <strong><?= $uc_count ?></strong></div>
        </div>
        <div class="kpi-box green">
            <div class="kpi-lbl">Commission Earned YTD</div>
            <div class="kpi-val"><?= money($comm_year,true) ?></div>
            <div class="kpi-sub">Avg sale price <strong><?= money($avg_price,true) ?></strong></div>
        </div>
    </div>

    <!-- Stat pills -->
    <div class="stat-grid">
        <div class="stat-pill"><div class="stat-dot" style="background:#1e3a5f"></div><div><div class="stat-val"><?= $total_listings ?></div><div class="stat-lbl">Total Listings</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#0d9488"></div><div><div class="stat-val"><?= $uc_count ?></div><div class="stat-lbl">Under Contract</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#d97706"></div><div><div class="stat-val"><?= $avg_dom ?></div><div class="stat-lbl">Avg Days on Market</div></div></div>
        <div class="stat-pill"><div class="stat-dot" style="background:#e11d48"></div><div><div class="stat-val"><?= $resc_year ?></div><div class="stat-lbl">Rescinded YTD</div></div></div>
    </div>

    <!-- Monthly Chart + Referral Sources -->
    <div class="row2">
        <div class="card">
            <div class="card-title">Monthly Performance <?= $year ?></div>
            <div class="chart-wrap"><canvas id="monthlyChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-title">Referral Sources — Closed YTD</div>
            <?php if ($lead_data):
                $colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#7c3aed','#0891b2','#059669'];
            ?>
            <?php foreach ($lead_data as $i => $ld):
                $pct = $total_leads > 0 ? round($ld['cnt']/$total_leads*100) : 0;
                $col = $colors[$i % count($colors)];
            ?>
            <div class="ref-row">
                <div class="ref-dot" style="background:<?= $col ?>"></div>
                <div class="ref-name"><?= htmlspecialchars($ld['src']) ?></div>
                <div class="ref-cnt"><?= $ld['cnt'] ?></div>
                <div class="ref-pct"><?= $pct ?>%</div>
            </div>
            <div class="ref-bar" style="background:<?= $col ?>;width:<?= $pct ?>%;max-width:100%;transition:width 1s"></div>
            <?php endforeach; ?>
            <div class="ref-total">Total closed: <strong><?= $total_leads ?></strong> transactions</div>
            <?php else: ?>
            <p style="color:#718096;font-size:12px;padding:8px 0">No closed transactions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Settlements + Top Agents -->
    <div class="row2b">
        <div class="card">
            <div class="card-title">Upcoming Settlements — Next 30 Days</div>
            <?php if ($upcoming): foreach ($upcoming as $u):
                $days = daysUntil($u['settlement_date']);
                $cls  = $days <= 7 ? 'urgent' : ($days <= 14 ? 'soon' : 'soon2');
            ?>
            <div class="settle-row">
                <div class="settle-badge <?= $cls ?>">
                    <div class="settle-num"><?= $days ?></div>
                    <div class="settle-lbl">days</div>
                </div>
                <div>
                    <div class="settle-addr"><?= htmlspecialchars($u['address1'] ?? '') ?><?= $u['city'] ? ', '.htmlspecialchars($u['city']) : '' ?></div>
                    <div class="settle-info"><?= htmlspecialchars($u['LA_Name'] ?? '') ?> · MLS <?= htmlspecialchars($u['mls_number']) ?></div>
                </div>
                <div class="settle-date"><?= fmtD($u['settlement_date']) ?></div>
            </div>
            <?php endforeach; else: ?>
            <p style="color:#718096;font-size:12px;padding:8px 0">🎉 No settlements due in next 30 days.</p>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-title">Top Agents by Volume</div>
            <?php
            $agent_colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48'];
            foreach ($top_agents as $i => $ag): ?>
            <div class="ab-row">
                <div class="ab-top">
                    <span class="ab-name"><?= htmlspecialchars($ag['aname']) ?></span>
                    <span class="ab-vol"><?= money($ag['volume'],true) ?></span>
                </div>
                <div class="ab-track">
                    <div class="ab-fill" style="width:<?= round($ag['volume']/$max_vol*100) ?>%;background:<?= $agent_colors[$i] ?>"></div>
                </div>
                <div class="ab-cnt"><?= $ag['closed_count'] ?> closed</div>
            </div>
            <?php endforeach;
            if (!$top_agents) echo '<p style="color:#718096;font-size:12px">No data yet.</p>';
            ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-title">Recent Transactions</div>
        <table>
            <thead><tr>
                <th>MLS / TN</th><th>Property</th><th>Agent</th>
                <th>Buyer</th><th>Seller</th><th>Price</th><th>Date</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $row):
                $st = strtolower($row['status'] ?? '');
                $chip = $st==='closed' ? 'chip-closed' : ($st==='listed' ? 'chip-listed' : ($st==='under contract' ? 'chip-uc' : 'chip-resc'));
                $kd = $row['closing_date'] ?: ($row['contract_date'] ?: null);
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['mls_number']??'—') ?></strong><br><span style="color:#718096"><?= htmlspecialchars($row['transaction_number']??'') ?></span></td>
                <td><?= htmlspecialchars($row['address1']??'—') ?><br><span style="color:#718096"><?= htmlspecialchars($row['city']??'') ?></span></td>
                <td><?= htmlspecialchars($row['LA_Name']??'—') ?></td>
                <td><?= htmlspecialchars($row['buyer_name']??'—') ?></td>
                <td><?= htmlspecialchars($row['seller_name']??'—') ?></td>
                <td style="font-weight:600"><?= $row['final_price'] ? money($row['final_price'],true) : '—' ?></td>
                <td><?= fmtD($kd) ?></td>
                <td><span class="chip <?= $chip ?>"><?= htmlspecialchars($row['status']??'') ?></span></td>
            </tr>
            <?php endforeach;
            if (!$recent) echo '<tr><td colspan="8" style="color:#718096;text-align:center">No transactions yet.</td></tr>';
            ?>
            </tbody>
        </table>
    </div>

</div><!-- /.wrap -->

<script>
Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";
Chart.defaults.color = '#718096';
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const closedData = <?= json_encode(array_values($monthly_closed)) ?>;
const volumeData = <?= json_encode(array_values($monthly_volume)) ?>;

new Chart(document.getElementById('monthlyChart'), {
    data: {
        labels: months,
        datasets: [
            { type:'bar', label:'Closed', data:closedData, backgroundColor:'rgba(30,58,95,0.15)', borderColor:'#1e3a5f', borderWidth:2, borderRadius:4, yAxisID:'y' },
            { type:'line', label:'Volume ($)', data:volumeData, borderColor:'#c9a84c', backgroundColor:'rgba(201,168,76,0.08)', borderWidth:2, pointRadius:3, fill:true, tension:0.4, yAxisID:'y2' }
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        interaction:{ mode:'index', intersect:false },
        plugins:{ legend:{ position:'top', labels:{ boxWidth:10, padding:10, font:{size:10} } },
                  tooltip:{ callbacks:{ label: ctx => ctx.datasetIndex===1 ? ' $'+ctx.parsed.y.toLocaleString('en-US') : ' '+ctx.parsed.y+' closed' } } },
        scales:{
            x:{ grid:{display:false}, ticks:{font:{size:9}} },
            y:{ position:'left', ticks:{stepSize:1,precision:0,font:{size:9}}, grid:{color:'#e8ecf4'} },
            y2:{ position:'right', grid:{drawOnChartArea:false}, ticks:{ font:{size:9}, callback: v=>'$'+(v>=1000000?(v/1000000).toFixed(1)+'M':(v/1000).toFixed(0)+'K') } }
        }
    }
});
</script>
</body>
</html>