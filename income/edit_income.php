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
$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: add_income.php");
    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT amount, source, category, notes, income_date
     FROM income
     WHERE id = ? AND user_id = ?"
);
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $amount, $source, $category, $notes, $income_date);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    header("Location: add_income.php");
    exit();
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $amount = (float) ($_POST["amount"] ?? 0);
    $source = trim($_POST["source"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $notes = trim($_POST["notes"] ?? "");
    $income_date = $_POST["income_date"] ?? "";

    if ($amount <= 0 || $source === "" || $category === "" || $income_date === "") {
        $errors[] = "Amount, source, category and date are required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE income
             SET amount = ?, source = ?, category = ?, notes = ?, income_date = ?
             WHERE id = ? AND user_id = ?"
        );
        mysqli_stmt_bind_param($stmt, "dssssii", $amount, $source, $category, $notes, $income_date, $id, $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $redirect_month = date("Y-m", strtotime($income_date));
        header("Location: add_income.php?month=" . $redirect_month);
        exit();
    }
}

include "../includes/header.php";
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="mb-3">Edit Income</h5>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Amount</label>
                <input type="number" min="0" step="0.01" name="amount" class="form-control" value="<?php echo htmlspecialchars((string) $amount); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Source</label>
                <input type="text" name="source" class="form-control" value="<?php echo htmlspecialchars($source); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <?php foreach ($income_categories as $item): ?>
                        <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $category === $item ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($item); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" name="income_date" class="form-control" value="<?php echo htmlspecialchars($income_date); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
            </div>

            <button class="btn btn-primary">Update</button>
            <a href="add_income.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
