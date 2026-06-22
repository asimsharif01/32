<?php
// lending_print_report.php — Clean standalone printable version of any
// lending report tab. No nav, no sidebar — same pattern as print_report.php
// on the real estate side.
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

$from_date     = $_GET['from_date']     ?? date('Y-01-01');
$to_date       = $_GET['to_date']       ?? date('Y-12-31');
$year          = intval($_GET['year']   ?? date('Y'));
$consultant_id = intval($_GET['consultant_id'] ?? 0);
$tab           = $_GET['tab'] ?? 'funded';
$allowed       = ['funded','funded_per_type','company','progress','referral','consultant'];
if (!in_array($tab, $allowed)) $tab = 'funded';

$from_esc = mysqli_real_escape_string($conn, $from_date);
$to_esc   = mysqli_real_escape_string($conn, $to_date);

function h($v) { return htmlspecialchars($v ?? ''); }
function money($v) { return '$' . number_format((float)($v ?? 0), 2); }
function fmtD($d) { return ($d && $d != '0000-00-00') ? date('m/d/Y', strtotime($d)) : ''; }

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

$tab_titles = [
    'funded'          => 'Funded Loans',
    'funded_per_type' => 'Funded Loans Per Type',
    'company'         => 'Company Summary',
    'progress'        => 'Progress Report',
    'referral'        => 'Referral Source',
    'consultant'      => 'Loan Consultant Summary',
];

