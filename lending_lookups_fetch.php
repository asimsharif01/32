<?php
// lending_lookups_fetch.php
// Generic "fetch one row for editing" handler — returns JSON.
// Table name validated against the same allowlist as the save handler.

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) { http_response_code(401); exit; }
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) { http_response_code(403); exit; }

require_once 'db.php';
header('Content-Type: application/json');

$ALLOWED_TABLES = [
    'loan_consultants'      => ['employment_start_date'],
    'loan_processors'       => [],
    'loan_statuses'         => [],
    'loan_types'            => [],
    'purchase_types'        => [],
    'loan_role_types'       => [],
    'loan_referral_sources' => ['company', 'phone', 'email'],
];

$table = $_GET['table'] ?? '';
$id    = intval($_GET['id'] ?? 0);

if (!array_key_exists($table, $ALLOWED_TABLES) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$cols = array_merge(['id', 'name', 'active'], $ALLOWED_TABLES[$table]);
$colList = implode(',', array_map(fn($c) => "`$c`", $cols));

$stmt = $conn->prepare("SELECT $colList FROM `$table` WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode($result->fetch_assoc());
