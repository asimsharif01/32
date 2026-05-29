<?php
require_once '../db.php';
header('Content-Type: application/json');

$id = intval($_GET['id']);
$sql = "SELECT * FROM agents WHERE id = $id";
$result = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
?>