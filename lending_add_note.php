<?php
// lending_add_note.php — AJAX endpoint to add a loan note
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['auth'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$loan_id = intval($_POST['loan_id'] ?? 0);
$note_text = trim($_POST['note_text'] ?? '');

if (!$loan_id || !$note_text) {
    echo json_encode(['success' => false, 'error' => 'Missing loan ID or note text.']);
    exit;
}

$username = $_SESSION['name'] ?? 'Unknown User';
$stmt = $conn->prepare("INSERT INTO loan_notes (loan_id, username, note_text) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $loan_id, $username, $note_text);
if ($stmt->execute()) {
    $newId = $conn->insert_id;
    // Touch loans.updated_at so the Last Updated column in the
    // Loan Info grid reflects note activity, not just form saves
    $conn->query("UPDATE loans SET updated_at = NOW() WHERE id = $loan_id");
    // Fetch the created_at timestamp to format it nicely
    $res = $conn->query("SELECT created_at FROM loan_notes WHERE id = $newId");
    $row = $res->fetch_assoc();
    $created = $row ? date('M j, Y g:i a', strtotime($row['created_at'])) : date('M j, Y g:i a');
    echo json_encode([
        'success' => true,
        'id' => $newId,
        'created_at_formatted' => $created
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>