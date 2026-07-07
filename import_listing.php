<?php
// import_listings_fixed.php
// Imports PropertyTb.xlsx into the listings table and populates
// listing_milestones correctly.
//
// PROBLEMS THIS FIXES vs the previous import:
//
// 1. MILESTONE DATES were never written to listings because the columns
//    (seller_disclosure_date, due_diligence_date, financing_appraisal_date,
//    settlement_date) do NOT exist on the listings table — those were
//    assumed but never created. This script writes deadlines directly
//    into listing_milestones from the Excel source, bypassing listings
//    entirely.
//
// 2. SETTLEMENT DATE: Access's Settlement_Deadline_Date is 100% NULL.
//    The real settlement date is Closing_Date. This script maps
//    'Settlement' → Closing_Date (same date used in all Access reports).
//
// 3. FINANCING & APPRAISAL: Access calls it Funding_and_Appraisal,
//    completely different name. Correctly mapped here.
//
// 4. STATUS MISMATCH: Access stores "Closed"/"Rescinded"/"Listed"/
//    "Under Contract" as plain text. This script looks up the matching
//    status_id from sales_statuses by name (case-insensitive).
//
// 5. DATES as raw Excel serials: toArray() $formatData=false avoids the
//    formatted-string-parsing bug that caused NULL dates in the loan import.
//
// BEFORE RUNNING:
//   - Run cleanup: TRUNCATE TABLE listings; TRUNCATE TABLE listing_milestones;
//   - Place PropertyTb.xlsx in the same folder as this script
//   - Run from browser: http://localhost/032/import_listings_fixed.php

require_once 'vendor/autoload.php';
require_once 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

set_time_limit(0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth']) || $_SESSION['role'] !== 'super_admin') {
    die('Access denied.');
}

echo "<h2>Listings Import (Fixed Version)</h2><pre>";

$excelFile = __DIR__ . '/PropertyTb.xlsx';
if (!file_exists($excelFile)) die("ERROR: PropertyTb.xlsx not found.");

// ── Load lookup tables ────────────────────────────────────────────────────
function loadLookup($conn, $table, $nameCol = 'description') {
    $map = [];
    $res = mysqli_query($conn, "SELECT id, `$nameCol` FROM `$table`");
    while ($row = mysqli_fetch_assoc($res)) {
        $map[strtolower(trim($row[$nameCol]))] = intval($row['id']);
    }
    return $map;
}

// sales_statuses uses 'description' column
$statusMap = loadLookup($conn, 'sales_statuses', 'description');
// Map Access text values → sales_statuses names (case insensitive)
$accessStatusMap = [
    'closed'         => 'closed',
    'rescinded'      => 'rescinded',
    'listed'         => 'listed',
    'under contract' => 'under contract',
];
echo "Statuses loaded: " . implode(', ', array_keys($statusMap)) . "\n";

$finTypeMap   = loadLookup($conn, 'financing_types',  'description');
$propTypeMap  = loadLookup($conn, 'property_types',   'description');
$leadSrcMap   = loadLookup($conn, 'lead_sources',     'description');
echo "Lookups loaded.\n";

// ── Date helpers ────────────────────────────────────────────────────────────
function parseExcelDate($value) {
    if ($value === null || $value === '' || $value === false) return null;
    if (is_numeric($value) && $value > 1) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float)$value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) { return null; }
    }
    $str = trim((string)$value);
    if ($str === '' || strtoupper($str) === 'NULL') return null;
    $ts = strtotime($str);
    return ($ts !== false) ? date('Y-m-d', $ts) : null;
}

function parseMoney($v) {
    if ($v === null || $v === '') return null;
    $clean = str_replace(['$',',',' '], '', (string)$v);
    return is_numeric($clean) ? floatval($clean) : null;
}

function parsePhone($v) {
    if ($v === null || $v === '') return null;
    $n = preg_replace('/[^0-9]/', '', (string)floatval($v));
    return strlen($n) >= 7 ? $n : null;
}

