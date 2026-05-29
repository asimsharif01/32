<?php
require_once '../../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM lead_sources WHERE id = $id");
}
header('Location: ../../financing_types.php?success=Deleted');
exit;
?>