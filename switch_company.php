<?php
// switch_company.php
// Only usable by users whose module = 'both'.
// Sets $_SESSION['active_company'] and redirects to that company's dashboard.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['auth'])) {
    header('Location: login.php');
    exit;
}

// Only 'both' users are allowed to switch
if (($_SESSION['module'] ?? '') !== 'both') {
    header('Location: index.php');
    exit;
}

$target = $_GET['to'] ?? '';

if (!in_array($target, ['real_estate', 'mortgage'], true)) {
    header('Location: index.php');
    exit;
}

$_SESSION['active_company'] = $target;

// Remember the choice across future logins too (90 days)
setcookie('active_company', $target, time() + 60 * 60 * 24 * 90, '/');

if ($target === 'mortgage') {
    header('Location: lending_dashboard.php');
} else {
    header('Location: index.php');
}
exit;