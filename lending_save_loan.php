<?php
// lending_save_loan.php — handles both create and edit for loans table
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lending_loans.php');
    exit;
}

function esc($conn, $v) { return mysqli_real_escape_string($conn, $v); }

// ── Money fields — strip $ and commas, return NULL if empty ──────────────
function moneyVal($raw) {
    $raw = trim($raw ?? '');
    if ($raw === '') return null;
    $clean = preg_replace('/[^0-9.\-]/', '', $raw);
    return $clean === '' ? null : (float) $clean;
}

// ── Date fields — Flatpickr already sends Y-m-d, NULL if empty ───────────
function dateVal($raw) {
    $raw = trim($raw ?? '');
    return $raw === '' ? null : $raw;
}

// ── Dropdown FK fields — NULL if not selected ─────────────────────────────
function fkVal($raw) {
    $raw = trim($raw ?? '');
    return ($raw === '' || intval($raw) <= 0) ? null : intval($raw);
}

$action = $_POST['action'] ?? '';
$id     = intval($_POST['id'] ?? 0);

// ── Validate required fields ──────────────────────────────────────────────
$borrower_name = trim($_POST['borrower_name'] ?? '');
$status_id     = fkVal($_POST['status_id'] ?? '');

if ($borrower_name === '') {
    $back = $action === 'edit' ? "lending_loan_detail.php?id=$id" : "lending_loan_detail.php?action=create";
    header('Location: ' . $back . '&error=' . urlencode('Borrower name is required.'));
    exit;
}
if (!$status_id) {
    $back = $action === 'edit' ? "lending_loan_detail.php?id=$id" : "lending_loan_detail.php?action=create";
    header('Location: ' . $back . '&error=' . urlencode('Status is required.'));
    exit;
}

// ── Collect all fields ────────────────────────────────────────────────────
$fields = [
    'transaction_no'        => trim($_POST['transaction_no'] ?? '') ?: null,
    'borrower_name'          => $borrower_name,
    'loan_consultant_id'    => fkVal($_POST['loan_consultant_id'] ?? ''),
    'loan_processor_id'     => fkVal($_POST['loan_processor_id'] ?? ''),
    'status_id'              => $status_id,
    'loan_type_id'           => fkVal($_POST['loan_type_id'] ?? ''),
    'purchase_type_id'       => fkVal($_POST['purchase_type_id'] ?? ''),
    'loan_role_type_id'      => fkVal($_POST['loan_role_type_id'] ?? ''),
    'referral_source_id'    => fkVal($_POST['referral_source_id'] ?? ''),
    'loan_1_amount'         => moneyVal($_POST['loan_1_amount'] ?? ''),
    'loan_2_amount'         => moneyVal($_POST['loan_2_amount'] ?? ''),
    'lo_revenue'             => moneyVal($_POST['lo_revenue'] ?? ''),
    'processing_fee'        => moneyVal($_POST['processing_fee'] ?? ''),
    'date_submitted'        => dateVal($_POST['date_submitted'] ?? ''),
    'approved_date'          => dateVal($_POST['approved_date'] ?? ''),
    'date_welcome_docs'     => dateVal($_POST['date_welcome_docs'] ?? ''),
    'to_docs_date'           => dateVal($_POST['to_docs_date'] ?? ''),
    'date_closed'            => dateVal($_POST['date_closed'] ?? ''),
    'lender_check'           => moneyVal($_POST['lender_check'] ?? ''),
    'title_co_check'        => moneyVal($_POST['title_co_check'] ?? ''),
    'plm_check'              => moneyVal($_POST['plm_check'] ?? ''),
    'loan_consultant_check' => moneyVal($_POST['loan_consultant_check'] ?? ''),
    'notes'                  => trim($_POST['notes'] ?? '') ?: null,
];

// ── Build SQL using prepared statement (safer than manual escaping for ~20 fields) ──
$columns = array_keys($fields);
$placeholders = array_fill(0, count($columns), '?');

