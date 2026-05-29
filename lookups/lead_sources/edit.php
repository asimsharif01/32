<?php
require_once '../../db.php';

$id = intval($_POST['id'] ?? 0);
$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
$active = isset($_POST['active']) ? 1 : 0;
if ($id <= 0 || $desc === '') { header('Location: ../../lead_sources.php?error=Invalid data'); exit; }

mysqli_query($conn, "UPDATE lead_sources SET description = '$desc', active = $active WHERE id = $id");
header('Location: ../../lead_sources.php?success=Updated');
exit;
?>