<?php
require_once '../db.php';
$id = intval($_GET['id']);
// Delete related records first (roles, milestones, financials, contacts? We'll keep contacts but remove links)
mysqli_query($conn, "DELETE FROM transaction_roles WHERE listing_id = $id");
mysqli_query($conn, "DELETE FROM listing_milestones WHERE listing_id = $id");
mysqli_query($conn, "DELETE FROM listing_financials WHERE listing_id = $id");
mysqli_query($conn, "DELETE FROM listings WHERE id = $id");
header('Location: ../transactions.php');
exit;
?>