<?php
// agents/add.php — Insert a new agent
require_once '../db.php';

function esc($conn, $key) {
    return mysqli_real_escape_string($conn, trim($_POST[$key] ?? ''));
}

$name               = esc($conn, 'name');
$company            = esc($conn, 'company');
$address1           = esc($conn, 'address1');
$address2           = esc($conn, 'address2');
$city               = esc($conn, 'city');
$state              = esc($conn, 'state');
$zip                = esc($conn, 'zip');
$office_phone       = esc($conn, 'office_phone');
$cell_phone         = esc($conn, 'cell_phone');
$cell_phone2        = esc($conn, 'cell_phone2');
$fax                = esc($conn, 'fax');
$email              = esc($conn, 'email');

// Assistant 1
$asst_name          = esc($conn, 'asst_name');
$asst_office_phone  = esc($conn, 'asst_office_phone');
$asst_cell_phone1   = esc($conn, 'asst_cell_phone1');
$asst_fax           = esc($conn, 'asst_fax');
$asst_email         = esc($conn, 'asst_email');

// Assistant 2
$asst_name2         = esc($conn, 'asst_name2');
$asst_office_phone2 = esc($conn, 'asst_office_phone2');
$asst_cell_phone2   = esc($conn, 'asst_cell_phone2');
$asst_fax2          = esc($conn, 'asst_fax2');
$asst_email2        = esc($conn, 'asst_email2');

// Flags
$is_loan_officer    = isset($_POST['is_loan_officer'])   ? 1 : 0;
$is_buyer_escrow    = isset($_POST['is_buyer_escrow'])   ? 1 : 0;
$is_seller_escrow   = isset($_POST['is_seller_escrow'])  ? 1 : 0;
$is_listing_agent   = isset($_POST['is_listing_agent'])  ? 1 : 0;
$is_selling_agent   = isset($_POST['is_selling_agent'])  ? 1 : 0;
$include_in_reports = isset($_POST['include_in_reports'])? 1 : 0;
$active             = isset($_POST['active'])            ? 1 : 0;
$add_asst_flag      = isset($_POST['add_asst_flag'])     ? 1 : 0;

$sql = "
    INSERT INTO agents (
        name, company, address1, address2, city, state, zip,
        office_phone, cell_phone, cell_phone2, fax, email,
        asst_name, asst_office_phone, asst_cell_phone1, asst_fax, asst_email,
        asst_name2, asst_office_phone2, asst_cell_phone2, asst_fax2, asst_email2,
        is_loan_officer, is_buyer_escrow, is_seller_escrow,
        is_listing_agent, is_selling_agent,
        include_in_reports, active, add_asst_flag
    ) VALUES (
        '$name','$company','$address1','$address2','$city','$state','$zip',
        '$office_phone','$cell_phone','$cell_phone2','$fax','$email',
        '$asst_name','$asst_office_phone','$asst_cell_phone1','$asst_fax','$asst_email',
        '$asst_name2','$asst_office_phone2','$asst_cell_phone2','$asst_fax2','$asst_email2',
        $is_loan_officer,$is_buyer_escrow,$is_seller_escrow,
        $is_listing_agent,$is_selling_agent,
        $include_in_reports,$active,$add_asst_flag
    )
";

if (!mysqli_query($conn, $sql)) {
    die('DB error: ' . mysqli_error($conn));
}

header('Location: ../agents.php');
exit;