function esc($conn, $v) {
    if ($v === null) return null;
    return mysqli_real_escape_string($conn, (string)$v);
}

function nullOrStr($v) {
    $v = trim((string)($v ?? ''));
    return $v === '' || strtoupper($v) === 'NAN' ? null : $v;
}

// ── milestone_type → [Access column, NA-flag column] ─────────────────────
// CRITICAL CORRECTION:
//   'Settlement' → Closing_Date (NOT Settlement_Deadline_Date which is 100% NULL)
//   'Financing & Appraisal' → Funding_and_Appraisal (different name in Access)
$milestoneMap = [
    'Date of Contract'      => ['Contract_Date',         'Contract_DateNA'],
    'Seller Disclosure'     => ['Sellers_Disclosure_Date','Sellers_Disclosure_DateNA'],
    'Due Diligence'         => ['Due_Diligence_Deadline', 'Due_Diligence_DeadlineNA'],
    'Financing & Appraisal' => ['Funding_and_Appraisal',  'Funding_and_AppraisalNA'],
    'Settlement'            => ['Closing_Date',           'Closing_DateNA'],
];

// ── Milestone type and completion column on listing_milestones
//    (no 'completed' or 'seller_disclosure_completed' flags exist on
//     the listings table — milestone_milestones has its own 'completed' col)

// ── Load Excel ──────────────────────────────────────────────────────────────
$spreadsheet = IOFactory::load($excelFile);
$ws = $spreadsheet->getActiveSheet();
// formatData=false: get raw values so dates come as Excel serials, not strings
$rows = $ws->toArray(null, true, false, false);
$header = array_shift($rows);
$colIndex = array_flip($header);

echo "Excel loaded: " . count($rows) . " rows, " . count($header) . " columns.\n";
echo "----------------------------------------\n";

$imported = 0;
$skipped  = 0;
$errors   = [];
$milestoneRows = 0;

$conn->begin_transaction();

// ── Helper: get a cell value by column name, return null if empty/NaN ──────
function col($row, $colIndex, $name) {
    $idx = $colIndex[$name] ?? null;
    if ($idx === null) return null;
    $v = $row[$idx] ?? null;
    return ($v === null || trim((string)$v) === '' || strtolower(trim((string)$v)) === 'nan') ? null : $v;
}

// ── SQL value helpers ───────────────────────────────────────────────────────
$q = function($v) { return $v === null ? "NULL" : "'$v'"; };  // quoted or NULL
$n = function($v) { return $v === null ? "NULL" : $v; };      // numeric or NULL

