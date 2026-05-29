<?php
//agent delete
require_once '../db.php';
$id = intval($_GET['id']);
mysqli_query($conn, "DELETE FROM agents WHERE id = $id");
header('Location: ../agents.php');
exit;
?>