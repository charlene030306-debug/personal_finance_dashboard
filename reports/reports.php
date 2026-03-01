<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$page_title = "Monthly Reports";

$user_id = (int) $_SESSION["user_id"];
$month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $month = $_GET["month"];
}

$start_date = $month . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

/* Monthly income total */
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $monthly_income_total);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

/* Monthly expense total */
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $monthly_expense_total);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

/* Net savings */
$net_savings = (float) $monthly_income_total - (float) $monthly_expense_total;

include "../includes/header.php";
?>

<form method="GET" class="month-filter-form mb-3">
    <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
    <button class="btn btn-primary filter-btn" type="submit">Filter</button>
</form>

<section class="dashboard-card">
    <div class="card-heading">
        <h5>Monthly Financial Summary</h5>
        <span class="heading-meta"><?php echo htmlspecialchars($month); ?></span>
    </div>

    <div class="table-responsive">
        <table class="table finance-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-end">Monthly Income Total</th>
                    <th class="text-end">Monthly Expense Total</th>
                    <th class="text-end">Net Savings</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($month); ?></td>
                    <td class="text-end">Rs <?php echo number_format((float) $monthly_income_total, 2); ?></td>
                    <td class="text-end">Rs <?php echo number_format((float) $monthly_expense_total, 2); ?></td>
                    <td class="text-end fw-semibold <?php echo $net_savings < 0 ? "text-danger" : "text-success"; ?>">
                        Rs <?php echo number_format((float) $net_savings, 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php include "../includes/footer.php"; ?>