foreach ($rows as $rowNum => $row) {
    // Skip completely empty rows
    if (empty(array_filter($row, fn($v) => $v !== null && trim((string)$v) !== ''))) continue;

    $accessStatus = strtolower(trim((string)(col($row, $colIndex, 'Status') ?? '')));
    $statusId = null;
    if ($accessStatus !== '') {
        $mappedStatus = $accessStatusMap[$accessStatus] ?? $accessStatus;
        $statusId = $statusMap[$mappedStatus] ?? null;
        if (!$statusId) {
            $errors[] = "Row " . ($rowNum + 2) . ": Unknown status '$accessStatus'";
        }
    }

    $finType  = strtolower(trim((string)(col($row, $colIndex, 'Financing_Type') ?? '')));
    $finTypeId = $finType ? ($finTypeMap[$finType] ?? null) : null;

    $propType  = strtolower(trim((string)(col($row, $colIndex, 'Type') ?? '')));
    $propTypeId = $propType ? ($propTypeMap[$propType] ?? null) : null;

    $lead = strtolower(trim((string)(col($row, $colIndex, 'Lead') ?? '')));
    $leadSrcId = $lead ? ($leadSrcMap[$lead] ?? null) : null;

    // ── Build INSERT for listings ─────────────────────────────────────────
    $mls         = esc($conn, nullOrStr(col($row, $colIndex, 'MLS_Number')));
    $txnNo       = esc($conn, nullOrStr(col($row, $colIndex, 'Transaction_Number')));
    $addr1       = esc($conn, nullOrStr(col($row, $colIndex, 'Address1')));
    $addr2       = esc($conn, nullOrStr(col($row, $colIndex, 'Address2')));
    $city        = esc($conn, nullOrStr(col($row, $colIndex, 'City')));
    $state       = esc($conn, nullOrStr(col($row, $colIndex, 'State')));
    $zip         = esc($conn, nullOrStr(col($row, $colIndex, 'ZipCode')));
    $purchPrice  = parseMoney(col($row, $colIndex, 'Purchase_Price'));
    $ucPrice     = parseMoney(col($row, $colIndex, 'UC_Price'));
    $finalPrice  = parseMoney(col($row, $colIndex, 'Final_Price'));
    $earnAmt     = parseMoney(col($row, $colIndex, 'Earnest_Money_Amount'));
    $earnWith    = esc($conn, nullOrStr(col($row, $colIndex, 'Earnest_Money_On_Deposit_With')));
    $dolDate     = parseExcelDate(col($row, $colIndex, 'Date_of_Listing'));
    $doeDate     = parseExcelDate(col($row, $colIndex, 'Date_of_Expiration'));
    $contractDt  = parseExcelDate(col($row, $colIndex, 'Contract_Date'));
    $closingDt   = parseExcelDate(col($row, $colIndex, 'Closing_Date'));
    $comments    = esc($conn, nullOrStr(col($row, $colIndex, 'Comments')));
    $isPrivate   = ((col($row, $colIndex, 'Private') ?? false) == true) ? 1 : 0;
    $multiplier  = intval(col($row, $colIndex, 'Multiplier') ?? 1);
    $splitWith   = esc($conn, nullOrStr(col($row, $colIndex, 'Split_With')));

    // Commission fields
    $commPrice   = parseMoney(col($row, $colIndex, 'Commission_Price'));
    $commPct     = parseMoney(col($row, $colIndex, 'Commission_Pct'));
    $commOther   = parseMoney(col($row, $colIndex, 'Commission_Other'));
    $txnFee      = parseMoney(col($row, $colIndex, 'Transaction_Fee'));
    $errOmit     = parseMoney(col($row, $colIndex, 'Errors_and_Omissions'));
    $agentSplit  = parseMoney(col($row, $colIndex, 'Agent_Split'));
    $procFee     = parseMoney(col($row, $colIndex, 'Processing_Fee'));
    $other2      = parseMoney(col($row, $colIndex, 'Other2'));

    // Agent/Key Player fields
    $LA_Name = esc($conn, nullOrStr(col($row, $colIndex, 'LA_Name')));
    $LA_Co   = esc($conn, nullOrStr(col($row, $colIndex, 'LA_Company')));
    $LA_Fr   = ((col($row, $colIndex, 'LA_For_Report') ?? false) == true) ? 1 : 0;
    $SA_Name = esc($conn, nullOrStr(col($row, $colIndex, 'SA_Name')));
    $SA_Co   = esc($conn, nullOrStr(col($row, $colIndex, 'SA_Company')));
    $SA_Fr   = ((col($row, $colIndex, 'SA_For_Report') ?? false) == true) ? 1 : 0;

    $buyer_name   = esc($conn, nullOrStr(col($row, $colIndex, 'Buyer_Name')));
    $seller_name  = esc($conn, nullOrStr(col($row, $colIndex, 'Seller_Name')));

    // Other key players (LO, BEO, SEO) — abbreviated for brevity
    $LO_Name = esc($conn, nullOrStr(col($row, $colIndex, 'LO_Name')));
    $LO_Co   = esc($conn, nullOrStr(col($row, $colIndex, 'LO_Company')));
    $BEO_Name= esc($conn, nullOrStr(col($row, $colIndex, 'BEO_Name')));
    $BEO_Co  = esc($conn, nullOrStr(col($row, $colIndex, 'BEO_Company')));
    $SEO_Name= esc($conn, nullOrStr(col($row, $colIndex, 'SEO_Name')));
    $SEO_Co  = esc($conn, nullOrStr(col($row, $colIndex, 'SEO_Company')));


    $sql = "INSERT INTO listings (
        mls_number, transaction_number, address1, address2, city, state, zip,
        property_type_id, purchase_price, uc_price, final_price,
        financing_type_id, status_id, lead_source_id,
        earnest_money_amount, earnest_money_deposit_with,
        date_of_listing, date_of_expiration, contract_date, closing_date,
        private, multiplier, split_with, comments,
        commission_price, commission_pct, commission_other,
        transaction_fee, errors_omissions, agent_split, processing_fee, other2,
        buyer_name, seller_name,
        LA_Name, LA_Company, LA_ForReport,
        SA_Name, SA_Company, SA_ForReport,
        LO_Name, LO_Company, BEO_Name, BEO_Company, SEO_Name, SEO_Company
    ) VALUES (
        {$q($mls)}, {$q($txnNo)}, {$q($addr1)}, {$q($addr2)}, {$q($city)}, {$q($state)}, {$q($zip)},
        {$n($propTypeId)}, {$n($purchPrice)}, {$n($ucPrice)}, {$n($finalPrice)},
        {$n($finTypeId)}, {$n($statusId)}, {$n($leadSrcId)},
        {$n($earnAmt)}, {$q($earnWith)},
        {$q($dolDate)}, {$q($doeDate)}, {$q($contractDt)}, {$q($closingDt)},
        $isPrivate, $multiplier, {$q($splitWith)}, {$q($comments)},
        {$n($commPrice)}, {$n($commPct)}, {$n($commOther)},
        {$n($txnFee)}, {$n($errOmit)}, {$n($agentSplit)}, {$n($procFee)}, {$n($other2)},
        {$q($buyer_name)}, {$q($seller_name)},
        {$q($LA_Name)}, {$q($LA_Co)}, $LA_Fr,
        {$q($SA_Name)}, {$q($SA_Co)}, $SA_Fr,
        {$q($LO_Name)}, {$q($LO_Co)}, {$q($BEO_Name)}, {$q($BEO_Co)}, {$q($SEO_Name)}, {$q($SEO_Co)}
    )";

    if (!mysqli_query($conn, $sql)) {
        $errors[] = "Row " . ($rowNum + 2) . ": " . mysqli_error($conn);
        $skipped++;
        continue;
    }

    $listingId = (int)mysqli_insert_id($conn);
    $imported++;

    // ── Write listing_milestones directly from Excel source ─────────────
    foreach ($milestoneMap as $milestoneType => [$dateCol, $naCol]) {
        $rawDate = col($row, $colIndex, $dateCol);
        $rawNA   = col($row, $colIndex, $naCol);
        $dueDate = parseExcelDate($rawDate);
        $hasDate = ($dueDate !== null);
        $naFlag  = (!$hasDate || $rawNA == true) ? 1 : 0;

        $dueDateSql = $hasDate ? "'$dueDate'" : "NULL";
        $mSql = "INSERT INTO listing_milestones
            (listing_id, milestone_type, due_date, completed, na_flag)
            VALUES ($listingId, '" . mysqli_real_escape_string($conn, $milestoneType) . "',
            $dueDateSql, 0, $naFlag)";

        if (mysqli_query($conn, $mSql)) {
            $milestoneRows++;
        } else {
            $errors[] = "Milestone $listingId/$milestoneType: " . mysqli_error($conn);
        }
    }

    if ($imported % 100 === 0) echo "  ... $imported rows imported\n";
}

$conn->commit();

echo "----------------------------------------\n";
echo "✅ Listings imported:  $imported\n";
echo "✅ Milestone rows:     $milestoneRows\n";
echo "⚠️  Skipped:           $skipped\n";
echo "❌ Errors:             " . count($errors) . "\n";

if ($errors) {
    echo "\nFirst 20 errors:\n";
    foreach (array_slice($errors, 0, 20) as $e) echo "  $e\n";
}

echo "</pre><p><a href='transactions.php'>View Transactions →</a></p>";