$consultant_name = '';
if ($consultant_id) {
    $stmt = $conn->prepare("SELECT name FROM loan_consultants WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $consultant_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $consultant_name = $r['name'] ?? '';
}

// ── Build data for the selected tab (mirrors lending_reports.php exactly) ──
if ($tab === 'funded') {
    $sql = "
        SELECT lc.name AS consultant, l.borrower_name, l.date_closed,
               (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
               lt.name AS loan_type, pt.name AS purchase_type, rt.name AS role_type
        FROM loans l
        LEFT JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
        LEFT JOIN loan_types       lt ON l.loan_type_id       = lt.id
        LEFT JOIN purchase_types   pt ON l.purchase_type_id   = pt.id
        LEFT JOIN loan_role_types  rt ON l.loan_role_type_id  = rt.id
        WHERE l.status_id = $s_funded AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
        ORDER BY consultant, l.date_closed
    ";
    $res = mysqli_query($conn, $sql);
    $grouped = []; $total_volume = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $grouped[$row['consultant'] ?? 'Unassigned'][] = $row;
        $total_volume += $row['loan_total'];
    }
}

if ($tab === 'funded_per_type') {
    $sql = "
        SELECT lt.name AS loan_type, pt.name AS purchase_type,
               l.borrower_name, l.date_closed,
               (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
               rt.name AS role_type
        FROM loans l
        LEFT JOIN loan_types      lt ON l.loan_type_id      = lt.id
        LEFT JOIN purchase_types  pt ON l.purchase_type_id  = pt.id
        LEFT JOIN loan_role_types rt ON l.loan_role_type_id = rt.id
        WHERE l.status_id = $s_funded AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
        ORDER BY loan_type, purchase_type, l.date_closed
    ";
    $res = mysqli_query($conn, $sql);
    $grouped = []; $total_volume = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $grouped[$row['loan_type'] ?? 'Unspecified'][$row['purchase_type'] ?? 'Unspecified'][] = $row;
        $total_volume += $row['loan_total'];
    }
}

if ($tab === 'company') {
    function companySection($conn, $statusId, $dateCol, $from, $to) {
        $sql = "
            SELECT lc.name AS consultant, l.$dateCol AS key_date,
                   (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
                   lt.name AS loan_type, pt.name AS purchase_type, rt.name AS role_type
            FROM loans l
            LEFT JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
            LEFT JOIN loan_types       lt ON l.loan_type_id       = lt.id
            LEFT JOIN purchase_types   pt ON l.purchase_type_id   = pt.id
            LEFT JOIN loan_role_types  rt ON l.loan_role_type_id  = rt.id
            WHERE l.status_id = $statusId AND l.$dateCol BETWEEN '$from' AND '$to'
            ORDER BY l.$dateCol
        ";
        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }
    $funded_rows    = companySection($conn, $s_funded, 'date_closed', $from_esc, $to_esc);
    $rescinded_rows = companySection($conn, $s_rescinded, 'date_closed', $from_esc, $to_esc);
}

if ($tab === 'progress') {
    $months_names = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function monthlyCounts($conn, $statusId, $dateCol, $year) {
        $sql = "
            SELECT MONTH(l.$dateCol) mn, lc.name AS consultant, COUNT(*) cnt
            FROM loans l JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
            WHERE l.status_id = $statusId AND YEAR(l.$dateCol) = $year
            GROUP BY MONTH(l.$dateCol), lc.name
        ";
        $out = [];
        $res = mysqli_query($conn, $sql);
        while ($r = mysqli_fetch_assoc($res)) $out[$r['mn']][$r['consultant']] = intval($r['cnt']);
        return $out;
    }
    $submitted_by_month = monthlyCounts($conn, $s_submitted, 'date_submitted', $year);
    $funded_by_month    = monthlyCounts($conn, $s_funded,    'date_closed',    $year);
    $rescinded_by_month = monthlyCounts($conn, $s_rescinded, 'date_closed',    $year);

    $startDates = [];
    $cr = mysqli_query($conn, "SELECT name, employment_start_date FROM loan_consultants");
    while ($c = mysqli_fetch_assoc($cr)) $startDates[$c['name']] = $c['employment_start_date'];

    function employedThisMonth($consultantName, $monthNum, $year, $startDates) {
        if (empty($startDates[$consultantName])) return true;
        $monthFirst = sprintf('%04d-%02d-01', $year, $monthNum);
        return $monthFirst >= $startDates[$consultantName];
    }
}

if ($tab === 'referral') {
    $sql = "
        SELECT rs.name AS source, l.borrower_name,
               (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
               ls.name AS status_name
        FROM loans l
        LEFT JOIN loan_referral_sources rs ON l.referral_source_id = rs.id
        LEFT JOIN loan_statuses         ls ON l.status_id          = ls.id
        WHERE l.status_id = $s_funded AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
        ORDER BY source, l.borrower_name
    ";
    $res = mysqli_query($conn, $sql);
    $grouped = [];
    while ($row = mysqli_fetch_assoc($res)) $grouped[$row['source'] ?? 'Unknown'][] = $row;
}

if ($tab === 'consultant' && $consultant_id) {
    function consultantSection($conn, $consultantId, $statusId, $dateCol, $from, $to) {
        $sql = "
            SELECT l.borrower_name, l.$dateCol AS key_date,
                   (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total
            FROM loans l
            WHERE l.loan_consultant_id = $consultantId AND l.status_id = $statusId
              AND l.$dateCol BETWEEN '$from' AND '$to'
            ORDER BY l.$dateCol
        ";
        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
        return $rows;
    }
    $welcome_rows   = consultantSection($conn, $consultant_id, $s_submitted, 'date_welcome_docs', $from_esc, $to_esc);
    $submitted_rows = consultantSection($conn, $consultant_id, $s_submitted, 'date_submitted',    $from_esc, $to_esc);
    $funded_rows_c  = consultantSection($conn, $consultant_id, $s_funded,    'date_closed',       $from_esc, $to_esc);
    $funded_total_c = array_sum(array_column($funded_rows_c, 'loan_total'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tab_titles[$tab]) ?> — Laser Lending</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; padding:24px; font-size:13px; color:#1e293b; }
        .report-wrap { max-width:1100px; margin:0 auto; background:#fff; border-radius:10px; padding:28px 32px; box-shadow:0 4px 20px rgba(0,0,0,.1); }
        .no-print { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .btn-print { background:#1e3a5f;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600; }
        .btn-print:hover { background:#2a4f82; }
        .print-tip { font-size:11px;color:#6b7280;background:#f3f4f6;padding:6px 12px;border-radius:6px; }
        .rpt-header { display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e3a5f;padding-bottom:12px;margin-bottom:18px; }
        .rpt-header h1 { font-size:20px;font-weight:700;color:#0f2b3d; }
        .rpt-header p  { font-size:11.5px;color:#4b5563;margin-top:3px; }
        .co-block { text-align:right;font-size:11.5px;color:#4b5563; }
        .co-name  { font-size:15px;font-weight:700;color:#c0392b; }
        .sec-title { font-size:11px;font-weight:700;background:#eef2ff;padding:4px 10px;border-left:4px solid #1e3a5f;margin:14px 0 8px;color:#1e3a5f;text-transform:uppercase;letter-spacing:.4px; }
        table  { width:100%;border-collapse:collapse;font-size:11.5px; }
        th     { background:#f8f9fc;font-size:10px;font-weight:700;color:#4a5568;text-transform:uppercase;letter-spacing:.5px;padding:6px 8px;text-align:left;border-bottom:2px solid #e2e8f0; }
        td     { padding:7px 8px;border-bottom:1px solid #f0f2f5;vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        .total-row td { font-weight:700;background:#f1f3f5; }
        .totals-box { display:flex;gap:32px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 16px;margin-bottom:12px; }
        .t-lbl { font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px; }
        .t-val { font-size:20px;font-weight:700;color:#1e3a5f; }
        .prog-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px; }
        .prog-panel { border:1px solid #e2e8f0;border-radius:6px;padding:10px; }
        .prog-panel h6 { background:#e9ecef;padding:4px 8px;margin:-10px -10px 8px;border-radius:6px 6px 0 0;font-size:11px;font-weight:700;color:#1e3a5f; }
        .prog-month { font-weight:700;color:#1e3a5f;font-size:11px;margin-top:8px; }
        .prog-row,.prog-total { display:flex;justify-content:space-between;font-size:11px;padding:1px 0; }
        .prog-total { font-weight:700;border-top:1px solid #e2e8f0;margin-top:2px;padding-top:3px; }
        @page { size:A4 portrait; margin:10mm 12mm; }
        @media print {
            * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            body { background:#fff; padding:0; }
            .report-wrap { padding:0; box-shadow:none; border-radius:0; max-width:100%; }
            .no-print { display:none !important; }
            table { font-size:9px; }
            th, td { padding:4px 5px; }
        }
    </style>
</head>
<body>
<div class="report-wrap">

    <div class="no-print">
        <div class="print-tip">💡 Set paper to <strong>A4</strong>, margins to <strong>Minimum</strong>, enable <strong>Background graphics</strong></div>
        <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>

    <div class="rpt-header">
        <div>
            <h1><?= h($tab_titles[$tab]) ?></h1>
            <p>
                <?= $tab === 'progress' ? 'Year: ' . $year : fmtD($from_date) . ' — ' . fmtD($to_date) ?>
                <?= $tab === 'consultant' && $consultant_name ? ' · ' . h($consultant_name) : '' ?>
            </p>
        </div>
        <div class="co-block">
            <div class="co-name">LASER LENDING</div>
            <div>The Future of Funding</div>
        </div>
    </div>


    <?php if ($tab === 'funded'): ?>
        <?php if (!$grouped): ?>
        <p style="color:#6c757d">No funded loans in this period.</p>
        <?php endif; ?>
        <?php foreach ($grouped as $consultant => $rows):
            $sub_total = array_sum(array_column($rows, 'loan_total'));
        ?>
        <div class="sec-title"><?= h($consultant) ?> (<?= count($rows) ?>)</div>
        <table>
            <thead><tr><th>Borrower</th><th>Funded</th><th>Loan Total</th><th>Loan Type</th><th>Purchase/Refinance</th><th>Brokered/Warehouse</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['borrower_name']) ?></td><td><?= fmtD($r['date_closed']) ?></td><td><?= money($r['loan_total']) ?></td><td><?= h($r['loan_type']) ?></td><td><?= h($r['purchase_type']) ?></td><td><?= h($r['role_type']) ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row"><td colspan="2">Subtotal</td><td><?= money($sub_total) ?></td><td colspan="3"></td></tr>
            </tbody>
        </table>
        <?php endforeach; ?>

    <?php elseif ($tab === 'funded_per_type'): ?>
        <?php if (!$grouped): ?>
        <p style="color:#6c757d">No funded loans in this period.</p>
        <?php endif; ?>
        <?php foreach ($grouped as $type => $purchaseGroups):
            $type_count = array_sum(array_map('count', $purchaseGroups));
        ?>
        <div class="sec-title" style="background:#e9ecef"><?= h($type) ?> (<?= $type_count ?>)</div>
        <?php foreach ($purchaseGroups as $purchase => $rows):
            $sub_total = array_sum(array_column($rows, 'loan_total'));
        ?>
        <p style="font-weight:600;font-size:11px;margin:6px 0 3px"><?= h($purchase) ?> (<?= count($rows) ?>)</p>
        <table>
            <thead><tr><th>Borrower</th><th>Funded</th><th>Loan Total</th><th>Brokered/Warehouse</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['borrower_name']) ?></td><td><?= fmtD($r['date_closed']) ?></td><td><?= money($r['loan_total']) ?></td><td><?= h($r['role_type']) ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row"><td colspan="2">Total</td><td><?= money($sub_total) ?></td><td></td></tr>
            </tbody>
        </table>
        <?php endforeach; ?>
        <?php endforeach; ?>

    <?php elseif ($tab === 'company'): ?>
        <?php foreach (['Funded' => $funded_rows, 'Rescinded' => $rescinded_rows] as $label => $rows):
            $sub_total = array_sum(array_column($rows, 'loan_total'));
        ?>
        <div class="sec-title"><?= h($label) ?> (<?= count($rows) ?>)</div>
        <?php if ($rows): ?>
        <table>
            <thead><tr><th>Consultant</th><th><?= $label ?> Date</th><th>Loan Total</th><th>Loan Type</th><th>Purchase/Refinance</th><th>Brokered/Warehouse</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['consultant']) ?></td><td><?= fmtD($r['key_date']) ?></td><td><?= money($r['loan_total']) ?></td><td><?= h($r['loan_type']) ?></td><td><?= h($r['purchase_type']) ?></td><td><?= h($r['role_type']) ?></td></tr>
            <?php endforeach; ?>
            <tr class="total-row"><td colspan="2">Total</td><td><?= money($sub_total) ?></td><td colspan="3"></td></tr>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#6c757d;font-size:11px">None in this period.</p>
        <?php endif; ?>
        <?php endforeach; ?>

    <?php elseif ($tab === 'progress'): ?>
        <div class="prog-grid">
            <?php foreach (['Submitted' => $submitted_by_month, 'Funded' => $funded_by_month, 'Rescinded' => $rescinded_by_month] as $label => $byMonth): ?>
            <div class="prog-panel">
                <h6><?= h($label) ?></h6>
                <?php $hasAny = false; foreach ($months_names as $idx => $mname):
                    $mn = $idx + 1;
                    $monthData = $byMonth[$mn] ?? [];
                    $filtered = []; $monthTotal = 0;
                    foreach ($monthData as $cname => $cnt) {
                        if (employedThisMonth($cname, $mn, $year, $startDates)) { $filtered[$cname] = $cnt; $monthTotal += $cnt; }
                    }
                    if (!$filtered) continue;
                    $hasAny = true;
                ?>
                <div class="prog-month"><?= $mname ?></div>
                <?php foreach ($filtered as $cname => $cnt): ?>
                <div class="prog-row"><span><?= h($cname) ?></span><span><?= $cnt ?></span></div>
                <?php endforeach; ?>
                <div class="prog-total"><span>Total</span><span><?= $monthTotal ?></span></div>
                <?php endforeach; ?>
                <?php if (!$hasAny): ?><p style="font-size:10px;color:#6c757d">No data for <?= $year ?>.</p><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($tab === 'referral'): ?>
        <?php if (!$grouped): ?>
        <p style="color:#6c757d">No funded loans with referral source data in this period.</p>
        <?php endif; ?>
        <?php foreach ($grouped as $source => $rows): ?>
        <div class="sec-title"><?= h($source) ?></div>
        <table>
            <thead><tr><th>Borrower</th><th>Loan Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr><td><?= h($r['borrower_name']) ?></td><td><?= money($r['loan_total']) ?></td><td><?= h($r['status_name']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach; ?>

    <?php elseif ($tab === 'consultant'):
        if (!$consultant_id): ?>
        <p style="color:#6c757d">No consultant selected.</p>
        <?php else: ?>
        <div class="totals-box">
            <div><div class="t-lbl">Funded Count</div><div class="t-val"><?= count($funded_rows_c) ?></div></div>
            <div><div class="t-lbl">Funded Total Volume</div><div class="t-val"><?= money($funded_total_c) ?></div></div>
        </div>
        <?php foreach (['Welcome Docs' => $welcome_rows, 'Submitted' => $submitted_rows, 'Funded' => $funded_rows_c] as $label => $rows): ?>
        <div class="sec-title"><?= h($label) ?></div>
        <?php if ($rows): ?>
        <table>
            <thead><tr><th>#</th><th>Borrower Name</th><th>Date</th><th>Loan Total</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
            <tr><td><?= $i+1 ?></td><td><?= h($r['borrower_name']) ?></td><td><?= fmtD($r['key_date']) ?></td><td><?= money($r['loan_total']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:#6c757d;font-size:11px">None in this period.</p>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>
</body>
</html>