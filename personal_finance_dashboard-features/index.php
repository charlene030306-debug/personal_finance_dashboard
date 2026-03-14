<?php
session_start();

// If user is logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /personal_finance_dashboard-features/personal_finance_dashboard-features/dashboard.php");
    exit();
}

// If not logged in → go to login page
header("Location: /personal_finance_dashboard-features/personal_finance_dashboard-features/auth/login.php");
exit();
?>
