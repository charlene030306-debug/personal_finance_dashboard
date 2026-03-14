<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

$page_title = "Budget Tracking";

$budget_categories = ["Food", "Transport", "Rent", "Utilities", "Shopping", "Health", "Entertainment", "Education", "Other"];

$user_id = (int) $_SESSION["user_id"];
$selected_month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$errors = [];
$success = "";
$form_category = "";
$form_amount = "";
$form_month = $selected_month;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "save";

    if ($action === "delete") {
        $delete_category = trim($_POST["delete_category"] ?? "");
        $delete_month = $_POST["delete_month"] ?? "";

        if ($delete_category === "" || !preg_match("/^\d{4}-\d{2}$/", $delete_month)) {
            $errors[] = "Invalid delete request.";
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM budgets WHERE user_id = ? AND category = ? AND month = ?"
            );
            mysqli_stmt_bind_param($stmt, "iss", $user_id, $delete_category, $delete_month);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $selected_month = $delete_month;
            $form_month = $selected_month;
            $success = "Budget deleted successfully.";
        }
    } else {
        $category = trim($_POST["category"] ?? "");
        $amount = (float) ($_POST["amount"] ?? 0);
        $month = $_POST["month"] ?? "";

        $form_category = $category;
        $form_amount = $_POST["amount"] ?? "";
        $form_month = $month;

        if ($category === "" || $amount <= 0 || !preg_match("/^\d{4}-\d{2}$/", $month)) {
            $errors[] = "Category, valid month and amount are required.";
        }

        if (!in_array($category, $budget_categories, true)) {
            $errors[] = "Invalid category selected.";
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

            $success = "Budget saved successfully.";
            $selected_month = $month;
            $form_month = $selected_month;
            $form_category = "";
            $form_amount = "";
        }
    }
}

if (isset($_GET["edit_category"]) && isset($_GET["edit_amount"])) {
    $candidate_category = trim($_GET["edit_category"]);
    $candidate_amount = $_GET["edit_amount"];

    if (in_array($candidate_category, $budget_categories, true) && is_numeric($candidate_amount)) {
        $form_category = $candidate_category;
        $form_amount = $candidate_amount;
    }
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT b.category,
            b.amount AS budget_amount,
            COALESCE(e.spent_amount, 0) AS spent_amount
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

$budget_rows = [];
$total_budget = 0.0;
$total_spent = 0.0;

while ($row = mysqli_fetch_assoc($result)) {
    $budget_amount = (float) $row["budget_amount"];
    $spent_amount = (float) $row["spent_amount"];
    $remaining_amount = $budget_amount - $spent_amount;
    $percentage = $budget_amount > 0 ? (($spent_amount / $budget_amount) * 100) : 0;
    $is_exceeded = $spent_amount > $budget_amount;

    $budget_rows[] = [
        "category" => $row["category"],
        "budget_amount" => $budget_amount,
        "spent_amount" => $spent_amount,
        "remaining_amount" => $remaining_amount,
        "percentage" => min($percentage, 100),
        "is_exceeded" => $is_exceeded
    ];

    $total_budget += $budget_amount;
    $total_spent += $spent_amount;
}
mysqli_stmt_close($stmt);

$remaining_balance = $total_budget - $total_spent;
$used_percentage = $total_budget > 0 ? min(($total_spent / $total_budget) * 100, 100) : 0;

include "../includes/header.php";
?>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<?php if ($success !== ""): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<section class="stats-grid">
    <article class="stat-card budget">
        <div>
            <p class="stat-title">Total Budget</p>
            <h3 class="stat-value">Rs <?php echo number_format($total_budget, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-piggy-bank"></i></span>
    </article>
    <article class="stat-card expense">
        <div>
            <p class="stat-title">Total Spent</p>
            <h3 class="stat-value">Rs <?php echo number_format($total_spent, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-receipt"></i></span>
    </article>
    <article class="stat-card balance">
        <div>
            <p class="stat-title">Remaining Balance</p>
            <h3 class="stat-value">Rs <?php echo number_format($remaining_balance, 2); ?></h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-scale-balanced"></i></span>
    </article>
    <article class="stat-card income">
        <div>
            <p class="stat-title">Budget Used</p>
            <h3 class="stat-value"><?php echo round($used_percentage); ?>%</h3>
        </div>
        <span class="stat-icon"><i class="fa-solid fa-chart-pie"></i></span>
    </article>
</section>

<form method="GET" class="month-filter-form mb-3">
    <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
    <button class="btn btn-primary filter-btn" type="submit">Filter</button>
</form>

<section class="dashboard-card mb-3">
    <div class="card-heading">
        <h5>Set Monthly Budget</h5>
    </div>
    <form method="POST">
        <div class="row g-2">
            <div class="col-md-4">
                <select name="category" class="form-select" required>
                    <option value="">Select category</option>
                    <?php foreach ($budget_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $form_category === $category ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" min="0" step="0.01" name="amount" class="form-control" placeholder="Budget amount" value="<?php echo htmlspecialchars((string) $form_amount); ?>" required>
            </div>
            <div class="col-md-3">
                <input type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($form_month); ?>" required>
            </div>
            <div class="col-md-2">
                <input type="hidden" name="action" value="save">
                <button class="btn btn-primary w-100" type="submit">Save</button>
            </div>
        </div>
    </form>
</section>

<section class="dashboard-card">
    <div class="card-heading">
        <h5>Category Budget Tracking</h5>
        <span class="heading-meta"><?php echo htmlspecialchars($selected_month); ?></span>
    </div>

    <?php if (empty($budget_rows)): ?>
        <p class="empty-state mb-0">No budgets set for this month.</p>
    <?php else: ?>
        <div class="budget-list">
            <?php foreach ($budget_rows as $item): ?>
                <div class="budget-item">
                    <div class="budget-item-head">
                        <strong><?php echo htmlspecialchars($item["category"]); ?></strong>
                        <small>
                            Used <?php echo round($item["percentage"]); ?>%
                            | Spent Rs <?php echo number_format($item["spent_amount"], 2); ?>
                            / Budget Rs <?php echo number_format($item["budget_amount"], 2); ?>
                            | Remaining Rs <?php echo number_format($item["remaining_amount"], 2); ?>
                        </small>
                    </div>

                    <div class="progress finance-progress">
                        <div
                            class="progress-bar <?php echo $item["is_exceeded"] ? "bg-danger" : "bg-success"; ?>"
                            role="progressbar"
                            style="width: <?php echo round($item["percentage"], 2); ?>%"
                        ></div>
                    </div>

                    <?php if ($item["is_exceeded"]): ?>
                        <p class="warning-text">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Warning: You exceeded this category budget.
                        </p>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-2">
                        <a
                            href="add_budget.php?month=<?php echo urlencode($selected_month); ?>&edit_category=<?php echo urlencode($item["category"]); ?>&edit_amount=<?php echo urlencode((string) $item["budget_amount"]); ?>"
                            class="btn btn-sm btn-outline-primary"
                        >
                            Edit
                        </a>
                        <form method="POST" onsubmit="return confirm('Delete this budget category?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_category" value="<?php echo htmlspecialchars($item["category"]); ?>">
                            <input type="hidden" name="delete_month" value="<?php echo htmlspecialchars($selected_month); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include "../includes/footer.php"; ?>
