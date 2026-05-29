<?php
require_once '../db.php';

$listing_id = intval($_POST['listing_id']);

// Property fields
$mls = mysqli_real_escape_string($conn, $_POST['mls_number']);
$tn = mysqli_real_escape_string($conn, $_POST['transaction_number']);
$address1 = mysqli_real_escape_string($conn, $_POST['address1']);
$city = mysqli_real_escape_string($conn, $_POST['city']);
$state = mysqli_real_escape_string($conn, $_POST['state']);
$zip = mysqli_real_escape_string($conn, $_POST['zip']);
$property_type_id = intval($_POST['property_type_id']) ?: 'NULL';
$purchase_price = floatval($_POST['purchase_price']);
$uc_price = floatval($_POST['uc_price']);
$final_price = floatval($_POST['final_price']);
$financing_type_id = intval($_POST['financing_type_id']) ?: 'NULL';
$status_id = intval($_POST['status_id']) ?: 'NULL';
$lead_source = mysqli_real_escape_string($conn, $_POST['lead_source']);
$earnest_amount = floatval($_POST['earnest_money_amount']);
$earnest_with = mysqli_real_escape_string($conn, $_POST['earnest_money_deposit_with']);
$dol = !empty($_POST['date_of_listing']) ? "'".mysqli_real_escape_string($conn, $_POST['date_of_listing'])."'" : "NULL";
$doe = !empty($_POST['date_of_expiration']) ? "'".mysqli_real_escape_string($conn, $_POST['date_of_expiration'])."'" : "NULL";
$private = isset($_POST['private']) ? 1 : 0;
$comments = mysqli_real_escape_string($conn, $_POST['comments']);

// Financial fields
$commission_price = floatval($_POST['commission_price']);
$commission_pct = floatval($_POST['commission_pct']);
$commission_other = floatval($_POST['commission_other']);
$transaction_fee = floatval($_POST['transaction_fee']);
$errors_omissions = floatval($_POST['errors_omissions']);
$agent_split = floatval($_POST['agent_split']);
$processing_fee = floatval($_POST['processing_fee']);
$other2 = floatval($_POST['other2']);
$split_with = mysqli_real_escape_string($conn, $_POST['split_with']);
$multiplier = floatval($_POST['multiplier']);
$folder_path = mysqli_real_escape_string($conn, $_POST['folder_path']);

// Calculate net and check amounts
$net_amt = ($commission_price * $commission_pct / 100) + $commission_other + $transaction_fee + $errors_omissions;
$check_amt = ($net_amt * $agent_split / 100) + $processing_fee + $other2;

// Denormalized agent fields for each role (prefixes: LO, BEO, SEO, LA, SA)
$role_prefixes = ['LO', 'BEO', 'SEO', 'LA', 'SA'];
$agent_fields = [];
foreach ($role_prefixes as $pre) {
    $agent_fields[$pre.'_Name'] = mysqli_real_escape_string($conn, $_POST[$pre.'_Name'] ?? '');
    $agent_fields[$pre.'_Company'] = mysqli_real_escape_string($conn, $_POST[$pre.'_Company'] ?? '');
    $agent_fields[$pre.'_Email'] = mysqli_real_escape_string($conn, $_POST[$pre.'_Email'] ?? '');
    $agent_fields[$pre.'_OfficePhone'] = mysqli_real_escape_string($conn, $_POST[$pre.'_OfficePhone'] ?? '');
    $agent_fields[$pre.'_CellPhone'] = mysqli_real_escape_string($conn, $_POST[$pre.'_CellPhone'] ?? '');
    $agent_fields[$pre.'_Fax'] = mysqli_real_escape_string($conn, $_POST[$pre.'_Fax'] ?? '');
    $agent_fields[$pre.'_AsstName'] = mysqli_real_escape_string($conn, $_POST[$pre.'_AsstName'] ?? '');
    $agent_fields[$pre.'_AsstOfficePhone'] = mysqli_real_escape_string($conn, $_POST[$pre.'_AsstOfficePhone'] ?? '');
    $agent_fields[$pre.'_AsstFax'] = mysqli_real_escape_string($conn, $_POST[$pre.'_AsstFax'] ?? '');
    $agent_fields[$pre.'_AsstEmail'] = mysqli_real_escape_string($conn, $_POST[$pre.'_AsstEmail'] ?? '');
    $agent_fields[$pre.'_AddAsstFlag'] = isset($_POST[$pre.'_AddAsstFlag']) ? 1 : 0;
    // For LA and SA, also handle ForReport
    if ($pre == 'LA' || $pre == 'SA') {
        $agent_fields[$pre.'_ForReport'] = isset($_POST[$pre.'_ForReport']) ? 1 : 1;
    }
}

// Build SQL SET part dynamically
$set_parts = [];
foreach ($agent_fields as $col => $val) {
    $set_parts[] = "$col = '$val'";
}
$set_str = implode(", ", $set_parts);

