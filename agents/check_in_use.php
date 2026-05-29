<?php
// agents/check_in_use.php
// Returns JSON { "in_use": true/false }
// Checks whether an agent name appears in any listing's inline agent columns.
// Used by the delete confirmation modal to show a warning.
require_once '../db.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['in_use' => false]);
    exit;
}

// Get the agent's name first
$res  = mysqli_query($conn, "SELECT name FROM agents WHERE id = $id LIMIT 1");
$row  = mysqli_fetch_assoc($res);
if (!$row) {
    echo json_encode(['in_use' => false]);
    exit;
}

$name = mysqli_real_escape_string($conn, $row['name']);

// Check if this name appears in any of the 5 inline agent name columns
$check = mysqli_query($conn, "
    SELECT id FROM listings
    WHERE LA_Name  = '$name'
       OR SA_Name  = '$name'
       OR LO_Name  = '$name'
       OR BEO_Name = '$name'
       OR SEO_Name = '$name'
    LIMIT 1
");

echo json_encode(['in_use' => mysqli_num_rows($check) > 0]);