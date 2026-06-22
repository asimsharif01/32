<?php
// login_script.php
require_once 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header('Location: login.php?error=' . urlencode('Email and password are required.'));
    exit;
}

// ── Fetch user by email ───────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: login.php?error=' . urlencode('No account found with that email.'));
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// ── Check active BEFORE setting session ──────────────────────────────────
if (!$user['active']) {
    header('Location: login.php?error=' . urlencode('Your account is inactive. Please contact the administrator.'));
    exit;
}

// ── Verify password ───────────────────────────────────────────────────────
if (!password_verify($password, $user['password'])) {
    header('Location: login.php?error=' . urlencode('Incorrect password.'));
    exit;
}

// ── Set session ───────────────────────────────────────────────────────────
$_SESSION['id']     = $user['id'];
$_SESSION['name']   = $user['name'];
$_SESSION['email']  = $user['email'];
$_SESSION['role']   = $user['role'];    // 'super_admin' | 'admin' | 'basic_user'
$_SESSION['module'] = $user['module'];  // 'real_estate' | 'mortgage' | 'both'
$_SESSION['auth']   = true;             // single flag checked by header.php
$_SESSION['profile_image'] = $user['profile_image']; // for session timeout

// ── Set active_company — controls which nav/dashboard is shown ───────────
if ($user['module'] === 'real_estate') {
    $_SESSION['active_company'] = 'real_estate';
} elseif ($user['module'] === 'mortgage') {
    $_SESSION['active_company'] = 'mortgage';
} else {
    // module === 'both' — restore last choice from cookie, default real_estate
    $_SESSION['active_company'] = ($_COOKIE['active_company'] ?? '') === 'mortgage'
        ? 'mortgage'
        : 'real_estate';
}

// ── Redirect based on active_company ──────────────────────────────────────
if ($_SESSION['active_company'] === 'mortgage') {
    header('Location: lending_dashboard.php');
} else {
    header('Location: index.php');
}
exit;