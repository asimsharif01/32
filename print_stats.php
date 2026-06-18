<?php
// print_stats.php — Clean standalone printable stats report
// No nav, no sidebar — opened in new tab from stats_report.php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Date range ────────────────────────────────────────────────────────────
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

// ── All queries (same as stats_report.php) ────────────────────────────────
$total_closings = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc'"))['c'];
$total_listings = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE date_of_listing BETWEEN '$from_esc' AND '$to_esc'"))['c'];
$total_volume   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(final_price) v FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc'"))['v'] ?? 0;
$total_uc       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_uc AND contract_date BETWEEN '$from_esc' AND '$to_esc'"))['c'];
$uc_active_now  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_uc"))['c'];
$rescinded      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM listings WHERE status_id=$s_resc AND date_of_listing BETWEEN '$from_esc' AND '$to_esc'"))['c'];
$avg_price      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(final_price) a FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc'"))['a'] ?? 0;
$avg_dom_r      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT AVG(DATEDIFF(contract_date,date_of_listing)) d FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND contract_date IS NOT NULL AND date_of_listing IS NOT NULL"));
$avg_dom        = $avg_dom_r['d'] ? round($avg_dom_r['d'],1) : 0;

// Referral sources
$lead_data = [];
$ld = mysqli_query($conn,"SELECT IFNULL(NULLIF(lead_source,''),'Unknown') AS src, COUNT(*) cnt FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' GROUP BY src ORDER BY cnt DESC");
while($r=mysqli_fetch_assoc($ld)) $lead_data[] = $r;
$total_leads = array_sum(array_column($lead_data,'cnt'));

// Top agents
$top_agents = [];
$ta = mysqli_query($conn,"SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS aname, COUNT(*) closed_count, SUM(final_price) volume FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND (LA_ForReport=1 OR SA_ForReport=1) GROUP BY aname ORDER BY volume DESC LIMIT 10");
while($r=mysqli_fetch_assoc($ta)) $top_agents[] = $r;
$max_vol = $top_agents ? max(array_column($top_agents,'volume')) : 1;

// Days on market detail
$dom_detail = [];
$dr = mysqli_query($conn,"SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS agent, l.address1, l.city, l.mls_number, l.date_of_listing, l.contract_date, DATEDIFF(l.contract_date,l.date_of_listing) AS dom, l.final_price FROM listings l WHERE l.status_id=$s_closed AND l.closing_date BETWEEN '$from_esc' AND '$to_esc' AND l.contract_date IS NOT NULL AND l.date_of_listing IS NOT NULL ORDER BY dom ASC");
while($r=mysqli_fetch_assoc($dr)) $dom_detail[] = $r;
$dom_fast   = count(array_filter($dom_detail, fn($r)=>$r['dom']<=14));
$dom_medium = count(array_filter($dom_detail, fn($r)=>$r['dom']>14&&$r['dom']<=30));
$dom_slow   = count(array_filter($dom_detail, fn($r)=>$r['dom']>30));

// City data
$city_data = [];
$cr = mysqli_query($conn,"SELECT IFNULL(NULLIF(city,''),'Unknown') AS loc, COUNT(*) closings, SUM(final_price) volume FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' GROUP BY loc ORDER BY closings DESC");
while($r=mysqli_fetch_assoc($cr)) $city_data[] = $r;

// County data
$county_data = [];
$cor = mysqli_query($conn,"SELECT IFNULL(NULLIF(county,''),'Not Set') AS loc, COUNT(*) closings, SUM(final_price) volume FROM listings WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' GROUP BY loc ORDER BY closings DESC");
while($r=mysqli_fetch_assoc($cor)) $county_data[] = $r;

