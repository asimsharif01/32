<?php
// print_report.php — Clean standalone printable version of any report tab.
// Opens in a new tab from reports.php export buttons.
// No nav, no header, no sidebar — just the report content + print button.
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Parameters (same as reports.php) ─────────────────────────────────────
$from_date  = $_GET['from_date'] ?? date('Y-01-01');
$to_date    = $_GET['to_date']   ?? date('Y-12-31');
$year       = intval($_GET['year'] ?? date('Y'));
$agent_id   = intval($_GET['agent_id'] ?? 0);
$tab        = $_GET['tab'] ?? 'company';
$allowed    = ['company','progress','listings','summary'];
if (!in_array($tab, $allowed)) $tab = 'company';

$from_esc = mysqli_real_escape_string($conn, $from_date);
$to_esc   = mysqli_real_escape_string($conn, $to_date);

// Agent name
$agent_name = '';
if ($agent_id > 0) {
    $ar = mysqli_query($conn, "SELECT name FROM agents WHERE id = $agent_id LIMIT 1");
    if ($row = mysqli_fetch_assoc($ar)) $agent_name = $row['name'];
}
$an_esc      = mysqli_real_escape_string($conn, $agent_name);
$agent_label = $agent_name ?: 'Larson & Company';

// ── Status IDs ────────────────────────────────────────────────────────────
function getStatusId($conn, $desc) {
    $r = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description='" .
        mysqli_real_escape_string($conn,$desc) . "' LIMIT 1");
    $row = mysqli_fetch_assoc($r);
    return $row ? intval($row['id']) : 0;
}
$s_closed = getStatusId($conn,'Closed');
$s_listed = getStatusId($conn,'Listed');
$s_uc     = getStatusId($conn,'Under Contract');
$s_resc   = getStatusId($conn,'Rescinded');

// ── Agent WHERE ───────────────────────────────────────────────────────────
function agentWhere($an_esc, $a = 'l') {
    if ($an_esc === '') return "($a.LA_ForReport=1 OR $a.SA_ForReport=1)";
    return "(($a.LA_Name='$an_esc' AND $a.LA_ForReport=1)
          OR ($a.SA_Name='$an_esc' AND $a.SA_ForReport=1)
          OR  $a.split_with='$an_esc')";
}
$aw = agentWhere($an_esc, 'l');

// ── Helpers ───────────────────────────────────────────────────────────────
function h($v)  { return htmlspecialchars($v ?? ''); }
function money($v) { return '$'.number_format((float)($v??0),2); }
function fmtD($d) { return (!$d||$d==='0000-00-00') ? '' : date('m/d/Y',strtotime($d)); }

$tab_titles = [
    'company'  => 'Sales Summary',
    'progress' => 'Progress Report',
    'listings' => 'Listings Report',
    'summary'  => 'Agent Sales Summary',
];

// ── Build data for selected tab only ─────────────────────────────────────

