<?php
// logout.php
session_start();
session_unset();
session_destroy();

// Always redirect — whether called via AJAX or direct link
header('Location: login.php');
exit;