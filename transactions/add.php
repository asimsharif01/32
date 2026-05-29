<?php
// transactions/add.php
require_once '../db.php';

// ── Read POST values ──────────────────────────────────────────────────────
$listing_agent_id = intval($_POST['listing_agent_name'] ?? 0);  // note: field name is still 'listing_agent_name', but value is ID
$mls              = mysqli_real_escape_string($conn, $_POST['mls_number']         ?? '');
$dol              = !empty($_POST['date_of_listing'])
                    ? "'" . mysqli_real_escape_string($conn, $_POST['date_of_listing']) . "'"
                    : 'NULL';
$doe              = !empty($_POST['date_of_expiration'])
                    ? "'" . mysqli_real_escape_string($conn, $_POST['date_of_expiration']) . "'"
                    : 'NULL';
$price            = floatval($_POST['price']       ?? 0);
$seller_name      = mysqli_real_escape_string($conn, $_POST['seller_name'] ?? '');
$address          = mysqli_real_escape_string($conn, $_POST['address']     ?? '');
$city             = mysqli_real_escape_string($conn, $_POST['city']        ?? '');
$state            = mysqli_real_escape_string($conn, $_POST['state']       ?? 'UT');
$status_text      = mysqli_real_escape_string($conn, $_POST['status']      ?? 'Listed');

// ── Resolve status_id ────────────────────────────────────────────────────
$st_res  = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description = '$status_text'");
$st_row  = mysqli_fetch_assoc($st_res);
$status_id = $st_row ? intval($st_row['id']) : 1;

// ── Fetch agent details by ID (not by name) ──────────────────────────────
$ag_row = null;
if ($listing_agent_id > 0) {
    $ag_res = mysqli_query($conn, "SELECT * FROM agents
                                   WHERE id = $listing_agent_id
                                     AND is_listing_agent = 1
                                     AND active = 1
                                   LIMIT 1");
    $ag_row = mysqli_fetch_assoc($ag_res);
}

// ── Build LA_* inline columns from agent row ─────────────────────────────
// Use agent’s name for LA_Name, not the ID
$LA_Name        = $ag_row ? mysqli_real_escape_string($conn, $ag_row['name']              ?? '') : '';
$LA_Company     = $ag_row ? mysqli_real_escape_string($conn, $ag_row['company']           ?? '') : '';
$LA_Email       = $ag_row ? mysqli_real_escape_string($conn, $ag_row['email']             ?? '') : '';
$LA_OfficePhone = $ag_row ? mysqli_real_escape_string($conn, $ag_row['office_phone']      ?? '') : '';
$LA_CellPhone   = $ag_row ? mysqli_real_escape_string($conn, $ag_row['cell_phone']        ?? '') : '';
$LA_Fax         = $ag_row ? mysqli_real_escape_string($conn, $ag_row['fax']               ?? '') : '';
$LA_AsstName    = $ag_row ? mysqli_real_escape_string($conn, $ag_row['asst_name']         ?? '') : '';
$LA_AsstOffice  = $ag_row ? mysqli_real_escape_string($conn, $ag_row['asst_office_phone'] ?? '') : '';
$LA_AsstFax     = $ag_row ? mysqli_real_escape_string($conn, $ag_row['asst_fax']          ?? '') : '';
$LA_AsstEmail   = $ag_row ? mysqli_real_escape_string($conn, $ag_row['asst_email']        ?? '') : '';

// ── Insert into listings ──────────────────────────────────────────────────
$sql = "
    INSERT INTO listings (
        mls_number, purchase_price, address1, city, state,
        status_id, date_of_listing, date_of_expiration,
        seller_name,
        LA_Name, LA_Company, LA_Email,
        LA_OfficePhone, LA_CellPhone, LA_Fax,
        LA_AsstName, LA_AsstOfficePhone, LA_AsstFax, LA_AsstEmail,
        LA_ForReport
    ) VALUES (
        '$mls', $price, '$address', '$city', '$state',
        $status_id, $dol, $doe,
        '$seller_name',
        '$LA_Name', '$LA_Company', '$LA_Email',
        '$LA_OfficePhone', '$LA_CellPhone', '$LA_Fax',
        '$LA_AsstName', '$LA_AsstOffice', '$LA_AsstFax', '$LA_AsstEmail',
        1
    )
";

if (!mysqli_query($conn, $sql)) {
    die('DB error: ' . mysqli_error($conn));
}

header('Location: ../transactions.php');
exit;