// ── TAB: company ─────────────────────────────────────────────────────────
if ($tab === 'company') {
    $totals = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT SUM(CASE WHEN split_with<>'' AND split_with IS NOT NULL THEN final_price*.5 ELSE final_price END) vol,
               SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) comm
        FROM listings l WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND $aw"));

    $listed_res  = mysqli_query($conn,"
        SELECT l.mls_number,
               CASE WHEN l.SA_ForReport=1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
               l.seller_name, l.address1, l.city,
               l.purchase_price AS price, l.uc_price,
               l.date_of_listing AS dol, l.date_of_expiration AS doe, s.description AS status
        FROM listings l LEFT JOIN sales_statuses s ON l.status_id=s.id
        WHERE l.status_id=$s_listed AND l.date_of_listing BETWEEN '$from_esc' AND '$to_esc' AND $aw
        ORDER BY l.date_of_listing");

    $closed_res  = mysqli_query($conn,"
        SELECT CASE WHEN l.SA_ForReport=1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
               l.buyer_name, l.seller_name, l.address1, l.city, l.final_price,
               l.closing_date,
               DATEDIFF(l.contract_date,l.date_of_listing) AS dom,
               CASE WHEN l.uc_price>0 THEN ROUND(l.final_price/l.uc_price*100,2) ELSE 0 END AS pp_uc,
               l.lead_source
        FROM listings l
        WHERE l.status_id=$s_closed AND l.closing_date BETWEEN '$from_esc' AND '$to_esc' AND $aw
        ORDER BY l.closing_date");

    $avgs = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT AVG(final_price) avg_p,
               AVG(DATEDIFF(contract_date,date_of_listing)) avg_dom,
               AVG(CASE WHEN uc_price>0 THEN final_price/uc_price*100 ELSE NULL END) avg_pp
        FROM listings l WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND $aw"));

    $uc_res = mysqli_query($conn,"
        SELECT CASE WHEN l.SA_ForReport=1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
               l.buyer_name, l.seller_name, l.address1, l.city,
               l.purchase_price AS price, l.contract_date, s.description AS status
        FROM listings l LEFT JOIN sales_statuses s ON l.status_id=s.id
        WHERE l.status_id=$s_uc AND l.contract_date BETWEEN '$from_esc' AND '$to_esc' AND $aw
        ORDER BY l.contract_date");
}

// ── TAB: progress ─────────────────────────────────────────────────────────
if ($tab === 'progress') {
    $months_names = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

    $uc_m = $cl_m = $li_m = $re_m = [];
    $r = mysqli_query($conn,"SELECT MONTH(contract_date) mn,COUNT(*) c FROM listings l
         WHERE status_id=$s_uc AND YEAR(contract_date)=$year AND $aw GROUP BY MONTH(contract_date)");
    while($row=mysqli_fetch_assoc($r)) $uc_m[$row['mn']]=$row['c'];

    $r = mysqli_query($conn,"SELECT MONTH(closing_date) mn,COUNT(*) c FROM listings l
         WHERE status_id=$s_closed AND YEAR(closing_date)=$year AND $aw GROUP BY MONTH(closing_date)");
    while($row=mysqli_fetch_assoc($r)) $cl_m[$row['mn']]=$row['c'];

    $r = mysqli_query($conn,"SELECT MONTH(date_of_listing) mn,
         SUM(status_id=$s_listed) lc, SUM(status_id=$s_resc) rc
         FROM listings l WHERE YEAR(date_of_listing)=$year
         AND (status_id=$s_listed OR status_id=$s_resc) AND $aw GROUP BY MONTH(date_of_listing)");
    while($row=mysqli_fetch_assoc($r)) { $li_m[$row['mn']]=$row['lc']; $re_m[$row['mn']]=$row['rc']; }

    $ytd = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) ytd_cl,
               SUM(CASE WHEN split_with<>'' AND split_with IS NOT NULL THEN final_price*.5 ELSE final_price END) ytd_vol,
               SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) ytd_comm
        FROM listings l WHERE status_id=$s_closed AND YEAR(closing_date)=$year AND $aw"));

    $ytdl = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT SUM(status_id=$s_listed) ytd_li, SUM(status_id=$s_resc) ytd_re
        FROM listings l WHERE YEAR(date_of_listing)=$year
        AND (status_id=$s_listed OR status_id=$s_resc) AND $aw"));
}

// ── TAB: listings ─────────────────────────────────────────────────────────
if ($tab === 'listings') {
    $months_names = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

    $listings_res = mysqli_query($conn,"
        SELECT CASE WHEN l.status_id=$s_closed THEN l.closing_date
                    WHEN l.status_id=$s_uc     THEN l.contract_date
                    ELSE l.date_of_listing END AS key_date,
               CASE WHEN l.status_id=$s_closed THEN MONTH(l.closing_date)
                    WHEN l.status_id=$s_uc     THEN MONTH(l.contract_date)
                    ELSE MONTH(l.date_of_listing) END AS mn,
               CASE WHEN l.status_id=$s_closed THEN YEAR(l.closing_date)
                    WHEN l.status_id=$s_uc     THEN YEAR(l.contract_date)
                    ELSE YEAR(l.date_of_listing) END AS yr,
               s.description AS status,
               CASE WHEN l.SA_ForReport=1 THEN l.SA_Name ELSE l.LA_Name END AS agent,
               l.mls_number, l.purchase_price AS price, l.seller_name, l.address1, l.city
        FROM listings l LEFT JOIN sales_statuses s ON l.status_id=s.id
        WHERE l.status_id IN ($s_closed,$s_uc,$s_listed)
          AND ((l.status_id=$s_closed AND l.closing_date BETWEEN '$from_esc' AND '$to_esc')
            OR (l.status_id=$s_uc    AND l.contract_date BETWEEN '$from_esc' AND '$to_esc')
            OR (l.status_id=$s_listed AND l.date_of_listing BETWEEN '$from_esc' AND '$to_esc'))
          AND $aw ORDER BY key_date, s.description");

    $grouped = [];
    while ($row = mysqli_fetch_assoc($listings_res)) {
        $mk = $months_names[$row['mn']-1].' '.$row['yr'];
        $grouped[$mk][$row['status']][] = $row;
    }
}

// ── TAB: summary ─────────────────────────────────────────────────────────
if ($tab === 'summary') {
    $totals = mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT SUM(CASE WHEN split_with<>'' AND split_with IS NOT NULL THEN final_price*.5 ELSE final_price END) vol,
               SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) comm
        FROM listings l WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND $aw"));

    $summary_res = mysqli_query($conn,"
        SELECT CASE WHEN SA_ForReport=1 THEN SA_Name ELSE LA_Name END AS agent,
               SUM(CASE WHEN split_with<>'' AND split_with IS NOT NULL THEN final_price*.5 ELSE final_price END) vol,
               SUM(((commission_price*commission_pct/100)+commission_other+transaction_fee+errors_omissions)*agent_split/100+processing_fee+other2) comm
        FROM listings l WHERE status_id=$s_closed AND closing_date BETWEEN '$from_esc' AND '$to_esc' AND $aw
        GROUP BY agent ORDER BY vol DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tab_titles[$tab]) ?> — Larson &amp; Company</title>
    <style>
        /* ── Base ──────────────────────────────────────────────────── */
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; padding:24px; font-size:13px; color:#1e293b; }

        .report-wrap { max-width:1100px; margin:0 auto; background:#fff; border-radius:10px; padding:28px 32px; box-shadow:0 4px 20px rgba(0,0,0,.1); }

        /* ── Print button bar ──────────────────────────────────────── */
        .no-print { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .btn-print { background:#1e3a5f;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600; }
        .btn-print:hover { background:#2a4f82; }
        .print-tip { font-size:11px;color:#6b7280;background:#f3f4f6;padding:6px 12px;border-radius:6px; }

        /* ── Report header ─────────────────────────────────────────── */
        .rpt-header { display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e3a5f;padding-bottom:12px;margin-bottom:18px; }
        .rpt-header h1 { font-size:20px;font-weight:700;color:#0f2b3d; }
        .rpt-header p  { font-size:11.5px;color:#4b5563;margin-top:3px; }
        .co-block { text-align:right;font-size:11.5px;color:#4b5563; }
        .co-name  { font-size:15px;font-weight:700;color:#1e3a5f; }

        /* ── Section ───────────────────────────────────────────────── */
        .sec-title { font-size:11px;font-weight:700;background:#eef2ff;padding:4px 10px;border-left:4px solid #1e3a5f;margin:14px 0 8px;color:#1e3a5f;text-transform:uppercase;letter-spacing:.4px; }

        /* ── Tables ────────────────────────────────────────────────── */
        table  { width:100%;border-collapse:collapse;font-size:11.5px; }
        th     { background:#f8f9fc;font-size:10px;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:.5px;padding:6px 8px;text-align:left;border-bottom:2px solid #e2e8f0; }
        td     { padding:7px 8px;border-bottom:1px solid #f0f2f5;vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        .avg-row td { font-style:italic;color:#555;background:#fafbfc; }
        .total-row td { font-weight:700;background:#f1f3f5; }

        /* ── Totals box ────────────────────────────────────────────── */
        .totals-box { display:flex;gap:32px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 16px;margin-bottom:12px; }
        .t-lbl { font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px; }
        .t-val { font-size:20px;font-weight:700;color:#1e3a5f; }

        /* ── Progress panels ───────────────────────────────────────── */
        .prog-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px; }
        .prog-panel { border:1px solid #e2e8f0;border-radius:6px;padding:10px; }
        .prog-panel h6 { background:#e9ecef;padding:4px 8px;margin:-10px -10px 8px;border-radius:6px 6px 0 0;font-size:11px;font-weight:700;color:#1e3a5f; }
        .prog-month { font-weight:700;color:#1e3a5f;font-size:11px;margin-top:8px; }
        .prog-row,.prog-total { display:flex;justify-content:space-between;font-size:11px;padding:1px 0; }
        .prog-total { font-weight:700;border-top:1px solid #e2e8f0;margin-top:2px;padding-top:3px; }

        /* ── YTD box ───────────────────────────────────────────────── */
        .ytd-box { background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:12px 16px; }
        .ytd-grid { display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-top:8px; }
        .ytd-stat { background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:8px;text-align:center; }
        .ytd-num  { font-size:18px;font-weight:700;color:#1e3a5f; }
        .ytd-lbl  { font-size:10px;color:#6c757d;text-transform:uppercase;letter-spacing:.4px;margin-top:2px; }

        /* ── Print ─────────────────────────────────────────────────── */
        @page { size:A4 portrait; margin:10mm 12mm; }
        @media print {
            * { -webkit-print-color-adjust:exact;print-color-adjust:exact; }
            body { background:#fff;padding:0; }
            .report-wrap { padding:0;box-shadow:none;border-radius:0;max-width:100%; }
            .no-print { display:none !important; }
            table { font-size:9px; }
            th,td { padding:4px 5px; }
            .prog-panel h6 { font-size:9px; }
            .prog-month,.prog-row,.prog-total { font-size:9px; }
            .ytd-num { font-size:14px; }
            .t-val { font-size:15px; }
        }
    </style>
</head>
<body>
<div class="report-wrap">

    <!-- Print tip bar -->
    <div class="no-print">
        <div class="print-tip">
            💡 Set paper to <strong>A4</strong>, margins to <strong>Minimum</strong>, enable <strong>Background graphics</strong>
        </div>
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <!-- Report header -->
    <div class="rpt-header">
        <div>
            <h1><?= h($tab_titles[$tab]) ?></h1>
            <p><?= fmtD($from_date) ?> — <?= fmtD($to_date) ?> &nbsp;·&nbsp; <?= h($agent_label) ?></p>
        </div>
        <div class="co-block">
            <div class="co-name">LARSON &amp; COMPANY</div>
            <div>pros@larsonandcompany.com</div>
            <div>www.larsonandcompany.com</div>
        </div>
    </div>


<?php if ($tab === 'company'): ?>
    <!-- ── Totals ───────────────────────────────────────────────────── -->
    <div class="totals-box">
        <div><div class="t-lbl">Total Volume</div><div class="t-val"><?= money($totals['vol']) ?></div></div>
        <div><div class="t-lbl">Commission Paid</div><div class="t-val"><?= money($totals['comm']) ?></div></div>
    </div>

    <!-- Listed -->
    <?php $lc = mysqli_num_rows($listed_res); ?>
    <div class="sec-title">Listed (<?= $lc ?>)</div>
    <?php if ($lc): ?>
    <table><thead><tr><th>#</th><th>MLS #</th><th>Agent</th><th>Seller</th><th>Address</th><th>City</th><th>Price</th><th>UC Price</th><th>D.O.L</th><th>D.O.E</th></tr></thead>
    <tbody><?php $i=1; while($r=mysqli_fetch_assoc($listed_res)): ?>
    <tr><td><?=$i++?></td><td><?=h($r['mls_number'])?></td><td><?=h($r['agent'])?></td><td><?=h($r['seller_name'])?></td><td><?=h($r['address1'])?></td><td><?=h($r['city'])?></td><td><?=money($r['price'])?></td><td><?=$r['uc_price']?money($r['uc_price']):''?></td><td><?=fmtD($r['dol'])?></td><td><?=fmtD($r['doe'])?></td></tr>
    <?php endwhile;?></tbody></table>
    <?php else: ?><p style="color:#6c757d;font-size:12px;padding:4px 0">No listed properties in this period.</p><?php endif; ?>

    <!-- Closed -->
    <?php $cc = mysqli_num_rows($closed_res); ?>
    <div class="sec-title">Closed (<?= $cc ?>)</div>
    <?php if ($cc): ?>
    <table><thead><tr><th>#</th><th>Agent</th><th>Buyer</th><th>Seller</th><th>Address</th><th>City</th><th>Final Price</th><th>Close Date</th><th>Days on Market</th><th>PP/UC %</th><th>Lead Source</th></tr></thead>
    <tbody><?php $i=1; while($r=mysqli_fetch_assoc($closed_res)): ?>
    <tr><td><?=$i++?></td><td><?=h($r['agent'])?></td><td><?=h($r['buyer_name'])?></td><td><?=h($r['seller_name'])?></td><td><?=h($r['address1'])?></td><td><?=h($r['city'])?></td><td><?=money($r['final_price'])?></td><td><?=fmtD($r['closing_date'])?></td><td><?=intval($r['dom'])?></td><td><?=$r['pp_uc']?number_format($r['pp_uc'],2):''?>%</td><td><?=h($r['lead_source'])?></td></tr>
    <?php endwhile;?>
    <tr class="avg-row"><td colspan="6" style="text-align:right;padding-right:8px">Average</td><td><?=money($avgs['avg_p'])?></td><td></td><td><?=$avgs['avg_dom']?number_format($avgs['avg_dom'],1):''?></td><td><?=$avgs['avg_pp']?number_format($avgs['avg_pp'],3):''?>%</td><td></td></tr>
    </tbody></table>
    <?php else: ?><p style="color:#6c757d;font-size:12px;padding:4px 0">No closed transactions in this period.</p><?php endif; ?>

    <!-- Under Contract -->
    <?php $uc = mysqli_num_rows($uc_res); ?>
    <div class="sec-title">Under Contract (<?= $uc ?>)</div>
    <?php if ($uc): ?>
    <table><thead><tr><th>#</th><th>Agent</th><th>Buyer</th><th>Seller</th><th>Address</th><th>City</th><th>Price</th><th>Contract Date</th></tr></thead>
    <tbody><?php $i=1; while($r=mysqli_fetch_assoc($uc_res)): ?>
    <tr><td><?=$i++?></td><td><?=h($r['agent'])?></td><td><?=h($r['buyer_name'])?></td><td><?=h($r['seller_name'])?></td><td><?=h($r['address1'])?></td><td><?=h($r['city'])?></td><td><?=money($r['price'])?></td><td><?=fmtD($r['contract_date'])?></td></tr>
    <?php endwhile;?></tbody></table>
    <?php else: ?><p style="color:#6c757d;font-size:12px;padding:4px 0">No under-contract properties in this period.</p><?php endif; ?>


<?php elseif ($tab === 'progress'): ?>
    <div class="prog-grid">
        <!-- Under Contract -->
        <div class="prog-panel"><h6>Under Contract</h6>
        <?php foreach($months_names as $idx=>$mn): $mn_num=$idx+1; $c=$uc_m[$mn_num]??0; if(!$c) continue; ?>
        <div class="prog-month"><?=$mn?></div>
        <div class="prog-row"><span><?=h($agent_label)?></span><span><?=$c?></span></div>
        <div class="prog-total"><span>Total</span><span><?=$c?></span></div>
        <?php endforeach; if(!array_sum($uc_m)) echo '<p style="font-size:10px;color:#6c757d">No data</p>'; ?>
        </div>
        <!-- Closed -->
        <div class="prog-panel"><h6>Closed</h6>
        <?php foreach($months_names as $idx=>$mn): $mn_num=$idx+1; $c=$cl_m[$mn_num]??0; if(!$c) continue; ?>
        <div class="prog-month"><?=$mn?></div>
        <div class="prog-row"><span><?=h($agent_label)?></span><span><?=$c?></span></div>
        <div class="prog-total"><span>Total</span><span><?=$c?></span></div>
        <?php endforeach; if(!array_sum($cl_m)) echo '<p style="font-size:10px;color:#6c757d">No data</p>'; ?>
        </div>
        <!-- Listed -->
        <div class="prog-panel"><h6>Listed / Rescinded</h6>
        <?php foreach($months_names as $idx=>$mn): $mn_num=$idx+1; $lc=$li_m[$mn_num]??0; $rc=$re_m[$mn_num]??0; if(!$lc&&!$rc) continue; ?>
        <div class="prog-month"><?=$mn?></div>
        <div class="prog-row"><span><?=h($agent_label)?></span><span><?=$lc?> / <?=$rc?></span></div>
        <div class="prog-total"><span>Total</span><span><?=$lc?> / <?=$rc?></span></div>
        <?php endforeach; if(!array_sum($li_m)&&!array_sum($re_m)) echo '<p style="font-size:10px;color:#6c757d">No data</p>'; ?>
        </div>
    </div>
    <!-- YTD -->
    <div class="ytd-box">
        <strong style="font-size:12px;color:#1e3a5f">YEAR TO DATE (<?= $year ?>)</strong>
        <div class="ytd-grid">
            <div class="ytd-stat"><div class="ytd-num"><?=intval($ytd['ytd_cl']??0)?></div><div class="ytd-lbl">Closed</div></div>
            <div class="ytd-stat"><div class="ytd-num"><?=intval($ytdl['ytd_li']??0)?></div><div class="ytd-lbl">Listed</div></div>
            <div class="ytd-stat"><div class="ytd-num"><?=intval($ytdl['ytd_re']??0)?></div><div class="ytd-lbl">Rescinded</div></div>
            <div class="ytd-stat"><div class="ytd-num" style="font-size:14px"><?=money($ytd['ytd_vol']??0)?></div><div class="ytd-lbl">Volume</div></div>
            <div class="ytd-stat"><div class="ytd-num" style="font-size:14px"><?=money($ytd['ytd_comm']??0)?></div><div class="ytd-lbl">Commission</div></div>
        </div>
    </div>


<?php elseif ($tab === 'listings'): ?>
    <?php if(empty($grouped)): ?>
    <p style="color:#6c757d;font-size:12px">No listings found for the selected criteria.</p>
    <?php endif; ?>
    <?php foreach($grouped as $month_key => $status_groups):
        $mt = array_sum(array_map('count',$status_groups)); ?>
    <div class="sec-title"><?=h($month_key)?> (<?=$mt?>)</div>
    <?php foreach(['Closed','Under Contract','Listed'] as $st):
        if(!isset($status_groups[$st])) continue; $rows=$status_groups[$st]; ?>
    <p style="font-weight:700;font-size:11px;margin:6px 0 3px"><?=h($st)?></p>
    <table><thead><tr><th>#</th><th>Agent</th><th>MLS #</th><th>Price</th><th>Seller</th><th>Address</th><th>City</th></tr></thead>
    <tbody><?php $i=1; foreach($rows as $r): ?>
    <tr><td><?=$i++?></td><td><?=h($r['agent'])?></td><td><?=h($r['mls_number'])?></td><td><?=money($r['price'])?></td><td><?=h($r['seller_name'])?></td><td><?=h($r['address1'])?></td><td><?=h($r['city'])?></td></tr>
    <?php endforeach;?></tbody></table>
    <?php endforeach; ?>
    <?php endforeach; ?>


<?php elseif ($tab === 'summary'): ?>
    <div class="totals-box">
        <div><div class="t-lbl"><?=h($agent_label)?> — Total Volume</div><div class="t-val"><?=money($totals['vol']??0)?></div></div>
        <div><div class="t-lbl">Commission Paid</div><div class="t-val"><?=money($totals['comm']??0)?></div></div>
    </div>
    <table><thead><tr><th>Agent</th><th>Total Volume</th><th>Commission Paid</th></tr></thead>
    <tbody>
    <?php $has=false; while($r=mysqli_fetch_assoc($summary_res)): $has=true; ?>
    <tr><td><?=h($r['agent'])?></td><td><?=money($r['vol'])?></td><td><?=money($r['comm'])?></td></tr>
    <?php endwhile; if(!$has): ?>
    <tr><td colspan="3" style="color:#6c757d">No closed transactions found for this period.</td></tr>
    <?php endif; ?>
    </tbody></table>
<?php endif; ?>

</div><!-- /.report-wrap -->
</body>
</html>