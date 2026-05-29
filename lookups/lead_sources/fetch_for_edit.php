<?php
require_once '../../db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { echo json_encode([]); exit; }

$result = mysqli_query($conn, "SELECT id, description, active FROM lead_sources WHERE id = $id");
if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
?>