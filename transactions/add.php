<?php
require_once '../db.php';

$agent_id = intval($_POST['listing_agent_id']);
$mls = mysqli_real_escape_string($conn, $_POST['mls_number']);
$dol = !empty($_POST['date_of_listing']) ? "'".mysqli_real_escape_string($conn, $_POST['date_of_listing'])."'" : "NULL";
$doe = !empty($_POST['date_of_expiration']) ? "'".mysqli_real_escape_string($conn, $_POST['date_of_expiration'])."'" : "NULL";
$price = floatval($_POST['price']);
$seller_name = mysqli_real_escape_string($conn, $_POST['seller_name']);
$address = mysqli_real_escape_string($conn, $_POST['address']);
$city = mysqli_real_escape_string($conn, $_POST['city']);
$status = mysqli_real_escape_string($conn, $_POST['status']);

// Get status_id from sales_statuses table
$status_res = mysqli_query($conn, "SELECT id FROM sales_statuses WHERE description = '$status'");
$status_row = mysqli_fetch_assoc($status_res);
$status_id = $status_row ? $status_row['id'] : 1; // default to Listed

// Insert into listings
$sql = "INSERT INTO listings (mls_number, listing_agent_id, purchase_price, address1, city, status_id, date_of_listing, date_of_expiration) 
        VALUES ('$mls', $agent_id, $price, '$address', '$city', $status_id, $dol, $doe)";
mysqli_query($conn, $sql);
$listing_id = mysqli_insert_id($conn);

// Insert seller as a contact
$contact_sql = "INSERT INTO contacts (name) VALUES ('$seller_name')";
mysqli_query($conn, $contact_sql);
$contact_id = mysqli_insert_id($conn);

// Link seller to listing
$role_sql = "INSERT INTO transaction_roles (listing_id, contact_id, role_type) VALUES ($listing_id, $contact_id, 'Seller')";
mysqli_query($conn, $role_sql);

// Link listing agent (if agent exists in agents table, we also add as a contact? Actually listing agent is already in agents table, but we need to link as a role)
// We'll add a role for listing agent using the agent's name as contact (or we can reuse agents as contacts? For simplicity, we'll create a contact from agent)
$agent_res = mysqli_query($conn, "SELECT name FROM agents WHERE id = $agent_id");
if($agent_row = mysqli_fetch_assoc($agent_res)) {
    $agent_contact_sql = "INSERT INTO contacts (name) VALUES ('".mysqli_real_escape_string($conn, $agent_row['name'])."')";
    mysqli_query($conn, $agent_contact_sql);
    $agent_contact_id = mysqli_insert_id($conn);
    $role_agent_sql = "INSERT INTO transaction_roles (listing_id, contact_id, role_type) VALUES ($listing_id, $agent_contact_id, 'Listing Agent')";
    mysqli_query($conn, $role_agent_sql);
}

header('Location: ../transactions.php');
exit;
?>