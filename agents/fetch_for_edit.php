<?php
// agents/fetch_for_edit.php – returns HTML form for edit modal
require_once '../db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo '<p class="text-danger">Invalid agent ID.</p>';
    exit;
}

$sql = "SELECT * FROM agents WHERE id = $id";
$result = mysqli_query($conn, $sql);
$agent = mysqli_fetch_assoc($result);

if (!$agent) {
    echo '<p class="text-danger">Agent not found.</p>';
    exit;
}

$agent_data = $agent; // This will be used inside _form_fields.php
?>

    <?php include '_form_fields.php'; ?>
