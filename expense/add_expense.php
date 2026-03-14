<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$expense_categories = ["Food", "Transport", "Rent", "Utilities", "Shopping", "Health", "Entertainment", "Education", "Other"];

$user_id = (int) $_SESSION["user_id"];
$filter_type = $_GET["filter_type"] ?? "all";
$selected_month = date("Y-m");
if ($filter_type === "month" && isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $amount = (float) ($_POST["amount"] ?? 0);
    $category = trim($_POST["category"] ?? "");
    $expense_date = $_POST["expense_date"] ?? "";
    $notes = trim($_POST["notes"] ?? "");

    if ($amount <= 0 || $category === "" || $expense_date === "") {
        $errors[] = "Amount, category and date are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO expenses (user_id, amount, category, notes, expense_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "idsss", $user_id, $amount, $category, $notes, $expense_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $redirect_month = date("Y-m", strtotime($expense_date));
        header("Location: add_expense.php?month=" . $redirect_month);
        exit();
    }
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

if ($filter_type === "month") {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, amount, category, notes, expense_date
         FROM expenses
         WHERE user_id = ? AND expense_date BETWEEN ? AND ?
         ORDER BY expense_date DESC, id DESC"
    );
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, amount, category, notes, expense_date
         FROM expenses
         WHERE user_id = ?
         ORDER BY expense_date DESC, id DESC"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

include "../includes/header.php";
?>

<div class="expense-page">
<h3 class="mb-4">Expense Management</h3>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<form method="GET" class="filter-bar mb-3">
    <select name="filter_type" id="expenseFilterType" class="form-select">
        <option value="all" <?php echo $filter_type === "all" ? "selected" : ""; ?>>All Time</option>
        <option value="month" <?php echo $filter_type === "month" ? "selected" : ""; ?>>Specific Month</option>
    </select>
    <input type="month" name="month" id="expenseMonthFilter" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
    <button class="btn btn-primary btn-filter">Filter</button>
</form>
<script>
    const expenseFilterType = document.getElementById("expenseFilterType");
    const expenseMonthFilter = document.getElementById("expenseMonthFilter");
    function toggleExpenseMonth() {
        expenseMonthFilter.style.display = expenseFilterType.value === "month" ? "block" : "none";
    }
    expenseFilterType.addEventListener("change", toggleExpenseMonth);
    toggleExpenseMonth();
</script>

<div class="page-stack">
    <div class="card dashboard-card shadow-sm add-expense-card">
        <div class="card-body">
            <h5 class="mb-3">Add Expense</h5>
            <form method="POST" class="transaction-form">
                <input type="number" min="0" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                <select name="category" class="form-select" required>
                    <option value="">Category</option>
                    <?php foreach ($expense_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="expense_date" class="form-control" required>
                <input type="text" name="notes" class="form-control" placeholder="Notes (optional)">
                <button class="btn btn-danger btn-add-expense" type="submit">Add</button>
            </form>
        </div>
    </div>

    <div class="card dashboard-card shadow-sm expense-table-card">
        <div class="card-body">
            <h5 class="mb-3">Expense Records</h5>
            <div class="table-responsive">
                <table class="table finance-table dashboard-table table-sticky align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                $notes_text = $row["notes"] ?? "";
                                $notes_trimmed = trim($notes_text);
                                if ($notes_trimmed === "") {
                                    $notes_display = "-";
                                } else {
                                    $notes_display = strlen($notes_trimmed) > 80 ? substr($notes_trimmed, 0, 80) . "..." : $notes_trimmed;
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row["expense_date"]); ?></td>
                                <td><?php echo htmlspecialchars($row["category"]); ?></td>
                                <td class="notes-cell truncate" title="<?php echo htmlspecialchars($notes_trimmed); ?>">
                                    <?php echo htmlspecialchars($notes_display); ?>
                                </td>
                            <td class="amount-column amount-expense">₹<?php echo number_format((float) $row["amount"], 2); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_expense.php?id=<?php echo (int) $row["id"]; ?>"
                                           class="btn btn-action btn-action-edit"
                                           title="Edit"
                                           aria-label="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="delete_expense.php?id=<?php echo (int) $row["id"]; ?>"
                                           class="btn btn-action btn-action-delete"
                                           title="Delete"
                                           aria-label="Delete"
                                           onclick="return confirm('Delete this expense record?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
mysqli_stmt_close($stmt);
?>
</div>

<?php include "../includes/footer.php"; ?>
