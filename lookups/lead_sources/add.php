<?php
require_once '../../db.php';

$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
$active = isset($_POST['active']) ? 1 : 0;
if ($desc === '') { header('Location: ../../lead_sources.php?error=Description required'); exit; }

mysqli_query($conn, "INSERT INTO lead_sources (description, active) VALUES ('$desc', $active)");
header('Location: ../../lead_sources.php?success=Added');
exit;
?>