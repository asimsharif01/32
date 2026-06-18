<?php
// transactions/save_transaction.php
// Handles form submission from transaction_detail.php (both INSERT and UPDATE)
// Everything is saved inline into the listings table — no contacts/transaction_roles
require_once '../db.php';

$listing_id = intval($_POST['listing_id'] ?? 0);
$is_edit    = ($listing_id > 0);

// ── Helper: safe string ───────────────────────────────────────────────────
function esc($conn, $val) {
    return mysqli_real_escape_string($conn, trim($val ?? ''));
}
function escDate($conn, $val) {
    return !empty($val) ? "'" . mysqli_real_escape_string($conn, $val) . "'" : 'NULL';
}

// ── Property fields ───────────────────────────────────────────────────────
$mls            = esc($conn, $_POST['mls_number']               ?? '');
$tn             = esc($conn, $_POST['transaction_number']        ?? '');
$address1       = esc($conn, $_POST['address1']                  ?? '');
$city           = esc($conn, $_POST['city']                      ?? '');
$county         = esc($conn, $_POST['county']                    ?? '');
$state          = esc($conn, $_POST['state']                     ?? 'UT');
$zip            = esc($conn, $_POST['zip']                       ?? '');
$prop_type_id   = intval($_POST['property_type_id']              ?? 0) ?: 'NULL';
$purchase_price = floatval($_POST['purchase_price']              ?? 0);
$uc_price       = floatval($_POST['uc_price']                    ?? 0);
$final_price    = floatval($_POST['final_price']                 ?? 0);
$fin_type_id    = intval($_POST['financing_type_id']             ?? 0) ?: 'NULL';
$status_id      = intval($_POST['status_id']                     ?? 0) ?: 'NULL';
$lead_source    = esc($conn, $_POST['lead_source']               ?? '');
$earnest_amt    = floatval($_POST['earnest_money_amount']        ?? 0);
$earnest_with   = esc($conn, $_POST['earnest_money_deposit_with'] ?? '');
$dol            = escDate($conn, $_POST['date_of_listing']       ?? '');
$doe            = escDate($conn, $_POST['date_of_expiration']    ?? '');
$closing_date   = escDate($conn, $_POST['closing_date']          ?? '');
$contract_date  = escDate($conn, $_POST['contract_date']         ?? '');
$private_flag   = isset($_POST['private']) ? 1 : 0;
$comments       = esc($conn, $_POST['comments']                  ?? '');
$multiplier     = floatval($_POST['multiplier']                  ?? 1);

// ── Financial fields ──────────────────────────────────────────────────────
$comm_price     = floatval($_POST['commission_price']   ?? 0);
$comm_pct       = floatval($_POST['commission_pct']     ?? 0);
$comm_other     = floatval($_POST['commission_other']   ?? 0);
$trans_fee      = floatval($_POST['transaction_fee']    ?? 0);
$err_omiss      = floatval($_POST['errors_omissions']   ?? 0);
$agent_split    = floatval($_POST['agent_split']        ?? 0);
$proc_fee       = floatval($_POST['processing_fee']     ?? 0);
$other2         = floatval($_POST['other2']             ?? 0);
$split_with     = esc($conn, $_POST['split_with']       ?? '');

// Recalculate on server side (never trust client-side calc alone)
$net_amt   = ($comm_price * $comm_pct / 100) + $comm_other + $trans_fee + $err_omiss;
$check_amt = ($net_amt * $agent_split / 100) + $proc_fee + $other2;

// ── Buyer fields (inline in listings) ────────────────────────────────────
$buyer_name         = esc($conn, $_POST['buyer_name']         ?? '');
$buyer_home_phone   = esc($conn, $_POST['buyer_home_phone']   ?? '');
$buyer_cell_phone1  = esc($conn, $_POST['buyer_cell_phone1']  ?? '');
$buyer_cell_phone2  = esc($conn, $_POST['buyer_cell_phone2']  ?? '');
$buyer_fax          = esc($conn, $_POST['buyer_fax']          ?? '');
$buyer_email1       = esc($conn, $_POST['buyer_email1']       ?? '');
$buyer_email2       = esc($conn, $_POST['buyer_email2']       ?? '');

// ── Seller fields (inline in listings) ───────────────────────────────────
$seller_name        = esc($conn, $_POST['seller_name']        ?? '');
$seller_home_phone  = esc($conn, $_POST['seller_home_phone']  ?? '');
$seller_cell_phone1 = esc($conn, $_POST['seller_cell_phone1'] ?? '');
$seller_cell_phone2 = esc($conn, $_POST['seller_cell_phone2'] ?? '');
$seller_fax         = esc($conn, $_POST['seller_fax']         ?? '');
$seller_email1      = esc($conn, $_POST['seller_email1']      ?? '');
$seller_email2      = esc($conn, $_POST['seller_email2']      ?? '');

