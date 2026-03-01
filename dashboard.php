<?php
require_once "config/db.php";
require_once "includes/auth_check.php";

$page_title = "Dashboard";

$user_id = (int) $_SESSION["user_id"];
$selected_month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_income);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0)
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_expense);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$remaining_balance = $total_income - $total_expense;

$category_data = [];
$stmt = mysqli_prepare(
    $conn,
    "SELECT category, SUM(amount) AS total
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?
     GROUP BY category
     ORDER BY total DESC"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $category_data[$row["category"]] = (float) $row["total"];
}
mysqli_stmt_close($stmt);

$trend_labels = [];
$trend_income_map = [];
$trend_expense_map = [];

$selected_date = DateTime::createFromFormat("Y-m-d", $month_start);
$trend_start_date = (clone $selected_date)->modify("-5 months")->format("Y-m-01");
$trend_end_date = (clone $selected_date)->format("Y-m-t");

for ($i = 5; $i >= 0; $i--) {
    $month_key = (clone $selected_date)->modify("-" . $i . " months")->format("Y-m");
    $trend_labels[] = $month_key;
    $trend_income_map[$month_key] = 0.0;
    $trend_expense_map[$month_key] = 0.0;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT DATE_FORMAT(income_date, '%Y-%m') AS month_key, SUM(amount) AS total
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?
     GROUP BY month_key"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $trend_start_date, $trend_end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $key = $row["month_key"];
    if (array_key_exists($key, $trend_income_map)) {
        $trend_income_map[$key] = (float) $row["total"];
    }
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $conn,
    "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month_key, SUM(amount) AS total
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?
     GROUP BY month_key"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $trend_start_date, $trend_end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $key = $row["month_key"];
    if (array_key_exists($key, $trend_expense_map)) {
        $trend_expense_map[$key] = (float) $row["total"];
    }
}
mysqli_stmt_close($stmt);

$trend_income_values = array_values($trend_income_map);
$trend_expense_values = array_values($trend_expense_map);

$budget_rows = [];
$budget_total = 0.0;
$budget_spent_total = 0.0;

$stmt = mysqli_prepare(
    $conn,
    "SELECT b.category,
            b.amount AS budget_amount,
            COALESCE(e.spent, 0) AS spent_amount
     FROM budgets b
     LEFT JOIN (
         SELECT category, SUM(amount) AS spent
         FROM expenses
         WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ?
         GROUP BY category
     ) e ON e.category = b.category
     WHERE b.user_id = ? AND b.month = ?
     ORDER BY b.category"
);
mysqli_stmt_bind_param($stmt, "isis", $user_id, $selected_month, $user_id, $selected_month);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $budget_amount = (float) $row["budget_amount"];
    $spent_amount = (float) $row["spent_amount"];
    $remaining = $budget_amount - $spent_amount;
    $usage = $budget_amount > 0 ? min(($spent_amount / $budget_amount) * 100, 100) : 0;

    $budget_rows[] = [
        "category" => $row["category"],
        "budget" => $budget_amount,
        "spent" => $spent_amount,
        "remaining" => $remaining,
        "usage" => $usage,
        "is_exceeded" => $spent_amount > $budget_amount,
    ];

    $budget_total += $budget_amount;
    $budget_spent_total += $spent_amount;
}
mysqli_stmt_close($stmt);
$budget_remaining_total = $budget_total - $budget_spent_total;

$recent_transactions = [];
$stmt = mysqli_prepare(
    $conn,
    "(SELECT 'Income' AS txn_type,
             id,
             income_date AS txn_date,
             source,
             category,
             amount,
             notes
      FROM income
      WHERE user_id = ?)
     UNION ALL
     (SELECT 'Expense' AS txn_type,
             id,
             expense_date AS txn_date,
             '' AS source,
             category,
             amount,
             notes
      FROM expenses
      WHERE user_id = ?)
     ORDER BY txn_date DESC, id DESC
     LIMIT 10"
);
mysqli_stmt_bind_param($stmt, "ii", $user_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $recent_transactions[] = $row;
}
mysqli_stmt_close($stmt);

include "includes/header.php";
?>

<form method="GET" class="month-filter-form mb-3">
    <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
    <button class="btn btn-primary filter-btn" type="submit">Filter</button>
</form>

