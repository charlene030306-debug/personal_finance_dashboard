<?php
session_start();

// If user is logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// If not logged in → go to login page
header("Location: auth/login.php");
exit();
?>
