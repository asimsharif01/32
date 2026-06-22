<?php
// lending_reports.php — All 6 Laser Lending reports
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

// ── Parameters ────────────────────────────────────────────────────────────
$from_date     = $_GET['from_date']     ?? date('Y-01-01');
$to_date       = $_GET['to_date']       ?? date('Y-12-31');
$year          = intval($_GET['year']   ?? date('Y'));
$consultant_id = intval($_GET['consultant_id'] ?? 0);
$active_tab    = $_GET['tab'] ?? 'funded';   // funded | funded_per_type | company | progress | referral | consultant

$from_esc = mysqli_real_escape_string($conn, $from_date);
$to_esc   = mysqli_real_escape_string($conn, $to_date);

function h($v) { return htmlspecialchars($v ?? ''); }
function money($v) { return '$' . number_format((float)($v ?? 0), 2); }
function fmtD($d) { return ($d && $d != '0000-00-00') ? date('m/d/Y', strtotime($d)) : ''; }

// ── Status IDs (by name — safer than hardcoded numbers) ───────────────────
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

// ── Consultants for dropdown (active + anyone with loans in range) ────────
$consultants = [];
$cr = mysqli_query($conn, "SELECT id, name, employment_start_date FROM loan_consultants ORDER BY name");
while ($r = mysqli_fetch_assoc($cr)) $consultants[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — Laser Lending</title>
    <link rel="shortcut icon" type="image/png" href="images/favicon.png">
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
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= h($from_date) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm">To</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= h($to_date) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm">Year</label>
                    <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" min="2000" max="2099">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Consultant
                        <small class="text-muted">(required for Consultant Summary)</small>
                    </label>
                    <select name="consultant_id" class="form-select form-select-sm">
                        <option value="0">— Select Consultant —</option>
                        <?php foreach ($consultants as $c):
                            $sel = ($consultant_id == $c['id']) ? 'selected' : '';
                        ?>
                        <option value="<?= $c['id'] ?>" <?= $sel ?>><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
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
                'funded'          => 'Funded Loans',
                'funded_per_type' => 'Funded Loans Per Type',
                'company'         => 'Company Summary',
                'progress'        => 'Progress Report',
                'referral'        => 'Referral Source',
                'consultant'      => 'Consultant Summary',
            ];
            foreach ($tabs as $slug => $label):
                $active_class = ($active_tab === $slug) ? 'active' : '';
                $url = '?' . http_build_query(array_merge($_GET, ['tab' => $slug]));
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $active_class ?>" href="<?= $url ?>"><?= h($label) ?></a>
            </li>
            <?php endforeach; ?>
        </ul>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 1 — FUNDED LOANS  (grouped by Consultant)
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php if ($active_tab === 'funded'):

            $sql = "
                SELECT lc.name AS consultant, l.borrower_name, l.date_closed,
                       (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
                       lt.name AS loan_type, pt.name AS purchase_type, rt.name AS role_type
                FROM loans l
                LEFT JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
                LEFT JOIN loan_types       lt ON l.loan_type_id       = lt.id
                LEFT JOIN purchase_types   pt ON l.purchase_type_id   = pt.id
                LEFT JOIN loan_role_types  rt ON l.loan_role_type_id  = rt.id
                WHERE l.status_id = $s_funded
                  AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
                ORDER BY consultant, l.date_closed
            ";
            $res = mysqli_query($conn, $sql);
            $grouped = [];
            $total_volume = 0;
            while ($row = mysqli_fetch_assoc($res)) {
                $key = $row['consultant'] ?? 'Unassigned';
                $grouped[$key][] = $row;
                $total_volume += $row['loan_total'];
            }
        ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Funded Loans</div>
                    <div class="rpt-meta">Period: <?= fmtD($from_date) ?> – <?= fmtD($to_date) ?></div>
                </div>
                <div class="text-end">
                    <div style="font-size:.75rem;color:#6c757d">Total Volume</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#1e3a5f"><?= money($total_volume) ?></div>
                </div>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'funded'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
            </div>

            <?php if (!$grouped): ?>
            <p class="text-muted">No funded loans in this period.</p>
            <?php endif; ?>

            <?php foreach ($grouped as $consultant => $rows):
                $sub_total = array_sum(array_column($rows, 'loan_total'));
            ?>
            <div class="sub-hdr"><?= h($consultant) ?> (<?= count($rows) ?>)</div>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Borrower</th><th>Funded</th><th>Loan Total</th><th>Loan Type</th><th>Purchase/Refinance</th><th>Brokered/Warehouse</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['borrower_name']) ?></td>
                    <td><?= fmtD($r['date_closed']) ?></td>
                    <td><?= money($r['loan_total']) ?></td>
                    <td><?= h($r['loan_type']) ?></td>
                    <td><?= h($r['purchase_type']) ?></td>
                    <td><?= h($r['role_type']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="2">Subtotal</td><td><?= money($sub_total) ?></td><td colspan="3"></td></tr>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 2 — FUNDED LOANS PER TYPE  (Loan Type → Purchase/Refinance)
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'funded_per_type'):

            $sql = "
                SELECT lt.name AS loan_type, pt.name AS purchase_type,
                       l.borrower_name, l.date_closed,
                       (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
                       rt.name AS role_type
                FROM loans l
                LEFT JOIN loan_types      lt ON l.loan_type_id      = lt.id
                LEFT JOIN purchase_types  pt ON l.purchase_type_id  = pt.id
                LEFT JOIN loan_role_types rt ON l.loan_role_type_id = rt.id
                WHERE l.status_id = $s_funded
                  AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
                ORDER BY loan_type, purchase_type, l.date_closed
            ";
            $res = mysqli_query($conn, $sql);
            $grouped = [];
            $total_volume = 0;
            while ($row = mysqli_fetch_assoc($res)) {
                $t = $row['loan_type'] ?? 'Unspecified';
                $p = $row['purchase_type'] ?? 'Unspecified';
                $grouped[$t][$p][] = $row;
                $total_volume += $row['loan_total'];
            }
        ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Funded Loans Per Type</div>
                    <div class="rpt-meta">Period: <?= fmtD($from_date) ?> – <?= fmtD($to_date) ?></div>
                </div>
                <div class="text-end">
                    <div style="font-size:.75rem;color:#6c757d">Total Volume</div>
                    <div style="font-size:1.2rem;font-weight:700;color:#1e3a5f"><?= money($total_volume) ?></div>
                </div>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'funded_per_type'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
            </div>

            <?php if (!$grouped): ?>
            <p class="text-muted">No funded loans in this period.</p>
            <?php endif; ?>

            <?php foreach ($grouped as $type => $purchaseGroups):
                $type_count = array_sum(array_map('count', $purchaseGroups));
            ?>
            <div class="sub-hdr" style="background:#e9ecef;border-left-color:#1e3a5f"><?= h($type) ?> (<?= $type_count ?>)</div>
            <?php foreach ($purchaseGroups as $purchase => $rows):
                $sub_total = array_sum(array_column($rows, 'loan_total'));
            ?>
            <p style="font-weight:600;font-size:.85rem;margin:8px 0 4px"><?= h($purchase) ?> (<?= count($rows) ?>)</p>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Borrower</th><th>Funded</th><th>Loan Total</th><th>Brokered/Warehouse</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['borrower_name']) ?></td>
                    <td><?= fmtD($r['date_closed']) ?></td>
                    <td><?= money($r['loan_total']) ?></td>
                    <td><?= h($r['role_type']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="2">Total</td><td><?= money($sub_total) ?></td><td></td></tr>
                </tbody>
            </table>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 3 — COMPANY SUMMARY  (sectioned by Status)
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'company'):

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
                    WHERE l.status_id = $statusId
                      AND l.$dateCol BETWEEN '$from' AND '$to'
                    ORDER BY l.$dateCol
                ";
                $rows = [];
                $res = mysqli_query($conn, $sql);
                while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                return $rows;
            }

            $funded_rows    = companySection($conn, $s_funded, 'date_closed', $from_esc, $to_esc);
            $rescinded_rows = companySection($conn, $s_rescinded, 'date_closed', $from_esc, $to_esc);
        ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Company Summary</div>
                    <div class="rpt-meta">Period: <?= fmtD($from_date) ?> – <?= fmtD($to_date) ?></div>
                </div>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'company'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
            </div>

            <?php foreach (['Funded' => $funded_rows, 'Rescinded' => $rescinded_rows] as $label => $rows):
                $sub_total = array_sum(array_column($rows, 'loan_total'));
            ?>
            <div class="sub-hdr"><?= h($label) ?> (<?= count($rows) ?>)</div>
            <?php if ($rows): ?>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Consultant</th><th><?= $label ?> Date</th><th>Loan Total</th><th>Loan Type</th><th>Purchase/Refinance</th><th>Brokered/Warehouse</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['consultant']) ?></td>
                    <td><?= fmtD($r['key_date']) ?></td>
                    <td><?= money($r['loan_total']) ?></td>
                    <td><?= h($r['loan_type']) ?></td>
                    <td><?= h($r['purchase_type']) ?></td>
                    <td><?= h($r['role_type']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row"><td colspan="2">Total</td><td><?= money($sub_total) ?></td><td colspan="3"></td></tr>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">None in this period.</p>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 4 — PROGRESS REPORT  (Status → Month → Consultant counts)
               Computed live via SQL — no tempProgress staging table needed.
               Honors loan_consultants.employment_start_date: a consultant's
               numbers only count for months on/after their start date,
               matching the original VBA "make sure employed on this date"
               logic from Module2.Populate_tempProgress.
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'progress'):

            $months_names = ['January','February','March','April','May','June','July','August','September','October','November','December'];

            function monthlyCounts($conn, $statusId, $dateCol, $year) {
                $sql = "
                    SELECT MONTH(l.$dateCol) mn, lc.name AS consultant, COUNT(*) cnt
                    FROM loans l
                    JOIN loan_consultants lc ON l.loan_consultant_id = lc.id
                    WHERE l.status_id = $statusId AND YEAR(l.$dateCol) = $year
                    GROUP BY MONTH(l.$dateCol), lc.name
                ";
                $out = [];
                $res = mysqli_query($conn, $sql);
                while ($r = mysqli_fetch_assoc($res)) {
                    $out[$r['mn']][$r['consultant']] = intval($r['cnt']);
                }
                return $out;
            }

            $submitted_by_month = monthlyCounts($conn, $s_submitted, 'date_submitted', $year);
            $funded_by_month    = monthlyCounts($conn, $s_funded,    'date_closed',    $year);
            $rescinded_by_month = monthlyCounts($conn, $s_rescinded, 'date_closed',    $year);

            // Build consultant_name => employment_start_date map for the gating rule
            $startDates = [];
            foreach ($consultants as $c) $startDates[$c['name']] = $c['employment_start_date'];

            function employedThisMonth($consultantName, $monthNum, $year, $startDates) {
                if (empty($startDates[$consultantName])) return true; // no start date on file = always counted
                $monthFirst = sprintf('%04d-%02d-01', $year, $monthNum);
                return $monthFirst >= $startDates[$consultantName];
            }
        ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Progress Report</div>
                    <div class="rpt-meta">Year: <?= $year ?></div>
                </div>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'progress'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
            </div>

            <div class="prog-grid">
                <?php foreach ([
                    'Submitted' => $submitted_by_month,
                    'Funded'    => $funded_by_month,
                    'Rescinded' => $rescinded_by_month,
                ] as $label => $byMonth): ?>
                <div class="prog-panel">
                    <h6><?= h($label) ?></h6>
                    <?php
                    $hasAny = false;
                    foreach ($months_names as $idx => $mname):
                        $mn = $idx + 1;
                        $monthData = $byMonth[$mn] ?? [];
                        // Apply employment-start-date gating + total
                        $filtered = [];
                        $monthTotal = 0;
                        foreach ($monthData as $cname => $cnt) {
                            if (employedThisMonth($cname, $mn, $year, $startDates)) {
                                $filtered[$cname] = $cnt;
                                $monthTotal += $cnt;
                            }
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
                    <?php if (!$hasAny): ?>
                    <p class="text-muted small">No data for <?= $year ?>.</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 5 — REFERRAL SOURCE  (grouped by referral source)
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'referral'):

            $sql = "
                SELECT rs.name AS source, l.borrower_name,
                       (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total,
                       ls.name AS status_name
                FROM loans l
                LEFT JOIN loan_referral_sources rs ON l.referral_source_id = rs.id
                LEFT JOIN loan_statuses         ls ON l.status_id          = ls.id
                WHERE l.status_id = $s_funded
                  AND l.date_closed BETWEEN '$from_esc' AND '$to_esc'
                ORDER BY source, l.borrower_name
            ";
            $res = mysqli_query($conn, $sql);
            $grouped = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $key = $row['source'] ?? 'Unknown';
                $grouped[$key][] = $row;
            }
        ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Referral Source</div>
                    <div class="rpt-meta">Period: <?= fmtD($from_date) ?> – <?= fmtD($to_date) ?> (Funded loans)</div>
                </div>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'referral'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
            </div>

            <?php if (!$grouped): ?>
            <p class="text-muted">No funded loans with referral source data in this period.</p>
            <?php endif; ?>

            <?php foreach ($grouped as $source => $rows): ?>
            <div class="sub-hdr"><?= h($source) ?></div>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Borrower</th><th>Loan Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['borrower_name']) ?></td>
                    <td><?= money($r['loan_total']) ?></td>
                    <td><?= h($r['status_name']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; ?>
        </div>


        <?php /* ═══════════════════════════════════════════════════════════
               TAB 6 — CONSULTANT SUMMARY  (single consultant, 3 sections)
               Matches Access Loan Consultant Summary: Welcome Docs /
               Submitted / Funded sections, all gated to one consultant.
               ═══════════════════════════════════════════════════════════ */ ?>
        <?php elseif ($active_tab === 'consultant'): ?>
        <div class="rpt-card">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div>
                    <div class="rpt-title">Loan Consultant Summary</div>
                    <div class="rpt-meta">Period: <?= fmtD($from_date) ?> – <?= fmtD($to_date) ?></div>
                </div>
                <?php if ($consultant_id): ?>
                <a href="lending_print_report.php?<?= http_build_query(array_merge($_GET,['tab'=>'consultant'])) ?>"
                   target="_blank" class="btn btn-sm btn-outline-secondary">🖨️ Export / Print</a>
                <?php endif; ?>
            </div>

            <?php if (!$consultant_id): ?>
            <p class="text-muted">Please select a Consultant above to view this report.</p>
            <?php else:
                $cname = '';
                foreach ($consultants as $c) if ($c['id'] == $consultant_id) $cname = $c['name'];
            ?>
            <h6 class="mb-3"><?= h($cname) ?></h6>

            <?php
            function consultantSection($conn, $consultantId, $statusId, $dateCol, $from, $to) {
                $sql = "
                    SELECT l.borrower_name, l.$dateCol AS key_date,
                           (IFNULL(l.loan_1_amount,0)+IFNULL(l.loan_2_amount,0)) AS loan_total
                    FROM loans l
                    WHERE l.loan_consultant_id = $consultantId
                      AND l.status_id = $statusId
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
            $funded_rows    = consultantSection($conn, $consultant_id, $s_funded,    'date_closed',       $from_esc, $to_esc);

            $funded_total = array_sum(array_column($funded_rows, 'loan_total'));
            ?>

            <div class="totals-box">
                <div><div class="t-lbl">Funded Count</div><div class="t-val"><?= count($funded_rows) ?></div></div>
                <div><div class="t-lbl">Funded Total Volume</div><div class="t-val"><?= money($funded_total) ?></div></div>
            </div>

            <?php foreach (['Welcome Docs' => $welcome_rows, 'Submitted' => $submitted_rows, 'Funded' => $funded_rows] as $label => $rows): ?>
            <div class="sub-hdr"><?= h($label) ?></div>
            <?php if ($rows): ?>
            <table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Borrower Name</th><th>Date</th><th>Loan Total</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= h($r['borrower_name']) ?></td>
                    <td><?= fmtD($r['key_date']) ?></td>
                    <td><?= money($r['loan_total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted small">None in this period.</p>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include('footer.php'); ?>
</body>
</html>