if ($listing_id > 0) {
    $sql = "UPDATE listings SET 
        mls_number='$mls', transaction_number='$tn', address1='$address1', city='$city', state='$state', zip='$zip',
        property_type_id=$property_type_id, purchase_price=$purchase_price, uc_price=$uc_price, final_price=$final_price,
        financing_type_id=$financing_type_id, status_id=$status_id, lead_source='$lead_source',
        earnest_money_amount=$earnest_amount, earnest_money_deposit_with='$earnest_with',
        date_of_listing=$dol, date_of_expiration=$doe, private=$private, comments='$comments',
        commission_price=$commission_price, commission_pct=$commission_pct, commission_other=$commission_other,
        transaction_fee=$transaction_fee, errors_omissions=$errors_omissions, commission_amount=$net_amt,
        agent_split=$agent_split, processing_fee=$processing_fee, other2=$other2, check_amount=$check_amt,
        split_with='$split_with', multiplier=$multiplier, folder_path='$folder_path',
        $set_str
        WHERE id=$listing_id";
    mysqli_query($conn, $sql);
} else {
    // Insert new record – build column list and values
    $columns = "mls_number, transaction_number, address1, city, state, zip, property_type_id,
                purchase_price, uc_price, final_price, financing_type_id, status_id, lead_source,
                earnest_money_amount, earnest_money_deposit_with, date_of_listing, date_of_expiration,
                private, comments, commission_price, commission_pct, commission_other, transaction_fee,
                errors_omissions, commission_amount, agent_split, processing_fee, other2, check_amount,
                split_with, multiplier, folder_path";
    $values = "'$mls','$tn','$address1','$city','$state','$zip',$property_type_id,
                $purchase_price,$uc_price,$final_price,$financing_type_id,$status_id,'$lead_source',
                $earnest_amount,'$earnest_with',$dol,$doe,$private,'$comments',
                $commission_price,$commission_pct,$commission_other,$transaction_fee,
                $errors_omissions,$net_amt,$agent_split,$processing_fee,$other2,$check_amt,
                '$split_with',$multiplier,'$folder_path'";
    
    // Add denormalized columns
    $col_add = array_keys($agent_fields);
    $val_add = array_map(function($v) { return "'$v'"; }, array_values($agent_fields));
    if (!empty($col_add)) {
        $columns .= ", " . implode(", ", $col_add);
        $values .= ", " . implode(", ", $val_add);
    }
    
    $sql = "INSERT INTO listings ($columns) VALUES ($values)";
    mysqli_query($conn, $sql);
    $listing_id = mysqli_insert_id($conn);
}

// Milestones (unchanged)
$milestone_types = ['Date of Contract', 'Seller Disclosure', 'Due Diligence', 'Financing & Appraisal', 'Settlement'];
foreach ($milestone_types as $mt) {
    $due_date = !empty($_POST['milestone'][$mt]['due_date']) ? "'".mysqli_real_escape_string($conn, $_POST['milestone'][$mt]['due_date'])."'" : "NULL";
    $completed = isset($_POST['milestone'][$mt]['completed']) ? 1 : 0;
    $check = mysqli_query($conn, "SELECT id FROM listing_milestones WHERE listing_id=$listing_id AND milestone_type='$mt'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE listing_milestones SET due_date=$due_date, completed=$completed WHERE listing_id=$listing_id AND milestone_type='$mt'");
    } else {
        mysqli_query($conn, "INSERT INTO listing_milestones (listing_id, milestone_type, due_date, completed) VALUES ($listing_id, '$mt', $due_date, $completed)");
    }
}

// Optionally, you can still save buyer/seller contacts if you want to keep that separate, but for denormalized we might not need them. Remove if not needed.
// For now, keep buyer/seller contacts as is.

// Save buyer & seller contacts (if you still want them in contacts table)
function saveContact($conn, $name, $home, $cell, $fax, $email, $listing_id, $role_type) {
    if (empty($name)) return;
    $name = mysqli_real_escape_string($conn, $name);
    $home = mysqli_real_escape_string($conn, $home);
    $cell = mysqli_real_escape_string($conn, $cell);
    $fax = mysqli_real_escape_string($conn, $fax);
    $email = mysqli_real_escape_string($conn, $email);
    $sql = "INSERT INTO contacts (name, home_phone, cell_phone, fax, email) VALUES ('$name','$home','$cell','$fax','$email')";
    mysqli_query($conn, $sql);
    $contact_id = mysqli_insert_id($conn);
    mysqli_query($conn, "DELETE FROM transaction_roles WHERE listing_id=$listing_id AND role_type='$role_type'");
    mysqli_query($conn, "INSERT INTO transaction_roles (listing_id, contact_id, role_type) VALUES ($listing_id, $contact_id, '$role_type')");
}
saveContact($conn, $_POST['buyer_name'] ?? '', $_POST['buyer_home_phone'] ?? '', $_POST['buyer_cell_phone'] ?? '', $_POST['buyer_fax'] ?? '', $_POST['buyer_email'] ?? '', $listing_id, 'Buyer');
saveContact($conn, $_POST['seller_name'] ?? '', $_POST['seller_home_phone'] ?? '', $_POST['seller_cell_phone'] ?? '', $_POST['seller_fax'] ?? '', $_POST['seller_email'] ?? '', $listing_id, 'Seller');

header('Location: ../transactions.php');
exit;
?>