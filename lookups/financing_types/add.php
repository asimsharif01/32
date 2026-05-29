<?php
require_once '../../db.php';

$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
if ($desc === '') { header('Location: ../../financing_types.php?error=Description required'); exit; }

mysqli_query($conn, "INSERT INTO financing_types (description) VALUES ('$desc')");
header('Location: ../../financing_types.php?success=Added');
exit;
?>