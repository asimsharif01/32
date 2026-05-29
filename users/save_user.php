<?php
// users/save_user.php — INSERT or UPDATE a system user
require_once '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['auth'])) { header('Location: ../login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../users.php'); exit; }

$user_id        = intval($_POST['user_id']       ?? 0);
$name           = trim($_POST['name']            ?? '');
$email          = trim($_POST['email']           ?? '');
$password       = trim($_POST['password']        ?? '');
$confirm        = trim($_POST['confirm_password'] ?? '');
$role           = trim($_POST['role']            ?? 'basic_user');
$module         = trim($_POST['module']          ?? 'real_estate');
$active         = isset($_POST['active']) ? 1 : 0;
$existing_image = trim($_POST['existing_image']  ?? '');
$is_edit        = ($user_id > 0);

// ── Validate ──────────────────────────────────────────────────────────────
if (empty($name) || empty($email)) {
    header('Location: ../users.php?error=' . urlencode('Name and email are required.'));
    exit;
}

// Check email uniqueness (exclude self on edit)
$email_esc = mysqli_real_escape_string($conn, $email);
$dupe_sql  = $is_edit
    ? "SELECT id FROM users WHERE email = '$email_esc' AND id <> $user_id LIMIT 1"
    : "SELECT id FROM users WHERE email = '$email_esc' LIMIT 1";
$dupe_res  = mysqli_query($conn, $dupe_sql);
if (mysqli_num_rows($dupe_res) > 0) {
    header('Location: ../users.php?error=' . urlencode('That email is already in use.'));
    exit;
}

// Password required for new users
if (!$is_edit && empty($password)) {
    header('Location: ../users.php?error=' . urlencode('Password is required for new users.'));
    exit;
}

// If a password was supplied, validate match
if (!empty($password)) {
    if ($password !== $confirm) {
        header('Location: ../users.php?error=' . urlencode('Passwords do not match.'));
        exit;
    }
    if (strlen($password) < 6) {
        header('Location: ../users.php?error=' . urlencode('Password must be at least 6 characters.'));
        exit;
    }
}

// ── Handle profile image upload ───────────────────────────────────────────
$upload_dir = '../uploads/users/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$profile_image = $existing_image; // default: keep existing

if (!empty($_FILES['profile_image']['name'])) {
    $file      = $_FILES['profile_image'];
    $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        header('Location: ../users.php?error=' . urlencode('Invalid image type. Use JPG, PNG, GIF or WEBP.'));
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        header('Location: ../users.php?error=' . urlencode('Image must be under 2 MB.'));
        exit;
    }

    // Delete old image if replacing
    if ($existing_image && file_exists($upload_dir . $existing_image)) {
        unlink($upload_dir . $existing_image);
    }

    $profile_image = uniqid('usr_', true) . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $upload_dir . $profile_image);
}

// ── Escape values ─────────────────────────────────────────────────────────
$name_esc   = mysqli_real_escape_string($conn, $name);
$role_esc   = mysqli_real_escape_string($conn, $role);
$module_esc = mysqli_real_escape_string($conn, $module);
$img_esc    = mysqli_real_escape_string($conn, $profile_image);

// ── INSERT ────────────────────────────────────────────────────────────────
if (!$is_edit) {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = "
        INSERT INTO users (name, email, password, role, module, active, profile_image)
        VALUES ('$name_esc', '$email_esc', '$hash', '$role_esc', '$module_esc', $active, '$img_esc')
    ";
    if (!mysqli_query($conn, $sql)) {
        header('Location: ../users.php?error=' . urlencode('DB error: ' . mysqli_error($conn)));
        exit;
    }

// ── UPDATE ────────────────────────────────────────────────────────────────
} else {
    // Build password clause only if a new password was supplied
    $pass_clause = '';
    if (!empty($password)) {
        $hash        = password_hash($password, PASSWORD_DEFAULT);
        $pass_clause = ", password = '$hash'";
    }

    $sql = "
        UPDATE users SET
            name          = '$name_esc',
            email         = '$email_esc',
            role          = '$role_esc',
            module        = '$module_esc',
            active        = $active,
            profile_image = '$img_esc'
            $pass_clause
        WHERE id = $user_id
    ";
    if (!mysqli_query($conn, $sql)) {
        header('Location: ../users.php?error=' . urlencode('DB error: ' . mysqli_error($conn)));
        exit;
    }
}

header('Location: ../users.php?success=' . urlencode($is_edit ? 'User updated.' : 'User created.'));
exit;