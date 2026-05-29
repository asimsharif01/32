<?php
require_once '../../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM financing_types WHERE id = $id");
}
header('Location: ../../financing_types.php?success=Deleted');
exit;
?>