<section class="stats-grid">
    <article class="stat-card income">
        <div>
            <p class="stat-title">Total Income</p>
            <h3 class="stat-value">Rs <?php echo number_format($total_income, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-circle-arrow-up"></i></span>
    </article>
    <article class="stat-card expense">
        <div>
            <p class="stat-title">Total Expense</p>
            <h3 class="stat-value">Rs <?php echo number_format($total_expense, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-circle-arrow-down"></i></span>
    </article>
    <article class="stat-card balance">
        <div>
            <p class="stat-title">Remaining Balance</p>
            <h3 class="stat-value">Rs <?php echo number_format($remaining_balance, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></span>
    </article>
    <article class="stat-card budget">
        <div>
            <p class="stat-title">Budget Remaining</p>
            <h3 class="stat-value">Rs <?php echo number_format($budget_remaining_total, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-shield-heart"></i></span>
    </article>
</section>

<section class="charts-grid">
    <article class="dashboard-card">
        <div class="card-heading"><h5>Expense Categories</h5></div>
        <div class="chart-wrap">
            <?php if (empty($category_data)): ?>
                <p class="empty-state">No expenses in this month.</p>
            <?php else: ?>
                <canvas id="pieChart"></canvas>
            <?php endif; ?>
        </div>
    </article>
    <article class="dashboard-card">
        <div class="card-heading"><h5>Income vs Expense</h5></div>
        <div class="chart-wrap"><canvas id="barChart"></canvas></div>
    </article>
    <article class="dashboard-card chart-wide">
        <div class="card-heading"><h5>6-Month Trend</h5></div>
        <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
    </article>
</section>

<section class="dashboard-card budget-card">
    <div class="card-heading">
        <h5>Budget Overview</h5>
        <span class="heading-meta">Budget Rs <?php echo number_format($budget_total, 2); ?> | Spent Rs <?php echo number_format($budget_spent_total, 2); ?></span>
    </div>
    <div class="budget-list">
        <?php if (empty($budget_rows)): ?>
            <p class="empty-state mb-0">No budgets found for <?php echo htmlspecialchars($selected_month); ?>.</p>
        <?php else: ?>
            <?php foreach ($budget_rows as $budget): ?>
                <div class="budget-item">
                    <div class="budget-item-head">
                        <strong><?php echo htmlspecialchars($budget["category"]); ?></strong>
                        <small>
                            Rs <?php echo number_format($budget["spent"], 2); ?> / Rs <?php echo number_format($budget["budget"], 2); ?>
                            (<?php echo round($budget["usage"]); ?>%)
                        </small>
                    </div>
                    <div class="progress finance-progress">
                        <div class="progress-bar <?php echo $budget["is_exceeded"] ? "bg-danger" : "bg-success"; ?>"
                             role="progressbar"
                             style="width: <?php echo round($budget["usage"], 2); ?>%">
                        </div>
                    </div>
                    <?php if ($budget["is_exceeded"]): ?>
                        <p class="warning-text"><i class="fa-solid fa-triangle-exclamation"></i> Budget exceeded</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="dashboard-card table-card">
    <div class="card-heading">
        <h5>Recent Transactions</h5>
        <div class="quick-links">
            <a href="income/index.php" class="btn btn-sm btn-outline-success">Add Income</a>
            <a href="expense/add_expense.php" class="btn btn-sm btn-outline-danger">Add Expense</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table finance-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Category</th>
                    <th>Notes</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No transactions yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_transactions as $txn): ?>
                        <tr>
                            <td>
                                <span class="badge-type <?php echo $txn["txn_type"] === "Income" ? "income-badge" : "expense-badge"; ?>">
                                    <?php echo htmlspecialchars($txn["txn_type"]); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($txn["txn_date"]); ?></td>
                            <td><?php echo htmlspecialchars($txn["source"] ?: "-"); ?></td>
                            <td><?php echo htmlspecialchars($txn["category"]); ?></td>
                            <td><?php echo htmlspecialchars($txn["notes"] ?: "-"); ?></td>
                            <td class="text-end fw-semibold">Rs <?php echo number_format((float) $txn["amount"], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
    window.dashboardData = {
        income: <?php echo json_encode((float) $total_income); ?>,
        expense: <?php echo json_encode((float) $total_expense); ?>,
        categoryLabels: <?php echo json_encode(array_keys($category_data)); ?>,
        categoryValues: <?php echo json_encode(array_values($category_data)); ?>,
        trendLabels: <?php echo json_encode($trend_labels); ?>,
        trendIncomeValues: <?php echo json_encode($trend_income_values); ?>,
        trendExpenseValues: <?php echo json_encode($trend_expense_values); ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/charts.js"></script>

<?php include "includes/footer.php"; ?>
