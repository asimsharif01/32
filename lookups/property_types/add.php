<?php
require_once '../../db.php';

$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
if ($desc === '') { header('Location: ../../property_types.php?error=Description required'); exit; }

mysqli_query($conn, "INSERT INTO property_types (description) VALUES ('$desc')");
header('Location: ../../property_types.php?success=Added');
exit;
?>