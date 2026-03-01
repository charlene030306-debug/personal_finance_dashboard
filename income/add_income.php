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
$selected_month = date("Y-m");
if (isset($_GET["month"]) && preg_match("/^\d{4}-\d{2}$/", $_GET["month"])) {
    $selected_month = $_GET["month"];
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $amount = (float) ($_POST["amount"] ?? 0);
    $source = trim($_POST["source"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $income_date = $_POST["income_date"] ?? "";
    $notes = trim($_POST["notes"] ?? "");

    if ($amount <= 0 || $source === "" || $category === "" || $income_date === "") {
        $errors[] = "Amount, source, category and date are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO income (user_id, amount, source, category, notes, income_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "idssss", $user_id, $amount, $source, $category, $notes, $income_date);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $redirect_month = date("Y-m", strtotime($income_date));
        header("Location: add_income.php?month=" . $redirect_month);
        exit();
    }
}

$month_start = $selected_month . "-01";
$month_end = date("Y-m-t", strtotime($month_start));

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, amount, source, category, notes, income_date
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?
     ORDER BY income_date DESC, id DESC"
);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $month_start, $month_end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

include "../includes/header.php";
?>

<h3 class="mb-4">Income Management</h3>

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
        <h5 class="mb-3">Add Income</h5>
        <form method="POST">
            <div class="row g-2">
                <div class="col-md-2">
                    <input type="number" min="0" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="source" class="form-control" placeholder="Source" required>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select" required>
                        <option value="">Category</option>
                        <?php foreach ($income_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="income_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="notes" class="form-control" placeholder="Notes (optional)">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-success w-100">Add</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="mb-3">Income Records</h5>
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Category</th>
                        <th>Notes</th>
                        <th class="text-end">Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row["income_date"]); ?></td>
                            <td><?php echo htmlspecialchars($row["source"]); ?></td>
                            <td><?php echo htmlspecialchars($row["category"]); ?></td>
                            <td><?php echo htmlspecialchars($row["notes"] ?: "-"); ?></td>
                            <td class="text-end">Rs <?php echo number_format((float) $row["amount"], 2); ?></td>
                            <td>
                                <a href="edit_income.php?id=<?php echo (int) $row["id"]; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="delete_income.php?id=<?php echo (int) $row["id"]; ?>"
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this income record?');">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
mysqli_stmt_close($stmt);
include "../includes/footer.php";
?>