// ── Helpers ───────────────────────────────────────────────────────────────
function h($v)  { return htmlspecialchars($v ?? ''); }
function money($v) { return '$'.number_format((float)($v??0),2); }
function moneyAbbr($v) {
    if($v>=1000000) return '$'.number_format($v/1000000,2).'M';
    if($v>=1000)    return '$'.number_format($v/1000,1).'K';
    return '$'.number_format($v,2);
}
function fmtD($d) { return $d ? date('m/d/Y',strtotime($d)) : '—'; }
$colors = ['#1e3a5f','#0d9488','#c9a84c','#d97706','#e11d48','#7c3aed','#0891b2','#059669'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats Report — Larson &amp; Company</title>
    <style>
        /* ── Base — no background, clean white ──────────────────── */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; padding:20px; color:#1e293b; font-size:12px; }

        .wrap { max-width:1050px; margin:0 auto; background:#fff; border-radius:10px; padding:26px 30px; box-shadow:0 4px 20px rgba(0,0,0,.1); }

        /* ── Print button bar ──────────────────────────────────── */
        .no-print { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .print-tip { font-size:11px; color:#6b7280; background:#f3f4f6; padding:6px 12px; border-radius:6px; }
        .btn-print { background:#1e3a5f; color:#fff; border:none; padding:8px 20px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
        .btn-print:hover { background:#2a4f82; }

        /* ── Report header ─────────────────────────────────────── */
        .rpt-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e3a5f; padding-bottom:12px; margin-bottom:18px; }
        .rpt-header h1 { font-size:20px; font-weight:700; color:#0f2b3d; }
        .rpt-header p  { font-size:11px; color:#4b5563; margin-top:3px; }
        .co-block { text-align:right; font-size:11px; color:#4b5563; }
        .co-name  { font-size:14px; font-weight:700; color:#1e3a5f; }

        /* ── KPI grid ──────────────────────────────────────────── */
        .kpi5 { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px; }
        .kpi-box { border-radius:8px; padding:12px; text-align:center; border:1px solid #e2e8f0; position:relative; overflow:hidden; }
        .kpi-box::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .kpi-box.blue::before   { background:linear-gradient(90deg,#1e3a5f,#2a4f82); }
        .kpi-box.teal::before   { background:linear-gradient(90deg,#0d9488,#14b8a6); }
        .kpi-box.gold::before   { background:linear-gradient(90deg,#c9a84c,#f0c866); }
        .kpi-box.amber::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
        .kpi-box.purple::before { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
        .kpi-num { font-size:22px; font-weight:800; color:#0d1117; letter-spacing:-0.5px; line-height:1; margin-bottom:4px; }
        .kpi-lbl { font-size:9px; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.5px; }
        .kpi-sub { font-size:10px; color:#4a5568; margin-top:4px; }

        /* ── Quick stats ───────────────────────────────────────── */
        .quick3 { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px; }
        .qs { background:#f8f9fc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 12px; }
        .qs-num { font-size:18px; font-weight:700; color:#0d1117; }
        .qs-lbl { font-size:9px; color:#718096; text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }

        /* ── Section title ─────────────────────────────────────── */
        .sec { font-size:10px; font-weight:700; color:#1e3a5f; text-transform:uppercase; letter-spacing:.5px; border-left:3px solid #1e3a5f; padding-left:8px; margin-bottom:10px; }

        /* ── Two column ────────────────────────────────────────── */
        .two { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .three { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px; margin-bottom:10px; }

        /* ── Referral rows ─────────────────────────────────────── */
        .ref-row { display:flex; align-items:center; gap:7px; margin-bottom:4px; }
        .ref-dot  { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .ref-name { flex:1; font-size:11px; }
        .ref-cnt  { font-size:12px; font-weight:700; color:#0d1117; }
        .ref-pct  { font-size:10px; color:#718096; min-width:28px; text-align:right; }
        .ref-bar  { height:4px; border-radius:2px; margin-left:15px; margin-bottom:5px; background:#e8ecf4; }
        .ref-fill { height:100%; border-radius:2px; }

        /* ── Tables ────────────────────────────────────────────── */
        table  { width:100%; border-collapse:collapse; }
        th { font-size:9px; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.4px; padding:0 6px 6px; text-align:left; border-bottom:1.5px solid #e2e8f0; }
        td { font-size:11px; padding:6px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        .bar-wrap { height:4px; background:#e8ecf4; border-radius:2px; overflow:hidden; margin-top:2px; }
        .bar-fill { height:100%; border-radius:2px; }

        /* ── DOM colors ────────────────────────────────────────── */
        .fast   { color:#059669; font-weight:700; }
        .medium { color:#d97706; font-weight:700; }
        .slow   { color:#e11d48; font-weight:700; }

        /* ── Divider ───────────────────────────────────────────── */
        .divider { border:none; border-top:1px solid #f0f2f5; margin:16px 0; }

        /* ── Print ─────────────────────────────────────────────── */
        @page { size:A4 portrait; margin:8mm 10mm; }
        @media print {
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            body { background:#fff; padding:0; }
            .wrap { padding:0; box-shadow:none; border-radius:0; max-width:100%; }
            .no-print { display:none !important; }
            .kpi5  { gap:6px; }
            .kpi-num { font-size:18px; }
            table  { font-size:9px; }
            th, td { padding:4px 5px; }
            .two   { gap:10px; }
            .quick3{ gap:8px; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <!-- Print tip + button (hidden on print) -->
    <div class="no-print">
        <div class="print-tip">
            💡 Set paper to <strong>A4 Portrait</strong>, margins <strong>Minimum</strong>, enable <strong>Background graphics</strong>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <!-- Report header -->
    <div class="rpt-header">
        <div>
            <h1>Stats Report</h1>
            <p><?= fmtD($from) ?> — <?= fmtD($to) ?></p>
        </div>
        <div class="co-block">
            <div class="co-name">LARSON &amp; COMPANY</div>
            <div>pros@larsonandcompany.com</div>
            <div>www.larsonandcompany.com</div>
        </div>
    </div>

    <!-- ── 5 KPI Boxes ─────────────────────────────────────────────── -->
    <div class="kpi5">
        <div class="kpi-box blue">
            <div class="kpi-num"><?= $total_closings ?></div>
            <div class="kpi-lbl">Total Closings</div>
            <div class="kpi-sub"><?= fmtD($from) ?> – <?= fmtD($to) ?></div>
        </div>
        <div class="kpi-box teal">
            <div class="kpi-num"><?= $total_listings ?></div>
            <div class="kpi-lbl">Total Listings</div>
            <div class="kpi-sub">Listed in period</div>
        </div>
        <div class="kpi-box gold">
            <div class="kpi-num" style="font-size:18px"><?= moneyAbbr($total_volume) ?></div>
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

    <!-- ── Quick Stats ──────────────────────────────────────────────── -->
    <div class="quick3">
        <div class="qs"><div class="qs-num"><?= moneyAbbr($avg_price) ?></div><div class="qs-lbl">Avg Sale Price</div></div>
        <div class="qs"><div class="qs-num"><?= $avg_dom ?: '—' ?></div><div class="qs-lbl">Avg Days on Market</div></div>
        <div class="qs"><div class="qs-num"><?= $rescinded ?></div><div class="qs-lbl">Rescinded in Period</div></div>
    </div>

    <!-- ── Referral Sources + Top Agents ────────────────────────────── -->
    <div class="two">
        <div>
            <div class="sec">Referral Sources</div>
            <?php if ($lead_data): foreach ($lead_data as $i => $ld):
                $pct = $total_leads>0 ? round($ld['cnt']/$total_leads*100) : 0;
                $col = $colors[$i%count($colors)];
            ?>
            <div class="ref-row">
                <div class="ref-dot" style="background:<?= $col ?>"></div>
                <div class="ref-name"><?= h($ld['src']) ?></div>
                <div class="ref-cnt"><?= $ld['cnt'] ?></div>
                <div class="ref-pct"><?= $pct ?>%</div>
            </div>
            <div class="ref-bar"><div class="ref-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
            <?php endforeach;
            echo '<p style="font-size:10px;color:#718096;margin-top:6px">Total tracked: <strong>'.$total_leads.'</strong></p>';
            else: echo '<p style="color:#718096;font-size:11px">No data.</p>'; endif; ?>
        </div>
        <div>
            <div class="sec">Closings by Agent</div>
            <?php if ($top_agents): ?>
            <table>
                <thead><tr><th>Agent</th><th style="text-align:right">Closings</th><th style="text-align:right">Volume</th></tr></thead>
                <tbody>
                <?php foreach ($top_agents as $i => $ag):
                    $col = $colors[$i%count($colors)];
                    $pct = round($ag['volume']/$max_vol*100);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?= h($ag['aname']) ?></div>
                        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    </td>
                    <td style="text-align:right;font-weight:700"><?= $ag['closed_count'] ?></td>
                    <td style="text-align:right;color:#1e3a5f;font-weight:600"><?= moneyAbbr($ag['volume']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: echo '<p style="color:#718096;font-size:11px">No data.</p>'; endif; ?>
        </div>
    </div>

    <hr class="divider">

    <!-- ── Average Days on Market ────────────────────────────────────── -->
    <div class="sec">Average Days on Market
        <span style="font-weight:400;font-size:9px;color:#718096;text-transform:none;letter-spacing:0;margin-left:6px">(Date of Listing → Contract Date)</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
        <div class="qs"><div class="qs-num"><?= $avg_dom ?: '—' ?></div><div class="qs-lbl">Avg DOM</div></div>
        <div class="qs"><div class="qs-num fast"><?= $dom_fast ?></div><div class="qs-lbl">Under 14 Days</div></div>
        <div class="qs"><div class="qs-num medium"><?= $dom_medium ?></div><div class="qs-lbl">15–30 Days</div></div>
        <div class="qs"><div class="qs-num slow"><?= $dom_slow ?></div><div class="qs-lbl">Over 30 Days</div></div>
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
            $dv = intval($row['dom']);
            $dc = $dv<=14 ? 'fast' : ($dv<=30 ? 'medium' : 'slow');
        ?>
        <tr>
            <td><?= h($row['address1']) ?></td>
            <td><?= h($row['city']) ?></td>
            <td><?= h($row['agent']) ?></td>
            <td><?= fmtD($row['date_of_listing']) ?></td>
            <td><?= fmtD($row['contract_date']) ?></td>
            <td style="text-align:right" class="<?= $dc ?>"><?= $dv ?></td>
            <td style="text-align:right"><?= $row['final_price'] ? moneyAbbr($row['final_price']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color:#718096;font-size:11px">No closed transactions with both list date and contract date in this period.</p>
    <?php endif; ?>

    <hr class="divider">

    <!-- ── City / County ─────────────────────────────────────────────── -->
    <div class="two">
        <!-- By City -->
        <div>
            <div class="sec">Closings by City</div>
            <?php if ($city_data):
                $max_city = max(array_column($city_data,'closings')) ?: 1; ?>
            <table>
                <thead><tr><th>City</th><th style="text-align:right">Closings</th><th style="text-align:right">Volume</th></tr></thead>
                <tbody>
                <?php foreach ($city_data as $i => $row):
                    $col = $colors[$i%count($colors)];
                    $pct = round($row['closings']/$max_city*100);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600"><?= h($row['loc']) ?></div>
                        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    </td>
                    <td style="text-align:right;font-weight:700"><?= $row['closings'] ?></td>
                    <td style="text-align:right;color:#4a5568"><?= moneyAbbr($row['volume']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: echo '<p style="color:#718096;font-size:11px">No data.</p>'; endif; ?>
        </div>

        <!-- By County -->
        <div>
            <div class="sec">Closings by County</div>
            <?php
            $has_county = !empty(array_filter($county_data, fn($r) => $r['loc'] !== 'Not Set'));
            if ($has_county):
                $max_county = max(array_column($county_data,'closings')) ?: 1; ?>
            <table>
                <thead><tr><th>County</th><th style="text-align:right">Closings</th><th style="text-align:right">Volume</th></tr></thead>
                <tbody>
                <?php foreach ($county_data as $i => $row):
                    $col = $colors[$i%count($colors)];
                    $pct = round($row['closings']/$max_county*100);
                ?>
                <tr>
                    <td style="color:<?= $row['loc']==='Not Set'?'#718096':'inherit' ?>">
                        <div style="font-weight:600"><?= h($row['loc']) ?></div>
                        <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
                    </td>
                    <td style="text-align:right;font-weight:700"><?= $row['closings'] ?></td>
                    <td style="text-align:right;color:#4a5568"><?= moneyAbbr($row['volume']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="background:#fffbe6;border:1px solid #fde68a;border-radius:6px;padding:10px;font-size:11px;color:#92400e">
                ⚠️ No county data yet. Add the County field when entering transactions.
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.wrap -->
</body>
</html>