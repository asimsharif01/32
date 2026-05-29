<?php
require_once '../../db.php';

$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
if ($desc === '') { header('Location: ../../sales_statuses.php?error=Description required'); exit; }

mysqli_query($conn, "INSERT INTO sales_statuses (description) VALUES ('$desc')");
header('Location: ../../sales_statuses.php?success=Added');
exit;
?>