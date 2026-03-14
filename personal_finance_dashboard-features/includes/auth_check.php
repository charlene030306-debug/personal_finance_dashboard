<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined("APP_BASE_URL")) {
    define("APP_BASE_URL", "/personal_finance_dashboard-features/personal_finance_dashboard-features");
}

if (!isset($_SESSION["user_id"])) {
    header("Location: " . APP_BASE_URL . "/auth/login.php");
    exit();
}
