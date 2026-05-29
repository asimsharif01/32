<?php
// users/delete_user.php
require_once '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth'])) { header('Location: ../login.php'); exit; }

// Only super_admin and admin can delete users
if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header('Location: ../users.php'); exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ../users.php'); exit; }

// Prevent self-delete
if ($id === intval($_SESSION['id'])) {
    header('Location: ../users.php?error=' . urlencode('You cannot delete your own account.'));
    exit;
}

// Delete profile image file if it exists
$res = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = $id LIMIT 1");
if ($row = mysqli_fetch_assoc($res)) {
    $img_path = '../uploads/users/' . $row['profile_image'];
    if (!empty($row['profile_image']) && file_exists($img_path)) {
        unlink($img_path);
    }
}

mysqli_query($conn, "DELETE FROM users WHERE id = $id");

header('Location: ../users.php?success=' . urlencode('User deleted.'));
exit;