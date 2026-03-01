<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $month = $_GET["month"];
}

$start = $month . "-01";
$end = date("Y-m-t", strtotime($start));

/* SUMMARY */
$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) FROM income WHERE user_id = ? AND income_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $start, $end);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_income);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND expense_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $start, $end);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_expense);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$net_balance = $total_income - $total_expense;

/* TRANSACTIONS */
$stmt = mysqli_prepare(
    $conn,
    "(SELECT 'Income' AS type, income_date AS txn_date, source, category, notes, amount
      FROM income
      WHERE user_id = ? AND income_date BETWEEN ? AND ?)
     UNION ALL
     (SELECT 'Expense' AS type, expense_date AS txn_date, '' AS source, category, notes, amount
      FROM expenses
      WHERE user_id = ? AND expense_date BETWEEN ? AND ?)
     ORDER BY txn_date DESC, type ASC"
);
mysqli_stmt_bind_param($stmt, "ississ", $user_id, $start, $end, $user_id, $start, $end);
mysqli_stmt_execute($stmt);
$transactions_result = mysqli_stmt_get_result($stmt);

include "../includes/header.php";
?>

<h3 class="mb-4">Monthly Reports</h3>

<form method="GET" class="row g-2 mb-4">
    <div class="col-md-3">
        <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($month); ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-md-7 text-md-end">
        <a href="export_csv.php?month=<?php echo urlencode($month); ?>" class="btn btn-success">Download CSV</a>
        <a href="export_pdf.php?month=<?php echo urlencode($month); ?>" class="btn btn-secondary">Printable Report</a>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm text-white bg-success">
            <div class="card-body">
                <h6 class="mb-2">Total Income</h6>
                <h4 class="mb-0">Rs <?php echo number_format((float) $total_income, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm text-white bg-danger">
            <div class="card-body">
                <h6 class="mb-2">Total Expense</h6>
                <h4 class="mb-0">Rs <?php echo number_format((float) $total_expense, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm text-white <?php echo $net_balance < 0 ? "bg-danger" : "bg-primary"; ?>">
            <div class="card-body">
                <h6 class="mb-2">Net Balance</h6>
                <h4 class="mb-0">Rs <?php echo number_format((float) $net_balance, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="mb-3">Transactions</h5>
        <?php if (mysqli_num_rows($transactions_result) === 0): ?>
            <p class="text-muted mb-0">No transactions found for this month.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0">
                    <thead class="table-dark">
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
                        <?php while ($row = mysqli_fetch_assoc($transactions_result)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row["type"]); ?></td>
                                <td><?php echo htmlspecialchars($row["txn_date"]); ?></td>
                                <td><?php echo htmlspecialchars($row["source"] ?: "-"); ?></td>
                                <td><?php echo htmlspecialchars($row["category"]); ?></td>
                                <td><?php echo htmlspecialchars($row["notes"] ?: "-"); ?></td>
                                <td class="text-end">Rs <?php echo number_format((float) $row["amount"], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
mysqli_stmt_close($stmt);
include "../includes/footer.php";
?>