// ── Agent role fields (denormalised inline, 5 roles) ─────────────────────
$role_prefixes = ['LO', 'BEO', 'SEO', 'LA', 'SA'];
$agent_set_parts = [];

foreach ($role_prefixes as $p) {
    $agent_set_parts[] = "{$p}_Name           = '" . esc($conn, $_POST["{$p}_Name"]           ?? '') . "'";
    $agent_set_parts[] = "{$p}_Company        = '" . esc($conn, $_POST["{$p}_Company"]        ?? '') . "'";
    $agent_set_parts[] = "{$p}_Email          = '" . esc($conn, $_POST["{$p}_Email"]          ?? '') . "'";
    $agent_set_parts[] = "{$p}_OfficePhone    = '" . esc($conn, $_POST["{$p}_OfficePhone"]    ?? '') . "'";
    $agent_set_parts[] = "{$p}_CellPhone      = '" . esc($conn, $_POST["{$p}_CellPhone"]      ?? '') . "'";
    $agent_set_parts[] = "{$p}_Fax            = '" . esc($conn, $_POST["{$p}_Fax"]            ?? '') . "'";
    $agent_set_parts[] = "{$p}_AsstName       = '" . esc($conn, $_POST["{$p}_AsstName"]       ?? '') . "'";
    $agent_set_parts[] = "{$p}_AsstOfficePhone= '" . esc($conn, $_POST["{$p}_AsstOfficePhone"] ?? '') . "'";
    $agent_set_parts[] = "{$p}_AsstCellPhone1 = '" . esc($conn, $_POST["{$p}_AsstCellPhone1"] ?? '') . "'";
    $agent_set_parts[] = "{$p}_AsstFax        = '" . esc($conn, $_POST["{$p}_AsstFax"]        ?? '') . "'";
    $agent_set_parts[] = "{$p}_AsstEmail      = '" . esc($conn, $_POST["{$p}_AsstEmail"]      ?? '') . "'";
    $agent_set_parts[] = "{$p}_AddAsstFlag    = "  . (isset($_POST["{$p}_AddAsstFlag"]) ? 1 : 0);

    // ForReport flag only for LA and SA
    if ($p === 'LA' || $p === 'SA') {
        // Default to 1 (include in reports) if not explicitly unchecked
        $agent_set_parts[] = "{$p}_ForReport = " . (isset($_POST["{$p}_ForReport"]) ? 1 : 1);
    }
}

$agent_sql = implode(",\n        ", $agent_set_parts);

