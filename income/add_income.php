<?php
require_once "../config/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

$income_categories = ["Salary", "Freelance", "Business", "Investment", "Bonus", "Gift", "Other"];

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
    $income_date = $_POST["income_date"] ?? "";
    $notes = trim($_POST["notes"] ?? "");

    if ($amount <= 0 || $category === "" || $income_date === "") {
        $errors[] = "Amount, category and date are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO income (user_id, amount, category, notes, income_date)
             VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "idsss", $user_id, $amount, $category, $notes, $income_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $redirect_month = date("Y-m", strtotime($income_date));
        header("Location: add_income.php?month=" . $redirect_month);
        exit();
    }
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

if ($filter_type === "month") {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, amount, category, notes, income_date
         FROM income
         WHERE user_id = ? AND income_date BETWEEN ? AND ?
         ORDER BY income_date DESC, id DESC"
    );
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
} else {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, amount, category, notes, income_date
         FROM income
         WHERE user_id = ?
         ORDER BY income_date DESC, id DESC"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

include "../includes/header.php";
?>

<div class="income-page">
<h3 class="mb-4">Income Management</h3>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endforeach; ?>

<form method="GET" class="filter-bar mb-3">
    <select name="filter_type" id="incomeFilterType" class="form-select">
        <option value="all" <?php echo $filter_type === "all" ? "selected" : ""; ?>>All Time</option>
        <option value="month" <?php echo $filter_type === "month" ? "selected" : ""; ?>>Specific Month</option>
    </select>
    <input type="month" name="month" id="incomeMonthFilter" class="form-control" value="<?php echo htmlspecialchars($selected_month); ?>">
    <button class="btn btn-primary btn-filter">Filter</button>
</form>
<script>
    const incomeFilterType = document.getElementById("incomeFilterType");
    const incomeMonthFilter = document.getElementById("incomeMonthFilter");
    function toggleIncomeMonth() {
        incomeMonthFilter.style.display = incomeFilterType.value === "month" ? "block" : "none";
    }
    incomeFilterType.addEventListener("change", toggleIncomeMonth);
    toggleIncomeMonth();
</script>

<div class="page-stack">
    <div class="card dashboard-card shadow-sm add-income-card">
        <div class="card-body">
            <h5 class="mb-3">Add Income</h5>
            <form method="POST" class="transaction-form">
                <input type="number" min="0" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                <select name="category" class="form-select" required>
                    <option value="">Category</option>
                    <?php foreach ($income_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="income_date" class="form-control" required>
                <input type="text" name="notes" class="form-control" placeholder="Notes (optional)">
                <button class="btn btn-success btn-add" type="submit">Add</button>
            </form>
        </div>
    </div>

    <div class="card dashboard-card shadow-sm income-table-card">
        <div class="card-body">
            <h5 class="mb-3">Income Records</h5>
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
                                <td><?php echo htmlspecialchars($row["income_date"]); ?></td>
                                <td><?php echo htmlspecialchars($row["category"]); ?></td>
                                <td class="notes-cell truncate" title="<?php echo htmlspecialchars($notes_trimmed); ?>">
                                    <?php echo htmlspecialchars($notes_display); ?>
                                </td>
                            <td class="amount-column amount-income">₹<?php echo number_format((float) $row["amount"], 2); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_income.php?id=<?php echo (int) $row["id"]; ?>"
                                           class="btn btn-action btn-action-edit"
                                           title="Edit"
                                           aria-label="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="delete_income.php?id=<?php echo (int) $row["id"]; ?>"
                                           class="btn btn-action btn-action-delete"
                                           title="Delete"
                                           aria-label="Delete"
                                           onclick="return confirm('Delete this income record?');">
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
