<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: /personal_finance_dashboard-features/personal_finance_dashboard-features/auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: add_expense.php");
    exit();
}

$stmt = mysqli_prepare($conn,
    "DELETE FROM expenses WHERE id=? AND user_id=?"
);

mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);

header("Location: add_expense.php");
exit();
