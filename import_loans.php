<?php
// import_loans_fixed.php — Corrected version
// Fixes 4 bugs from the original import_loans.php:
//   1. Dates were always NULL (toArray() returned formatted date strings,
//      parser only handled dash-format) — fixed by reading raw Excel
//      serial numbers instead of formatted strings.
//   2. Status mapping treated Access's raw number as if it already
//      matched our table's auto-increment ID — created a junk "5" row
//      instead of correctly mapping to our real "Submitted" status.
//   3. Same bug for consultants — created junk "Consultant ID: X (from
//      import)" placeholders instead of matching real names.
//   4. No execution time limit — likely caused the 818/1403 partial import.
//
// BEFORE RUNNING THIS: run cleanup_before_reimport.sql first to remove
// the 818 rows + junk lookup rows from the previous buggy run.

require_once 'vendor/autoload.php';
require_once 'db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

set_time_limit(0); // FIX #4 — no PHP execution timeout

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth']) || $_SESSION['role'] !== 'super_admin') {
    die('Access denied. Super Admin only.');
}

// ── Configuration ──────────────────────────────────────────────────────────
$excelFile = __DIR__ . '/Loan_Info.xlsx';

// ── FIX #2 & #3 — Explicit, authoritative mapping tables ───────────────────
// Built from real exports, NOT from coincidental ID matching.

// Access Status number -> real name, confirmed by data-pattern analysis
// of all 1,403 rows in Loan_Info.xlsx:
//   2 (1,119 loans, 99.6% have close date + real checks)  = Funded
//   3 (155 loans, has close dates but $0 checks)           = Rescinded
//   5 (112 loans, has submit dates, rarely closes, $0 fee) = Submitted
//   1 and 4 are too rare (4 and 9 loans) to confirm with confidence
$statusNameMap = [
    '1' => 'Unknown (Status 1)',
    '2' => 'Funded',
    '3' => 'Rescinded',
    '4' => 'Unknown (Status 4)',
    '5' => 'Submitted',
];

// Access Loan_ConsultantID -> real name, from Loan_Consultants.xlsx export
// (authoritative — NOT a guess)
$consultantNameMap = [
    1  => 'Guy Welker',
    2  => 'John Newman',
    3  => 'Mark Davis',
    5  => 'Geoff Newman',
    6  => 'Dale Noble',
    7  => 'Carl DeMita',
    8  => 'DeLoy Griffin',
    9  => 'KJ',
    10 => 'Adam Larson',
    11 => 'Steve Ramseyer',
    12 => 'Darrin Shemon',
    13 => 'Curt Van Hove',
    14 => 'David James',
    15 => 'Zac Miner',
    16 => 'Mark Larson',
    17 => 'Steve Carter',
    18 => 'Daemon Wathen',
    19 => 'Griffin Dutson',
    20 => 'Hector Lopez',
    21 => 'Dale & Griffin',
    22 => 'Brian Thomas',
    23 => 'Michael Turner',
];

// ── Lookup maps for free-text fields (these ARE safe to map by name
//    directly since Access stores them as text, not numeric IDs) ──────────
$lookupMaps = [
    'loan_type_id'       => 'loan_types',
    'purchase_type_id'   => 'purchase_types',
    'loan_role_type_id'  => 'loan_role_types',
    'loan_processor_id'  => 'loan_processors',
    'referral_source_id' => 'loan_referral_sources',
];

// ── Cache to avoid repeat queries for the same name ─────────────────────────
$nameIdCache = [];

function getOrCreateByName($conn, $table, $name, &$cache) {
    $name = trim((string)$name);
    if ($name === '' || strtoupper($name) === 'NULL') return null;

    $cacheKey = "$table::$name";
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $stmt = $conn->prepare("SELECT id FROM `$table` WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $cache[$cacheKey] = intval($row['id']);
        return $cache[$cacheKey];
    }

    $stmt2 = $conn->prepare("INSERT INTO `$table` (name, active) VALUES (?, 1)");
    $stmt2->bind_param('s', $name);
    $stmt2->execute();
    $newId = intval($stmt2->insert_id);
    $cache[$cacheKey] = $newId;
    return $newId;
}

