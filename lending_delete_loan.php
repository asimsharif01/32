<?php
// lending_delete_loan.php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) { header('Location: lending_loans.php'); exit; }

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: lending_loans.php?error=' . urlencode('Invalid loan ID.'));
    exit;
}

// loan_borrowers rows cascade-delete automatically (FK ON DELETE CASCADE)
$stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    header('Location: lending_loans.php?success=' . urlencode('Loan deleted.'));
} else {
    header('Location: lending_loans.php?error=' . urlencode('Failed to delete: ' . mysqli_error($conn)));
}
exit;