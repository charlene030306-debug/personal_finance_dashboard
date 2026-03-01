<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Finance Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/finance_dashboard/assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="/finance_dashboard/dashboard.php">Finance Dashboard</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="d-flex gap-2">
                <a href="/finance_dashboard/dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <a href="/finance_dashboard/income/add_income.php" class="btn btn-outline-light btn-sm">Income</a>
                <a href="/finance_dashboard/expense/add_expense.php" class="btn btn-outline-light btn-sm">Expense</a>
                <a href="/finance_dashboard/budget/add_budget.php" class="btn btn-outline-light btn-sm">Budget</a>
                <a href="/finance_dashboard/reports/reports.php" class="btn btn-outline-light btn-sm">Reports</a>
                <a href="/finance_dashboard/auth/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        <?php endif; ?>

    </div>
</nav>

<div class="container mt-4">
