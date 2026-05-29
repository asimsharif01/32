<?php
require_once '../db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT company, office_phone, cell_phone, fax, email, 
               asst_name, asst_office_phone, asst_fax, asst_email 
        FROM agents WHERE id = $id";
$result = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode($row);
} else {
    echo json_encode([]);
}
?>