// ── FIX #1 — Robust date parsing ────────────────────────────────────────────
// Root cause of the original bug: toArray() defaults to $formatData=true,
// which returns dates as pre-formatted strings (e.g. "9/1/2009" with
// slashes). The old parser only handled dash-separated strings.
//
// Fix: we call toArray() with $formatData = FALSE below, so date cells
// come through as their RAW Excel serial number (a float) instead of a
// formatted string. That makes the is_numeric() branch reliably fire.
// The strtotime() fallback below is just a safety net for any cell that
// wasn't actually formatted as a date at the Excel level.
function parseExcelDate($value) {
    if ($value === null || $value === '') return null;

    if (is_numeric($value)) {
        try {
            $date = ExcelDate::excelToDateTimeObject($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    $value = trim((string)$value);
    if ($value === '' || strtoupper($value) === 'NULL') return null;

    $ts = strtotime($value);
    return $ts === false ? null : date('Y-m-d', $ts);
}

function parseMoney($value) {
    if ($value === null || $value === '') return null;
    $value = (string) $value;
    $clean = str_replace(['$', ','], '', trim($value));
    if ($clean === '' || strtoupper($clean) === 'NULL') return null;
    return is_numeric($clean) ? floatval($clean) : null;
}

// ── Column map (matches Loan_Info.xlsx column order) ────────────────────────
$colMap = [
    'ID' => 0, 'Loan_ConsultantID' => 1, 'Borrower_Name' => 2,
    'Loan_Type' => 3, 'Purchase_Type' => 4, 'Loan_Role_Type' => 5,
    'Loan_1_Amount' => 6, 'Loan_2_Amount' => 7, 'Date_Welcome_Docs' => 8,
    'Date_Submitted' => 9, 'Date_Closed' => 10, 'Status' => 11,
    'Lender_Check' => 12, 'Title_Co_Check' => 13, 'Loan_Consultant_Check' => 14,
    'PLM_Check' => 15, 'Processing_Fee' => 16, 'TransactionNo' => 17,
    'Loan_Processor' => 18, 'Approved_Date' => 19, 'To_Docs_Date' => 20,
    'LO_Revenue' => 21, 'Referral' => 22,
];

// ── Start Import ──────────────────────────────────────────────────────────
echo "<h1>Importing Loans from Excel (Fixed Version)</h1><pre>";

if (!file_exists($excelFile)) {
    die("ERROR: Excel file not found at: $excelFile");
}

$spreadsheet = IOFactory::load($excelFile);
$worksheet = $spreadsheet->getActiveSheet();

// FIX #1 (the actual mechanism) — formatData = FALSE so dates come through
// as raw Excel serial numbers, not lossy formatted strings.
$rows = $worksheet->toArray(null, true, false, false);

$header = array_shift($rows);
echo "Found " . count($rows) . " rows to process.\n";
echo "----------------------------------------\n";

$imported = 0;
$skipped = 0;
$errors = [];
$unmappedConsultants = [];
$unmappedStatuses = [];

foreach ($rows as $rowIndex => $row) {
    if (empty(array_filter($row, fn($v) => !is_null($v) && trim((string)$v) !== ''))) {
        continue;
    }

    $excel_id          = intval($row[$colMap['ID']] ?? 0);
    $consultant_id_raw = intval($row[$colMap['Loan_ConsultantID']] ?? 0);
    $borrower_name     = trim((string)($row[$colMap['Borrower_Name']] ?? ''));

    $loan_type_text     = trim((string)($row[$colMap['Loan_Type']] ?? ''));
    $purchase_type_text = trim((string)($row[$colMap['Purchase_Type']] ?? ''));
    $role_type_text     = trim((string)($row[$colMap['Loan_Role_Type']] ?? ''));
    $status_raw         = trim((string)($row[$colMap['Status']] ?? ''));
    $processor_text     = trim((string)($row[$colMap['Loan_Processor']] ?? ''));
    $referral_text      = trim((string)($row[$colMap['Referral']] ?? ''));

    $loan_1_amount    = parseMoney($row[$colMap['Loan_1_Amount']] ?? null);
    $loan_2_amount    = parseMoney($row[$colMap['Loan_2_Amount']] ?? null);
    $lender_check     = parseMoney($row[$colMap['Lender_Check']] ?? null);
    $title_co_check   = parseMoney($row[$colMap['Title_Co_Check']] ?? null);
    $consultant_check = parseMoney($row[$colMap['Loan_Consultant_Check']] ?? null);
    $plm_check        = parseMoney($row[$colMap['PLM_Check']] ?? null);
    $processing_fee   = parseMoney($row[$colMap['Processing_Fee']] ?? null);
    $lo_revenue       = parseMoney($row[$colMap['LO_Revenue']] ?? null);

    $date_submitted    = parseExcelDate($row[$colMap['Date_Submitted']] ?? null);
    $date_closed       = parseExcelDate($row[$colMap['Date_Closed']] ?? null);
    $date_welcome_docs = parseExcelDate($row[$colMap['Date_Welcome_Docs']] ?? null);
    $approved_date     = parseExcelDate($row[$colMap['Approved_Date']] ?? null);
    $to_docs_date      = parseExcelDate($row[$colMap['To_Docs_Date']] ?? null);

    $transaction_no = trim((string)($row[$colMap['TransactionNo']] ?? '')) ?: null;

    if ($borrower_name === '') {
        $errors[] = "Row " . ($rowIndex + 2) . ": Missing borrower name (ID: $excel_id)";
        $skipped++;
        continue;
    }

    // ── FIX #3 — Consultant: explicit ID->Name map, then name lookup ──────
    $consultant_id = null;
    if ($consultant_id_raw > 0) {
        if (isset($consultantNameMap[$consultant_id_raw])) {
            $consultant_id = getOrCreateByName($conn, 'loan_consultants', $consultantNameMap[$consultant_id_raw], $nameIdCache);
        } else {
            $unmappedConsultants[$consultant_id_raw] = ($unmappedConsultants[$consultant_id_raw] ?? 0) + 1;
        }
    }

    // ── FIX #2 — Status: explicit number->name map, then name lookup ──────
    $status_id = null;
    if ($status_raw !== '') {
        if (isset($statusNameMap[$status_raw])) {
            $status_id = getOrCreateByName($conn, 'loan_statuses', $statusNameMap[$status_raw], $nameIdCache);
        } else {
            $unmappedStatuses[$status_raw] = ($unmappedStatuses[$status_raw] ?? 0) + 1;
        }
    }

    // ── Free-text lookups (safe to map by name directly) ──────────────────
    $loan_type_id        = getOrCreateByName($conn, 'loan_types', $loan_type_text, $nameIdCache);
    $purchase_type_id    = getOrCreateByName($conn, 'purchase_types', $purchase_type_text, $nameIdCache);
    $loan_role_type_id   = getOrCreateByName($conn, 'loan_role_types', $role_type_text, $nameIdCache);
    $loan_processor_id   = getOrCreateByName($conn, 'loan_processors', $processor_text, $nameIdCache);
    $referral_source_id  = getOrCreateByName($conn, 'loan_referral_sources', $referral_text, $nameIdCache);

    // ── Build the INSERT (prepared statement) ──────────────────────────────
    $stmt = $conn->prepare("INSERT INTO loans (
        transaction_no, borrower_name, loan_consultant_id, loan_processor_id,
        status_id, loan_type_id, purchase_type_id, loan_role_type_id,
        referral_source_id, loan_1_amount, loan_2_amount, lo_revenue, processing_fee,
        date_submitted, approved_date, date_welcome_docs, to_docs_date, date_closed,
        lender_check, title_co_check, plm_check, loan_consultant_check
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        'ssiiiiiiiddddsssssdddd',
        $transaction_no, $borrower_name, $consultant_id, $loan_processor_id,
        $status_id, $loan_type_id, $purchase_type_id, $loan_role_type_id,
        $referral_source_id, $loan_1_amount, $loan_2_amount, $lo_revenue, $processing_fee,
        $date_submitted, $approved_date, $date_welcome_docs, $to_docs_date, $date_closed,
        $lender_check, $title_co_check, $plm_check, $consultant_check
    );

    if ($stmt->execute()) {
        $imported++;
        if ($imported % 100 === 0) echo "  ... imported $imported rows\n";
    } else {
        $errors[] = "Row " . ($rowIndex + 2) . " (ID: $excel_id): " . $stmt->error;
        $skipped++;
    }
}

echo "----------------------------------------\n";
echo "✅ Imported: $imported rows\n";
echo "⚠️  Skipped: $skipped rows\n";
echo "❌ Errors: " . count($errors) . "\n";

if ($unmappedConsultants) {
    echo "\n⚠️  Unmapped consultant IDs (not in consultantNameMap — check these):\n";
    foreach ($unmappedConsultants as $id => $cnt) echo "  - Access ID $id: $cnt loan(s)\n";
}
if ($unmappedStatuses) {
    echo "\n⚠️  Unmapped status values (not in statusNameMap — check these):\n";
    foreach ($unmappedStatuses as $val => $cnt) echo "  - Status '$val': $cnt loan(s)\n";
}

if ($errors) {
    echo "\nError details (first 20):\n";
    foreach (array_slice($errors, 0, 20) as $e) echo "  - $e\n";
    if (count($errors) > 20) echo "  ... and " . (count($errors) - 20) . " more.\n";
}

echo "\n</pre><p><a href='lending_loans.php'>View Loans →</a></p>";