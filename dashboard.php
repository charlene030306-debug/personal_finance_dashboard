<?php
require_once "config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$selected_month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

/* TOTAL INCOME */
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

/* TOTAL EXPENSE */
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

/* EXPENSE CATEGORY BREAKDOWN */
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

/* MONTHLY TREND (LAST 6 MONTHS INCLUDING SELECTED) */
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

/* BUDGET OVERVIEW */
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

/* RECENT TRANSACTIONS */
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Dashboard</h3>
    <div class="d-flex gap-2">
        <a href="income/add_income.php" class="btn btn-success btn-sm">Add Income</a>
        <a href="expense/add_expense.php" class="btn btn-danger btn-sm">Add Expense</a>
        <a href="budget/add_budget.php" class="btn btn-primary btn-sm">Manage Budgets</a>
        <a href="reports/reports.php?month=<?php echo htmlspecialchars($selected_month); ?>" class="btn btn-secondary btn-sm">Reports</a>
    </div>
</div>

<form method="GET" class="mb-4">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-success shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Total Income</h6>
                <h4 class="mb-0">Rs <?php echo number_format($total_income, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Total Expense</h6>
                <h4 class="mb-0">Rs <?php echo number_format($total_expense, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white <?php echo $remaining_balance < 0 ? "bg-danger" : "bg-primary"; ?> shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Remaining Balance</h6>
                <h4 class="mb-0">Rs <?php echo number_format($remaining_balance, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white <?php echo $budget_remaining_total < 0 ? "bg-warning" : "bg-info"; ?> shadow-sm h-100">
            <div class="card-body">
                <h6 class="card-title mb-2">Budget Remaining</h6>
                <h4 class="mb-0">Rs <?php echo number_format($budget_remaining_total, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Expense Categories</h5>
                <?php if (empty($category_data)): ?>
                    <p class="text-muted mb-0">No expenses in this month.</p>
                <?php else: ?>
                    <canvas id="pieChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Income vs Expense</h5>
                <canvas id="barChart" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">6-Month Trend</h5>
                <canvas id="lineChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Budget Overview</h5>
            <small class="text-muted">
                Budget: Rs <?php echo number_format($budget_total, 2); ?> |
                Spent: Rs <?php echo number_format($budget_spent_total, 2); ?>
            </small>
        </div>

        <?php if (empty($budget_rows)): ?>
            <p class="text-muted mb-0">No budgets found for <?php echo htmlspecialchars($selected_month); ?>.</p>
        <?php else: ?>
            <?php foreach ($budget_rows as $budget): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong><?php echo htmlspecialchars($budget["category"]); ?></strong>
                        <small>
                            Rs <?php echo number_format($budget["spent"], 2); ?>
                            / Rs <?php echo number_format($budget["budget"], 2); ?>
                            (Remaining: Rs <?php echo number_format($budget["remaining"], 2); ?>)
                        </small>
                    </div>
                    <div class="progress">
                        <div
                            class="progress-bar <?php echo $budget["is_exceeded"] ? "bg-danger" : "bg-success"; ?>"
                            role="progressbar"
                            style="width: <?php echo round($budget["usage"], 2); ?>%"
                        >
                            <?php echo round($budget["usage"]); ?>%
                        </div>
                    </div>
                    <?php if ($budget["is_exceeded"]): ?>
                        <div class="text-danger small mt-1">Warning: Budget exceeded in this category.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-3">Recent Transactions</h5>
        <?php if (empty($recent_transactions)): ?>
            <p class="text-muted mb-0">No transactions yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
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
                        <?php foreach ($recent_transactions as $txn): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $txn["txn_type"] === "Income" ? "bg-success" : "bg-danger"; ?>">
                                        <?php echo htmlspecialchars($txn["txn_type"]); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($txn["txn_date"]); ?></td>
                                <td><?php echo htmlspecialchars($txn["source"] ?: "-"); ?></td>
                                <td><?php echo htmlspecialchars($txn["category"]); ?></td>
                                <td><?php echo htmlspecialchars($txn["notes"] ?: "-"); ?></td>
                                <td class="text-end">Rs <?php echo number_format((float) $txn["amount"], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

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