if ($action === 'create') {

    $sql = "INSERT INTO loans (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
    $stmt = $conn->prepare($sql);

    // Build type string: i=int (FKs), d=double (money), s=string (everything else incl. dates)
    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $val = $fields[$col];
        if (in_array($col, ['loan_consultant_id','loan_processor_id','status_id','loan_type_id','purchase_type_id','loan_role_type_id','referral_source_id'])) {
            $types .= 'i';
        } elseif (in_array($col, ['loan_1_amount','loan_2_amount','lo_revenue','processing_fee','lender_check','title_co_check','plm_check','loan_consultant_check'])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $val;
    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        header('Location: lending_loan_detail.php?action=create&error=' . urlencode('Database error: ' . $stmt->error));
        exit;
    }

    $new_id = $stmt->insert_id;
    saveBorrowerContacts($conn, $new_id);

    header('Location: lending_loan_detail.php?id=' . $new_id . '&success=' . urlencode('Loan created.'));
    exit;

} elseif ($action === 'edit') {

    if ($id <= 0) {
        header('Location: lending_loans.php?error=' . urlencode('Invalid loan ID.'));
        exit;
    }

    $setSql = implode(', ', array_map(fn($c) => "$c = ?", $columns));
    $sql = "UPDATE loans SET $setSql WHERE id = ?";
    $stmt = $conn->prepare($sql);

    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $val = $fields[$col];
        if (in_array($col, ['loan_consultant_id','loan_processor_id','status_id','loan_type_id','purchase_type_id','loan_role_type_id','referral_source_id'])) {
            $types .= 'i';
        } elseif (in_array($col, ['loan_1_amount','loan_2_amount','lo_revenue','processing_fee','lender_check','title_co_check','plm_check','loan_consultant_check'])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $val;
    }
    $types .= 'i';
    $values[] = $id;

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {
        header('Location: lending_loan_detail.php?id=' . $id . '&error=' . urlencode('Database error: ' . $stmt->error));
        exit;
    }

    saveBorrowerContacts($conn, $id);

    header('Location: lending_loan_detail.php?id=' . $id . '&success=' . urlencode('Loan updated.'));
    exit;

} else {
    header('Location: lending_loans.php?error=' . urlencode('Invalid action.'));
    exit;
}

// ── Borrower contacts — delete-all-and-reinsert strategy ──────────────────
// Simpler and more robust than matching individual rows by ID when the
// form allows dynamically adding/removing borrower blocks via JS.
function saveBorrowerContacts($conn, $loanId) {
    $stmt = $conn->prepare("DELETE FROM loan_borrowers WHERE loan_id = ?");
    $stmt->bind_param('i', $loanId);
    $stmt->execute();

    $addresses = $_POST['borrower_address'] ?? [];
    $cities    = $_POST['borrower_city']    ?? [];
    $states    = $_POST['borrower_state']   ?? [];
    $zips      = $_POST['borrower_zip']     ?? [];
    $phones    = $_POST['borrower_phone']   ?? [];
    $emails    = $_POST['borrower_email']   ?? [];

    $count = max(count($addresses), count($cities), count($phones), count($emails));

    $insertStmt = $conn->prepare("
        INSERT INTO loan_borrowers (loan_id, address, city, state, zip, phone, email)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    for ($i = 0; $i < $count; $i++) {
        $address = trim($addresses[$i] ?? '');
        $city    = trim($cities[$i] ?? '');
        $state   = trim($states[$i] ?? '');
        $zip     = trim($zips[$i] ?? '');
        $phone   = trim($phones[$i] ?? '');
        $email   = trim($emails[$i] ?? '');

        // Skip entirely empty rows
        if ($address === '' && $city === '' && $phone === '' && $email === '') {
            continue;
        }

        $insertStmt->bind_param(
            'issssss',
            $loanId, $address, $city, $state, $zip, $phone, $email
        );
        $insertStmt->execute();
    }
}