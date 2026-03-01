<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$budget_categories = ["Food", "Transport", "Rent", "Utilities", "Shopping", "Health", "Entertainment", "Education", "Other"];

$user_id = (int) $_SESSION["user_id"];
$selected_month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category = trim($_POST["category"] ?? "");
    $amount = (float) ($_POST["amount"] ?? 0);
    $month = $_POST["month"] ?? "";

    if ($category === "" || $amount <= 0 || !preg_match("/^\d{4}-\d{2}$/", $month)) {
        $errors[] = "Category, valid month and amount are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO budgets (user_id, category, amount, month)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
        );
        mysqli_stmt_bind_param($stmt, "isds", $user_id, $category, $amount, $month);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        header("Location: add_budget.php?month=" . $month);
        exit();
    }
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT b.id, b.category, b.amount AS budget_amount, COALESCE(e.spent_amount, 0) AS spent_amount
     FROM budgets b
     LEFT JOIN (
         SELECT category, SUM(amount) AS spent_amount
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

include "../includes/header.php";
?>

<h3 class="mb-4">Budget Management</h3>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<form method="GET" class="mb-3">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</form>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="mb-3">Create/Update Budget</h5>
        <form method="POST">
            <div class="row g-2">
                <div class="col-md-3">
                    <select name="category" class="form-select" required>
                        <option value="">Category</option>
                        <?php foreach ($budget_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" min="0" step="0.01" name="amount" class="form-control" placeholder="Budget Amount" required>
                </div>
                <div class="col-md-3">
                    <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100">Save Budget</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="mb-3">Budget Status</h5>

        <?php if (mysqli_num_rows($result) === 0): ?>
            <p class="text-muted mb-0">No budgets set for this month.</p>
        <?php else: ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php
                $budget_amount = (float) $row["budget_amount"];
                $spent_amount = (float) $row["spent_amount"];
                $remaining_amount = $budget_amount - $spent_amount;
                $percentage = $budget_amount > 0 ? min(($spent_amount / $budget_amount) * 100, 100) : 0;
                $is_exceeded = $spent_amount > $budget_amount;
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <strong><?php echo htmlspecialchars($row["category"]); ?></strong>
                        <small>
                            Spent Rs <?php echo number_format($spent_amount, 2); ?>
                            / Budget Rs <?php echo number_format($budget_amount, 2); ?>
                            | Remaining Rs <?php echo number_format($remaining_amount, 2); ?>
                        </small>
                    </div>
                    <div class="progress">
                        <div
                            class="progress-bar <?php echo $is_exceeded ? "bg-danger" : "bg-success"; ?>"
                            role="progressbar"
                            style="width: <?php echo round($percentage, 2); ?>%"
                        >
                            <?php echo round($percentage); ?>%
                        </div>
                    </div>
                    <?php if ($is_exceeded): ?>
                        <div class="text-danger small mt-1">Warning: Budget exceeded.</div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php
mysqli_stmt_close($stmt);
include "../includes/footer.php";
?>