// ── UPDATE or INSERT ──────────────────────────────────────────────────────
if ($is_edit) {

    $sql = "
        UPDATE listings SET
            mls_number                  = '$mls',
            transaction_number          = '$tn',
            address1                    = '$address1',
            city                        = '$city',
            county                      = '$county',
            state                       = '$state',
            zip                         = '$zip',
            property_type_id            = $prop_type_id,
            purchase_price              = $purchase_price,
            uc_price                    = $uc_price,
            final_price                 = $final_price,
            financing_type_id           = $fin_type_id,
            status_id                   = $status_id,
            lead_source                 = '$lead_source',
            earnest_money_amount        = $earnest_amt,
            earnest_money_deposit_with  = '$earnest_with',
            date_of_listing             = $dol,
            date_of_expiration          = $doe,
            closing_date                = $closing_date,
            contract_date               = $contract_date,
            private                     = $private_flag,
            comments                    = '$comments',
            commission_price            = $comm_price,
            commission_pct              = $comm_pct,
            commission_other            = $comm_other,
            transaction_fee             = $trans_fee,
            errors_omissions            = $err_omiss,
            commission_amount           = $net_amt,
            agent_split                 = $agent_split,
            processing_fee              = $proc_fee,
            other2                      = $other2,
            check_amount                = $check_amt,
            split_with                  = '$split_with',
            multiplier                  = $multiplier,
            buyer_name                  = '$buyer_name',
            buyer_home_phone            = '$buyer_home_phone',
            buyer_cell_phone1           = '$buyer_cell_phone1',
            buyer_cell_phone2           = '$buyer_cell_phone2',
            buyer_fax                   = '$buyer_fax',
            buyer_email1                = '$buyer_email1',
            buyer_email2                = '$buyer_email2',
            seller_name                 = '$seller_name',
            seller_home_phone           = '$seller_home_phone',
            seller_cell_phone1          = '$seller_cell_phone1',
            seller_cell_phone2          = '$seller_cell_phone2',
            seller_fax                  = '$seller_fax',
            seller_email1               = '$seller_email1',
            seller_email2               = '$seller_email2',
            $agent_sql
        WHERE id = $listing_id
    ";

} else {

    // Build column list and value list dynamically for INSERT
    // Core columns
    $cols = "mls_number, transaction_number, address1, city, county, state, zip,
             property_type_id, purchase_price, uc_price, final_price,
             financing_type_id, status_id, lead_source,
             earnest_money_amount, earnest_money_deposit_with,
             date_of_listing, date_of_expiration, closing_date, contract_date,
             private, comments,
             commission_price, commission_pct, commission_other,
             transaction_fee, errors_omissions, commission_amount,
             agent_split, processing_fee, other2, check_amount,
             split_with, multiplier,
             buyer_name, buyer_home_phone, buyer_cell_phone1, buyer_cell_phone2,
             buyer_fax, buyer_email1, buyer_email2,
             seller_name, seller_home_phone, seller_cell_phone1, seller_cell_phone2,
             seller_fax, seller_email1, seller_email2";

    $vals = "'$mls', '$tn', '$address1', '$city', '$county', '$state', '$zip',
             $prop_type_id, $purchase_price, $uc_price, $final_price,
             $fin_type_id, $status_id, '$lead_source',
             $earnest_amt, '$earnest_with',
             $dol, $doe, $closing_date, $contract_date,
             $private_flag, '$comments',
             $comm_price, $comm_pct, $comm_other,
             $trans_fee, $err_omiss, $net_amt,
             $agent_split, $proc_fee, $other2, $check_amt,
             '$split_with', $multiplier,
             '$buyer_name', '$buyer_home_phone', '$buyer_cell_phone1', '$buyer_cell_phone2',
             '$buyer_fax', '$buyer_email1', '$buyer_email2',
             '$seller_name', '$seller_home_phone', '$seller_cell_phone1', '$seller_cell_phone2',
             '$seller_fax', '$seller_email1', '$seller_email2'";

    // Append agent columns dynamically
    foreach ($role_prefixes as $p) {
        $cols .= ", {$p}_Name, {$p}_Company, {$p}_Email, {$p}_OfficePhone, {$p}_CellPhone,
                   {$p}_Fax, {$p}_AsstName, {$p}_AsstOfficePhone, {$p}_AsstCellPhone1,
                   {$p}_AsstFax, {$p}_AsstEmail, {$p}_AddAsstFlag";
        $vals .= ",
                  '" . esc($conn, $_POST["{$p}_Name"]            ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_Company"]         ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_Email"]           ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_OfficePhone"]     ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_CellPhone"]       ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_Fax"]             ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_AsstName"]        ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_AsstOfficePhone"] ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_AsstCellPhone1"]  ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_AsstFax"]         ?? '') . "',
                  '" . esc($conn, $_POST["{$p}_AsstEmail"]       ?? '') . "',
                  "  . (isset($_POST["{$p}_AddAsstFlag"]) ? 1 : 0);

        if ($p === 'LA' || $p === 'SA') {
            $cols .= ", {$p}_ForReport";
            $vals .= ", 1"; // default include in reports
        }
    }

    $sql = "INSERT INTO listings ($cols) VALUES ($vals)";
}

if (!mysqli_query($conn, $sql)) {
    die('DB error: ' . mysqli_error($conn));
}

if (!$is_edit) {
    $listing_id = mysqli_insert_id($conn);
}

// ── Save milestones ───────────────────────────────────────────────────────
$milestone_types = [
    'Date of Contract',
    'Seller Disclosure',
    'Due Diligence',
    'Financing & Appraisal',
    'Settlement',
];

foreach ($milestone_types as $mt) {
    $due_date  = escDate($conn, $_POST['milestone'][$mt]['due_date'] ?? '');
    $completed = isset($_POST['milestone'][$mt]['completed']) ? 1 : 0;
    $na_flag   = isset($_POST['milestone'][$mt]['na_flag'])   ? 1 : 0;
    $mt_esc    = mysqli_real_escape_string($conn, $mt);

    $check = mysqli_query($conn,
        "SELECT id FROM listing_milestones
         WHERE listing_id = $listing_id AND milestone_type = '$mt_esc'"
    );

    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn,
            "UPDATE listing_milestones
             SET due_date = $due_date, completed = $completed, na_flag = $na_flag
             WHERE listing_id = $listing_id AND milestone_type = '$mt_esc'"
        );
    } else {
        mysqli_query($conn,
            "INSERT INTO listing_milestones (listing_id, milestone_type, due_date, completed, na_flag)
             VALUES ($listing_id, '$mt_esc', $due_date, $completed, $na_flag)"
        );
    }
}

// ── Redirect back to the detail page so user can see the saved record ─────
header("Location: ../transaction_detail.php?id=$listing_id");
exit;