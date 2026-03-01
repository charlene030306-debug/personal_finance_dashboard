<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined("APP_BASE_URL")) {
    define("APP_BASE_URL", "/finance_dashboard");
}

if (!isset($_SESSION["user_id"])) {
    header("Location: " . APP_BASE_URL . "/auth/login.php");
    exit();
}
