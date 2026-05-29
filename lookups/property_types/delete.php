<?php
require_once '../../db.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM property_types WHERE id = $id");
}
header('Location: ../../property_types.php?success=Deleted');
exit;
?>