<?php
require_once '../../db.php';

$id = intval($_POST['id'] ?? 0);
$desc = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
if ($id <= 0 || $desc === '') { header('Location: ../../financing_types.php?error=Invalid data'); exit; }

mysqli_query($conn, "UPDATE financing_types SET description = '$desc' WHERE id = $id");
header('Location: ../../financing_types.php?success=Updated');
exit;
?>