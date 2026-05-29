<?php
// ajax_search.php – returns JSON list of matching values for autocomplete
require_once 'db.php';
header('Content-Type: application/json');

$field = isset($_GET['field']) ? trim($_GET['field']) : '';
$term  = isset($_GET['term'])  ? trim($_GET['term'])  : '';

if ($term === '' || $field === '') {
    echo json_encode([]);
    exit;
}

$term = mysqli_real_escape_string($conn, $term);

// Define SQL for each field
$sql = '';
switch ($field) {
    case 'mls':
        $sql = "SELECT DISTINCT mls_number AS value FROM listings WHERE mls_number LIKE '%$term%' ORDER BY mls_number LIMIT 20";
        break;
    case 'tn':
        $sql = "SELECT DISTINCT transaction_number AS value FROM listings WHERE transaction_number LIKE '%$term%' ORDER BY transaction_number LIMIT 20";
        break;
    case 'agent':
        $sql = "SELECT DISTINCT name AS value FROM agents WHERE (is_listing_agent = 1 OR is_selling_agent = 1) AND active = 1 AND name LIKE '%$term%' ORDER BY name LIMIT 20";
        break;
    case 'seller':
        $sql = "SELECT DISTINCT seller_name AS value FROM listings WHERE seller_name LIKE '%$term%' ORDER BY seller_name LIMIT 20";
        break;
    case 'buyer':
        $sql = "SELECT DISTINCT buyer_name AS value FROM listings WHERE buyer_name LIKE '%$term%' ORDER BY buyer_name LIMIT 20";
        break;
    case 'address':
        $sql = "SELECT DISTINCT address1 AS value FROM listings WHERE address1 LIKE '%$term%' ORDER BY address1 LIMIT 20";
        break;
    default:
        echo json_encode([]);
        exit;
}

$result = mysqli_query($conn, $sql);
$options = [];
while ($row = mysqli_fetch_assoc($result)) {
    $options[] = $row['value'];
}
echo json_encode($options);
?>