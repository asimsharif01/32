<?php
// transactions/delete.php
// Deletes a listing and its related child records
require_once '../db.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // Delete child records first (FK constraint safe order)
    mysqli_query($conn, "DELETE FROM listing_milestones WHERE listing_id = $id");

    // Delete the listing itself
    mysqli_query($conn, "DELETE FROM listings WHERE id = $id");

    // Note: trust_account_transactions is linked by transaction_number (string),
    // not by listing id — leave those records intact for audit purposes.
}

header('Location: ../transactions.php');
exit;