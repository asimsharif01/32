<?php
require_once '../../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM sales_statuses WHERE id = $id");
}
header('Location: ../../sales_statuses.php?success=Deleted');
exit;
?>