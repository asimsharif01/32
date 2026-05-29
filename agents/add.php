<?php
require_once '../db.php';

$name = mysqli_real_escape_string($conn, $_POST['name']);
$company = mysqli_real_escape_string($conn, $_POST['company']);
$address1 = mysqli_real_escape_string($conn, $_POST['address1']);
$address2 = mysqli_real_escape_string($conn, $_POST['address2']);
$city = mysqli_real_escape_string($conn, $_POST['city']);
$state = mysqli_real_escape_string($conn, $_POST['state']);
$zip = mysqli_real_escape_string($conn, $_POST['zip']);
$office_phone = mysqli_real_escape_string($conn, $_POST['office_phone']);
$cell_phone = mysqli_real_escape_string($conn, $_POST['cell_phone']);
$fax = mysqli_real_escape_string($conn, $_POST['fax']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$asst_name = mysqli_real_escape_string($conn, $_POST['asst_name']);
$asst_office_phone = mysqli_real_escape_string($conn, $_POST['asst_office_phone']);
$asst_fax = mysqli_real_escape_string($conn, $_POST['asst_fax']);
$asst_email = mysqli_real_escape_string($conn, $_POST['asst_email']);
$is_loan_officer = isset($_POST['is_loan_officer']) ? 1 : 0;
$is_buyer_escrow = isset($_POST['is_buyer_escrow']) ? 1 : 0;
$is_seller_escrow = isset($_POST['is_seller_escrow']) ? 1 : 0;
$is_listing_agent = isset($_POST['is_listing_agent']) ? 1 : 0;
$is_selling_agent = isset($_POST['is_selling_agent']) ? 1 : 0;
$include_in_reports = isset($_POST['include_in_reports']) ? 1 : 0;
$active = isset($_POST['active']) ? 1 : 0;

$sql = "INSERT INTO agents (name, company, address1, address2, city, state, zip, office_phone, cell_phone, fax, email,
        asst_name, asst_office_phone, asst_fax, asst_email, is_loan_officer, is_buyer_escrow, is_seller_escrow,
        is_listing_agent, is_selling_agent, include_in_reports, active)
        VALUES ('$name','$company','$address1','$address2','$city','$state','$zip','$office_phone','$cell_phone','$fax','$email',
        '$asst_name','$asst_office_phone','$asst_fax','$asst_email',$is_loan_officer,$is_buyer_escrow,$is_seller_escrow,
        $is_listing_agent,$is_selling_agent,$include_in_reports,$active)";
mysqli_query($conn, $sql);

header('Location: ../agents.php');
exit;
?>