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

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"report_" . $month . ".csv\"");

$output = fopen("php://output", "w");
fputcsv($output, ["Type", "Date", "Source", "Category", "Notes", "Amount"]);

$query = "
    (SELECT 'Income' AS type, income_date AS txn_date, source, category, notes, amount
     FROM income
     WHERE user_id = ? AND income_date BETWEEN ? AND ?)
    UNION ALL
    (SELECT 'Expense' AS type, expense_date AS txn_date, '' AS source, category, notes, amount
     FROM expenses
     WHERE user_id = ? AND expense_date BETWEEN ? AND ?)
    ORDER BY txn_date DESC, type ASC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ississ", $user_id, $start, $end, $user_id, $start, $end);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row["type"],
        $row["txn_date"],
        $row["source"],
        $row["category"],
        $row["notes"],
        number_format((float) $row["amount"], 2, ".", ""),
    ]);
}

mysqli_stmt_close($stmt);
fclose($output);
exit();
?>
